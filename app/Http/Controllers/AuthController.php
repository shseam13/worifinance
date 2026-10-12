<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login', ['portal' => 'investor']);
    }

    public function showAdminLogin(): View
    {
        return view('auth.login', ['portal' => 'admin']);
    }

    public function showRegistration(): View
    {
        $referralCode = request()->query('ref');

        if (is_string($referralCode)) {
            $referrer = User::query()
                ->where('referral_code', $referralCode)
                ->where('role', 'investor')
                ->where('status', 'active')
                ->first();

            if ($referrer !== null) {
                $this->ensureReferralCode($referrer);
                request()->session()->put('referrer_user_id', $referrer->id);
            } else {
                request()->session()->forget('referrer_user_id');
            }
        }

        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        return $this->authenticate($request, 'investor');
    }

    public function adminLogin(Request $request): RedirectResponse
    {
        return $this->authenticate($request, 'admin');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $validated['email'] = Str::lower($validated['email']);
        $referrer = User::query()
            ->whereKey($request->session()->pull('referrer_user_id'))
            ->where('role', 'investor')
            ->where('status', 'active')
            ->first();
        $user = DB::transaction(function () use ($validated, $referrer): User {
            $user = User::query()->create($validated);
            $user->forceFill([
                'referral_code' => $this->newReferralCode(),
                'referred_by_user_id' => $referrer?->id,
            ])->save();

            return $user;
        });

        if (! Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'investor',
            'status' => 'active',
        ])) {
            throw new \LogicException('Newly created investor credentials could not be authenticated.');
        }

        $request->session()->regenerate();

        return redirect()->route('account.dashboard');
    }

    private function ensureReferralCode(User $user): string
    {
        if ($user->referral_code !== null) {
            return $user->referral_code;
        }

        $code = $this->newReferralCode();
        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    private function newReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (User::query()->where('referral_code', $code)->exists());

        return $code;
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function authenticate(Request $request, string $portal): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $email = Str::lower($validated['email']);
        $throttleKey = Str::transliterate($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('site.auth.throttled', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $credentials = [
            'email' => $email,
            'password' => $validated['password'],
            'role' => $portal === 'admin' ? 'super_admin' : 'investor',
            'status' => 'active',
        ];

        if (! Auth::attempt($credentials, (bool) ($validated['remember'] ?? false))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('site.auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->route($portal === 'admin' ? 'admin.dashboard' : 'account.dashboard');
    }
}
