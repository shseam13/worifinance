<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembershipChargeManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'due');
        abort_unless(in_array($status, ['due', 'paid', 'all'], true), 404);

        $query = DB::table('membership_charges')
            ->join('users', 'users.id', '=', 'membership_charges.user_id')
            ->select('membership_charges.*', 'users.name as investor_name', 'users.email as investor_email');
        if ($status !== 'all') {
            $query->where('membership_charges.status', $status);
        }

        return view('admin.membership-charges.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'pendingInvestments' => DB::table('investments')->where('status', 'pending')->count(),
            'dueMembershipCharges' => DB::table('membership_charges')->where('status', 'due')->count(),
            'charges' => $query->latest('membership_charges.period_start')->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }
}
