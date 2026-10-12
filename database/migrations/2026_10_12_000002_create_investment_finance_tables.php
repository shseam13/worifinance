<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100);
            $table->unsignedInteger('version');
            $table->text('value');
            $table->char('currency', 4)->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'version']);
            $table->index(['key', 'effective_at']);
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('base_currency', 4)->default('USDT');
            $table->char('quote_currency', 4)->default('BDT');
            $table->decimal('quote_per_base', 20, 8);
            $table->unsignedInteger('version');
            $table->timestamp('effective_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['base_currency', 'quote_currency', 'version'], 'exchange_rates_pair_version_unique');
            $table->index(['base_currency', 'quote_currency', 'effective_at'], 'exchange_rates_pair_effective_index');
        });

        Schema::create('wallet_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 4);
            $table->decimal('balance', 24, 8)->default(0);
            $table->decimal('reserved_balance', 24, 8)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 100)->unique();
            $table->string('type', 40)->index();
            $table->string('status', 24)->default('posted')->index();
            $table->string('description')->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_account_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 32);
            $table->decimal('amount', 24, 8);
            $table->decimal('balance_after', 24, 8);
            $table->decimal('reserved_after', 24, 8);
            $table->timestamps();

            $table->index(['wallet_account_id', 'created_at']);
        });

        Schema::create('deposit_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 4);
            $table->decimal('amount', 24, 8);
            $table->decimal('bdt_amount', 20, 2)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('bdt_per_usdt_snapshot', 20, 8)->nullable();
            $table->string('payment_reference', 150)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 4);
            $table->decimal('amount', 24, 8);
            $table->decimal('fee_amount', 24, 8)->default(0);
            $table->decimal('minimum_amount_snapshot', 24, 8);
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('bdt_per_usdt_snapshot', 20, 8)->nullable();
            $table->string('destination', 255);
            $table->string('network', 40)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->string('payout_reference', 150)->nullable()->unique();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('investments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_term_id')->constrained('project_terms')->restrictOnDelete();
            $table->unsignedBigInteger('unit_count');
            $table->decimal('unit_price_bdt', 20, 2);
            $table->decimal('amount_bdt', 20, 2);
            $table->char('payment_currency', 4);
            $table->decimal('paid_amount', 24, 8);
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('bdt_per_usdt_snapshot', 20, 8)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('profit_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_term_id')->constrained('project_terms')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('annual_rate_snapshot', 8, 4);
            $table->char('payout_currency', 4);
            $table->decimal('bdt_per_usdt_snapshot', 20, 8)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'period_start', 'period_end'], 'profit_distributions_project_period_unique');
        });

        Schema::create('distribution_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profit_distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->constrained()->restrictOnDelete();
            $table->decimal('eligible_principal_bdt', 20, 2);
            $table->decimal('annual_rate_snapshot', 8, 4);
            $table->unsignedInteger('eligible_days');
            $table->char('payout_currency', 4);
            $table->decimal('amount', 24, 8);
            $table->decimal('bdt_per_usdt_snapshot', 20, 8)->nullable();
            $table->timestamps();

            $table->unique(['profit_distribution_id', 'investment_id']);
        });

        Schema::create('referral_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('investment_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('commission_rate_snapshot', 8, 4);
            $table->decimal('basis_amount_bdt', 20, 2);
            $table->decimal('commission_amount_bdt', 20, 2);
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_user_id', 'status']);
        });

        Schema::create('membership_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->char('currency', 4);
            $table->decimal('amount', 24, 8);
            $table->unsignedInteger('setting_version');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('due')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_start']);
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('membership_charges');
        Schema::dropIfExists('referral_commissions');
        Schema::dropIfExists('distribution_items');
        Schema::dropIfExists('profit_distributions');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('deposit_requests');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('wallet_accounts');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('platform_settings');
    }
};
