<?php

$vendorDir = dirname(__DIR__);
$baseDir = dirname($vendorDir);

return array(
    'Comfino\\Api\\ApiClient' => $baseDir . '/src/Api/ApiClient.php',
    'Comfino\\Api\\ApiService' => $baseDir . '/src/Api/ApiService.php',
    'Comfino\\CategoryTree\\BuildStrategy' => $baseDir . '/src/CategoryTree/BuildStrategy.php',
    'Comfino\\Configuration\\ConfigManager' => $baseDir . '/src/Configuration/ConfigManager.php',
    'Comfino\\Configuration\\SettingsManager' => $baseDir . '/src/Configuration/SettingsManager.php',
    'Comfino\\Configuration\\StorageAdapter' => $baseDir . '/src/Configuration/StorageAdapter.php',
    'Comfino\\DebugLogger' => $baseDir . '/src/DebugLogger.php',
    'Comfino\\ErrorLogger' => $baseDir . '/src/ErrorLogger.php',
    'Comfino\\Main' => $baseDir . '/src/Main.php',
    'Comfino\\Order\\OrderManager' => $baseDir . '/src/Order/OrderManager.php',
    'Comfino\\Order\\ShopStatusManager' => $baseDir . '/src/Order/ShopStatusManager.php',
    'Comfino\\Order\\StatusAdapter' => $baseDir . '/src/Order/StatusAdapter.php',
    'Comfino\\PaywallAuthTokenGenerator' => $baseDir . '/src/PaywallAuthTokenGenerator.php',
    'Comfino\\Tools' => $baseDir . '/src/Tools.php',
    'Comfino\\Update\\UpdateManager' => $baseDir . '/src/Update/UpdateManager.php',
    'Comfino\\View\\FormManager' => $baseDir . '/src/View/FormManager.php',
    'Comfino\\View\\FrontendManager' => $baseDir . '/src/View/FrontendManager.php',
    'Comfino\\View\\SettingsForm' => $baseDir . '/src/View/SettingsForm.php',
    'Comfino\\View\\TemplateManager' => $baseDir . '/src/View/TemplateManager.php',
    'Composer\\InstalledVersions' => $vendorDir . '/composer/InstalledVersions.php',
);
