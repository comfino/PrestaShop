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

namespace Comfino\Order;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\ErrorCategory;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\PluginShared\CacheManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The financing product (type and term) a shopper picked in the paywall, kept in a cart-scoped cookie as a fallback
 * for checkouts that do not post the hidden loan inputs, and validated the same way wherever it comes from.
 */
final class LoanSelection
{
    public const COOKIE_KEY = 'comfino_loan_selection';
    public const MAX_TERM = 120;
    public const CODE_RECOVERED = 'LOAN_SELECTION_NOT_POSTED_RECOVERED';
    public const CODE_DEFAULTED = 'LOAN_SELECTION_NOT_POSTED_DEFAULTED';

    private const REPORT_INTERVAL = 86400;
    private const STATE_TTL = 2592000;
    private const EXCLUDED_DEFAULT_TYPES = ['INSTANT_PAYMENTS', 'BLIK'];

    /**
     * Validates a selection against the loan type enum, the term bound and, when product type filters are active, the
     * allowed product types (null means "no filter", not "no validation").
     *
     * @param string[]|null $allowedProductTypes
     *
     * @return array{0: string, 1: int}|null [type, term] or null when the selection is not acceptable
     */
    public static function validate(string $loanType, int $loanTerm, ?array $allowedProductTypes): ?array
    {
        $loanType = trim($loanType);

        if ($loanType === '' || $loanTerm < 1 || $loanTerm > self::MAX_TERM || preg_match('/^[A-Z_]{1,40}$/', $loanType) !== 1) {
            return null;
        }

        try {
            new LoanTypeEnum($loanType);
        } catch (\Throwable $e) {
            return null;
        }

        if ($allowedProductTypes !== null && !in_array($loanType, array_map('strval', $allowedProductTypes), true)) {
            return null;
        }

        return [$loanType, $loanTerm];
    }

    /**
     * Picks the default financial product when the shopper's selection is unavailable (direct-redirect mode with no paywall, or an order posted without a usable selection): the
     * first offer, skipping INSTANT_PAYMENTS and BLIK. INSTANT_PAYMENTS starts a completely different payment path that
     * cannot be changed later, and BLIK is excluded as a default as well. When only excluded types are offered, nothing
     * is chosen.
     *
     * @param object[] $financialProducts Offers in the order returned by the financial products endpoint.
     *
     * @return object|null
     */
    public static function selectDefaultProduct(array $financialProducts)
    {
        foreach ($financialProducts as $financialProduct) {
            if (!in_array((string) $financialProduct->type, self::EXCLUDED_DEFAULT_TYPES, true)) {
                return $financialProduct;
            }
        }

        return null;
    }

    public static function store(\Cookie $cookie, int $cartId, string $loanType, int $loanTerm): void
    {
        $cookie->{self::COOKIE_KEY} = json_encode(['cart' => $cartId, 'type' => $loanType, 'term' => $loanTerm]);
        $cookie->write();
    }

    /**
     * @return array{0: string, 1: int}|null Raw [type, term] stored for this cart, not yet validated.
     */
    public static function load(\Cookie $cookie, int $cartId): ?array
    {
        $stored = isset($cookie->{self::COOKIE_KEY}) ? json_decode((string) $cookie->{self::COOKIE_KEY}, true) : null;

        if (!is_array($stored) || (int) ($stored['cart'] ?? 0) !== $cartId || $cartId === 0) {
            return null;
        }

        return [(string) ($stored['type'] ?? ''), (int) ($stored['term'] ?? 0)];
    }

    /**
     * Counts one occurrence of a code and tells whether a report is due: the first occurrence is due at once, then one
     * report per interval. The counter keeps accumulating between reports, so a due report carries the number of
     * occurrences since the previous report and its start time.
     *
     * @param string $code One of the CODE_* constants.
     *
     * @return array{count: int, since: int}|null Summary to report, or null when the interval has not elapsed yet.
     */
    public static function recordOccurrence(string $code): ?array
    {
        $cacheKey = 'loan_selection_report.' . strtolower($code);
        $now = time();
        $state = CacheManager::get($cacheKey);

        if (!is_array($state) || !isset($state['since'], $state['count'])) {
            $state = ['since' => $now, 'count' => 0, 'reported' => false];
        }

        $state['count'] = (int) $state['count'] + 1;
        $due = empty($state['reported']) || $now - (int) $state['since'] >= self::REPORT_INTERVAL;

        if ($due) {
            $summary = ['count' => $state['count'], 'since' => (int) $state['since']];

            $state = ['since' => $now, 'count' => 0, 'reported' => true];
        } else {
            $summary = null;
        }

        CacheManager::set($cacheKey, $state, self::STATE_TTL);

        return $summary;
    }

    /**
     * Logs the missing selection locally and reports it to ComfinoPay at most once per code per 24 hours. The report
     * is a summary: the number of occurrences since the previous report, to show the scale of the problem in the shop.
     * It comes from the backend: only the controller sees a POST that arrives without the selection.
     *
     * @param string $code One of the CODE_* constants.
     * @param string[] $missingFields
     */
    public static function reportNotPosted(string $code, array $missingFields): void
    {
        $checkoutKind = COMFINO_PS_17 ? '1.7+' : '1.6';

        DebugLogger::logEvent('[PAYMENT]', 'Loan selection not posted', [
            'code' => $code,
            'missing_fields' => $missingFields,
            'checkout' => $checkoutKind,
        ]);

        if (($summary = self::recordOccurrence($code)) === null) {
            return;
        }

        try {
            ErrorLogger::sendWarning(
                ErrorCategory::ConfigurationError,
                OperationContext::OrderCreation,
                $code,
                sprintf(
                    'Orders submitted without the selected financing offer: %d since %s (missing in the latest: %s), '
                    . 'checkout %s, %s.',
                    $summary['count'],
                    gmdate('Y-m-d H:i:s', $summary['since']) . ' UTC',
                    implode(', ', $missingFields),
                    $checkoutKind,
                    $code === self::CODE_RECOVERED
                        ? 'selection recovered from the stored choice'
                        : 'safe default financing offer used'
                )
            );
        } catch (\Throwable $e) {
            // Reporting is best-effort.
        }
    }
}
