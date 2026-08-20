<?php

declare(strict_types=1);

use App\Models\User;
use App\Reporting\Filters\ReportingFilter;
use App\Reporting\Queries\ReportingQueryService;
use App\Reporting\Scopes\ReportingScopeResolver;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$root = dirname(__DIR__, 2);
$fixture = json_decode((string) file_get_contents($root.'/flow14_canonical_fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$expected = $fixture['expected'];
$from = '2026-08-01';
$to = '2026-09-10';
$unitId = (int) $fixture['scope_unit_id'];

$assert = static function (bool $condition, string $label, mixed $actual = null, mixed $want = null) use (&$results): void {
    $results[] = ['label' => $label, 'pass' => $condition, 'actual' => $actual, 'expected' => $want];
    if (!$condition) throw new RuntimeException($label.' failed: actual='.json_encode($actual, JSON_UNESCAPED_UNICODE).' expected='.json_encode($want, JSON_UNESCAPED_UNICODE));
};
$results = [];
$normalize = static function (array $value): array { ksort($value); return $value; };
$user = User::where('email', 'tech.exec@example.test')->firstOrFail();
$scope = new ReportingScopeResolver($user);
$filter = ReportingFilter::fromRequest(Request::create('/api/reporting/dashboard/executive', 'GET', [
    'from' => $from, 'to' => $to, 'timezone' => 'Asia/Kabul', 'involved_unit_id' => $unitId,
    'per_page' => 50,
]));
$service = app(ReportingQueryService::class);
$dashboard = $service->dashboard($scope, $filter);
$cards = collect($dashboard['cards'])->keyBy('metric_id')->map(fn ($row) => $row['value'])->all();

$assert($cards['requests.total'] === $expected['request_total'], 'requests.total', $cards['requests.total'], $expected['request_total']);
$assert($cards['meetings.distinct_total'] === $expected['meeting_distinct_total'], 'meetings.distinct_total', $cards['meetings.distinct_total'], $expected['meeting_distinct_total']);
$assert($cards['meetings.conducted'] === $expected['conducted_meeting_count'], 'meetings.conducted', $cards['meetings.conducted'], $expected['conducted_meeting_count']);
$assert($cards['meetings.upcoming'] === $expected['upcoming_meeting_count'], 'meetings.upcoming', $cards['meetings.upcoming'], $expected['upcoming_meeting_count']);
$assert($cards['meetings.high_authority'] === $expected['high_authority_meeting_count'], 'meetings.high_authority', $cards['meetings.high_authority'], $expected['high_authority_meeting_count']);
$assert($cards['attendance.official_present'] === $expected['official_attendance_count'], 'attendance.official_present', $cards['attendance.official_present'], $expected['official_attendance_count']);
$assert(abs((float) $cards['attendance.rate'] - (float) $expected['attendance_rate']) < 0.0001, 'attendance.rate', $cards['attendance.rate'], $expected['attendance_rate']);
$assert($cards['decisions.finalized'] === $expected['finalized_decision_count'], 'decisions.finalized', $cards['decisions.finalized'], $expected['finalized_decision_count']);
$assert($cards['followups.total'] === $expected['followup_count'], 'followups.total', $cards['followups.total'], $expected['followup_count']);
$assert($cards['action_items.overdue'] === $expected['action_item_overdue_count'], 'action_items.overdue', $cards['action_items.overdue'], $expected['action_item_overdue_count']);
$assert(abs((float) $cards['action_items.completion_rate'] - (float) $expected['action_item_completion_rate']) < 0.0001, 'action_items.completion_rate', $cards['action_items.completion_rate'], $expected['action_item_completion_rate']);
$assert($normalize($dashboard['summary']['meeting_status_counts']) === $normalize($expected['meeting_status_counts']), 'summary.meeting_status_counts', $dashboard['summary']['meeting_status_counts'], $expected['meeting_status_counts']);
$assert($normalize($dashboard['summary']['action_item_status_counts']) === $normalize($expected['action_item_status_counts']), 'summary.action_item_status_counts', $dashboard['summary']['action_item_status_counts'], $expected['action_item_status_counts']);
$assert($dashboard['summary']['invalidated_attendance_count'] === $expected['invalidated_attendance_count'], 'summary.invalidated_attendance_count', $dashboard['summary']['invalidated_attendance_count'], $expected['invalidated_attendance_count']);
$assert($normalize($dashboard['summary']['exception_event_counts']) === $normalize(['POSTPONEMENT' => 1, 'CANCELLATION' => 2, 'REOPENING' => 1, 'RESUMPTION' => 1]), 'summary.exception_event_counts', $dashboard['summary']['exception_event_counts'], ['POSTPONEMENT' => 1, 'CANCELLATION' => 2, 'REOPENING' => 1, 'RESUMPTION' => 1]);

$meetingRows = $service->meetings($scope, $filter)->getCollection();
$cancelledRows = $meetingRows->where('status', 'CANCELLED')->count();
$actionRows = $service->actionItems($scope, $filter)->getCollection();
$overdueRows = $actionRows->filter(fn ($row) => $row->due_date !== null && $row->due_date->lt(now()) && !in_array($row->status, ['COMPLETED', 'CANCELLED'], true))->count();
$decisionRows = $service->decisions($scope, $filter)->getCollection()->count();
$attendanceRows = $service->attendance($scope, $filter)->getCollection()->whereNull('invalidation')->count();
$assert($cancelledRows === ($expected['meeting_status_counts']['CANCELLED'] ?? 0), 'drilldown.cancelled_matches_dashboard', $cancelledRows, $expected['meeting_status_counts']['CANCELLED']);
$assert($overdueRows === $expected['action_item_overdue_count'], 'drilldown.overdue_matches_dashboard', $overdueRows, $expected['action_item_overdue_count']);
$assert($decisionRows === $expected['finalized_decision_count'], 'drilldown.decisions_matches_dashboard', $decisionRows, $expected['finalized_decision_count']);
$assert($attendanceRows === $expected['official_attendance_count'], 'drilldown.official_attendance_matches_dashboard', $attendanceRows, $expected['official_attendance_count']);

$out = ['gate' => 'FLOW14_EXACT_METRIC_GATE', 'pass' => true, 'fixture' => $fixture['fixture_key'], 'from' => $from, 'to' => $to, 'unit_id' => $unitId, 'results' => $results];
file_put_contents($root.'/flow14_exact_metric_gate.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
