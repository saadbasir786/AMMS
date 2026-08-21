<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AmmsRoleScope;
use App\Models\User;
use App\Reporting\Filters\ReportingFilter;
use App\Reporting\Queries\ReportingQueryService;
use App\Reporting\Scopes\ReportingScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class Flow14ReportingTest extends TestCase
{
    private array $fixture;
    private ReportingQueryService $service;
    private ReportingScopeResolver $scope;
    private ReportingFilter $filters;
    private string $fixturePath;
    private string $fixtureDatabasePath;
    private mixed $originalSqliteDatabase;
    private mixed $originalDefaultConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSqliteDatabase = config('database.connections.sqlite.database');
        $this->originalDefaultConnection = config('database.default');
        $this->fixturePath = dirname(__DIR__, 3).'/flow14_canonical_fixture.json';
        $this->fixtureDatabasePath = storage_path('framework/testing/flow14-'.getmypid().'-'.bin2hex(random_bytes(6)).'.sqlite');

        $this->regenerateCanonicalFixture();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->fixtureDatabasePath,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection('sqlite')->beginTransaction();

        $this->fixture = json_decode(
            (string) file_get_contents($this->fixturePath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (($this->fixture['database_driver'] ?? null) !== 'sqlite') {
            throw new \RuntimeException('FLOW-14 canonical fixture must be generated on isolated SQLite.');
        }

        // The canonical reporting database is deliberately isolated from the suite's MariaDB
        // connection. Bind a temporary actor inside a rolled-back SQLite transaction so FLOW-14
        // cannot leak fixture rows into later Feature tests.
        $user = User::query()->firstOrCreate(
            ['email' => 'flow14.fixture.actor@example.test'],
            ['name' => 'FLOW-14 Fixture Actor', 'password' => 'Flow14FixtureOnly!2026', 'status' => 'ACTIVE'],
        );
        $role = Role::query()->firstOrCreate(['name' => 'EXECUTIVE_MANAGEMENT', 'guard_name' => 'sanctum']);
        $user->assignRole($role);
        AmmsRoleScope::query()->firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'organizational_unit_id' => (int) $this->fixture['scope_unit_id'],
        ]);

        $this->scope = new ReportingScopeResolver($user->fresh());
        $this->filters = ReportingFilter::fromRequest(Request::create('/', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-09-10',
            'timezone' => 'Asia/Kabul',
            'involved_unit_id' => $this->fixture['scope_unit_id'],
            'per_page' => 50,
        ]));
        $this->service = app(ReportingQueryService::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureDatabasePath)) {
            try {
                if (DB::connection('sqlite')->transactionLevel() > 0) {
                    DB::connection('sqlite')->rollBack();
                }
            } catch (\Throwable) {
                // Setup failures may occur before the isolated connection is available.
            }

            DB::disconnect('sqlite');
        }

        if (isset($this->originalDefaultConnection, $this->originalSqliteDatabase)) {
            config([
                'database.default' => $this->originalDefaultConnection,
                'database.connections.sqlite.database' => $this->originalSqliteDatabase,
            ]);
            DB::purge('sqlite');
        }

        if (isset($this->fixturePath) && is_file($this->fixturePath)) {
            @unlink($this->fixturePath);
        }
        if (isset($this->fixtureDatabasePath) && is_file($this->fixtureDatabasePath)) {
            @unlink($this->fixtureDatabasePath);
        }

        parent::tearDown();
    }

    public function test_canonical_dashboard_asserts_exact_frozen_metric_contract(): void
    {
        $expected = $this->fixture['expected'];
        $dashboard = $this->service->dashboard($this->scope, $this->filters);
        $cards = collect($dashboard['cards'])->keyBy('metric_id')->map(fn (array $card) => $card['value'])->all();

        $this->assertSame($expected['request_total'], $cards['requests.total']);
        $this->assertSame($expected['meeting_distinct_total'], $cards['meetings.distinct_total']);
        $this->assertSame($expected['conducted_meeting_count'], $cards['meetings.conducted']);
        $this->assertSame($expected['upcoming_meeting_count'], $cards['meetings.upcoming']);
        $this->assertSame($expected['high_authority_meeting_count'], $cards['meetings.high_authority']);
        $this->assertSame($expected['official_attendance_count'], $cards['attendance.official_present']);
        $this->assertEquals($expected['attendance_rate'], $cards['attendance.rate']);
        $this->assertSame($expected['finalized_decision_count'], $cards['decisions.finalized']);
        $this->assertSame($expected['followup_count'], $cards['followups.total']);
        $this->assertSame($expected['action_item_overdue_count'], $cards['action_items.overdue']);
        $this->assertEquals($expected['action_item_completion_rate'], $cards['action_items.completion_rate']);
        $this->assertSame($this->sorted($expected['meeting_status_counts']), $this->sorted($dashboard['summary']['meeting_status_counts']));
        $this->assertSame($this->sorted($expected['action_item_status_counts']), $this->sorted($dashboard['summary']['action_item_status_counts']));
        $this->assertSame($expected['invalidated_attendance_count'], $dashboard['summary']['invalidated_attendance_count']);
        $this->assertSame($this->sorted(['CANCELLATION' => 2, 'POSTPONEMENT' => 1, 'REOPENING' => 1, 'RESUMPTION' => 1]), $this->sorted($dashboard['summary']['exception_event_counts']));
    }

    public function test_registers_use_the_same_authoritative_rows_as_dashboard(): void
    {
        $expected = $this->fixture['expected'];
        $meetings = $this->service->meetings($this->scope, $this->filters)->getCollection();
        $actions = $this->service->actionItems($this->scope, $this->filters)->getCollection();
        $decisions = $this->service->decisions($this->scope, $this->filters)->getCollection();
        $attendance = $this->service->attendance($this->scope, $this->filters)->getCollection();

        $this->assertSame($expected['meeting_distinct_total'], $meetings->pluck('id')->unique()->count());
        $this->assertSame($this->sorted($expected['meeting_status_counts']), $this->sorted($meetings->countBy('status')->all()));
        $this->assertSame($expected['finalized_decision_count'], $decisions->count());
        $this->assertSame($expected['official_attendance_count'], $attendance->whereNull('invalidation')->count());
        $this->assertSame($expected['action_item_overdue_count'], $actions->filter(fn ($item) => $item->is_overdue)->count());
        $this->assertSame($this->sorted($expected['action_item_status_counts']), $this->sorted($actions->countBy('status')->all()));
    }

    public function test_history_metrics_are_event_based_and_not_derived_from_current_status(): void
    {
        $dashboard = $this->service->dashboard($this->scope, $this->filters);
        $events = $dashboard['summary']['exception_event_counts'];

        $this->assertSame(1, $events['POSTPONEMENT']);
        $this->assertSame(2, $events['CANCELLATION']);
        $this->assertSame(1, $events['REOPENING']);
        $this->assertSame(1, $events['RESUMPTION']);
        $this->assertNotSame('RESUMPTION', 'POSTPONEMENT');
        $this->assertSame(1, $this->fixture['expected']['reschedule_confirmed_count']);
        $this->assertSame(1, $this->fixture['expected']['participant_change_confirmed_count']);
    }

    private function regenerateCanonicalFixture(): void
    {
        if (is_file($this->fixturePath)) {
            @unlink($this->fixturePath);
        }

        $directory = dirname($this->fixtureDatabasePath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create FLOW-14 fixture directory.');
        }
        if (is_file($this->fixtureDatabasePath)) {
            @unlink($this->fixtureDatabasePath);
        }
        if (! touch($this->fixtureDatabasePath)) {
            throw new \RuntimeException('Unable to create FLOW-14 SQLite fixture database.');
        }

        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) (config('app.key') ?: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='),
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->fixtureDatabasePath,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];

        $this->runFixtureCommand([PHP_BINARY, 'artisan', 'migrate:fresh', '--seed', '--force'], $environment);
        $this->runFixtureCommand([PHP_BINARY, 'scripts/flow14_canonical_fixture.php'], $environment);

        if (! is_file($this->fixturePath)) {
            throw new \RuntimeException('FLOW-14 canonical fixture generation did not produce its JSON contract.');
        }
    }

    private function runFixtureCommand(array $command, array $environment): void
    {
        $process = new Process($command, base_path(), $environment, null, 120);
        $process->run();

        if (! $process->isSuccessful()) {
            $diagnostic = trim($process->getErrorOutput().PHP_EOL.$process->getOutput());
            throw new \RuntimeException('FLOW-14 isolated fixture command failed'.($diagnostic !== '' ? ': '.$diagnostic : '.'));
        }
    }

    private function sorted(array $value): array
    {
        ksort($value);
        return $value;
    }
}
