<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
 declare(strict_types=1);

/**
 * It's free open-source software released under the MIT License.
 *
 * @author Anatoly Fenric <anatoly@fenric.ru>
 * @copyright Copyright (c) 2018, Anatoly Fenric
 * @license https://github.com/sunrise-php/http-server-request/blob/master/LICENSE
 * @link https://github.com/sunrise-php/http-server-request
 */

namespace Sunrise\Http\ServerRequest;

/**
 * Import functions
 */
use function is_array;

/**
 * Import constants
 */
use const UPLOAD_ERR_NO_FILE;

/**
 * Normalizes the given uploaded files
 *
 * Note that not sent files will not be handled.
 *
 * @param array $files
 *
 * @return array
 *
 * @link http://php.net/manual/en/reserved.variables.files.php
 * @link https://www.php.net/manual/ru/features.file-upload.post-method.php
 * @link https://www.php.net/manual/ru/features.file-upload.multiple.php
 * @link https://github.com/php/php-src/blob/8c5b41cefb88b753c630b731956ede8d9da30c5d/main/rfc1867.c
 */
function request_files(array $files) : array
{
    $walker = function ($path, $size, $error, $name, $type) use (&$walker) {
        if (! is_array($path)) {
            return new UploadedFile(
                $path,
                $size,
                $error,
                $name,
                $type
            );
        }

        $result = [];
        foreach ($path as $key => $_) {
            if (UPLOAD_ERR_NO_FILE <> $error[$key]) {
                $result[$key] = $walker(
                    $path[$key],
                    $size[$key],
                    $error[$key],
                    $name[$key],
                    $type[$key]
                );
            }
        }

        return $result;
    };

    $result = [];
    foreach ($files as $key => $file) {
        if (UPLOAD_ERR_NO_FILE <> $file['error']) {
            $result[$key] = $walker(
                $file['tmp_name'],
                $file['size'],
                $file['error'],
                $file['name'],
                $file['type']
            );
        }
    }

    return $result;
}
