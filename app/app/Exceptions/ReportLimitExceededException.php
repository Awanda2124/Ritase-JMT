<?php

namespace App\Exceptions;

use RuntimeException;

class ReportLimitExceededException extends RuntimeException
{
    public function __construct(public readonly int $alreadyReported = 0)
    {
        parent::__construct('Batas ritase per sesi terlampaui.');
    }
}
