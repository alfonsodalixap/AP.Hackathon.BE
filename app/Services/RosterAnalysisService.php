<?php

namespace App\Services;

use App\DTOs\RosterAnalysisResult;
use App\Models\Employee;
use App\Models\Roster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class RosterAnalysisService
{
    private const COLUMN_MAP = [
        'job title'          => 'job_title',
        'job function'       => 'job_function',
        'seniority'          => 'seniority',
        'country'            => 'country',
        'fully loaded cost'  => 'fully_loaded_cost',
        'flc'                => 'fully_loaded_cost',
        'employ id'          => 'employee_id',
    ];

    private const REQUIRED = ['job_title', 'job_function', 'seniority', 'country', 'fully_loaded_cost'];

    public function analyze(UploadedFile $file): RosterAnalysisResult
    {
        $raw = Excel::toArray(new \stdClass(), $file)[0] ?? [];

        if (count($raw) < 2) {
            throw new RuntimeException('El archivo no contiene datos suficientes.');
        }

        [$headers, $rows] = $this->parseSheet($raw);
        $this->assertRequiredColumns($headers);

        return DB::transaction(function () use ($file, $headers, $rows) {
            $roster = Roster::create(['filename' => $file->getClientOriginalName()]);

            $records = $this->buildRecords($roster->id, $headers, $rows);
            foreach (array_chunk($records, 500) as $chunk) {
                Employee::insert($chunk);
            }

            return $this->buildResult($roster);
        });
    }

    private function parseSheet(array $raw): array
    {
        // Normalize: lowercase, trim edges, collapse internal whitespace
        $rawHeaders = array_map(
            fn($h) => preg_replace('/\s+/', ' ', trim(strtolower((string) $h))),
            $raw[0]
        );

        $headers = [];
        foreach ($rawHeaders as $index => $raw_header) {
            foreach (self::COLUMN_MAP as $pattern => $normalized) {
                if (str_contains($raw_header, $pattern)) {
                    $headers[$index] = $normalized;
                    break;
                }
            }
            if (!isset($headers[$index])) {
                $headers[$index] = $raw_header;
            }
        }

        $rows = array_slice($raw, 1);

        return [$headers, $rows];
    }

    private function assertRequiredColumns(array $headers): void
    {
        $mapped = array_values($headers);
        $missing = array_diff(self::REQUIRED, $mapped);

        if (!empty($missing)) {
            throw new RuntimeException('Faltan columnas requeridas: ' . implode(', ', $missing));
        }
    }

    private function buildRecords(int $rosterId, array $headers, array $rows): array
    {
        $now = now()->toDateTimeString();
        $records = [];

        foreach ($rows as $row) {
            if (empty(array_filter((array) $row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $record = ['roster_id' => $rosterId, 'created_at' => $now, 'updated_at' => $now];
            foreach ($headers as $index => $field) {
                $value = $row[$index] ?? null;
                if ($field === 'fully_loaded_cost') {
                    $value = (int) (is_numeric($value) ? $value : 0);
                }
                $record[$field] = $value;
            }

            foreach (['job_title', 'job_function', 'seniority', 'country'] as $required) {
                $record[$required] ??= '';
            }
            $record['employee_id'] ??= null;
            $record['fully_loaded_cost'] ??= 0;

            $records[] = $record;
        }

        return $records;
    }

    private function buildResult(Roster $roster): RosterAnalysisResult
    {
        $employees = $roster->employees();

        $total = $employees->count();
        $totalSpend = (int) $employees->sum('fully_loaded_cost');

        $groupBy = fn(string $col) => Employee::where('roster_id', $roster->id)
            ->select($col, DB::raw('COUNT(*) as cnt'), DB::raw('SUM(fully_loaded_cost) as cost_sum'))
            ->groupBy($col)
            ->get();

        $byFunction    = $groupBy('job_function');
        $bySeniority   = $groupBy('seniority');
        $byCountry     = $groupBy('country');

        $roster->update(['total_headcount' => $total, 'total_labor_spend' => $totalSpend]);

        return new RosterAnalysisResult(
            roster_id: $roster->id,
            total_headcount: $total,
            total_labor_spend: $totalSpend,
            avg_cost_per_employee: $total > 0 ? intdiv($totalSpend, $total) : 0,
            by_function: $byFunction->pluck('cnt', 'job_function')->toArray(),
            by_seniority: $bySeniority->pluck('cnt', 'seniority')->toArray(),
            by_country: $byCountry->pluck('cnt', 'country')->toArray(),
            cost_by_function: $byFunction->pluck('cost_sum', 'job_function')->map(fn($v) => (int) $v)->toArray(),
            cost_by_seniority: $bySeniority->pluck('cost_sum', 'seniority')->map(fn($v) => (int) $v)->toArray(),
            cost_by_country: $byCountry->pluck('cost_sum', 'country')->map(fn($v) => (int) $v)->toArray(),
        );
    }
}
