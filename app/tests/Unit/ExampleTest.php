<?php

use App\Models\Driver;

it('masks ktp showing first four and last four digits', function () {
    $driver = new Driver(['ktp' => '3172010101010010']);

    expect($driver->maskedKtp())->toBe('3172••••••••0010');
});
