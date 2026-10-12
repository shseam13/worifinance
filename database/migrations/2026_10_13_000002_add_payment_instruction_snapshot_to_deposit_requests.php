<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table): void {
            $table->unsignedInteger('payment_instructions_version')->nullable();
            $table->text('payment_instructions_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table): void {
            $table->dropColumn(['payment_instructions_version', 'payment_instructions_snapshot']);
        });
    }
};
