<?php

declare(strict_types=1);

namespace App\Support;

final class AmmsPermissionRegistry
{
    public static function allPermissions(): array
    {
        return [
            'meeting-request.view', 'meeting-request.create', 'meeting-request.update', 'meeting-request.submit',
            'meeting-request.resubmit', 'meeting-request.approve', 'meeting-request.return', 'meeting-request.reject',
            'meeting-request.attach', 'meeting-request.attachment-download', 'meeting-request.attachment-delete',
            'meeting-request.view-incoming', 'meeting-request.approve-target', 'meeting-request.return-target',
            'meeting-request.reject-target', 'meeting-request.approve-delegated',
            'meeting-schedule.propose', 'meeting-schedule.accept', 'meeting-participant.nominate',
            'meeting-participant.confirm', 'meeting.schedule', 'meeting.create', 'meeting.view',
            'authority-request.view', 'authority-request.review', 'authority-request.forward', 'authority-request.return',
            'authority-request.decide', 'authority-request.approve', 'authority-request.reject',
            'authority-request.return-by-authority', 'authority-schedule.view', 'authority-schedule.propose',
            'authority-schedule.confirm', 'authority-schedule.acknowledge', 'authority-participant.manage',
            'authority-participant.confirm', 'authority-meeting.finalize', 'authority-initiated-meeting.prepare',
            'authority-initiated-meeting.issue', 'authority-initiated-meeting.distribute', 'authority-directive.view',
            'authority-directive.coordinate', 'authority-directive.acknowledge', 'authority-directive.clarification-request',
            'authority-directive.clarification-respond', 'meeting-reschedule.create', 'meeting-reschedule.propose',
            'meeting-reschedule.acknowledge', 'meeting-reschedule.request-alternative', 'meeting-reschedule.confirm',
            'meeting-reschedule.view-history', 'meeting-participant-change.create', 'meeting-participant-change.manage',
            'meeting-participant-change.submit', 'meeting-participant-change.confirm', 'meeting-participant-change.return',
            'meeting-participant-change.reject', 'meeting-participant-change.view-history', 'terminal.view',
            'terminal.register', 'terminal.manage', 'terminal.rotate-credential', 'terminal.revoke',
            'meeting-terminal.view', 'meeting-terminal.assign', 'meeting-terminal.refresh', 'meeting-terminal.revoke',
            'terminal-session.view', 'biometric.status.view', 'biometric.enroll', 'biometric.reenroll',
            'biometric.revoke', 'biometric.enrollment.cancel', 'attendance.view', 'attendance.override',
            'meeting.execution.start', 'meeting.execution.end', 'meeting.minutes.view', 'meeting.minutes.prepare',
            'meeting.minutes.submit', 'meeting.minutes.review', 'meeting.minutes.review-delegated',
            'meeting.minutes.approve_authority', 'meeting.minutes.finalize', 'meeting.minutes.document.view',
            'decision.followup.view', 'decision.followup.initialize', 'decision.followup.classify', 'action-item.create',
            'action-item.view', 'action-item.start', 'action-item.progress', 'action-item.block',
            'action-item.submit-completion', 'action-item.verify-completion', 'action-item.assign',
            'action-item.change-unit', 'action-item.extension.request', 'action-item.extension.review',
            'action-item.cancel', 'action-item.evidence.view', 'action-item.evidence.upload',
            'meeting.exception.view', 'meeting.exception.create', 'meeting.exception.update-draft', 'meeting.exception.submit',
            'meeting.exception.review', 'meeting.exception.approve', 'meeting.exception.acknowledge',
            'meeting.exception.apply', 'meeting.exception.withdraw', 'meeting.exception.history.view', 'meeting.postpone',
            'meeting.cancel', 'meeting.reopen', 'meeting.resume', 'reporting.dashboard.executive',
            'reporting.dashboard.directorate', 'reporting.dashboard.personal', 'reporting.dashboard.authority',
            'reporting.dashboard.technical', 'reporting.meetings.view', 'reporting.requests.view',
            'reporting.attendance.summary', 'reporting.attendance.detail', 'reporting.decisions.view',
            'reporting.action-items.view', 'reporting.exceptions.view', 'reporting.exports.create',
            'reporting.exports.download', 'reporting.technical.view', 'organization.manage', 'employee.manage',
            'user.manage', 'role.manage', 'notification.view', 'notification.mark-read',
        ];
    }

    public static function rolePermissions(): array
    {
        $base = ['meeting-request.view', 'meeting-request.create', 'meeting-request.update', 'meeting-request.submit', 'meeting-request.resubmit', 'meeting-request.attach', 'meeting-request.attachment-download', 'meeting-request.attachment-delete', 'meeting.schedule', 'meeting.create', 'meeting.view', 'notification.view', 'notification.mark-read'];
        $flow04All = ['authority-request.view', 'authority-request.review', 'authority-request.forward', 'authority-request.return', 'authority-request.decide', 'authority-request.approve', 'authority-request.reject', 'authority-request.return-by-authority', 'authority-schedule.view', 'authority-schedule.propose', 'authority-schedule.confirm', 'authority-participant.manage', 'authority-participant.confirm', 'authority-meeting.finalize'];
        $flow06All = ['authority-initiated-meeting.prepare', 'authority-initiated-meeting.issue', 'authority-initiated-meeting.distribute', 'authority-directive.view', 'authority-directive.coordinate', 'authority-directive.acknowledge', 'authority-directive.clarification-request', 'authority-directive.clarification-respond'];
        $flow07All = ['meeting-reschedule.create', 'meeting-reschedule.propose', 'meeting-reschedule.acknowledge', 'meeting-reschedule.request-alternative', 'meeting-reschedule.confirm', 'meeting-reschedule.view-history'];
        $flow08All = ['meeting-participant-change.create', 'meeting-participant-change.manage', 'meeting-participant-change.submit', 'meeting-participant-change.confirm', 'meeting-participant-change.return', 'meeting-participant-change.reject', 'meeting-participant-change.view-history'];
        $flow09All = ['terminal.view', 'terminal.register', 'terminal.manage', 'terminal.rotate-credential', 'terminal.revoke', 'meeting-terminal.view', 'meeting-terminal.assign', 'meeting-terminal.refresh', 'meeting-terminal.revoke', 'terminal-session.view'];
        $flow11Management = ['meeting.execution.start', 'meeting.execution.end', 'meeting.minutes.view', 'meeting.minutes.prepare', 'meeting.minutes.submit', 'meeting.minutes.finalize', 'meeting.minutes.document.view'];

        return array_map(static fn (array $permissions): array => array_values(array_unique($permissions)), [
            AmmsRole::SYSTEM_ADMIN => array_merge($base, ['meeting-request.approve', 'meeting-request.return', 'meeting-request.reject'], $flow04All, $flow06All, $flow07All, $flow08All, $flow09All, ['biometric.status.view', 'reporting.dashboard.technical', 'reporting.technical.view', 'organization.manage', 'employee.manage', 'user.manage', 'role.manage']),
            AmmsRole::EMPLOYEE => ['meeting-request.view', 'meeting.view', 'notification.view', 'notification.mark-read', 'reporting.dashboard.personal', 'reporting.meetings.view', 'reporting.action-items.view'],
            AmmsRole::DIRECTOR => array_merge(['meeting-request.view', 'meeting-request.approve', 'meeting-request.return', 'meeting-request.reject', 'meeting-request.attachment-download', 'meeting.view', 'notification.view', 'notification.mark-read', 'meeting-request.view-incoming', 'meeting-request.approve-target', 'meeting-request.return-target', 'meeting-request.reject-target', 'authority-schedule.acknowledge', 'meeting-participant.nominate', 'meeting-participant.confirm'], ['authority-directive.view', 'authority-directive.acknowledge', 'authority-directive.clarification-request'], ['meeting-reschedule.create', 'meeting-reschedule.acknowledge', 'meeting-reschedule.request-alternative', 'meeting-reschedule.confirm', 'meeting-reschedule.view-history'], ['meeting-participant-change.confirm', 'meeting-participant-change.return', 'meeting-participant-change.reject', 'meeting-participant-change.view-history'], ['meeting.minutes.view', 'meeting.minutes.review', 'meeting.minutes.document.view'], ['decision.followup.view', 'action-item.view', 'action-item.start', 'action-item.progress', 'action-item.block', 'action-item.submit-completion', 'action-item.assign', 'action-item.extension.request', 'action-item.evidence.view', 'action-item.evidence.upload'], ['meeting.exception.view', 'meeting.exception.review', 'meeting.exception.acknowledge', 'meeting.exception.history.view', 'meeting.exception.approve'], ['reporting.dashboard.directorate', 'reporting.meetings.view', 'reporting.requests.view', 'reporting.attendance.summary', 'reporting.decisions.view', 'reporting.action-items.view', 'reporting.exceptions.view', 'reporting.exports.create', 'reporting.exports.download']),
            AmmsRole::DEPUTY_DIRECTOR => ['meeting.view', 'meeting.minutes.view', 'meeting.minutes.review'],
            AmmsRole::EXECUTIVE_MANAGEMENT => array_merge($base, ['meeting-request.view-incoming', 'meeting-request.approve-target', 'meeting-request.return-target', 'meeting-request.reject-target', 'meeting-schedule.propose', 'meeting-schedule.accept', 'meeting-participant.nominate', 'meeting-participant.confirm', 'authority-schedule.acknowledge'], ['authority-directive.view', 'authority-directive.coordinate'], ['meeting-reschedule.create', 'meeting-reschedule.propose', 'meeting-reschedule.acknowledge', 'meeting-reschedule.request-alternative', 'meeting-reschedule.confirm', 'meeting-reschedule.view-history'], ['meeting-participant-change.create', 'meeting-participant-change.manage', 'meeting-participant-change.submit', 'meeting-participant-change.view-history'], ['meeting-terminal.view', 'meeting-terminal.assign', 'meeting-terminal.refresh', 'meeting-terminal.revoke', 'terminal-session.view', 'biometric.status.view', 'attendance.view', 'attendance.override'], $flow11Management, ['decision.followup.view', 'decision.followup.initialize', 'decision.followup.classify', 'action-item.create', 'action-item.view', 'action-item.assign', 'action-item.change-unit', 'action-item.extension.review', 'action-item.verify-completion', 'action-item.cancel', 'action-item.evidence.view', 'action-item.evidence.upload'], ['meeting.exception.view', 'meeting.exception.create', 'meeting.exception.update-draft', 'meeting.exception.submit', 'meeting.exception.apply', 'meeting.exception.withdraw', 'meeting.exception.history.view', 'meeting.postpone', 'meeting.cancel', 'meeting.reopen', 'meeting.resume'], ['reporting.dashboard.executive', 'reporting.dashboard.directorate', 'reporting.meetings.view', 'reporting.requests.view', 'reporting.attendance.summary', 'reporting.decisions.view', 'reporting.action-items.view', 'reporting.exceptions.view', 'reporting.exports.create', 'reporting.exports.download']),
            AmmsRole::AUTHORITY_OFFICE => array_merge(['authority-request.view', 'authority-request.review', 'authority-request.forward', 'authority-request.return', 'authority-schedule.view', 'authority-participant.manage', 'authority-participant.confirm'], ['authority-initiated-meeting.prepare', 'authority-initiated-meeting.distribute', 'authority-directive.view', 'authority-directive.clarification-respond'], ['meeting-reschedule.create', 'meeting-reschedule.view-history'], $flow08All, ['meeting.minutes.view', 'meeting.minutes.document.view'], ['decision.followup.view', 'action-item.view', 'action-item.evidence.view'], ['meeting.exception.view', 'meeting.exception.history.view'], ['reporting.dashboard.authority', 'reporting.meetings.view', 'reporting.requests.view', 'reporting.decisions.view', 'reporting.action-items.view', 'reporting.exceptions.view', 'reporting.exports.create', 'reporting.exports.download']),
            AmmsRole::EXECUTIVE_SECRETARY => array_merge(['authority-request.view', 'authority-schedule.view', 'authority-schedule.propose', 'authority-schedule.confirm', 'authority-participant.manage', 'authority-participant.confirm', 'authority-meeting.finalize', 'authority-directive.view'], ['meeting-reschedule.create', 'meeting-reschedule.propose', 'meeting-reschedule.acknowledge', 'meeting-reschedule.request-alternative', 'meeting-reschedule.confirm', 'meeting-reschedule.view-history'], ['meeting-participant-change.view-history'], ['meeting-terminal.view', 'meeting-terminal.assign', 'meeting-terminal.refresh', 'meeting-terminal.revoke', 'terminal-session.view', 'biometric.status.view', 'attendance.view', 'attendance.override'], array_merge($flow11Management, ['meeting.minutes.review']), ['decision.followup.view', 'decision.followup.initialize', 'decision.followup.classify', 'action-item.create', 'action-item.view', 'action-item.assign', 'action-item.change-unit', 'action-item.extension.review', 'action-item.verify-completion', 'action-item.cancel', 'action-item.evidence.view', 'action-item.evidence.upload'], ['meeting.exception.view', 'meeting.exception.create', 'meeting.exception.update-draft', 'meeting.exception.submit', 'meeting.exception.apply', 'meeting.exception.withdraw', 'meeting.exception.history.view', 'meeting.postpone', 'meeting.cancel', 'meeting.reopen', 'meeting.resume'], ['reporting.dashboard.authority', 'reporting.meetings.view', 'reporting.requests.view', 'reporting.decisions.view', 'reporting.exceptions.view']),
            AmmsRole::HIGH_AUTHORITY => array_merge(['authority-request.view', 'authority-request.decide', 'authority-request.approve', 'authority-request.reject', 'authority-request.return-by-authority'], ['authority-directive.view', 'authority-initiated-meeting.issue', 'authority-initiated-meeting.prepare'], ['meeting-reschedule.view-history', 'meeting-participant-change.view-history'], ['meeting.minutes.view', 'meeting.minutes.approve_authority', 'meeting.minutes.document.view'], ['decision.followup.view', 'action-item.view', 'action-item.evidence.view'], ['meeting.exception.view', 'meeting.exception.review', 'meeting.exception.approve', 'meeting.exception.history.view'], ['reporting.dashboard.executive', 'reporting.dashboard.authority', 'reporting.meetings.view', 'reporting.decisions.view', 'reporting.action-items.view', 'reporting.exceptions.view', 'reporting.exports.create', 'reporting.exports.download']),
        ]);
    }

    public static function permissionsForRole(string $role): array
    {
        return self::rolePermissions()[$role] ?? [];
    }

    /**
     * Every permission is classified here. The assignable list is deliberately
     * small: custom roles can only receive read/reporting permissions whose
     * downstream policies and reporting scope resolver still enforce scope.
     */
    public static function metadata(): array
    {
        $assignable = [
            'meeting-request.attachment-download', 'meeting.view',
            'meeting.minutes.view', 'meeting.minutes.document.view', 'decision.followup.view',
            'action-item.view', 'action-item.evidence.view', 'attendance.view',
            'notification.view', 'notification.mark-read', 'reporting.dashboard.personal',
            'reporting.meetings.view', 'reporting.requests.view', 'reporting.attendance.summary',
            'reporting.decisions.view', 'reporting.action-items.view', 'reporting.exceptions.view',
        ];

        $protected = [
            'role.manage', 'user.manage', 'organization.manage', 'employee.manage',
            'authority-request.decide', 'authority-request.approve', 'meeting-request.approve',
            'meeting-request.approve-target', 'meeting-request.approve-delegated',
            'meeting.minutes.approve_authority', 'meeting.minutes.review-delegated',
            'biometric.enroll', 'biometric.reenroll', 'biometric.revoke',
        ];

        $delegatable = collect(SpecialCapability::all())
            ->flatMap(static fn (string $capability): array => SpecialCapability::permissions($capability))
            ->unique()
            ->all();

        return collect(self::allPermissions())->mapWithKeys(function (string $permission) use ($assignable, $protected, $delegatable): array {
            $category = str_contains($permission, '.') ? explode('.', $permission, 2)[0] : $permission;
            return [$permission => [
                'name' => $permission,
                'category' => $category,
                'custom_role_assignable' => in_array($permission, $assignable, true),
                'protected' => in_array($permission, $protected, true) || !in_array($permission, $assignable, true),
                'delegatable' => in_array($permission, $delegatable, true),
            ]];
        })->all();
    }

    public static function customRoleAssignablePermissions(): array
    {
        return array_values(array_keys(array_filter(self::metadata(), static fn (array $item): bool => $item['custom_role_assignable'])));
    }
}
