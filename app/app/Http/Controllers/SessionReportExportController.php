<?php

namespace App\Http\Controllers;

use App\Models\WorkSession;
use App\Services\SessionReportExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionReportExportController
{
    public function __invoke(WorkSession $workSession, SessionReportExporter $exporter): StreamedResponse
    {
        activity()->performedOn($workSession)->log('Export laporan Excel');

        return response()->streamDownload(
            fn () => $exporter->write($workSession, 'php://output'),
            $exporter->filename($workSession),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}