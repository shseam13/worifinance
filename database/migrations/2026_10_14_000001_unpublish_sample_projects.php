<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')
            ->where('is_sample', true)
            ->update([
                'status' => 'draft',
                'published_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Previously published sample projects must not be republished on rollback.
    }
};
