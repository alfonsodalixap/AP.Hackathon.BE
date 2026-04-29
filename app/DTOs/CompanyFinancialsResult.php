<?php

namespace App\DTOs;

readonly class CompanyFinancialsResult
{
    public function __construct(
        public string $company,
        public string $ticker,
        public string $cik,
        public ?string $fiscal_year,
        public ?int $total_revenue,
        public ?int $ebitda,
        public ?int $operating_income,
        public ?int $depreciation_amortization,
        public ?int $total_expenses,
        public array $expense_breakdown,
        public string $source,
        public ?string $filing_date,
    ) {}
}
