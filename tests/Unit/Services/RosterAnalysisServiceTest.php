<?php

namespace Tests\Unit\Services;

use App\Services\RosterAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Tests\TestCase;

class RosterAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private RosterAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RosterAnalysisService();
    }

    private function fakeFile(): UploadedFile
    {
        return UploadedFile::fake()->create(
            'roster.xlsx',
            0,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_analyze_returns_correct_headcount_and_spend(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn([[
            ['Job Title', 'Job Function', 'Seniority', 'Country', 'Fully Loaded Cost'],
            ['Software Engineer', 'Engineering', 'VP', 'United States', 150000],
            ['Account Executive', 'Sales', 'Director', 'United Kingdom', 120000],
            ['Tech Lead', 'Engineering', 'Manager', 'United States', 90000],
        ]]);

        $result = $this->service->analyze($this->fakeFile());

        $this->assertEquals(3, $result->total_headcount);
        $this->assertEquals(360000, $result->total_labor_spend);
        $this->assertEquals(120000, $result->avg_cost_per_employee);
    }

    public function test_analyze_groups_by_function_seniority_country(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn([[
            ['Job Title', 'Job Function', 'Seniority', 'Country', 'Fully Loaded Cost'],
            ['Software Engineer', 'Engineering', 'VP', 'United States', 150000],
            ['Account Executive', 'Sales', 'Director', 'United Kingdom', 120000],
            ['Tech Lead', 'Engineering', 'Manager', 'United States', 90000],
        ]]);

        $result = $this->service->analyze($this->fakeFile());

        $this->assertEquals(['Engineering' => 2, 'Sales' => 1], $result->by_function);
        $this->assertEquals(['VP' => 1, 'Director' => 1, 'Manager' => 1], $result->by_seniority);
        $this->assertEquals(['United States' => 2, 'United Kingdom' => 1], $result->by_country);
        $this->assertEquals(['Engineering' => 240000, 'Sales' => 120000], $result->cost_by_function);
    }

    public function test_analyze_normalizes_column_headers(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn([[
            ['JOB TITLE', '  JOB FUNCTION  ', ' SENIORITY ', 'COUNTRY', 'FLC'],
            ['HR Manager', 'HR', 'Analyst', 'Canada', 70000],
        ]]);

        $result = $this->service->analyze($this->fakeFile());

        $this->assertEquals(1, $result->total_headcount);
        $this->assertEquals(['HR' => 1], $result->by_function);
    }

    public function test_analyze_ignores_blank_rows(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn([[
            ['Job Title', 'Job Function', 'Seniority', 'Country', 'Fully Loaded Cost'],
            ['Engineer', 'Engineering', 'VP', 'US', 150000],
            ['', '', '', '', ''],
            [null, null, null, null, null],
            ['Sales Rep', 'Sales', 'Manager', 'UK', 80000],
        ]]);

        $result = $this->service->analyze($this->fakeFile());

        $this->assertEquals(2, $result->total_headcount);
    }

    public function test_analyze_throws_on_insufficient_data(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn([[
            ['Job Title'],
        ]]);

        $this->expectException(RuntimeException::class);

        $this->service->analyze($this->fakeFile());
    }

    public function test_analyze_throws_on_missing_required_columns(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn([[
            ['Name', 'Age'],
            ['Alice', 30],
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing required columns/');

        $this->service->analyze($this->fakeFile());
    }

    public function test_analyze_persists_roster_and_employees_in_database(): void
    {
        Excel::shouldReceive('toArray')->once()->andReturn([[
            ['Job Title', 'Job Function', 'Seniority', 'Country', 'Fully Loaded Cost'],
            ['Engineer', 'Engineering', 'VP', 'US', 100000],
            ['Sales Rep', 'Sales', 'Manager', 'UK', 80000],
        ]]);

        $result = $this->service->analyze($this->fakeFile());

        $this->assertDatabaseCount('rosters', 1);
        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('rosters', ['total_headcount' => 2, 'total_labor_spend' => 180000]);
        $this->assertNotNull($result->roster_id);
    }
}
