<?php

namespace App\Exceptions;

use RuntimeException;

class OutsideRadiusException extends RuntimeException
{
    public function __construct(public readonly ?float $distance = null)
    {
        parent::__construct('Lokasi di luar radius JMT.');
    }
}
