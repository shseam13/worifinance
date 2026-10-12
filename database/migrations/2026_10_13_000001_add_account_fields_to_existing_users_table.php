<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->unique();
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 24)->default('investor')->index();
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 24)->default('active')->index();
            }

            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 24)->nullable()->unique();
            }

            if (! Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->foreignId('referred_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->dropForeign(['referred_by_user_id']);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $columns = array_filter(
                ['phone', 'role', 'status', 'referral_code', 'referred_by_user_id'],
                fn (string $column): bool => Schema::hasColumn('users', $column),
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
