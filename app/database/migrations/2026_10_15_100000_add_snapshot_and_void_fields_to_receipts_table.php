<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->unsignedInteger('rate_per_trip')->default(0)->after('total_trips');
            $table->json('chassis')->nullable()->after('amount');
            $table->unsignedInteger('print_count')->default(0)->after('chassis');

            // true = struk aktif, NULL = dibatalkan. Unique (sesi, supir, active_flag)
            // menjamin satu struk aktif per supir per sesi di level database; NULL boleh
            // berulang, jadi struk yang dibatalkan tidak menghalangi struk pengganti.
            $table->boolean('active_flag')->nullable()->default(true)->after('print_count');

            $table->foreignId('voided_by')->nullable()->after('printed_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->string('void_reason', 255)->nullable()->after('voided_at');

            $table->unique(['work_session_id', 'driver_id', 'active_flag'], 'receipts_one_active_per_driver');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique('receipts_one_active_per_driver');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['rate_per_trip', 'chassis', 'print_count', 'active_flag', 'voided_at', 'void_reason']);
        });
    }
};