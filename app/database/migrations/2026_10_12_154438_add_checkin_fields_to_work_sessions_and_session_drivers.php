<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->timestamp('checkin_closes_at')->nullable()->after('closes_at');
        });

        Schema::table('session_drivers', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('registered_at');
            $table->decimal('checked_in_lat', 10, 7)->nullable()->after('checked_in_at');
            $table->decimal('checked_in_lng', 10, 7)->nullable()->after('checked_in_lat');
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_lng')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropColumn(['checked_in_at', 'checked_in_lat', 'checked_in_lng']);
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropColumn('checkin_closes_at');
        });
    }
};
