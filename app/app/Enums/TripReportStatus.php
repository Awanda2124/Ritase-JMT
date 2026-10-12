<?php

namespace App\Enums;

enum TripReportStatus: string
{
    case Menunggu = 'menunggu';
    case Cocok = 'cocok';
    case TidakCocok = 'tidak_cocok';
    case Void = 'void';
}
