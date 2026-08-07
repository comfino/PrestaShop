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

namespace Comfino\Crypto;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Pure-PHP NIST FIPS-202 SHA3-256 + HMAC-SHA3-256 implementation for PHP 5.6.
 *
 * PHP does not register the "sha3-256" algorithm in hash_algos() before PHP 7.1, so on the
 * legacy PHP 5.6 shops targeted by release 3.6.0 the native hash_hmac('sha3-256', ...) returns
 * false. This class is a self-contained fallback that produces byte-identical output to the
 * native implementation (verified against PHP >= 7.1 hash('sha3-256', ...) / hash_hmac(...)).
 *
 * The Keccak-f[1600] sponge core is adapted from the MIT-licensed kornrunner/php-keccak
 * (https://github.com/kornrunner/php-keccak) - both the 64-bit and 32-bit-safe code paths are
 * kept so it works on 32-bit and 64-bit PHP builds. The domain-separation suffix here is 0x06
 * (NIST SHA3), NOT 0x01 (the original Keccak used by kornrunner::hash()), so the output matches
 * PHP's "sha3-256" and the value the Comfino v3 backend signs/verifies.
 *
 * Use only when native SHA-3 is unavailable - see Comfino\PaywallAuthTokenGenerator.
 */
class Sha3
{
    const KECCAK_ROUNDS = 24;

    /** NIST FIPS-202 SHA3 domain-separation suffix (Keccak original is 0x01). */
    const SHA3_SUFFIX = 0x06;

    /** SHA3-256 sponge rate in bytes (r = 1088 bits) = HMAC block size. */
    const SHA3_256_BLOCK_SIZE = 136;

    /** @var int[] */
    private static $keccakf_rotc = [
        1, 3, 6, 10, 15, 21, 28, 36, 45, 55, 2, 14, 27, 41, 56, 8, 25, 43, 62, 18, 39, 61, 20, 44,
    ];

    /** @var int[] */
    private static $keccakf_piln = [
        10, 7, 11, 17, 18, 3, 5, 16, 8, 21, 24, 4, 15, 23, 19, 13, 12, 2, 20, 14, 22, 9, 6, 1,
    ];

    /**
     * Computes SHA3-256 of the given string.
     *
     * @param string $data
     * @param bool $rawOutput true for 32 raw bytes, false for 64 lowercase hex chars
     *
     * @return string
     */
    public static function hash256($data, $rawOutput = false)
    {
        /* First argument is the digest length in bits (kornrunner convention: internally /8 then
           rsiz = 200 - 2 * that, yielding the 136-byte SHA3-256 rate). NOT the 512-bit capacity. */
        return self::keccak($data, 256, 256, self::SHA3_SUFFIX, (bool) $rawOutput);
    }

    /**
     * Computes HMAC-SHA3-256 (RFC 2104) of the given message.
     *
     * @param string $data message
     * @param string $key secret key
     * @param bool $rawOutput true for 32 raw bytes, false for 64 lowercase hex chars
     *
     * @return string
     */
    public static function hmac256($data, $key, $rawOutput = false)
    {
        $blockSize = self::SHA3_256_BLOCK_SIZE;

        if (self::strlen($key) > $blockSize) {
            $key = self::hash256($key, true);
        }

        $key = str_pad($key, $blockSize, "\x00", STR_PAD_RIGHT);

        $oKeyPad = '';
        $iKeyPad = '';

        for ($i = 0; $i < $blockSize; $i++) {
            $keyByte = ord($key[$i]);
            $oKeyPad .= chr($keyByte ^ 0x5c);
            $iKeyPad .= chr($keyByte ^ 0x36);
        }

        $inner = self::hash256($iKeyPad . $data, true);

        return self::hash256($oKeyPad . $inner, (bool) $rawOutput);
    }

    /**
     * @return bool true on 64-bit PHP builds
     */
    private static function is64bit()
    {
        return PHP_INT_SIZE === 8;
    }

    /**
     * @param string $inRaw
     * @param int $capacity
     * @param int $outputLength
     * @param int $suffix
     * @param bool $rawOutput
     *
     * @return string
     */
    private static function keccak($inRaw, $capacity, $outputLength, $suffix, $rawOutput)
    {
        return self::is64bit()
            ? self::keccak64($inRaw, $capacity, $outputLength, $suffix, $rawOutput)
            : self::keccak32($inRaw, $capacity, $outputLength, $suffix, $rawOutput);
    }

    /**
     * @param string $str
     *
     * @return int
     */
    private static function strlen($str)
    {
        return mb_strlen($str, '8bit');
    }

    /**
     * @param string $str
     * @param int $start
     * @param int|null $length
     *
     * @return string
     */
    private static function substr($str, $start, $length = null)
    {
        return $length === null
            ? mb_substr($str, $start, null, '8bit')
            : mb_substr($str, $start, $length, '8bit');
    }

    /* ------------------------------------------------------------------ 64-bit path ----- */

    /**
     * @param array $st
     * @param int $rounds
     *
     * @return void
     */
    private static function keccakf64(&$st, $rounds)
    {
        $keccakf_rndc = [
            [0x00000000, 0x00000001], [0x00000000, 0x00008082], [0x80000000, 0x0000808a], [0x80000000, 0x80008000],
            [0x00000000, 0x0000808b], [0x00000000, 0x80000001], [0x80000000, 0x80008081], [0x80000000, 0x00008009],
            [0x00000000, 0x0000008a], [0x00000000, 0x00000088], [0x00000000, 0x80008009], [0x00000000, 0x8000000a],
            [0x00000000, 0x8000808b], [0x80000000, 0x0000008b], [0x80000000, 0x00008089], [0x80000000, 0x00008003],
            [0x80000000, 0x00008002], [0x80000000, 0x00000080], [0x00000000, 0x0000800a], [0x80000000, 0x8000000a],
            [0x80000000, 0x80008081], [0x80000000, 0x00008080], [0x00000000, 0x80000001], [0x80000000, 0x80008008],
        ];

        $bc = [];

        for ($round = 0; $round < $rounds; $round++) {
            // Theta
            for ($i = 0; $i < 5; $i++) {
                $bc[$i] = [
                    $st[$i][0] ^ $st[$i + 5][0] ^ $st[$i + 10][0] ^ $st[$i + 15][0] ^ $st[$i + 20][0],
                    $st[$i][1] ^ $st[$i + 5][1] ^ $st[$i + 10][1] ^ $st[$i + 15][1] ^ $st[$i + 20][1],
                ];
            }

            for ($i = 0; $i < 5; $i++) {
                $t = [
                    $bc[($i + 4) % 5][0] ^ (($bc[($i + 1) % 5][0] << 1) | ($bc[($i + 1) % 5][1] >> 31)) & 0xFFFFFFFF,
                    $bc[($i + 4) % 5][1] ^ (($bc[($i + 1) % 5][1] << 1) | ($bc[($i + 1) % 5][0] >> 31)) & 0xFFFFFFFF,
                ];

                for ($j = 0; $j < 25; $j += 5) {
                    $st[$j + $i] = [
                        $st[$j + $i][0] ^ $t[0],
                        $st[$j + $i][1] ^ $t[1],
                    ];
                }
            }

            // Rho Pi
            $t = $st[1];

            for ($i = 0; $i < 24; $i++) {
                $j = self::$keccakf_piln[$i];

                $bc[0] = $st[$j];

                $n = self::$keccakf_rotc[$i];
                $hi = $t[0];
                $lo = $t[1];

                if ($n >= 32) {
                    $n -= 32;
                    $hi = $t[1];
                    $lo = $t[0];
                }

                $st[$j] = [
                    (($hi << $n) | ($lo >> (32 - $n))) & 0xFFFFFFFF,
                    (($lo << $n) | ($hi >> (32 - $n))) & 0xFFFFFFFF,
                ];

                $t = $bc[0];
            }

            // Chi
            for ($j = 0; $j < 25; $j += 5) {
                for ($i = 0; $i < 5; $i++) {
                    $bc[$i] = $st[$j + $i];
                }

                for ($i = 0; $i < 5; $i++) {
                    $st[$j + $i] = [
                        $st[$j + $i][0] ^ ~$bc[($i + 1) % 5][0] & $bc[($i + 2) % 5][0],
                        $st[$j + $i][1] ^ ~$bc[($i + 1) % 5][1] & $bc[($i + 2) % 5][1],
                    ];
                }
            }

            // Iota
            $st[0] = [
                $st[0][0] ^ $keccakf_rndc[$round][0],
                $st[0][1] ^ $keccakf_rndc[$round][1],
            ];
        }
    }

    /**
     * @param string $inRaw
     * @param int $capacity
     * @param int $outputLength
     * @param int $suffix
     * @param bool $rawOutput
     *
     * @return string
     */
    private static function keccak64($inRaw, $capacity, $outputLength, $suffix, $rawOutput)
    {
        $capacity /= 8;

        $inlen = self::strlen($inRaw);

        $rsiz = 200 - 2 * $capacity;
        $rsizw = $rsiz / 8;

        $st = [];

        for ($i = 0; $i < 25; $i++) {
            $st[] = [0, 0];
        }

        for ($in_t = 0; $inlen >= $rsiz; $inlen -= $rsiz, $in_t += $rsiz) {
            for ($i = 0; $i < $rsizw; $i++) {
                $t = unpack('V*', self::substr($inRaw, (int) ($i * 8 + $in_t), 8));

                $st[$i] = [
                    $st[$i][0] ^ $t[2],
                    $st[$i][1] ^ $t[1],
                ];
            }

            self::keccakf64($st, self::KECCAK_ROUNDS);
        }

        $temp = self::substr($inRaw, (int) $in_t, (int) $inlen);
        $temp = str_pad($temp, (int) $rsiz, "\x0", STR_PAD_RIGHT);
        $temp = substr_replace($temp, chr($suffix), $inlen, 1);
        $temp = substr_replace($temp, chr(ord($temp[(int) ($rsiz - 1)]) | 0x80), $rsiz - 1, 1);

        for ($i = 0; $i < $rsizw; $i++) {
            $t = unpack('V*', self::substr($temp, $i * 8, 8));

            $st[$i] = [
                $st[$i][0] ^ $t[2],
                $st[$i][1] ^ $t[1],
            ];
        }

        self::keccakf64($st, self::KECCAK_ROUNDS);

        $out = '';

        for ($i = 0; $i < 25; $i++) {
            $out .= pack('V*', $st[$i][1], $st[$i][0]);
        }

        $r = self::substr($out, 0, (int) ($outputLength / 8));

        return $rawOutput ? $r : bin2hex($r);
    }

    /* ------------------------------------------------------------------ 32-bit path ----- */

    /**
     * @param array $st
     * @param int $rounds
     *
     * @return void
     */
    private static function keccakf32(&$st, $rounds)
    {
        $keccakf_rndc = [
            [0x0000, 0x0000, 0x0000, 0x0001], [0x0000, 0x0000, 0x0000, 0x8082], [0x8000, 0x0000, 0x0000, 0x0808a], [0x8000, 0x0000, 0x8000, 0x8000],
            [0x0000, 0x0000, 0x0000, 0x808b], [0x0000, 0x0000, 0x8000, 0x0001], [0x8000, 0x0000, 0x8000, 0x08081], [0x8000, 0x0000, 0x0000, 0x8009],
            [0x0000, 0x0000, 0x0000, 0x008a], [0x0000, 0x0000, 0x0000, 0x0088], [0x0000, 0x0000, 0x8000, 0x08009], [0x0000, 0x0000, 0x8000, 0x000a],
            [0x0000, 0x0000, 0x8000, 0x808b], [0x8000, 0x0000, 0x0000, 0x008b], [0x8000, 0x0000, 0x0000, 0x08089], [0x8000, 0x0000, 0x0000, 0x8003],
            [0x8000, 0x0000, 0x0000, 0x8002], [0x8000, 0x0000, 0x0000, 0x0080], [0x0000, 0x0000, 0x0000, 0x0800a], [0x8000, 0x0000, 0x8000, 0x000a],
            [0x8000, 0x0000, 0x8000, 0x8081], [0x8000, 0x0000, 0x0000, 0x8080], [0x0000, 0x0000, 0x8000, 0x00001], [0x8000, 0x0000, 0x8000, 0x8008],
        ];

        $bc = [];

        for ($round = 0; $round < $rounds; $round++) {
            // Theta
            for ($i = 0; $i < 5; $i++) {
                $bc[$i] = [
                    $st[$i][0] ^ $st[$i + 5][0] ^ $st[$i + 10][0] ^ $st[$i + 15][0] ^ $st[$i + 20][0],
                    $st[$i][1] ^ $st[$i + 5][1] ^ $st[$i + 10][1] ^ $st[$i + 15][1] ^ $st[$i + 20][1],
                    $st[$i][2] ^ $st[$i + 5][2] ^ $st[$i + 10][2] ^ $st[$i + 15][2] ^ $st[$i + 20][2],
                    $st[$i][3] ^ $st[$i + 5][3] ^ $st[$i + 10][3] ^ $st[$i + 15][3] ^ $st[$i + 20][3],
                ];
            }

            for ($i = 0; $i < 5; $i++) {
                $t = [
                    $bc[($i + 4) % 5][0] ^ ((($bc[($i + 1) % 5][0] << 1) | ($bc[($i + 1) % 5][1] >> 15)) & 0xFFFF),
                    $bc[($i + 4) % 5][1] ^ ((($bc[($i + 1) % 5][1] << 1) | ($bc[($i + 1) % 5][2] >> 15)) & 0xFFFF),
                    $bc[($i + 4) % 5][2] ^ ((($bc[($i + 1) % 5][2] << 1) | ($bc[($i + 1) % 5][3] >> 15)) & 0xFFFF),
                    $bc[($i + 4) % 5][3] ^ ((($bc[($i + 1) % 5][3] << 1) | ($bc[($i + 1) % 5][0] >> 15)) & 0xFFFF),
                ];

                for ($j = 0; $j < 25; $j += 5) {
                    $st[$j + $i] = [
                        $st[$j + $i][0] ^ $t[0],
                        $st[$j + $i][1] ^ $t[1],
                        $st[$j + $i][2] ^ $t[2],
                        $st[$j + $i][3] ^ $t[3],
                    ];
                }
            }

            // Rho Pi
            $t = $st[1];

            for ($i = 0; $i < 24; $i++) {
                $j = self::$keccakf_piln[$i];
                $bc[0] = $st[$j];

                $n = self::$keccakf_rotc[$i] >> 4;
                $m = self::$keccakf_rotc[$i] % 16;

                $st[$j] = [
                    ((($t[(0 + $n) % 4] << $m) | ($t[(1 + $n) % 4] >> (16 - $m))) & 0xFFFF),
                    ((($t[(1 + $n) % 4] << $m) | ($t[(2 + $n) % 4] >> (16 - $m))) & 0xFFFF),
                    ((($t[(2 + $n) % 4] << $m) | ($t[(3 + $n) % 4] >> (16 - $m))) & 0xFFFF),
                    ((($t[(3 + $n) % 4] << $m) | ($t[(0 + $n) % 4] >> (16 - $m))) & 0xFFFF),
                ];

                $t = $bc[0];
            }

            // Chi
            for ($j = 0; $j < 25; $j += 5) {
                for ($i = 0; $i < 5; $i++) {
                    $bc[$i] = $st[$j + $i];
                }

                for ($i = 0; $i < 5; $i++) {
                    $st[$j + $i] = [
                        $st[$j + $i][0] ^ ~$bc[($i + 1) % 5][0] & $bc[($i + 2) % 5][0],
                        $st[$j + $i][1] ^ ~$bc[($i + 1) % 5][1] & $bc[($i + 2) % 5][1],
                        $st[$j + $i][2] ^ ~$bc[($i + 1) % 5][2] & $bc[($i + 2) % 5][2],
                        $st[$j + $i][3] ^ ~$bc[($i + 1) % 5][3] & $bc[($i + 2) % 5][3],
                    ];
                }
            }

            // Iota
            $st[0] = [
                $st[0][0] ^ $keccakf_rndc[$round][0],
                $st[0][1] ^ $keccakf_rndc[$round][1],
                $st[0][2] ^ $keccakf_rndc[$round][2],
                $st[0][3] ^ $keccakf_rndc[$round][3],
            ];
        }
    }

    /**
     * @param string $inRaw
     * @param int $capacity
     * @param int $outputLength
     * @param int $suffix
     * @param bool $rawOutput
     *
     * @return string
     */
    private static function keccak32($inRaw, $capacity, $outputLength, $suffix, $rawOutput)
    {
        $capacity /= 8;

        $inlen = self::strlen($inRaw);

        $rsiz = 200 - 2 * $capacity;
        $rsizw = $rsiz / 8;

        $st = [];

        for ($i = 0; $i < 25; $i++) {
            $st[] = [0, 0, 0, 0];
        }

        for ($in_t = 0; $inlen >= $rsiz; $inlen -= $rsiz, $in_t += $rsiz) {
            for ($i = 0; $i < $rsizw; $i++) {
                $t = unpack('v*', self::substr($inRaw, (int) ($i * 8 + $in_t), 8));

                $st[$i] = [
                    $st[$i][0] ^ $t[4],
                    $st[$i][1] ^ $t[3],
                    $st[$i][2] ^ $t[2],
                    $st[$i][3] ^ $t[1],
                ];
            }

            self::keccakf32($st, self::KECCAK_ROUNDS);
        }

        $temp = self::substr($inRaw, (int) $in_t, (int) $inlen);
        $temp = str_pad($temp, (int) $rsiz, "\x0", STR_PAD_RIGHT);
        $temp = substr_replace($temp, chr($suffix), $inlen, 1);
        $temp = substr_replace($temp, chr((int) $temp[(int) ($rsiz - 1)] | 0x80), $rsiz - 1, 1);

        for ($i = 0; $i < $rsizw; $i++) {
            $t = unpack('v*', self::substr($temp, $i * 8, 8));

            $st[$i] = [
                $st[$i][0] ^ $t[4],
                $st[$i][1] ^ $t[3],
                $st[$i][2] ^ $t[2],
                $st[$i][3] ^ $t[1],
            ];
        }

        self::keccakf32($st, self::KECCAK_ROUNDS);

        $out = '';

        for ($i = 0; $i < 25; $i++) {
            $out .= pack('v*', $st[$i][3], $st[$i][2], $st[$i][1], $st[$i][0]);
        }

        $r = self::substr($out, 0, (int) ($outputLength / 8));

        return $rawOutput ? $r : bin2hex($r);
    }
}
