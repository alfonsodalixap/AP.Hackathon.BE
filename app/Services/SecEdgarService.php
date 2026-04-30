<?php

namespace App\Services;

use App\DTOs\CompanyFinancialsResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SecEdgarService
{
    private const BASE_URL        = 'https://data.sec.gov';
    private const TICKERS_URL     = 'https://www.sec.gov/files/company_tickers.json';
    private const USER_AGENT      = 'AlixPartners Hackathon hackathon@alixpartners.com';

    public function getFinancials(string $ticker): CompanyFinancialsResult
    {
        $ticker = strtoupper(trim($ticker));

        [$cik, $company] = $this->resolveTicker($ticker);

        $facts = $this->fetchCompanyFacts($cik);
        $usGaap = $facts['facts']['us-gaap'] ?? [];

        $revenue    = $this->latestAnnual($usGaap, ['Revenues', 'RevenueFromContractWithCustomerExcludingAssessedTax', 'SalesRevenueNet']);
        $opIncome   = $this->latestAnnual($usGaap, ['OperatingIncomeLoss']);
        $da         = $this->latestAnnual($usGaap, ['DepreciationDepletionAndAmortization', 'DepreciationAndAmortization']);
        $expenses   = $this->latestAnnual($usGaap, ['OperatingExpenses', 'CostsAndExpenses']);
        $cogs       = $this->latestAnnual($usGaap, ['CostOfRevenue', 'CostOfGoodsSold']);
        $rd         = $this->latestAnnual($usGaap, ['ResearchAndDevelopmentExpense']);
        $sga        = $this->latestAnnual($usGaap, ['SellingGeneralAndAdministrativeExpense']);

        $opIncomeVal = $this->val($opIncome);
        $daVal       = $this->val($da);
        $ebitda      = ($opIncomeVal !== null && $daVal !== null) ? $opIncomeVal + $daVal : null;

        return new CompanyFinancialsResult(
            company: $company,
            ticker: $ticker,
            cik: $cik,
            fiscal_year: $revenue ? substr($revenue['end'] ?? '', 0, 4) : null,
            total_revenue: $this->val($revenue),
            ebitda: $ebitda,
            operating_income: $opIncomeVal,
            depreciation_amortization: $daVal,
            total_expenses: $this->val($expenses),
            expense_breakdown: [
                'cost_of_revenue'       => $this->val($cogs),
                'research_and_development' => $this->val($rd),
                'selling_general_admin' => $this->val($sga),
            ],
            source: 'SEC EDGAR 10-K',
            filing_date: $revenue['filed'] ?? null,
        );
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'User-Agent'      => self::USER_AGENT,
            'Accept-Encoding' => 'gzip, deflate',
            'Accept'          => 'application/json',
        ])->timeout(30)->withoutVerifying();
    }

    private function resolveTicker(string $ticker): array
    {
        try {
            $response = $this->http()->get(self::TICKERS_URL);
        } catch (\Exception $e) {
            throw new RuntimeException('SEC EDGAR unavailable — please enter the data manually. (' . $e->getMessage() . ')', 502);
        }

        if ($response->failed()) {
            throw new RuntimeException('SEC EDGAR unavailable — status ' . $response->status(), 502);
        }

        foreach ($response->json() as $entry) {
            if (strtoupper($entry['ticker'] ?? '') === $ticker) {
                return [str_pad((string) $entry['cik_str'], 10, '0', STR_PAD_LEFT), $entry['title'] ?? $ticker];
            }
        }

        throw new RuntimeException("Ticker '{$ticker}' was not found on SEC EDGAR.", 404);
    }

    private function fetchCompanyFacts(string $cik): array
    {
        try {
            $response = $this->http()->get(self::BASE_URL . "/api/xbrl/companyfacts/CIK{$cik}.json");
        } catch (\Exception $e) {
            throw new RuntimeException('Could not retrieve company data. (' . $e->getMessage() . ')', 502);
        }

        if ($response->failed()) {
            throw new RuntimeException('Could not retrieve company data — status ' . $response->status(), 502);
        }

        return $response->json();
    }

    private function latestAnnual(array $usGaap, array $concepts): ?array
    {
        foreach ($concepts as $concept) {
            $usd = $usGaap[$concept]['units']['USD'] ?? [];
            $annual = array_filter($usd, fn($e) => ($e['form'] ?? '') === '10-K' && ($e['fp'] ?? '') === 'FY');

            if (!empty($annual)) {
                usort($annual, fn($a, $b) => strcmp($b['end'] ?? '', $a['end'] ?? ''));
                return $annual[0];
            }
        }

        return null;
    }

    private function val(?array $entry): ?int
    {
        return $entry ? (int) $entry['val'] : null;
    }
}
