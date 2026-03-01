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
        Schema::table('players', function (Blueprint $table): void {
            $table->timestamp('tap_window_started_at')->nullable()->after('last_tap_at');
            $table->unsignedInteger('tap_window_count')->default(0)->after('tap_window_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn(['tap_window_started_at', 'tap_window_count']);
        });
    }
};
