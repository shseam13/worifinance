<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WithdrawalManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending', 'paid', 'rejected', 'all'], true), 404);

        $query = DB::table('withdrawal_requests')
            ->join('users', 'users.id', '=', 'withdrawal_requests.user_id')
            ->select('withdrawal_requests.*', 'users.name as investor_name', 'users.email as investor_email');
        if ($status !== 'all') {
            $query->where('withdrawal_requests.status', $status);
        }

        return view('admin.withdrawals.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'pendingInvestments' => DB::table('investments')->where('status', 'pending')->count(),
            'withdrawals' => $query->latest('withdrawal_requests.created_at')->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function review(Request $request, int $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['paid', 'rejected'])],
            'payout_reference' => [
                'nullable',
                'string',
                'max:150',
                'unique:withdrawal_requests,payout_reference',
                Rule::requiredIf($request->input('decision') === 'paid'),
            ],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'rejected')],
        ]);

        DB::transaction(function () use ($request, $withdrawal, $validated): void {
            $record = DB::table('withdrawal_requests')
                ->where('id', $withdrawal)
                ->lockForUpdate()
                ->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'pending', 409, __('site.withdrawals.already_reviewed'));

            $wallet = DB::table('wallet_accounts')
                ->where('user_id', $record->user_id)
                ->where('currency', $record->currency)
                ->lockForUpdate()
                ->first();
            abort_if($wallet === null, 500, __('site.withdrawals.wallet_unavailable'));

            $hasReservedAmount = DB::table('wallet_accounts')
                ->where('id', $wallet->id)
                ->whereRaw('reserved_balance >= CAST(? AS DECIMAL(24,8))', [$record->amount])
                ->exists();
            abort_unless($hasReservedAmount, 409, __('site.withdrawals.reservation_unavailable'));

            if ($validated['decision'] === 'paid') {
                $hasBalance = DB::table('wallet_accounts')
                    ->where('id', $wallet->id)
                    ->whereRaw('balance >= CAST(? AS DECIMAL(24,8))', [$record->amount])
                    ->exists();
                abort_unless($hasBalance, 409, __('site.withdrawals.reservation_unavailable'));

                DB::table('wallet_accounts')->where('id', $wallet->id)
                    ->decrement('balance', $record->amount, ['updated_at' => now()]);
                DB::table('wallet_accounts')->where('id', $wallet->id)
                    ->decrement('reserved_balance', $record->amount, ['updated_at' => now()]);
            } else {
                DB::table('wallet_accounts')->where('id', $wallet->id)
                    ->decrement('reserved_balance', $record->amount, ['updated_at' => now()]);
            }

            $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
            abort_if($updatedWallet === null, 500, __('site.withdrawals.wallet_unavailable'));

            $transactionId = DB::table('ledger_transactions')->insertGetId([
                'idempotency_key' => 'withdrawal-review:'.$record->id,
                'type' => $validated['decision'] === 'paid' ? 'withdrawal' : 'withdrawal_release',
                'status' => 'posted',
                'description' => $validated['decision'] === 'paid'
                    ? 'Record manually completed investor withdrawal'
                    : 'Release reserved funds for rejected withdrawal request',
                'exchange_rate_id' => $record->exchange_rate_id,
                'metadata' => json_encode([
                    'withdrawal_request_id' => $record->id,
                    'decision' => $validated['decision'],
                    'payout_reference' => $validated['payout_reference'] ?? null,
                    'review_note' => $validated['review_note'] ?? null,
                    'bdt_per_usdt_snapshot' => $record->bdt_per_usdt_snapshot,
                ], JSON_THROW_ON_ERROR),
                'created_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ledger_entries')->insert([
                'ledger_transaction_id' => $transactionId,
                'wallet_account_id' => $wallet->id,
                'entry_type' => $validated['decision'] === 'paid' ? 'withdrawal_debit' : 'withdrawal_release',
                'amount' => $validated['decision'] === 'paid' ? '-'.$record->amount : '0.00000000',
                'balance_after' => $updatedWallet->balance,
                'reserved_after' => $updatedWallet->reserved_balance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('withdrawal_requests')->where('id', $record->id)->update([
                'status' => $validated['decision'],
                'payout_reference' => $validated['payout_reference'] ?? null,
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
                'updated_at' => now(),
            ]);
            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'withdrawal_request.'.$validated['decision'],
                'subject_type' => 'withdrawal_request',
                'subject_id' => $record->id,
                'before_values' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'status' => $validated['decision'],
                    'payout_reference' => $validated['payout_reference'] ?? null,
                    'review_note' => $validated['review_note'] ?? null,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $message = $validated['decision'] === 'paid'
            ? __('site.withdrawals.paid')
            : __('site.withdrawals.rejected');

        return redirect()->route('admin.withdrawals.index')->with('status', $message);
    }
}
