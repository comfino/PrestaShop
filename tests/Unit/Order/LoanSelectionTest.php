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

namespace Comfino\Tests\Unit\Order;

use Comfino\Order\LoanSelection;
use Comfino\PluginShared\CacheManager;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * Loan selection validation, the cart-scoped cookie fallback and the 24 h warning dedupe.
 */
class LoanSelectionTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        TestState::reset();
        CacheManager::init('');
        putenv('COMFINO_DEV_ENV=TRUE'); // Keeps the shared ErrorLogger from sending anything.
    }

    protected function tearDown()
    {
        putenv('COMFINO_DEV_ENV');
        CacheManager::init('');
        TestState::reset();

        parent::tearDown();
    }

    /**
     * @dataProvider selectionProvider
     */
    public function testValidate($type, $term, $allowed, $expected)
    {
        $this->assertSame($expected, LoanSelection::validate($type, $term, $allowed));
    }

    public function selectionProvider()
    {
        return [
            'valid, no filter' => ['PAY_LATER', 12, null, ['PAY_LATER', 12]],
            'valid, allowed' => ['PAY_LATER', 12, ['PAY_LATER', 'BLIK'], ['PAY_LATER', 12]],
            'not in allowlist' => ['LEASING', 12, ['PAY_LATER'], null],
            'empty filter result' => ['PAY_LATER', 12, [], null],
            'unknown type' => ['MADE_UP', 12, null, null],
            'lowercase type' => ['pay_later', 12, null, null],
            'markup' => ['<script>', 12, null, null],
            'empty' => ['', 0, null, null],
            'zero term' => ['PAY_LATER', 0, null, null],
            'negative term' => ['PAY_LATER', -3, null, null],
            'term above bound' => ['PAY_LATER', 121, null, null],
        ];
    }

    public function testCookieRoundTripIsCartScoped()
    {
        $cookie = new \Cookie();

        LoanSelection::store($cookie, 7, 'INSTALLMENTS_ZERO_PERCENT', 10);

        $this->assertSame(['INSTALLMENTS_ZERO_PERCENT', 10], LoanSelection::load($cookie, 7));
        $this->assertNull(LoanSelection::load($cookie, 8), 'Another cart must not see the selection.');
        $this->assertNull(LoanSelection::load($cookie, 0));
        $this->assertNull(LoanSelection::load(new \Cookie(), 7));
    }

    public function testTamperedCookieYieldsNothingUsable()
    {
        $cookie = new \Cookie();
        $cookie->{LoanSelection::COOKIE_KEY} = '{not json';

        $this->assertNull(LoanSelection::load($cookie, 7));

        $cookie->{LoanSelection::COOKIE_KEY} = json_encode(['cart' => 7, 'type' => '<b>', 'term' => 'x']);
        $stored = LoanSelection::load($cookie, 7);

        $this->assertNull(LoanSelection::validate($stored[0], $stored[1], null));
    }

    public function testOccurrencesAccumulateBetweenDailyReports()
    {
        $key = 'loan_selection_report.loan_selection_not_posted_defaulted';

        $first = LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED);

        $this->assertSame(1, $first['count']);
        $this->assertNull(LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED));
        $this->assertNull(LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED));
        $this->assertNull(CacheManager::get('loan_selection_report.loan_selection_not_posted_recovered'));

        $state = CacheManager::get($key);

        $this->assertSame(2, $state['count']);

        $state['since'] = time() - 86401;
        CacheManager::set($key, $state, 86400);

        $summary = LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED);

        $this->assertSame(3, $summary['count']);
        $this->assertSame($state['since'], $summary['since']);
        $this->assertSame(0, CacheManager::get($key)['count']);
        $this->assertNull(LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED));
    }

    /**
     * @dataProvider defaultProductProvider
     */
    public function testDefaultProductSkipsInstantPaymentsAndBlik(array $types, $expected)
    {
        $products = array_map(static function ($type) {
            return (object) ['type' => $type, 'loanTerm' => 12];
        }, $types);

        $selected = LoanSelection::selectDefaultProduct($products);

        $this->assertSame($expected, $selected === null ? null : $selected->type);
    }

    public function defaultProductProvider()
    {
        return [
            'first is fine' => [['INSTALLMENTS_ZERO_PERCENT', 'INSTANT_PAYMENTS'], 'INSTALLMENTS_ZERO_PERCENT'],
            'instant payments first' => [['INSTANT_PAYMENTS', 'PAY_LATER'], 'PAY_LATER'],
            'blik first' => [['BLIK', 'PAY_LATER'], 'PAY_LATER'],
            'both excluded first' => [['INSTANT_PAYMENTS', 'BLIK', 'COMPANY_BNPL', 'PAY_LATER'], 'COMPANY_BNPL'],
            'both excluded, reversed' => [['BLIK', 'INSTANT_PAYMENTS', 'LEASING'], 'LEASING'],
            'only excluded' => [['INSTANT_PAYMENTS', 'BLIK'], null],
            'empty' => [[], null],
        ];
    }
}
