<?php

namespace App\Http\Resources;

use App\DTOs\RosterAnalysisResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RosterAnalysisResult */
class RosterAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'roster_id'            => $this->roster_id,
            'total_headcount'      => $this->total_headcount,
            'total_labor_spend'    => $this->total_labor_spend,
            'avg_cost_per_employee' => $this->avg_cost_per_employee,
            'by_function'          => $this->by_function,
            'by_seniority'         => $this->by_seniority,
            'by_country'           => $this->by_country,
            'cost_by_function'     => $this->cost_by_function,
            'cost_by_seniority'    => $this->cost_by_seniority,
            'cost_by_country'      => $this->cost_by_country,
        ];
    }
}
