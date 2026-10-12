<?php

namespace App\Exceptions;

use RuntimeException;

class AlreadyRegisteredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Supir sudah terdaftar pada sesi ini.');
    }
}
