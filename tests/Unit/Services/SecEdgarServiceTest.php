<?php

namespace Tests\Unit\Services;

use App\Services\SecEdgarService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SecEdgarServiceTest extends TestCase
{
    private SecEdgarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SecEdgarService();
    }

    private function tickersPayload(): array
    {
        return [
            '0' => ['ticker' => 'AAPL', 'cik_str' => 320193,  'title' => 'Apple Inc.'],
            '1' => ['ticker' => 'MSFT', 'cik_str' => 789019,  'title' => 'Microsoft Corp.'],
        ];
    }

    private function factsPayload(int $revenue = 391035000000, int $opIncome = 123216000000, int $da = 11445000000): array
    {
        $entry = fn(int $val, string $end = '2024-09-28') => [
            'form' => '10-K', 'fp' => 'FY', 'end' => $end, 'val' => $val, 'filed' => '2024-11-01',
        ];

        return [
            'facts' => [
                'us-gaap' => [
                    'Revenues'                      => ['units' => ['USD' => [$entry($revenue)]]],
                    'OperatingIncomeLoss'            => ['units' => ['USD' => [$entry($opIncome)]]],
                    'DepreciationAndAmortization'    => ['units' => ['USD' => [$entry($da)]]],
                    'CostOfRevenue'                 => ['units' => ['USD' => [$entry(210352000000)]]],
                    'ResearchAndDevelopmentExpense' => ['units' => ['USD' => [$entry(31370000000)]]],
                    'SellingGeneralAndAdministrativeExpense' => ['units' => ['USD' => [$entry(26097000000)]]],
                ],
            ],
        ];
    }

    public function test_get_financials_returns_mapped_result(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
            '*companyfacts*'    => Http::response($this->factsPayload()),
        ]);

        $result = $this->service->getFinancials('AAPL');

        $this->assertEquals('Apple Inc.', $result->company);
        $this->assertEquals('AAPL', $result->ticker);
        $this->assertEquals('0000320193', $result->cik);
        $this->assertEquals(391035000000, $result->total_revenue);
        $this->assertEquals('2024', $result->fiscal_year);
        $this->assertEquals('2024-11-01', $result->filing_date);
        $this->assertEquals('SEC EDGAR 10-K', $result->source);
    }

    public function test_get_financials_computes_ebitda_as_operating_income_plus_da(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
            '*companyfacts*'    => Http::response($this->factsPayload(391035000000, 123216000000, 11445000000)),
        ]);

        $result = $this->service->getFinancials('AAPL');

        $this->assertEquals(123216000000 + 11445000000, $result->ebitda);
        $this->assertEquals(123216000000, $result->operating_income);
        $this->assertEquals(11445000000, $result->depreciation_amortization);
    }

    public function test_get_financials_includes_expense_breakdown(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
            '*companyfacts*'    => Http::response($this->factsPayload()),
        ]);

        $result = $this->service->getFinancials('AAPL');

        $this->assertEquals(210352000000, $result->expense_breakdown['cost_of_revenue']);
        $this->assertEquals(31370000000,  $result->expense_breakdown['research_and_development']);
        $this->assertEquals(26097000000,  $result->expense_breakdown['selling_general_admin']);
    }

    public function test_get_financials_is_case_insensitive(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
            '*companyfacts*'    => Http::response($this->factsPayload()),
        ]);

        $result = $this->service->getFinancials('aapl');

        $this->assertEquals('AAPL', $result->ticker);
    }

    public function test_get_financials_throws_404_for_unknown_ticker(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $this->service->getFinancials('ZZZZ');
    }

    public function test_get_financials_throws_502_when_tickers_endpoint_fails(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response(null, 503),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(502);

        $this->service->getFinancials('AAPL');
    }

    public function test_get_financials_uses_latest_annual_10k_entry(): void
    {
        $olderEntry  = ['form' => '10-K', 'fp' => 'FY', 'end' => '2023-09-30', 'val' => 383000000000, 'filed' => '2023-11-02'];
        $newerEntry  = ['form' => '10-K', 'fp' => 'FY', 'end' => '2024-09-28', 'val' => 391035000000, 'filed' => '2024-11-01'];
        $quarterEntry = ['form' => '10-Q', 'fp' => 'Q1', 'end' => '2024-12-28', 'val' => 999999999999, 'filed' => '2025-02-01'];

        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
            '*companyfacts*'    => Http::response([
                'facts' => [
                    'us-gaap' => [
                        'Revenues' => ['units' => ['USD' => [$olderEntry, $newerEntry, $quarterEntry]]],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->getFinancials('AAPL');

        $this->assertEquals(391035000000, $result->total_revenue);
    }
}
