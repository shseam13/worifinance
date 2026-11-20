<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_terms', function (Blueprint $table): void {
            $table->decimal('monthly_rate_percent', 8, 4)->nullable();
            $table->decimal('minimum_investment_usd', 20, 2)->nullable();
            $table->decimal('maximum_investment_usd', 20, 2)->nullable();
            $table->unsignedSmallInteger('minimum_duration_months')->nullable();
            $table->unsignedSmallInteger('maximum_duration_months')->nullable();
        });

        Schema::table('investments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->timestamp('matures_at')->nullable();
            $table->timestamp('principal_returned_at')->nullable();
            $table->foreignId('usd_exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
            $table->decimal('usd_bdt_rate_snapshot', 20, 8)->nullable();
            $table->index(
                ['status', 'matures_at', 'principal_returned_at'],
                'investments_maturity_return_index',
            );
        });

        Schema::table('profit_distributions', function (Blueprint $table): void {
            $table->decimal('annual_rate_snapshot', 8, 4)->nullable()->change();
            $table->decimal('monthly_rate_snapshot', 8, 4)->nullable();
        });

        Schema::table('distribution_items', function (Blueprint $table): void {
            $table->decimal('annual_rate_snapshot', 8, 4)->nullable()->change();
            $table->decimal('monthly_rate_snapshot', 8, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('profit_distributions')
            ->whereNull('annual_rate_snapshot')
            ->update(['annual_rate_snapshot' => '0.0000']);
        DB::table('distribution_items')
            ->whereNull('annual_rate_snapshot')
            ->update(['annual_rate_snapshot' => '0.0000']);

        Schema::table('distribution_items', function (Blueprint $table): void {
            $table->dropColumn('monthly_rate_snapshot');
            $table->decimal('annual_rate_snapshot', 8, 4)->nullable(false)->change();
        });

        Schema::table('profit_distributions', function (Blueprint $table): void {
            $table->dropColumn('monthly_rate_snapshot');
            $table->decimal('annual_rate_snapshot', 8, 4)->nullable(false)->change();
        });

        Schema::table('investments', function (Blueprint $table): void {
            $table->dropIndex('investments_maturity_return_index');
            $table->dropConstrainedForeignId('usd_exchange_rate_id');
            $table->dropColumn([
                'duration_months',
                'matures_at',
                'principal_returned_at',
                'usd_bdt_rate_snapshot',
            ]);
        });

        Schema::table('project_terms', function (Blueprint $table): void {
            $table->dropColumn([
                'monthly_rate_percent',
                'minimum_investment_usd',
                'maximum_investment_usd',
                'minimum_duration_months',
                'maximum_duration_months',
            ]);
        });
    }
};
