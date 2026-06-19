<?php

namespace Composer\Autoload;

class ComposerStaticInit00c6db970910ed15a7b210d46ca7abc2
{
    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'Comfino\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Comfino\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Comfino\\Api\\ApiClient' => __DIR__ . '/../..' . '/src/Api/ApiClient.php',
        'Comfino\\Api\\ApiService' => __DIR__ . '/../..' . '/src/Api/ApiService.php',
        'Comfino\\CategoryTree\\BuildStrategy' => __DIR__ . '/../..' . '/src/CategoryTree/BuildStrategy.php',
        'Comfino\\Configuration\\ConfigManager' => __DIR__ . '/../..' . '/src/Configuration/ConfigManager.php',
        'Comfino\\Configuration\\SettingsManager' => __DIR__ . '/../..' . '/src/Configuration/SettingsManager.php',
        'Comfino\\Configuration\\StorageAdapter' => __DIR__ . '/../..' . '/src/Configuration/StorageAdapter.php',
        'Comfino\\DebugLogger' => __DIR__ . '/../..' . '/src/DebugLogger.php',
        'Comfino\\ErrorLogger' => __DIR__ . '/../..' . '/src/ErrorLogger.php',
        'Comfino\\Main' => __DIR__ . '/../..' . '/src/Main.php',
        'Comfino\\Order\\OrderManager' => __DIR__ . '/../..' . '/src/Order/OrderManager.php',
        'Comfino\\Order\\ShopStatusManager' => __DIR__ . '/../..' . '/src/Order/ShopStatusManager.php',
        'Comfino\\Order\\StatusAdapter' => __DIR__ . '/../..' . '/src/Order/StatusAdapter.php',
        'Comfino\\PaywallAuthTokenGenerator' => __DIR__ . '/../..' . '/src/PaywallAuthTokenGenerator.php',
        'Comfino\\Tools' => __DIR__ . '/../..' . '/src/Tools.php',
        'Comfino\\Update\\UpdateManager' => __DIR__ . '/../..' . '/src/Update/UpdateManager.php',
        'Comfino\\View\\FormManager' => __DIR__ . '/../..' . '/src/View/FormManager.php',
        'Comfino\\View\\FrontendManager' => __DIR__ . '/../..' . '/src/View/FrontendManager.php',
        'Comfino\\View\\SettingsForm' => __DIR__ . '/../..' . '/src/View/SettingsForm.php',
        'Comfino\\View\\TemplateManager' => __DIR__ . '/../..' . '/src/View/TemplateManager.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit00c6db970910ed15a7b210d46ca7abc2::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit00c6db970910ed15a7b210d46ca7abc2::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit00c6db970910ed15a7b210d46ca7abc2::$classMap;

        }, null, ClassLoader::class);
    }
}
