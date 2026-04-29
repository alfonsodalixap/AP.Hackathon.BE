<?php

namespace App\DTOs;

readonly class RosterAnalysisResult
{
    public function __construct(
        public int $roster_id,
        public int $total_headcount,
        public int $total_labor_spend,
        public int $avg_cost_per_employee,
        public array $by_function,
        public array $by_seniority,
        public array $by_country,
        public array $cost_by_function,
        public array $cost_by_seniority,
        public array $cost_by_country,
    ) {}
}
