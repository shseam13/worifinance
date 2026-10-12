<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table): void {
            $table->string('payment_qr_path_snapshot')->nullable()->after('payment_instructions_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table): void {
            $table->dropColumn('payment_qr_path_snapshot');
        });
    }
};
