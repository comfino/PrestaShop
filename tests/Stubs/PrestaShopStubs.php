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

/*
 * Minimal in-memory doubles of the PrestaShop classes the module code reaches in unit tests. They implement only the
 * calls the tested code makes; extend them when a new test needs more. Comfino\Tests\TestState::reset() clears them.
 */

if (!class_exists('Configuration', false)) {
    class Configuration
    {
        /** @var array<string, mixed> Values keyed by "name|idShopGroup|idShop" (0 = unset scope level). */
        public static $values = [];

        public static function get($key, $idLang = null, $idShopGroup = null, $idShop = null, $default = false)
        {
            // PrestaShop's shop -> group -> global fallback.
            foreach ([[(int) $idShopGroup, (int) $idShop], [(int) $idShopGroup, 0], [0, 0]] as [$group, $shop]) {
                if (array_key_exists("$key|$group|$shop", self::$values)) {
                    return self::$values["$key|$group|$shop"];
                }
            }

            return $default;
        }

        public static function updateValue($key, $values, $html = false, $idShopGroup = null, $idShop = null): bool
        {
            self::$values[$key . '|' . (int) $idShopGroup . '|' . (int) $idShop] = $values;

            return true;
        }

        public static function hasKey($key, $idLang = null, $idShopGroup = null, $idShop = null): bool
        {
            return array_key_exists($key . '|' . (int) $idShopGroup . '|' . (int) $idShop, self::$values);
        }
    }
}

if (!class_exists('Db', false)) {
    class Db
    {
        /** @var string[] SQL passed to getValue(), in call order. */
        public static $queries = [];
        /** @var callable|null Maps the SQL passed to getValue() to its result; null makes every query return false. */
        public static $valueResolver;

        /** @var self|null */
        private static $instance;

        public static function getInstance($master = true): self
        {
            return self::$instance ?? (self::$instance = new self());
        }

        public function getValue($sql, $useCache = true): bool
        {
            self::$queries[] = $sql;

            return self::$valueResolver !== null ? (self::$valueResolver)($sql) : false;
        }

        public function getVersion(): string
        {
            return '8.0.0-test';
        }
    }
}

if (!class_exists('Shop', false)) {
    class Shop
    {
        public const CONTEXT_SHOP = 1;
        public const CONTEXT_GROUP = 2;
        public const CONTEXT_ALL = 4;

        /** @var bool */
        public static $featureActive = false;
        /** @var int */
        public static $context = self::CONTEXT_ALL;
        /** @var int|null */
        public static $contextShopId;
        /** @var int|null */
        public static $contextShopGroupId;

        public static function isFeatureActive(): bool
        {
            return self::$featureActive;
        }

        public static function getContext(): int
        {
            return self::$context;
        }

        public static function getContextShopID($nullIfNoShop = false): ?int
        {
            return self::$contextShopId;
        }

        public static function getContextShopGroupID($nullIfNoShop = false): ?int
        {
            return self::$contextShopGroupId;
        }

        /**
         * Test helper: switches the ambient context to one shop of an active Multistore install.
         */
        public static function setShopContext(int $idShop, int $idShopGroup = 1): void
        {
            self::$featureActive = true;
            self::$context = self::CONTEXT_SHOP;
            self::$contextShopId = $idShop;
            self::$contextShopGroupId = $idShopGroup;
        }
    }
}

if (!class_exists('Link', false)) {
    class Link
    {
        public function getModuleLink($module, $controller = 'default', array $params = [], $ssl = null, $idLang = null): string
        {
            return "https://shop.test/module/$module/$controller" . (!empty($params) ? '?' . http_build_query($params) : '');
        }
    }
}

if (!class_exists('Context', false)) {
    class Context
    {
        /** @var Link */
        public $link;
        /** @var object */
        public $language;

        /** @var self|null */
        private static $instance;

        public static function getContext(): ?Context
        {
            if (self::$instance === null) {
                self::$instance = new self();
                self::$instance->link = new Link();
                self::$instance->language = (object) ['id' => 1, 'iso_code' => 'pl'];
            }

            return self::$instance;
        }
    }
}
