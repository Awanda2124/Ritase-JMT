<?php

namespace App\Exceptions;

use RuntimeException;

class QuotaFullException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Penuh');
    }
}
