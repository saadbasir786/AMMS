<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MeetingMinuteAttendanceSnapshot;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingMinuteVersionItem;
use App\Models\MeetingParticipantVersion;
use App\Models\MeetingParticipantVersionMember;
use App\Models\Employee;
use App\Support\MeetingVersionInspection;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MeetingVersionInspectionTest extends TestCase
{
    public function test_participant_comparison_is_directional_and_uses_snapshot_members_and_roles(): void
    {
        $from = $this->participantVersion([
            $this->member(1, 'CHAIR', 4), $this->member(2, 'MEMBER', 4), $this->member(3, 'MEMBER', 4),
        ]);
        $to = $this->participantVersion([
            $this->member(1, 'MEMBER', 4), $this->member(2, 'MEMBER', 4), $this->member(4, 'MEMBER', 7),
        ]);
        $inspection = new MeetingVersionInspection;

        $forward = $inspection->compare('participants', $from, $to);
        self::assertSame([4], array_column($forward['added']->all(), 'employee_id'));
        self::assertSame([3], array_column($forward['removed']->all(), 'employee_id'));
        self::assertSame([1], array_column($forward['changed'], 'employee_id'));
        self::assertSame([2], array_column($forward['unchanged'], 'employee_id'));

        $reverse = $inspection->compare('participants', $to, $from);
        self::assertSame([3], array_column($reverse['added']->all(), 'employee_id'));
        self::assertSame([4], array_column($reverse['removed']->all(), 'employee_id'));
        self::assertSame('MEMBER', $reverse['changed'][0]['from']['role']);
        self::assertSame('CHAIR', $reverse['changed'][0]['to']['role']);
    }

    public function test_minute_comparison_uses_business_keys_and_excludes_database_ids(): void
    {
        $from = $this->minuteVersion('Draft A', 'Notes', [
            $this->model(new MeetingMinuteVersionItem, ['id' => 11, 'sequence_number' => 1, 'agenda_title' => 'Opening', 'discussion_summary' => 'Start']),
        ], [
            $this->model(new MeetingMinuteVersionDecision, ['id' => 21, 'uuid' => 'decision-stable', 'decision_text' => 'Approve', 'decision_number' => 'D1']),
        ], [
            $this->model(new MeetingMinuteAttendanceSnapshot, ['id' => 31, 'employee_id' => 7, 'display_name' => 'A. Person', 'attendance_status' => 'PRESENT']),
        ]);
        $to = $this->minuteVersion('Draft B', 'Notes', [
            $this->model(new MeetingMinuteVersionItem, ['id' => 99, 'sequence_number' => 1, 'agenda_title' => 'Opening', 'discussion_summary' => 'Extended']),
            $this->model(new MeetingMinuteVersionItem, ['id' => 100, 'sequence_number' => 2, 'agenda_title' => 'Budget', 'discussion_summary' => 'Reviewed']),
        ], [
            $this->model(new MeetingMinuteVersionDecision, ['id' => 88, 'uuid' => 'decision-stable', 'decision_text' => 'Approve with changes', 'decision_number' => 'D1']),
        ], [
            $this->model(new MeetingMinuteAttendanceSnapshot, ['id' => 77, 'employee_id' => 7, 'display_name' => 'A. Person', 'attendance_status' => 'ABSENT']),
        ]);
        $to->schedule_version_id = 8;

        $result = (new MeetingVersionInspection)->compare('minutes', $from, $to);
        self::assertTrue($result['fields'][0]['changed']);
        self::assertCount(1, $result['collections']['items']['added']);
        self::assertCount(1, $result['collections']['items']['changed']);
        self::assertCount(1, $result['collections']['decisions']['added']);
        self::assertCount(1, $result['collections']['decisions']['removed']);
        self::assertCount(1, $result['collections']['attendanceSnapshots']['changed']);
        self::assertSame(['from' => 4, 'to' => 8], $result['context']['schedule']);
    }

    private function participantVersion(array $members): MeetingParticipantVersion
    {
        $version = new MeetingParticipantVersion;
        $version->setRelation('members', new Collection($members));
        return $version;
    }

    private function member(int $employeeId, string $role, int $unitId): MeetingParticipantVersionMember
    {
        $member = new MeetingParticipantVersionMember(['employee_id' => $employeeId, 'participant_role' => $role, 'organizational_unit_id' => $unitId]);
        $employee = new Employee(['first_name' => 'Employee', 'last_name' => (string) $employeeId]);
        $member->setRelation('employee', $employee);
        $member->setRelation('organizationalUnit', null);
        return $member;
    }

    private function minuteVersion(string $summary, string $notes, array $items, array $decisions, array $attendance): MeetingMinuteVersion
    {
        $version = new MeetingMinuteVersion(['summary' => $summary, 'general_notes' => $notes, 'schedule_version_id' => 4, 'participant_version_id' => 5]);
        $version->setRelation('items', new Collection($items));
        $version->setRelation('decisions', new Collection($decisions));
        $version->setRelation('attendanceSnapshots', new Collection($attendance));
        return $version;
    }

    private function model(object $model, array $attributes): object
    {
        $model->setRawAttributes($attributes);
        return $model;
    }
}
