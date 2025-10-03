<?php
return [
  'database' => [
    'host' => 'localhost',
    'port' => '3306',
    'charset' => NULL,
    'dbname' => 'espocrm_thiago',
    'user' => 'espocrm_owner_thiago',
    'password' => '123456',
    'platform' => 'Mysql'
  ],
  'smtpPassword' => NULL,
  'logger' => [
    'path' => 'data/logs/espo.log',
    'level' => 'WARNING',
    'rotation' => true,
    'maxFileNumber' => 30,
    'printTrace' => false,
    'databaseHandler' => false,
    'sql' => false,
    'sqlFailed' => false
  ],
  'restrictedMode' => false,
  'cleanupAppLog' => true,
  'cleanupAppLogPeriod' => '30 days',
  'webSocketMessager' => 'ZeroMQ',
  'clientSecurityHeadersDisabled' => false,
  'clientCspDisabled' => false,
  'clientCspScriptSourceList' => [
    0 => 'https://maps.googleapis.com'
  ],
  'adminUpgradeDisabled' => false,
  'isInstalled' => true,
  'microtimeInternal' => 1758782169.446984,
  'cryptKey' => '3799b71ae0f9fc75295d5c422ed1f9a0',
  'hashSecretKey' => 'c0cbeedb06242e937423fbb128312573',
  'actualDatabaseType' => 'mariadb',
  'actualDatabaseVersion' => '10.4.32',
  'instanceId' => '02f623af-aacd-44c5-bc44-4559c8077e86'
];
