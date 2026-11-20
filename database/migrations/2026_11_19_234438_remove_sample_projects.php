<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sampleProjectIds = DB::table('projects')
            ->where('is_sample', true)
            ->pluck('id');

        if ($sampleProjectIds->isEmpty()) {
            return;
        }

        $hasInvestments = DB::table('investments')
            ->whereIn('project_id', $sampleProjectIds)
            ->exists();
        $hasDistributions = DB::table('profit_distributions')
            ->whereIn('project_id', $sampleProjectIds)
            ->exists();

        if ($hasInvestments || $hasDistributions) {
            throw new RuntimeException('Sample projects with financial history cannot be removed by this migration.');
        }

        DB::transaction(function () use ($sampleProjectIds): void {
            DB::table('projects')->whereIn('id', $sampleProjectIds)->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Removed sample projects are not restored when rolling back.
    }
};
