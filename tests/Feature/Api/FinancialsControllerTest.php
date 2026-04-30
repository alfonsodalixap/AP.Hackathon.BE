<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinancialsControllerTest extends TestCase
{
    private function tickersPayload(): array
    {
        return [
            '0' => ['ticker' => 'AAPL', 'cik_str' => 320193, 'title' => 'Apple Inc.'],
        ];
    }

    private function factsPayload(): array
    {
        $entry = fn(int $val) => [
            'form' => '10-K', 'fp' => 'FY', 'end' => '2024-09-28', 'val' => $val, 'filed' => '2024-11-01',
        ];

        return [
            'facts' => [
                'us-gaap' => [
                    'Revenues'           => ['units' => ['USD' => [$entry(391035000000)]]],
                    'OperatingIncomeLoss'=> ['units' => ['USD' => [$entry(123216000000)]]],
                    'DepreciationAndAmortization' => ['units' => ['USD' => [$entry(11445000000)]]],
                ],
            ],
        ];
    }

    public function test_returns_financial_data_for_valid_ticker(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
            '*companyfacts*'    => Http::response($this->factsPayload()),
        ]);

        $response = $this->getJson('/api/financials/AAPL');

        $response->assertOk()
                 ->assertJsonStructure([
                     'data' => [
                         'company', 'ticker', 'cik', 'fiscal_year', 'total_revenue',
                         'ebitda', 'operating_income', 'depreciation_amortization',
                         'total_expenses', 'expense_breakdown', 'source', 'filing_date',
                     ],
                 ])
                 ->assertJsonPath('data.company', 'Apple Inc.')
                 ->assertJsonPath('data.ticker', 'AAPL')
                 ->assertJsonPath('data.total_revenue', 391035000000)
                 ->assertJsonPath('data.fiscal_year', '2024')
                 ->assertJsonPath('data.source', 'SEC EDGAR 10-K');
    }

    public function test_returns_404_for_unknown_ticker(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
        ]);

        $response = $this->getJson('/api/financials/ZZZZ');

        $response->assertStatus(404)
                 ->assertJsonStructure(['message']);
    }

    public function test_returns_502_when_edgar_unavailable(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response(null, 503),
        ]);

        $response = $this->getJson('/api/financials/AAPL');

        $response->assertStatus(502)
                 ->assertJsonStructure(['message']);
    }

    public function test_ticker_lookup_is_case_insensitive(): void
    {
        Http::fake([
            '*company_tickers*' => Http::response($this->tickersPayload()),
            '*companyfacts*'    => Http::response($this->factsPayload()),
        ]);

        $response = $this->getJson('/api/financials/aapl');

        $response->assertOk()
                 ->assertJsonPath('data.ticker', 'AAPL');
    }
}
