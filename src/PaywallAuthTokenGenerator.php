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

namespace Comfino;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Crypto/Sha3.php';

use Comfino\Crypto\Sha3;

/**
 * Generates time-limited HMAC-signed tokens for the Comfino Paywall iframe and the frontend SDK.
 *
 * Auth token payload layout (binary, then base64 encoded):
 *   Bytes 0-7: Unix timestamp, unsigned 64-bit big-endian (pack('J', time()))
 *   Bytes 8-39: HMAC-SHA3-256(timestamp_bytes . widgetKey_utf8, apiKey), raw binary (32 bytes)
 *   Bytes 40-75: widgetKey UTF-8 string (UUIDv4, 36 bytes)
 *   Total: 76 bytes. Token lifetime is enforced server-side.
 *
 * The tokens use HMAC-SHA3-256. PHP does not register "sha3-256" in hash_algos() before 7.1, so on
 * PHP 5.6 the native hash_hmac() returns false; a byte-identical pure-PHP fallback
 * (Comfino\Crypto\Sha3) is used instead. The wire format is the same on both paths.
 */
class PaywallAuthTokenGenerator
{
    /**
     * @param string $widgetKey widget key (UUIDv4, 36 chars)
     * @param string $apiKey API key (HMAC secret)
     *
     * @return string base64-encoded auth token
     */
    public static function generateAuthToken($widgetKey, $apiKey)
    {
        $timestampBytes = pack('J', time());
        $hmac = self::hmacSha3256($timestampBytes . $widgetKey, $apiKey);

        return base64_encode($timestampBytes . $hmac . $widgetKey);
    }

    /**
     * Logging token for browser-side error reporting.
     *
     * Payload layout (binary, then base64 encoded):
     *   Byte 0: version byte (pack('C', 1))
     *   Bytes 1-8: Unix timestamp, unsigned 64-bit big-endian (pack('J', time()))
     *   Bytes 9-40: HMAC-SHA3-256('comfino-fe-log:v1' . versionByte . timestamp_bytes . widgetKey,
     *               signingKey), raw binary (32 bytes)
     *   Bytes 41-76: widgetKey UTF-8 string (UUIDv4, 36 bytes)
     *   Total: 77 bytes.
     *
     * @param string $widgetKey widget key (UUIDv4, 36 chars)
     * @param string $signingKey signing key (HMAC secret)
     *
     * @return string base64-encoded logging token
     */
    public static function generateLoggingToken($widgetKey, $signingKey)
    {
        $versionByte = pack('C', 1);
        $timestampBytes = pack('J', time());
        $hmac = self::hmacSha3256(
            'comfino-fe-log:v1' . $versionByte . $timestampBytes . $widgetKey,
            $signingKey
        );

        return base64_encode($versionByte . $timestampBytes . $hmac . $widgetKey);
    }

    /**
     * Raw (32-byte) HMAC-SHA3-256, using the native implementation when available (PHP 7.1+) and the
     * pure-PHP fallback otherwise (PHP 5.6). Both produce byte-identical output.
     *
     * @param string $data
     * @param string $key
     *
     * @return string 32 raw bytes
     */
    private static function hmacSha3256($data, $key)
    {
        if (function_exists('hash_hmac') && in_array('sha3-256', hash_algos(), true)) {
            return hash_hmac('sha3-256', $data, $key, true);
        }

        return Sha3::hmac256($data, $key, true);
    }
}
