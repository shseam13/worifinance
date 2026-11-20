<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuditLogManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'admin' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'super_admin')],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        if (
            isset($filters['from'], $filters['to'])
            && $filters['from'] > $filters['to']
        ) {
            throw ValidationException::withMessages([
                'to' => __('validation.before_or_equal', ['date' => $filters['from']]),
            ]);
        }
        $search = trim($filters['search'] ?? '');
        $query = DB::table('admin_audit_logs')
            ->leftJoin('users as admins', 'admins.id', '=', 'admin_audit_logs.admin_user_id')
            ->select([
                'admin_audit_logs.id',
                'admin_audit_logs.admin_user_id',
                'admin_audit_logs.action',
                'admin_audit_logs.subject_type',
                'admin_audit_logs.subject_id',
                'admin_audit_logs.before_values',
                'admin_audit_logs.after_values',
                'admin_audit_logs.ip_address',
                'admin_audit_logs.created_at',
                'admins.name as admin_name',
                'admins.email as admin_email',
            ]);

        if (! empty($filters['action'])) {
            $query->where('admin_audit_logs.action', $filters['action']);
        }
        if (! empty($filters['admin'])) {
            $query->where('admin_audit_logs.admin_user_id', $filters['admin']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('admin_audit_logs.created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('admin_audit_logs.created_at', '<=', $filters['to']);
        }
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where('admin_audit_logs.action', 'like', $like)
                    ->orWhere('admin_audit_logs.subject_type', 'like', $like)
                    ->orWhere('admin_audit_logs.ip_address', 'like', $like)
                    ->orWhere('admins.name', 'like', $like)
                    ->orWhere('admins.email', 'like', $like);
                if (ctype_digit($search)) {
                    $query->orWhere('admin_audit_logs.subject_id', (int) $search);
                }
            });
        }

        return view('admin.audit-logs.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'logs' => $query->latest('admin_audit_logs.created_at')
                ->latest('admin_audit_logs.id')
                ->paginate(30)
                ->withQueryString(),
            'actions' => DB::table('admin_audit_logs')->distinct()->orderBy('action')->pluck('action'),
            'admins' => DB::table('users')
                ->where('role', 'super_admin')
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
            'search' => $search,
        ]);
    }
}
