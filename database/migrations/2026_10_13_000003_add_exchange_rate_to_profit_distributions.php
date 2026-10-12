<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_distributions', function (Blueprint $table): void {
            $table->foreignId('exchange_rate_id')
                ->nullable()
                ->after('bdt_per_usdt_snapshot')
                ->constrained('exchange_rates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('profit_distributions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exchange_rate_id');
        });
    }
};
