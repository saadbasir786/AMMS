<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MeetingMinuteVersion;
use Mpdf\Mpdf;

class MinutesDocumentBuilder
{
    public function build(MeetingMinuteVersion $version): array
    {
        $version->loadMissing([
            'minutes.meeting.request',
            'minutes.meeting.chairperson',
            'minutes.meeting.units.unit',
            'executionSession',
            'scheduleVersion',
            'items',
            'decisions.responsibleUnit',
            'decisions.responsibleEmployee',
            'attendanceSnapshots.unit',
        ]);

        $minutes = $version->minutes;
        $meeting = $minutes->meeting;
        $canonical = [
            'meeting_id' => (int) $meeting->id,
            'meeting_number' => (string) $meeting->meeting_number,
            'subject' => (string) $meeting->title,
            'scheduled_date' => $version->scheduleVersion?->meeting_date?->toDateString(),
            'scheduled_start' => substr((string) $version->scheduleVersion?->start_time, 0, 5),
            'scheduled_end' => substr((string) $version->scheduleVersion?->end_time, 0, 5),
            'location' => (string) $version->scheduleVersion?->location,
            'actual_started_at' => $version->executionSession?->started_at?->toIso8601String(),
            'actual_ended_at' => $version->executionSession?->ended_at?->toIso8601String(),
            'chairperson' => (string) ($meeting->chairperson?->full_name ?? trim(($meeting->chairperson?->first_name ?? '').' '.($meeting->chairperson?->last_name ?? ''))),
            'formal_units' => $meeting->units
                ->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])
                ->map(fn ($item): string => (string) ($item->unit?->name ?? ''))
                ->values()
                ->all(),
            'attendance' => $version->attendanceSnapshots
                ->map(fn ($item): array => [
                    'employee_id' => (int) $item->employee_id,
                    'display_name' => (string) $item->display_name,
                    'unit' => (string) ($item->unit?->name ?? ''),
                    'role' => (string) ($item->participant_role ?? ''),
                    'status' => (string) $item->attendance_status,
                ])
                ->values()
                ->all(),
            'items' => $version->items
                ->map(fn ($item): array => [
                    'sequence_number' => (int) $item->sequence_number,
                    'agenda_title' => (string) $item->agenda_title,
                    'discussion_summary' => (string) $item->discussion_summary,
                ])
                ->values()
                ->all(),
            'decisions' => $version->decisions
                ->map(fn ($item): array => [
                    'decision_number' => (string) $item->decision_number,
                    'decision_text' => (string) $item->decision_text,
                    'responsible_unit' => (string) ($item->responsibleUnit?->name ?? ''),
                    'responsible_employee' => (string) ($item->responsibleEmployee?->full_name ?? ''),
                    'target_date' => $item->target_date?->toDateString(),
                    'notes' => (string) ($item->notes ?? ''),
                ])
                ->values()
                ->all(),
            'summary' => (string) $version->summary,
            'general_notes' => (string) ($version->general_notes ?? ''),
            'version_number' => (int) $version->version_number,
            'finalized_at' => $minutes->finalized_at?->toIso8601String(),
        ];

        $checksum = hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'tempDir' => $this->temporaryDirectory(),
            'default_font' => 'dejavusans',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->WriteHTML($this->html($canonical, $checksum));

        return [
            'bytes' => $mpdf->Output('', 'S'),
            'checksum' => $checksum,
            'file_name' => 'minutes-'.$meeting->meeting_number.'-v'.$version->version_number.'.pdf',
        ];
    }

    private function temporaryDirectory(): string
    {
        $directory = storage_path('app/mpdf');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    private function html(array $content, string $checksum): string
    {
        $e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ltr = static fn ($value): string => '<span dir="ltr" style="direction:ltr;unicode-bidi:embed;white-space:nowrap;font-size:9px;">'.$e($value).'</span>';
        $attendance = '';
        foreach ($content['attendance'] as $item) {
            $attendance .= '<tr><td>'.$e($item['display_name']).'</td><td>'.$e($item['unit']).'</td><td>'.$ltr($item['role']).'</td><td>'.$e($item['status'] === 'PRESENT' ? 'حاضر' : 'غایب').'</td></tr>';
        }

        $items = '';
        foreach ($content['items'] as $item) {
            $items .= '<tr><td>'.$ltr($item['sequence_number']).'</td><td>'.$e($item['agenda_title']).'</td><td>'.$e($item['discussion_summary']).'</td></tr>';
        }

        $decisions = '';
        foreach ($content['decisions'] as $item) {
            $decisions .= '<tr><td>'.$ltr($item['decision_number']).'</td><td>'.$e($item['decision_text']).'</td><td>'.$e($item['responsible_unit']).'</td><td>'.$e($item['responsible_employee']).'</td><td>'.$ltr($item['target_date']).'</td></tr>';
        }

        $units = implode('، ', array_map($e, $content['formal_units']));
        $scheduledDate = $ltr($content['scheduled_date'].' '.$content['scheduled_start'].' - '.$content['scheduled_end']);
        $actualStartedAt = $ltr($content['actual_started_at']);
        $actualEndedAt = $ltr($content['actual_ended_at']);
        $finalizedAt = $ltr($content['finalized_at']);

        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><style>
            body{font-family:dejavusans,sans-serif;direction:rtl;color:#1f2937;font-size:11px;line-height:1.7}
            h1,h2{color:#123c69;margin:12px 0 6px}
            .header{text-align:center;border-bottom:2px solid #123c69;padding-bottom:10px}
            .meta{width:100%;border-collapse:collapse;margin:8px 0 14px}
            .meta td{border:1px solid #cbd5e1;padding:5px}
            .label{font-weight:bold;background:#f1f5f9;width:25%}
            table{width:100%;border-collapse:collapse;margin:6px 0 14px}
            th,td{border:1px solid #94a3b8;padding:5px;text-align:right}
            th{background:#e2e8f0}
            .checksum{font-size:8px;direction:ltr;text-align:left;word-break:break-all;margin-top:18px;color:#475569}
        </style></head><body>
            <div class="header"><h1>صورت‌جلسهٔ رسمی</h1><div>'.$e($content['subject']).'</div></div>
            <table class="meta">
                <tr><td class="label">شماره جلسه</td><td>'.$ltr($content['meeting_number']).'</td><td class="label">نسخه صورت‌جلسه</td><td>'.$ltr($content['version_number']).'</td></tr>
                <tr><td class="label">تاریخ و زمان رسمی</td><td>'.$scheduledDate.'</td><td class="label">مکان</td><td>'.$e($content['location']).'</td></tr>
                <tr><td class="label">شروع واقعی</td><td>'.$actualStartedAt.'</td><td class="label">پایان واقعی</td><td>'.$actualEndedAt.'</td></tr>
                <tr><td class="label">رییس جلسه</td><td>'.$e($content['chairperson']).'</td><td class="label">ریاست‌های رسمی</td><td>'.$e($units).'</td></tr>
            </table>
            <h2>اشتراک‌کنندگان و حضور</h2><table><thead><tr><th>اشتراک‌کننده</th><th>واحد</th><th>نقش</th><th>وضعیت</th></tr></thead><tbody>'.$attendance.'</tbody></table>
            <h2>اجندا و مباحث</h2><table><thead><tr><th>شماره</th><th>عنوان اجندا</th><th>خلاصه بحث</th></tr></thead><tbody>'.$items.'</tbody></table>
            <h2>خلاصه عمومی</h2><p>'.$e($content['summary']).'</p><p>'.$e($content['general_notes']).'</p>
            <h2>تصمیمات</h2><table><thead><tr><th>شماره</th><th>متن تصمیم</th><th>واحد مسئول</th><th>کارمند مسئول</th><th>تاریخ هدف</th></tr></thead><tbody>'.$decisions.'</tbody></table>
            <p>زمان نهایی‌سازی: '.$finalizedAt.'</p>
            <p class="checksum">SHA-256 content reference: '.$e($checksum).'</p>
        </body></html>';
    }
}
