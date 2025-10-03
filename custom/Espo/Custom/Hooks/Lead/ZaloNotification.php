<?php

namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Entity;
use Espo\Custom\Services\ZaloOAService;
use Espo\ORM\Repository\Option\SaveOptions;
/**
 * @implements AfterSave<Lead>
 */
class ZaloNotification implements AfterSave
{
    public static int $order = 10;

    public function __construct(
        private ZaloOAService $zaloOAService,
        private Config $config,
        private Log $log
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->log->info('ZaloOA: afterSave hook triggered for Lead ' . $entity->getId());
        // Chỉ gửi thông báo khi tạo Lead mới
        if (!$entity->isNew()) {
            return;
        }

        // Kiểm tra xem có bật tính năng gửi ZaloOA không
        if (!$this->config->get('zaloOANotificationEnabled', false)) {
            return;
        }

        // Lấy danh sách userId admin cần nhận thông báo
        $adminUserIds = $this->config->get('zaloOAAdminUserIds', []);
        
        if (empty($adminUserIds)) {
            $this->log->warning('ZaloOA: No admin userIds configured');
            return;
        }

        foreach ($adminUserIds as $uid) {
            try {
                $this->log->info('ZaloOA: Sending notification to userId: ' . $uid);
                $ok = $this->zaloOAService->sendNewLeadNotificationByUserId($entity, $uid);
                $this->log->info($ok ? 'ZaloOA: Notification sent' : 'ZaloOA: Notification failed', ['userId' => $uid]);
            } catch (\Exception $e) {
                $this->log->error('ZaloOA: Exception', ['userId' => $uid, 'error' => $e->getMessage()]);
            }
        }
    }
}