<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAnnualMembershipCharges extends Command
{
    protected $signature = 'membership-charges:process';

    protected $description = 'Create due annual membership charges and collect available BDT wallet balances';

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $createdCount = 0;
        $paidCount = 0;

        User::query()
            ->where('role', 'investor')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($today, &$createdCount): void {
                foreach ($users as $user) {
                    $accountCreated = CarbonImmutable::parse($user->created_at)->startOfDay();
                    $anniversaryYear = $today->year - $accountCreated->year;
                    if ($anniversaryYear < 1) {
                        continue;
                    }

                    $periodStart = $accountCreated->addYears($anniversaryYear);
                    if ($periodStart->greaterThan($today)) {
                        $anniversaryYear--;
                    }
                    if ($anniversaryYear < 1) {
                        continue;
                    }

                    $periodStart = $accountCreated->addYears($anniversaryYear);
                    if ($this->createPeriodCharge($user, $periodStart, $periodStart->addYear()->subDay())) {
                        $createdCount++;
                    }
                }
            });

        DB::table('membership_charges')
            ->where('status', 'due')
            ->orderBy('id')
            ->chunkById(100, function ($charges) use (&$paidCount): void {
                foreach ($charges as $charge) {
                    if ($this->collectCharge((int) $charge->id)) {
                        $paidCount++;
                    }
                }
            });

        $dueCount = DB::table('membership_charges')->where('status', 'due')->count();
        $this->info("Created {$createdCount} annual charges, collected {$paidCount}, and left {$dueCount} due for retry.");

        return self::SUCCESS;
    }

    private function createPeriodCharge(User $user, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): bool
    {
        return DB::transaction(function () use ($user, $periodStart, $periodEnd): bool {
            $charge = DB::table('membership_charges')
                ->where('user_id', $user->id)
                ->where('period_start', $periodStart->toDateString())
                ->lockForUpdate()
                ->first();
            if ($charge !== null) {
                return false;
            }

            $setting = DB::table('platform_settings')
                ->where('key', 'platform.annual_fee')
                ->where('effective_at', '<', $periodStart->addDay()->toDateTimeString())
                ->orderByDesc('effective_at')
                ->orderByDesc('version')
                ->first();
            if ($setting === null || ! is_numeric($setting->value) || (float) $setting->value <= 0) {
                return false;
            }

            $chargeId = DB::table('membership_charges')->insertGetId([
                'user_id' => $user->id,
                'currency' => 'BDT',
                'amount' => $setting->value,
                'setting_version' => $setting->version,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => 'due',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => null,
                'action' => 'membership_charge.created',
                'subject_type' => 'membership_charge',
                'subject_id' => $chargeId,
                'before_values' => null,
                'after_values' => json_encode([
                    'user_id' => $user->id,
                    'amount' => $setting->value,
                    'currency' => 'BDT',
                    'setting_version' => $setting->version,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'status' => 'due',
                ], JSON_THROW_ON_ERROR),
                'ip_address' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    private function collectCharge(int $chargeId): bool
    {
        return DB::transaction(function () use ($chargeId): bool {
            $charge = DB::table('membership_charges')->where('id', $chargeId)->lockForUpdate()->first();
            if ($charge === null || $charge->status !== 'due') {
                return false;
            }

            $wallet = DB::table('wallet_accounts')
                ->where('user_id', $charge->user_id)
                ->where('currency', 'BDT')
                ->lockForUpdate()
                ->first();
            if (
                $wallet === null
                || DB::table('wallet_accounts')
                    ->where('id', $wallet->id)
                    ->whereRaw('balance - reserved_balance >= CAST(? AS DECIMAL(24,8))', [$charge->amount])
                    ->doesntExist()
            ) {
                return false;
            }

            DB::table('wallet_accounts')
                ->where('id', $wallet->id)
                ->decrement('balance', $charge->amount, ['updated_at' => now()]);
            $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
            if ($updatedWallet === null) {
                throw new \RuntimeException("Unable to retrieve BDT wallet {$wallet->id} after annual charge.");
            }

            DB::table('membership_charges')->where('id', $charge->id)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'updated_at' => now(),
            ]);
            $transactionId = DB::table('ledger_transactions')->insertGetId([
                'idempotency_key' => 'membership-charge:'.$charge->id,
                'type' => 'membership_charge',
                'status' => 'posted',
                'description' => 'Annual account membership charge',
                'metadata' => json_encode([
                    'membership_charge_id' => $charge->id,
                    'user_id' => $charge->user_id,
                    'period_start' => $charge->period_start,
                    'period_end' => $charge->period_end,
                    'setting_version' => $charge->setting_version,
                ], JSON_THROW_ON_ERROR),
                'created_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ledger_entries')->insert([
                'ledger_transaction_id' => $transactionId,
                'wallet_account_id' => $wallet->id,
                'entry_type' => 'membership_charge',
                'amount' => '-'.$charge->amount,
                'balance_after' => $updatedWallet->balance,
                'reserved_after' => $updatedWallet->reserved_balance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => null,
                'action' => 'membership_charge.paid',
                'subject_type' => 'membership_charge',
                'subject_id' => $charge->id,
                'before_values' => json_encode(['status' => 'due'], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'status' => 'paid',
                    'amount' => $charge->amount,
                    'user_id' => $charge->user_id,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }
}
