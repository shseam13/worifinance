<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepositRequestManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending', 'approved', 'rejected', 'all'], true), 404);

        $query = DB::table('deposit_requests')
            ->join('users', 'users.id', '=', 'deposit_requests.user_id')
            ->select('deposit_requests.*', 'users.name as investor_name', 'users.email as investor_email');

        if ($status !== 'all') {
            $query->where('deposit_requests.status', $status);
        }

        return view('admin.deposits.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'deposits' => $query->latest('deposit_requests.created_at')->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function review(Request $request, int $deposit): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'rejected')],
        ]);

        DB::transaction(function () use ($request, $deposit, $validated): void {
            $depositRequest = DB::table('deposit_requests')->where('id', $deposit)->lockForUpdate()->first();
            abort_if($depositRequest === null, 404);
            abort_unless($depositRequest->status === 'pending', 409, __('site.deposits.already_reviewed'));

            if ($validated['decision'] === 'approved') {
                DB::table('wallet_accounts')->insertOrIgnore([
                    'user_id' => $depositRequest->user_id,
                    'currency' => $depositRequest->currency,
                    'balance' => '0.00000000',
                    'reserved_balance' => '0.00000000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $wallet = DB::table('wallet_accounts')
                    ->where('user_id', $depositRequest->user_id)
                    ->where('currency', $depositRequest->currency)
                    ->lockForUpdate()
                    ->first();
                abort_if($wallet === null, 500, __('site.deposits.wallet_unavailable'));

                DB::table('wallet_accounts')
                    ->where('id', $wallet->id)
                    ->increment('balance', $depositRequest->amount, ['updated_at' => now()]);

                $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
                abort_if($updatedWallet === null, 500, __('site.deposits.wallet_unavailable'));

                $ledgerTransactionId = DB::table('ledger_transactions')->insertGetId([
                    'idempotency_key' => 'deposit-request:'.$depositRequest->id,
                    'type' => 'deposit',
                    'status' => 'posted',
                    'description' => 'Approved investor deposit request',
                    'exchange_rate_id' => $depositRequest->exchange_rate_id,
                    'metadata' => json_encode([
                        'deposit_request_id' => $depositRequest->id,
                        'payment_reference' => $depositRequest->payment_reference,
                        'bdt_per_usdt_snapshot' => $depositRequest->bdt_per_usdt_snapshot,
                    ], JSON_THROW_ON_ERROR),
                    'created_by_user_id' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('ledger_entries')->insert([
                    'ledger_transaction_id' => $ledgerTransactionId,
                    'wallet_account_id' => $wallet->id,
                    'entry_type' => 'deposit_credit',
                    'amount' => $depositRequest->amount,
                    'balance_after' => $updatedWallet->balance,
                    'reserved_after' => $updatedWallet->reserved_balance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('deposit_requests')->where('id', $depositRequest->id)->update([
                'status' => $validated['decision'],
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
                'updated_at' => now(),
            ]);

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'deposit_request.'.$validated['decision'],
                'subject_type' => 'deposit_request',
                'subject_id' => $depositRequest->id,
                'before_values' => json_encode([
                    'status' => 'pending',
                    'currency' => $depositRequest->currency,
                    'amount' => $depositRequest->amount,
                ], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'status' => $validated['decision'],
                    'review_note' => $validated['review_note'] ?? null,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $message = $validated['decision'] === 'approved'
            ? __('site.deposits.approved')
            : __('site.deposits.rejected');

        return redirect()->route('admin.deposits.index')->with('status', $message);
    }
}
