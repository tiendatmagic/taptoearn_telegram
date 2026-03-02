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
            $table->string('client_id', 64)->nullable()->after('client_nonce');
            $table->index(['player_id', 'client_id', 'client_seq'], 'tap_events_player_client_seq_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tap_events', function (Blueprint $table): void {
            $table->dropIndex('tap_events_player_client_seq_idx');
            $table->dropColumn('client_id');
        });
    }
};
