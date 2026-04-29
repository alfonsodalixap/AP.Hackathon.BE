<?php

namespace App\Http\Resources;

use App\DTOs\CompanyFinancialsResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompanyFinancialsResult */
class FinancialsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'company'                   => $this->company,
            'ticker'                    => $this->ticker,
            'cik'                       => $this->cik,
            'fiscal_year'               => $this->fiscal_year,
            'total_revenue'             => $this->total_revenue,
            'ebitda'                    => $this->ebitda,
            'operating_income'          => $this->operating_income,
            'depreciation_amortization' => $this->depreciation_amortization,
            'total_expenses'            => $this->total_expenses,
            'expense_breakdown'         => $this->expense_breakdown,
            'source'                    => $this->source,
            'filing_date'               => $this->filing_date,
        ];
    }
}
