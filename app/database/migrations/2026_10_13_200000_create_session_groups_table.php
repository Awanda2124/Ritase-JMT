<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_session_id')->constrained('work_sessions')->restrictOnDelete();
            $table->string('name', 50);
            $table->unsignedInteger('quota');
            $table->string('token', 64)->unique();
            $table->timestamps();
        });

        Schema::table('session_drivers', function (Blueprint $table) {
            $table->foreignId('session_group_id')->nullable()->after('driver_id')
                ->constrained('session_groups')->restrictOnDelete();
        });

        // Token pindah ke kelompok, jadi di sesi tidak wajib lagi.
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->change();
        });

        // Data lama: tiap sesi jadi satu kelompok "Umum" dengan token & kuota yang sama,
        // jadi link lama tetap berfungsi.
        foreach (DB::table('work_sessions')->get() as $s) {
            $groupId = DB::table('session_groups')->insertGetId([
                'work_session_id' => $s->id,
                'name' => 'Umum',
                'quota' => $s->quota,
                'token' => $s->token ?? bin2hex(random_bytes(20)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('session_drivers')
                ->where('work_session_id', $s->id)
                ->update(['session_group_id' => $groupId]);
        }
    }

    public function down(): void
    {
        Schema::table('session_drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_group_id');
        });

        Schema::dropIfExists('session_groups');
    }
};