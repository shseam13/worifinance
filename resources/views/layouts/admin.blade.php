<!doctype html>
<html lang="{{ app()->getLocale() }}" @if (app()->getLocale() === 'bn') class="lang-bn" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f6f8f7">
    <title>@yield('title', __('site.auth.admin_dashboard')) | {{ __('site.site_name') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/worifinance.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
    <button class="admin-sidebar-backdrop" type="button" aria-label="{{ __('site.auth.close_admin_menu') }}" hidden></button>
    <aside class="admin-sidebar" id="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <span class="admin-brand-mark"><img src="{{ asset('images/worifinance.jpg') }}" alt="" width="38" height="38"></span>
            <span class="admin-brand-copy">
                <strong>Wori<span>finance</span></strong>
                <small>{{ __('site.auth.admin_portal') }}</small>
            </span>
        </a>

        <nav class="admin-navigation" aria-label="{{ __('site.auth.admin_navigation') }}">
            <a class="admin-nav-link @if (request()->routeIs('admin.dashboard')) is-active @endif" href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span>{{ __('site.auth.admin_dashboard') }}</span>
            </a>

            <div class="admin-nav-group">
                <h2>{{ __('site.auth.nav_investors') }}</h2>
                <a class="admin-nav-link @if (request()->routeIs('admin.investors.*')) is-active @endif" href="{{ route('admin.investors.index') }}" @if (request()->routeIs('admin.investors.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0m1-17a4 4 0 0 1 0 8m1 3a6 6 0 0 1 4 6"/></svg>
                    <span>{{ __('site.auth.nav_investor_accounts') }}</span>
                </a>
                <a class="admin-nav-link @if (request()->routeIs('admin.referrals.*')) is-active @endif" href="{{ route('admin.referrals.index') }}" @if (request()->routeIs('admin.referrals.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v20m5-15H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>{{ __('site.auth.nav_referrals') }}</span>
                </a>
            </div>

            <div class="admin-nav-group">
                <h2>{{ __('site.auth.nav_projects') }}</h2>
                <a class="admin-nav-link @if (request()->routeIs('admin.projects.*')) is-active @endif" href="{{ route('admin.projects.index') }}" @if (request()->routeIs('admin.projects.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21V8l9-5 9 5v13M3 11h18M8 21v-6h8v6"/></svg>
                    <span>{{ __('site.auth.nav_project_catalogue') }}</span>
                </a>
                <a class="admin-nav-link @if (request()->routeIs('admin.investments.*')) is-active @endif" href="{{ route('admin.investments.index') }}" @if (request()->routeIs('admin.investments.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span>{{ __('site.auth.nav_investments') }}</span>
                </a>
            </div>

            <div class="admin-nav-group">
                <h2>{{ __('site.auth.nav_finance') }}</h2>
                <a class="admin-nav-link @if (request()->routeIs('admin.deposits.*')) is-active @endif" href="{{ route('admin.deposits.index') }}" @if (request()->routeIs('admin.deposits.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18m7-14H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H5"/></svg>
                    <span>{{ __('site.auth.nav_deposits') }}</span>
                    @if ($pendingDeposits > 0)<small class="admin-nav-count">{{ $pendingDeposits }}</small>@endif
                </a>
                <a class="admin-nav-link @if (request()->routeIs('admin.withdrawals.*')) is-active @endif" href="{{ route('admin.withdrawals.index') }}" @if (request()->routeIs('admin.withdrawals.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18m7-14H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H5"/></svg>
                    <span>{{ __('site.auth.nav_withdrawals') }}</span>
                    @if ($pendingWithdrawals > 0)<small class="admin-nav-count">{{ $pendingWithdrawals }}</small>@endif
                </a>
                <a class="admin-nav-link @if (request()->routeIs('admin.profit-distributions.*')) is-active @endif" href="{{ route('admin.profit-distributions.index') }}" @if (request()->routeIs('admin.profit-distributions.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <span>{{ __('site.auth.nav_profit_distribution') }}</span>
                </a>
                <a class="admin-nav-link @if (request()->routeIs('admin.membership-charges.*')) is-active @endif" href="{{ route('admin.membership-charges.index') }}" @if (request()->routeIs('admin.membership-charges.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18m-12 5h6"/></svg>
                    <span>{{ __('site.membership_charges.admin_title') }}</span>
                </a>
            </div>

            <div class="admin-nav-group">
                <h2>{{ __('site.auth.nav_configuration') }}</h2>
                <a class="admin-nav-link @if (request()->routeIs('admin.settings.*')) is-active @endif" href="{{ route('admin.settings.edit') }}" @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.7-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.5-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.5.9l1.7-.7 1.4 2.4-1.4 1.1a7 7 0 0 1 0 1.7z"/></svg>
                    <span>{{ __('site.auth.nav_platform_settings') }}</span>
                </a>
                <a class="admin-nav-link" href="{{ route('admin.settings.edit') }}#payment-instructions">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-13 5h4"/></svg>
                    <span>{{ __('site.auth.nav_payment_details') }}</span>
                </a>
                <a class="admin-nav-link @if (request()->routeIs('admin.password.*')) is-active @endif" href="{{ route('admin.password.edit') }}" @if (request()->routeIs('admin.password.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 5v2"/></svg>
                    <span>{{ __('site.auth.nav_update_password') }}</span>
                </a>
                <span class="admin-nav-link is-disabled" aria-disabled="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h16M8 15l3-4 3 2 5-7"/></svg>
                    <span>{{ __('site.auth.nav_audit_log') }}</span>
                    <small>{{ __('site.auth.coming_soon') }}</small>
                </span>
            </div>
        </nav>

        <div class="admin-sidebar-bottom">
            <a class="admin-sidebar-link" href="{{ route('home') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                <span>{{ __('site.auth.view_public_site') }}</span>
            </a>
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button class="admin-sidebar-link" type="submit">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/></svg>
                    <span>{{ __('site.auth.logout') }}</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-workspace">
        <header class="admin-topbar">
            <div class="admin-topbar-leading">
                <button class="admin-sidebar-toggle" type="button" aria-expanded="false" aria-controls="admin-sidebar" aria-label="{{ __('site.auth.open_admin_menu') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="admin-breadcrumb"><span>{{ __('site.auth.admin_portal') }}</span><span aria-hidden="true">/</span><strong>@yield('admin_breadcrumb', __('site.auth.admin_dashboard'))</strong></div>
            </div>
            <div class="admin-topbar-actions">
                <form class="admin-language-switch" action="{{ route('language.update') }}" method="post" aria-label="{{ __('site.nav.language') }}">
                    @csrf
                    <button type="submit" name="locale" value="en" @if (app()->getLocale() === 'en') aria-current="true" @endif>EN</button>
                    <span aria-hidden="true">|</span>
                    <button type="submit" name="locale" value="bn" lang="bn" @if (app()->getLocale() === 'bn') aria-current="true" @endif>বাংলা</button>
                </form>
                <div class="admin-user">
                    <span class="admin-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="admin-user-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ __('site.auth.admin_role_label') }}</small></span>
                </div>
            </div>
        </header>

        <main class="admin-main" id="main-content">
            @yield('content')
        </main>
    </div>
</body>
</html>
