<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\ORM\Entity;

class ZaloOAService
{
    private const ZALO_API_URL = 'https://openapi.zalo.me/v3.0/oa/message/cs';
    private const ZALO_REFRESH_TOKEN_URL = 'https://oauth.zaloapp.com/v4/oa/access_token';
    
    public function __construct(
        private Config $config,
        private Log $log
    ) {}

    /**
     * Gửi tin nhắn thông báo Lead mới
     */
    public function sendNewLeadNotification(Entity $lead, string $recipientId): bool
    {
        try {
            $message = $this->getLeadMessage($lead);
            return $this->sendTextMessage($recipientId, $message);
        } catch (\Exception $e) {
            $this->log->error('ZaloOA: Failed to send lead notification', [
                'error' => $e->getMessage(),
                'leadId' => $lead->getId()
            ]);
            return false;
        }
    }

    /**
     * Gửi tin nhắn theo USER ID trực tiếp 
     */
    public function sendNewLeadNotificationByUserId(Entity $lead, string $userId): bool
    {
        return $this->sendNewLeadNotification($lead, $userId);
    }

    /**
     * Tạo tin nhắn thông báo Lead mới
     */
    private function getLeadMessage(Entity $lead): string
    {
        $leadName = $lead->get('name') ?: 'Khách hàng tiềm năng';
        $leadPhone = $lead->get('phoneNumber') ?: 'Chưa có';
        $leadEmail = $lead->get('emailAddress') ?: 'Chưa có';
        $leadSource = $lead->get('source') ?: 'Chưa xác định';
        $leadStatus = $lead->get('status') ?: 'New';
        $assignedUser = $lead->get('assignedUser');
        $assignedUserName = $assignedUser ? $assignedUser->get('name') : 'Chưa phân công';
        
        $message = "LEAD MỚI\n";
        $message .= "\n";
        
        $message .= "Tên khách hàng: {$leadName}\n";
        $message .= "Số điện thoại: {$leadPhone}\n";
        $message .= "Email: {$leadEmail}\n";
        $message .= "Nguồn: {$leadSource}\n";
        $message .= "Trạng thái: {$leadStatus}\n";
        $message .= "Người phụ trách: {$assignedUserName}\n";
        $message .= "Thời gian: " . $this->getVietnamDateTime() . "\n\n";
        
        $message .= "Link xem chi tiết:\n";
        $message .= $this->getLeadDetailUrl($lead) . "\n\n";
        
        $message .= "Lưu ý: Ưu tiên liên hệ khách hàng sớm nhất có thể để tăng tỷ lệ chuyển đổi!";

        return $message;
    }

    /**
     * Gửi tin nhắn văn bản
     */
    public function sendTextMessage(string $recipientId, string $message): bool
    {
        try {
            // Kiểm tra và refresh token nếu cần
            $accessToken = $this->getValidAccessToken();
            
            if (!$accessToken) {
                $this->log->error('ZaloOA: Cannot get valid access token');
                return false;
            }

            $data = [
                'recipient' => [
                    'user_id' => $recipientId
                ],
                'message' => [
                    'text' => $message
                ]
            ];

            $response = $this->makeRequest($accessToken, $data);
            
            if ($response && isset($response['error']) && $response['error'] === 0) {
                $this->log->info('ZaloOA: Message sent successfully to ' . $recipientId);
                return true;
            } else {
                $this->log->error('ZaloOA: Failed to send message', [
                    'response' => $response,
                    'recipient' => $recipientId
                ]);
                return false;
            }
        } catch (\Exception $e) {
            $this->log->error('ZaloOA: Exception occurred', [
                'message' => $e->getMessage(),
                'recipient' => $recipientId
            ]);
            return false;
        }
    }

    /**
     * Lấy access token hợp lệ (tự động refresh nếu hết hạn)
     */
    private function getValidAccessToken(): ?string
    {
        $accessToken = $this->config->get('zaloOAAccessToken');
        $expiresAt = $this->config->get('zaloOATokenExpiresAt');
        
        // Nếu không có token
        if (!$accessToken) {
            $this->log->error('ZaloOA: Access token not found in config');
            return null;
        }

        // Nếu không có thời gian hết hạn, sử dụng token hiện tại
        if (!$expiresAt) {
            $this->log->warning('ZaloOA: Token expiry time not found, using current token');
            return $accessToken;
        }

        // Kiểm tra token có hết hạn không (thêm buffer 5 phút)
        $currentTime = time();
        $expiryTime = is_numeric($expiresAt) ? $expiresAt : strtotime($expiresAt);
        $bufferTime = 300; // 5 phút

        if ($currentTime >= ($expiryTime - $bufferTime)) {
            $this->log->info('ZaloOA: Access token expired or about to expire, refreshing...');
            
            // Refresh token
            $newToken = $this->refreshAccessToken();
            
            if ($newToken) {
                return $newToken;
            } else {
                $this->log->error('ZaloOA: Failed to refresh token');
                return null;
            }
        }

        // Token còn hợp lệ
        return $accessToken;
    }

    /**
     * Refresh access token bằng refresh token
     */
    private function refreshAccessToken(): ?string
    {
        try {
            $refreshToken = $this->config->get('zaloOARefreshToken');
            $appId = $this->config->get('zaloOAAppId');
            $appSecret = $this->config->get('zaloOAAppSecret');
            
            if (!$refreshToken || !$appId || !$appSecret) {
                $this->log->error('ZaloOA: Missing refresh token or app credentials', [
                    'hasRefreshToken' => !empty($refreshToken),
                    'hasAppId' => !empty($appId),
                    'hasAppSecret' => !empty($appSecret)
                ]);
                return null;
            }

            $data = [
                'refresh_token' => $refreshToken,
                'app_id' => $appId,
                'grant_type' => 'refresh_token'
            ];

            $headers = [
                'Content-Type: application/x-www-form-urlencoded',
                'secret_key: ' . $appSecret
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::ZALO_REFRESH_TOKEN_URL);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->log->error('ZaloOA: CURL error while refreshing token', ['error' => $error]);
                return null;
            }

            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                
                if (isset($result['access_token']) && isset($result['refresh_token'])) {
                    // Lưu token mới vào config
                    $this->saveTokens(
                        $result['access_token'],
                        $result['refresh_token'],
                        $result['expires_in'] ?? 7776000 // Default 90 ngày
                    );
                    
                    $this->log->info('ZaloOA: Token refreshed successfully');
                    return $result['access_token'];
                } else {
                    $this->log->error('ZaloOA: Invalid refresh token response', ['response' => $result]);
                    return null;
                }
            }

            $this->log->error('ZaloOA: HTTP error while refreshing token', [
                'httpCode' => $httpCode,
                'response' => $response
            ]);

            return null;
        } catch (\Exception $e) {
            $this->log->error('ZaloOA: Exception while refreshing token', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Lưu tokens vào config
     */
    private function saveTokens(string $accessToken, string $refreshToken, int $expiresIn): void
    {
        try {
            $expiresAt = time() + $expiresIn;
            
            $this->config->set('zaloOAAccessToken', $accessToken);
            $this->config->set('zaloOARefreshToken', $refreshToken);
            $this->config->set('zaloOATokenExpiresAt', $expiresAt);
            $this->config->save();
            
            $this->log->info('ZaloOA: Tokens saved successfully', [
                'expiresAt' => date('Y-m-d H:i:s', $expiresAt)
            ]);
        } catch (\Exception $e) {
            $this->log->error('ZaloOA: Failed to save tokens', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Tạo URL chi tiết Lead
     */
    private function getLeadDetailUrl(Entity $lead): string
    {
        $baseUrl = $this->config->get('siteUrl');
        return $baseUrl . '/#Lead/view/' . $lead->getId();
    }

    /**
     * Thực hiện HTTP request đến Zalo API
     */
    private function makeRequest(string $accessToken, array $data, string $url = null): ?array
    {
        $url = $url ?: self::ZALO_API_URL;
        
        $headers = [
            'Content-Type: application/json',
            'access_token: ' . $accessToken
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log->error('ZaloOA: CURL error', ['error' => $error]);
            return null;
        }

        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }

        $this->log->error('ZaloOA: HTTP error', [
            'httpCode' => $httpCode,
            'response' => $response
        ]);

        return null;
    }

    /**
     * Kiểm tra trạng thái access token hiện tại
     */
    public function getTokenStatus(): array
    {
        $accessToken = $this->config->get('zaloOAAccessToken');
        $expiresAt = $this->config->get('zaloOATokenExpiresAt');
        
        $status = [
            'has_access_token' => !empty($accessToken),
            'has_expiry_time' => !empty($expiresAt),
            'current_time' => $this->getVietnamDateTime(),
            'is_expired' => false,
            'expires_at' => null,
            'time_remaining' => null
        ];
        
        if ($expiresAt) {
            $expiryTime = is_numeric($expiresAt) ? $expiresAt : strtotime($expiresAt);
            $currentTime = time();
            
            $status['is_expired'] = $currentTime >= $expiryTime;
            $status['expires_at'] = date('d/m/Y H:i:s', $expiryTime);
            
            if (!$status['is_expired']) {
                $timeRemaining = $expiryTime - $currentTime;
                $days = floor($timeRemaining / 86400);
                $hours = floor(($timeRemaining % 86400) / 3600);
                $minutes = floor(($timeRemaining % 3600) / 60);
                
                $status['time_remaining'] = sprintf('%d ngày %d giờ %d phút', $days, $hours, $minutes);
            }
        }
        
        return $status;
    }

    /**
     * Lấy thời gian hiện tại theo múi giờ Việt Nam
     */
    private function getVietnamDateTime(): string
    {
        $dateTime = new \DateTime('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));
        return $dateTime->format('d/m/Y H:i:s');
    }
}