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
            $table->unsignedBigInteger('client_seq')->default(0)->after('client_nonce');
            $table->index(['player_id', 'created_at']);
            $table->index(['player_id', 'client_seq']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tap_events', function (Blueprint $table): void {
            $table->dropIndex(['player_id', 'client_seq']);
            $table->dropIndex(['player_id', 'created_at']);
            $table->dropColumn('client_seq');
        });
    }
};
