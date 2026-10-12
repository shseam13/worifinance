<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('admin.password.edit', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $admin = $request->user();
        $currentSessionId = $request->session()->getId();

        DB::transaction(function () use ($request, $admin, $validated, $currentSessionId): void {
            $lockedAdmin = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            abort_if($lockedAdmin === null || $lockedAdmin->role !== 'super_admin', 403);

            $admin->forceFill([
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $admin->id)
                    ->where('id', '<>', $currentSessionId)
                    ->delete();
            }

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $admin->id,
                'action' => 'super_admin.password_changed',
                'subject_type' => 'user',
                'subject_id' => $admin->id,
                'before_values' => null,
                'after_values' => json_encode(['password_changed' => true], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $request->session()->regenerate();

        return back()->with('status', __('site.admin_password.password_changed'));
    }
}
