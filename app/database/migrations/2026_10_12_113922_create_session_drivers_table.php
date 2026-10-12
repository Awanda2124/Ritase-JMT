<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_session_id')->constrained('work_sessions')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->timestamp('registered_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['work_session_id', 'driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_drivers');
    }
};
