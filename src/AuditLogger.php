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

require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Tools.php';

/**
 * Append-only record of every change to the plugin's configuration.
 *
 * The remote `configuration` endpoint can rewrite most of the module's settings, including the URLs of assets
 * loaded on the storefront. Without a record, a change made through that endpoint leaves no locally
 * reconstructable trace of who changed what, when, and from what to what.
 *
 * The file lives in the plugin's log directory (outside the document root) and is rotated on size like the
 * payment log.
 */
class AuditLogger
{
    /** Maximum size of the audit log before it is rotated. */
    const LOG_MAX_SIZE = 1048576;

    const LOG_FILE_NAME = 'config_audit.log';

    /** Actor labels. */
    const ACTOR_REMOTE_API = 'remote-api';
    const ACTOR_ADMIN = 'admin';
    const ACTOR_UPGRADE = 'upgrade';
    const ACTOR_INSTALL = 'install';

    /** Options whose values must be recorded as a change indicator only, never as plaintext. */
    const MASKED_OPTIONS = [
        'COMFINO_API_KEY',
        'COMFINO_SANDBOX_API_KEY',
        'COMFINO_WIDGET_KEY',
        'COMFINO_ERROR_LOGGING_ACCESS_TOKEN',
    ];

    /**
     * Records a set of configuration changes. Only options whose value actually changed are written, so a save
     * that touches nothing produces no entry.
     *
     * @param string $actor One of the ACTOR_* labels.
     * @param array $changes Map of option name => ['old' => mixed, 'new' => mixed].
     *
     * @return void
     */
    public static function logConfigurationChanges($actor, array $changes)
    {
        if (!count($changes)) {
            return;
        }

        $entries = [];

        foreach ($changes as $option_name => $change) {
            $entries[] = sprintf(
                '%s: %s -> %s',
                $option_name,
                self::formatValue($option_name, $change['old']),
                self::formatValue($option_name, $change['new'])
            );
        }

        $context = \Context::getContext();

        self::write(sprintf(
            '[%s] actor=%s source=%s shop=%s | %s',
            date('Y-m-d H:i:s'),
            $actor,
            self::describeSource($actor),
            isset($context->shop, $context->shop->id) ? (int) $context->shop->id : 0,
            implode('; ', $entries)
        ));
    }

    /**
     * Returns the last N lines of the audit log, for display in the plugin diagnostics tab.
     *
     * @param int $num_lines
     *
     * @return string
     */
    public static function getAuditLog($num_lines)
    {
        $log_file_path = self::getLogFilePath();

        if ($log_file_path === '' || !file_exists($log_file_path)) {
            return '';
        }

        $file = new \SplFileObject($log_file_path, 'r');
        $file->seek(PHP_INT_MAX);

        $last_line = $file->key();
        $lines = new \LimitIterator(
            $file,
            $last_line > $num_lines ? $last_line - $num_lines : 0,
            $last_line
        );

        return implode('', iterator_to_array($lines));
    }

    /**
     * @return string Empty string when the log directory could not be prepared.
     */
    public static function getLogFilePath()
    {
        $log_dir = ErrorLogger::getLogDirectory();

        return $log_dir !== '' ? $log_dir . '/' . self::LOG_FILE_NAME : '';
    }

    /**
     * @return void
     */
    public static function clearLogs()
    {
        $log_file_path = self::getLogFilePath();

        if ($log_file_path !== '' && file_exists($log_file_path)) {
            unlink($log_file_path);
        }
    }

    /**
     * Adds whatever identifies the actor concretely: the remote address for API-driven changes, the employee
     * for changes made in the back office.
     *
     * @param string $actor
     *
     * @return string
     */
    private static function describeSource($actor)
    {
        if ($actor === self::ACTOR_REMOTE_API) {
            return Tools::getServerValue('REMOTE_ADDR', 'unknown');
        }

        if ($actor === self::ACTOR_ADMIN) {
            $context = \Context::getContext();

            return isset($context->employee, $context->employee->id) && $context->employee->id
                ? 'employee#' . (int) $context->employee->id
                : 'employee#unknown';
        }

        return 'local';
    }

    /**
     * @param string $option_name
     * @param mixed $value
     *
     * @return string
     */
    private static function formatValue($option_name, $value)
    {
        if (in_array($option_name, self::MASKED_OPTIONS, true)) {
            /* Secrets are never written to the audit log - only whether one was set. */
            return $value === null || $value === false || $value === '' ? '(empty)' : '(set)';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '(null)';
        }

        if (is_array($value)) {
            $value = json_encode($value);
        }

        $value = (string) $value;

        if ($value === '') {
            return '(empty)';
        }

        /* Long values (category filter maps, CSS selectors) are truncated - the audit log records that a change
           happened and its shape, not a full backup of the value. */
        return \Tools::strlen($value) > 200 ? \Tools::substr($value, 0, 200) . '...(truncated)' : $value;
    }

    /**
     * @param string $entry
     *
     * @return void
     */
    private static function write($entry)
    {
        $log_file_path = self::getLogFilePath();

        if ($log_file_path === '') {
            error_log('Comfino: unable to prepare the log directory. ' . $entry);

            return;
        }

        self::rotateLog($log_file_path);

        if (file_put_contents($log_file_path, $entry . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log("Comfino: unable to write to $log_file_path. " . $entry);
        }
    }

    /**
     * @param string $log_file_path
     *
     * @return void
     */
    private static function rotateLog($log_file_path)
    {
        clearstatcache(true, $log_file_path);

        if (!file_exists($log_file_path) || filesize($log_file_path) < self::LOG_MAX_SIZE) {
            return;
        }

        $rotated_log_path = $log_file_path . '.1';

        if (file_exists($rotated_log_path)) {
            unlink($rotated_log_path);
        }

        rename($log_file_path, $rotated_log_path);
    }
}
