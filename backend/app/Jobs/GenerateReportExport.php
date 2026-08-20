<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Reporting\Exports\ReportExportManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(public readonly int $exportId) {}

    public function handle(ReportExportManager $manager): void
    {
        $manager->generate($this->exportId);
    }
}
