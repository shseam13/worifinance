<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LedgerTransactionManagementController extends Controller
{
    public function index(Request $request): View
    {
        $types = DB::table('ledger_transactions')->distinct()->orderBy('type')->pluck('type');
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:40', Rule::in($types->all())],
            'status' => ['nullable', Rule::in(['all', 'posted', 'pending', 'reversed'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'all';
        $search = trim($filters['search'] ?? '');

        $query = DB::table('ledger_transactions')
            ->leftJoin('users as actors', 'actors.id', '=', 'ledger_transactions.created_by_user_id')
            ->select([
                'ledger_transactions.id',
                'ledger_transactions.idempotency_key',
                'ledger_transactions.type',
                'ledger_transactions.status',
                'ledger_transactions.description',
                'ledger_transactions.metadata',
                'ledger_transactions.created_at',
                'actors.name as actor_name',
                'actors.email as actor_email',
            ]);

        if (! empty($filters['type'])) {
            $query->where('ledger_transactions.type', $filters['type']);
        }
        if ($status !== 'all') {
            $query->where('ledger_transactions.status', $status);
        }
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where('ledger_transactions.idempotency_key', 'like', $like)
                    ->orWhere('ledger_transactions.description', 'like', $like)
                    ->orWhere('actors.name', 'like', $like)
                    ->orWhere('actors.email', 'like', $like)
                    ->orWhereExists(function ($entryQuery) use ($like): void {
                        $entryQuery->selectRaw('1')
                            ->from('ledger_entries')
                            ->join('wallet_accounts', 'wallet_accounts.id', '=', 'ledger_entries.wallet_account_id')
                            ->join('users as investors', 'investors.id', '=', 'wallet_accounts.user_id')
                            ->whereColumn('ledger_entries.ledger_transaction_id', 'ledger_transactions.id')
                            ->where(function ($investorQuery) use ($like): void {
                                $investorQuery->where('investors.name', 'like', $like)
                                    ->orWhere('investors.email', 'like', $like);
                            });
                    });
            });
        }

        $transactions = $query->latest('ledger_transactions.created_at')
            ->latest('ledger_transactions.id')
            ->paginate(20)
            ->withQueryString();
        $entries = DB::table('ledger_entries')
            ->join('wallet_accounts', 'wallet_accounts.id', '=', 'ledger_entries.wallet_account_id')
            ->join('users as investors', 'investors.id', '=', 'wallet_accounts.user_id')
            ->whereIn('ledger_entries.ledger_transaction_id', $transactions->pluck('id'))
            ->select([
                'ledger_entries.ledger_transaction_id',
                'ledger_entries.entry_type',
                'ledger_entries.amount',
                'ledger_entries.balance_after',
                'ledger_entries.reserved_after',
                'wallet_accounts.currency',
                'investors.name as investor_name',
                'investors.email as investor_email',
            ])
            ->orderBy('ledger_entries.id')
            ->get()
            ->groupBy('ledger_transaction_id');

        return view('admin.transactions.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'transactions' => $transactions,
            'entries' => $entries,
            'types' => $types,
            'status' => $status,
            'selectedType' => $filters['type'] ?? '',
            'search' => $search,
        ]);
    }
}
