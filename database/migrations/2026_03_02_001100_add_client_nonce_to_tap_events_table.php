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
        Schema::table('tap_events', function (Blueprint $table): void {
            $table->string('client_nonce', 64)->nullable()->after('source');
            $table->unique(['player_id', 'client_nonce']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tap_events', function (Blueprint $table): void {
            $table->dropUnique(['player_id', 'client_nonce']);
            $table->dropColumn('client_nonce');
        });
    }
};
