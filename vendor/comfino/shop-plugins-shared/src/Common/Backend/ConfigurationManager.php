<?php

namespace Comfino\Common\Backend;

use Comfino\Api\SerializerInterface;
use Comfino\Common\Backend\Configuration\StorageAdapterInterface;

final class ConfigurationManager
{
    // Data types of configuration options as bit masks.
    public const OPT_VALUE_TYPE_STRING = (1 << 0);
    public const OPT_VALUE_TYPE_INT = (1 << 1);
    public const OPT_VALUE_TYPE_FLOAT = (1 << 2);
    public const OPT_VALUE_TYPE_BOOL = (1 << 3);
    public const OPT_VALUE_TYPE_ARRAY = (1 << 4);
    public const OPT_VALUE_TYPE_JSON = (1 << 5);
    public const OPT_VALUE_TYPE_STRING_ARRAY = self::OPT_VALUE_TYPE_STRING | self::OPT_VALUE_TYPE_ARRAY;
    public const OPT_VALUE_TYPE_INT_ARRAY = self::OPT_VALUE_TYPE_INT | self::OPT_VALUE_TYPE_ARRAY;
    public const OPT_VALUE_TYPE_FLOAT_ARRAY = self::OPT_VALUE_TYPE_FLOAT | self::OPT_VALUE_TYPE_ARRAY;
    public const OPT_VALUE_TYPE_BOOL_ARRAY = self::OPT_VALUE_TYPE_BOOL | self::OPT_VALUE_TYPE_ARRAY;

    private static ?self $instance = null;
    private ?array $configuration = null;
    private array $modified;
    private bool $loaded = false;

    /**
     * @param int[] $availConfigOptions List of available configuration options with data types as pairs [OPTION_NAME => OPT_VALUE_TYPE].
     * @param string[] $accessibleConfigOptions List of accessible configuration options via REST endpoints.
     */
    public static function getInstance(
        array $availConfigOptions,
        array $accessibleConfigOptions,
        StorageAdapterInterface $storageAdapter,
        SerializerInterface $serializer
    ): self {
        if (self::$instance === null) {
            self::$instance = new self($availConfigOptions, $accessibleConfigOptions, $storageAdapter, $serializer);
        }

        return self::$instance;
    }

    /**
     * @param int[] $availConfigOptions List of available configuration options with data types as pairs [OPTION_NAME => OPT_VALUE_TYPE].
     * @param string[] $accessibleConfigOptions List of accessible configuration options via REST endpoints.
     */
    private function __construct(
        private readonly array $availConfigOptions,
        private readonly array $accessibleConfigOptions,
        private readonly StorageAdapterInterface $storageAdapter,
        private readonly SerializerInterface $serializer
    ) {
        $this->modified = array_combine($availConfigOptions, array_fill(0, count($availConfigOptions), false));
    }

    public function __destruct()
    {
        $this->persist();
    }

    public function returnConfigurationOptions(): array
    {
        return $this->getConfigurationValues($this->accessibleConfigOptions);
    }

    public function updateConfigurationOptions(array $configurationOptions): void
    {
        $this->setConfigurationValues($configurationOptions, $this->accessibleConfigOptions);
    }

    public function getConfigurationValue(string $optionName): mixed
    {
        return $this->getConfiguration()[$optionName] ?? null;
    }

    /**
     * @param string[] $optionNames
     */
    public function getConfigurationValues(array $optionNames): array
    {
        return array_intersect_key($this->getConfiguration(), array_flip($optionNames));
    }

    public function setConfigurationValue(string $optionName, mixed $optionValue): void
    {
        if (isset($this->availConfigOptions[$optionName])) {
            $this->getConfiguration()[$optionName] = $optionValue;
            $this->modified[$optionName] = true;
        }
    }

    public function setConfigurationValues(array $configurationOptions, ?array $accessibleOptions = null): void
    {
        if ($this->configuration === null) {
            $this->configuration = [];
        }

        foreach ($configurationOptions as $optionName => $optionValue) {
            if (empty($accessibleOptions) || in_array($optionName, $accessibleOptions, true)) {
                $this->configuration[$optionName] = $optionValue;
                $this->modified[$optionName] = true;
            }
        }
    }

    public function persist(): void
    {
        if ($this->configuration !== null && count($optionsToSave = array_intersect_key($this->configuration, array_filter($this->modified)))) {
            foreach ($optionsToSave as $optionName => &$optionValue) {
                if (($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) && is_array($optionValue)) {
                    $optionValue = implode(',', $optionValue);
                } elseif ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_JSON) {
                    $optionValue = $this->serializer->serialize($optionValue);
                }
            }

            unset($optionValue);

            $this->storageAdapter->save($optionsToSave);

            $this->modified = array_merge($this->modified, array_combine($optionsToSave, array_fill(0, count($optionsToSave), false)));
        }
    }

    private function &getConfiguration(): array
    {
        if ($this->configuration === null) {
            $this->configuration = [];

            $this->load();

            $this->loaded = true;
        } elseif (!$this->loaded) {
            $modifiedOptions = $this->configuration;

            $this->load();

            $this->configuration = array_merge($this->configuration, $modifiedOptions);
            $this->loaded = true;
        }

        return $this->configuration;
    }

    private function load(): void
    {
        foreach ($this->storageAdapter->load() as $optionName => $optionValue) {
            if (isset($this->availConfigOptions[$optionName])) {
                switch ($this->availConfigOptions[$optionName] & (~self::OPT_VALUE_TYPE_ARRAY)) {
                    case self::OPT_VALUE_TYPE_STRING:
                        if ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) {
                            $this->configuration[$optionName] = array_map(
                                static fn ($value): string => (string) $value,
                                explode(',', $this->configuration[$optionName])
                            );
                        } else {
                            $this->configuration[$optionName] = (string) $optionValue;
                        }

                        break;

                    case self::OPT_VALUE_TYPE_INT:
                        if ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) {
                            $this->configuration[$optionName] = array_map(
                                static fn ($value): int => (int) $value,
                                explode(',', $this->configuration[$optionName])
                            );
                        } else {
                            $this->configuration[$optionName] = (int) $optionValue;
                        }

                        break;

                    case self::OPT_VALUE_TYPE_FLOAT:
                        if ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) {
                            $this->configuration[$optionName] = array_map(
                                static fn ($value): float => (float) $value,
                                explode(',', $this->configuration[$optionName])
                            );
                        } else {
                            $this->configuration[$optionName] = (float) $optionValue;
                        }

                        break;

                    case self::OPT_VALUE_TYPE_BOOL:
                        if ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) {
                            $this->configuration[$optionName] = array_map(
                                static fn ($value): bool => (bool) $value,
                                explode(',', $this->configuration[$optionName])
                            );
                        } else {
                            $this->configuration[$optionName] = (bool) $optionValue;
                        }

                        break;

                    case self::OPT_VALUE_TYPE_JSON:
                        $this->configuration[$optionName] = !empty($optionValue) ? $this->serializer->unserialize($optionValue) : null;
                        break;
                }
            }
        }
    }
}
