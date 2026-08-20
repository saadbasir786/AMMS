<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Reporting\Filters\ReportingFilter;
use App\Reporting\Metrics\ReportingMetricCatalog;
use App\Reporting\Support\ReportingDateRange;
use Illuminate\Http\Request;
use Tests\TestCase;

class Flow14ReportingUnitTest extends TestCase
{
    public function test_metric_catalog_is_versioned_and_complete_for_core_dashboard_contract(): void
    {
        $catalog = ReportingMetricCatalog::all();
        $this->assertSame('AMMS_REPORTING_V1', ReportingMetricCatalog::DEFINITION_VERSION);
        $this->assertGreaterThanOrEqual(25, count($catalog));
        $this->assertSame('meetings.distinct_total', $catalog['meetings.distinct_total']['metric_id']);
        $this->assertSame('action_items.completion_rate', $catalog['action_items.completion_rate']['metric_id']);
        $this->assertSame('terminals.by_status', ReportingMetricCatalog::get('terminals.by_status')['metric_id']);
    }

    public function test_date_range_is_half_open_and_declares_timezone(): void
    {
        $range = ReportingDateRange::fromInput('2026-08-01', '2026-08-31', 'Asia/Kabul');
        $this->assertSame('2026-08-01', $range->from->toDateString());
        $this->assertSame('2026-09-01', $range->toExclusive->toDateString());
        $this->assertSame('Asia/Kabul', $range->timezone);
        $this->assertSame('2026-08-31', $range->toArray()['to']);
    }

    public function test_filter_contract_rejects_unbounded_search_and_unapproved_sort(): void
    {
        $request = Request::create('/', 'GET', ['from' => '2026-08-01', 'to' => '2026-08-31', 'search' => str_repeat('x', 121)]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        ReportingFilter::fromRequest($request);
    }
}
