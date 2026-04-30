<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RosterControllerTest extends TestCase
{
    use RefreshDatabase;

    private function xlsxFile(string $name = 'roster.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->create(
            $name,
            0,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    private function twoRowPayload(): array
    {
        return [[
            ['Job Title', 'Job Function', 'Seniority', 'Country', 'Fully Loaded Cost'],
            ['Software Engineer', 'Engineering', 'VP', 'United States', 150000],
            ['Sales Rep', 'Sales', 'Manager', 'United Kingdom', 80000],
        ]];
    }

    public function test_returns_roster_analysis_data(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn($this->twoRowPayload());

        $response = $this->postJson('/api/roster/analyze', ['file' => $this->xlsxFile()]);

        $response->assertOk()
                 ->assertJsonStructure([
                     'data' => [
                         'roster_id', 'total_headcount', 'total_labor_spend',
                         'avg_cost_per_employee', 'by_function', 'by_seniority',
                         'by_country', 'cost_by_function', 'cost_by_seniority', 'cost_by_country',
                     ],
                 ])
                 ->assertJsonPath('data.total_headcount', 2)
                 ->assertJsonPath('data.total_labor_spend', 230000)
                 ->assertJsonPath('data.avg_cost_per_employee', 115000);
    }

    public function test_returns_correct_group_aggregates(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn($this->twoRowPayload());

        $response = $this->postJson('/api/roster/analyze', ['file' => $this->xlsxFile()]);

        $response->assertJsonPath('data.by_function.Engineering', 1)
                 ->assertJsonPath('data.by_function.Sales', 1)
                 ->assertJsonPath('data.by_country.United States', 1)
                 ->assertJsonPath('data.cost_by_function.Engineering', 150000);
    }

    public function test_requires_file_field(): void
    {
        $response = $this->postJson('/api/roster/analyze', []);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['file']);
    }

    public function test_rejects_non_excel_file(): void
    {
        $csv = UploadedFile::fake()->create('roster.csv', 1, 'text/csv');

        $response = $this->postJson('/api/roster/analyze', ['file' => $csv]);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['file']);
    }

    public function test_returns_422_for_missing_required_columns(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn([[
            ['Name', 'Age'],
            ['Alice', 30],
        ]]);

        $response = $this->postJson('/api/roster/analyze', ['file' => $this->xlsxFile()]);

        $response->assertUnprocessable()
                 ->assertJsonStructure(['message'])
                 ->assertJsonFragment(['message' => 'Missing required columns: job_title, job_function, seniority, country, fully_loaded_cost']);
    }

    public function test_persists_data_to_database(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn($this->twoRowPayload());

        $this->postJson('/api/roster/analyze', ['file' => $this->xlsxFile()]);

        $this->assertDatabaseCount('rosters', 1);
        $this->assertDatabaseCount('employees', 2);
    }
}
