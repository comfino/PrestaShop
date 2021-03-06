<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6da669cf9d570c79dee15246a3f3d9a1
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
        'Comfino' => __DIR__ . '/../..' . '/comfino.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit6da669cf9d570c79dee15246a3f3d9a1::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit6da669cf9d570c79dee15246a3f3d9a1::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit6da669cf9d570c79dee15246a3f3d9a1::$classMap;

        }, null, ClassLoader::class);
    }
}
