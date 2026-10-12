<!doctype html>
<html lang="{{ app()->getLocale() }}" @if (app()->getLocale() === 'bn') class="lang-bn" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', __('site.meta_description'))">
    <meta name="theme-color" content="#f7faf7">
    <title>@yield('title', __('site.site_name')) | {{ __('site.site_name') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/worifinance.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">{{ __('site.skip_link') }}</a>
    <header class="site-header">
        <div class="header-shell">
            <a class="brand" href="{{ route('home') }}" aria-label="{{ __('site.site_name') }} — {{ __('site.nav.home') }}">
                <span class="brand-mark"><img src="{{ asset('images/worifinance.jpg') }}" alt="" width="44" height="44"></span>
                <span class="brand-copy">
                    <span class="brand-name">Wori<span>finance</span></span>
                    <span class="brand-caption">{{ __('site.tagline') }}</span>
                </span>
            </a>

            <nav class="desktop-nav" aria-label="{{ __('site.nav.menu') }}">
                <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}">{{ __('site.nav.home') }}</a>
                <a class="{{ request()->routeIs('projects.*') ? 'is-active' : '' }}" href="{{ route('projects.index') }}">{{ __('site.nav.projects') }}</a>
                <a href="{{ route('home') }}#how-it-works">{{ __('site.nav.how_it_works') }}</a>
                @auth
                    <a href="{{ auth()->user()->role === 'super_admin' ? route('admin.dashboard') : route('account.dashboard') }}">{{ __('site.auth.dashboard') }}</a>
                @endauth
            </nav>

            <div class="header-actions">
                <form class="language-switch" action="{{ route('language.update') }}" method="post" aria-label="{{ __('site.nav.language') }}">
                    @csrf
                    <button type="submit" name="locale" value="en" @if (app()->getLocale() === 'en') aria-current="true" @endif>EN</button>
                    <span aria-hidden="true">|</span>
                    <button type="submit" name="locale" value="bn" lang="bn" @if (app()->getLocale() === 'bn') aria-current="true" @endif>বাংলা</button>
                </form>
                @auth
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button class="button button-small button-dark header-cta" type="submit">{{ __('site.auth.logout') }}</button>
                    </form>
                @else
                    <a class="button button-small button-outline header-cta" href="{{ route('login') }}">{{ __('site.auth.login') }}</a>
                    <a class="button button-small button-dark header-cta" href="{{ route('register') }}">
                        {{ __('site.auth.register') }}
                        <span aria-hidden="true">↗</span>
                    </a>
                @endauth
                <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="{{ __('site.nav.menu') }}">
                    <span></span><span></span>
                </button>
            </div>
        </div>
        <nav class="mobile-menu" id="mobile-menu" hidden aria-label="{{ __('site.nav.menu') }}">
            <a href="{{ route('home') }}">{{ __('site.nav.home') }}</a>
            <a href="{{ route('projects.index') }}">{{ __('site.nav.projects') }}</a>
            <a href="{{ route('home') }}#how-it-works">{{ __('site.nav.how_it_works') }}</a>
            @guest
                <a href="{{ route('login') }}">{{ __('site.auth.login') }}</a>
                <a href="{{ route('register') }}">{{ __('site.auth.register') }}</a>
            @else
                <a href="{{ auth()->user()->role === 'super_admin' ? route('admin.dashboard') : route('account.dashboard') }}">{{ __('site.auth.dashboard') }}</a>
            @endguest
        </nav>
    </header>

    <main id="main-content">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="footer-main">
            <div class="footer-brand-column">
                <a class="brand footer-brand" href="{{ route('home') }}">
                    <span class="brand-mark"><img src="{{ asset('images/worifinance.jpg') }}" alt="" width="44" height="44"></span>
                    <span class="brand-copy">
                        <span class="brand-name">Wori<span>finance</span></span>
                        <span class="brand-caption">{{ __('site.tagline') }}</span>
                    </span>
                </a>
                <p>{{ __('site.footer.description') }}</p>
            </div>
            <div class="footer-link-group">
                <h2>{{ __('site.footer.company') }}</h2>
                <a href="{{ route('home') }}#how-it-works">{{ __('site.nav.how_it_works') }}</a>
                <a href="{{ route('home') }}#clarity">{{ __('site.clarity.eyebrow') }}</a>
            </div>
            <div class="footer-link-group">
                <h2>{{ __('site.footer.explore') }}</h2>
                <a href="{{ route('projects.index') }}">{{ __('site.nav.projects') }}</a>
                <a href="{{ route('home') }}#featured-projects">{{ __('site.actions.all_projects') }}</a>
            </div>
            <div class="footer-language">
                <span>{{ __('site.nav.language') }}</span>
                <form class="language-switch" action="{{ route('language.update') }}" method="post">
                    @csrf
                    <button type="submit" name="locale" value="en" @if (app()->getLocale() === 'en') aria-current="true" @endif>EN</button>
                    <span aria-hidden="true">|</span>
                    <button type="submit" name="locale" value="bn" lang="bn" @if (app()->getLocale() === 'bn') aria-current="true" @endif>বাংলা</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom">
            <p class="footer-disclaimer">{{ __('site.footer.disclaimer') }}</p>
            <p>{{ __('site.footer.copyright', ['year' => now()->year]) }}</p>
        </div>
    </footer>

    <nav class="mobile-bottom-nav" aria-label="{{ __('site.nav.menu') }}">
        <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
            <span>{{ __('site.nav.home') }}</span>
        </a>
        <a class="{{ request()->routeIs('projects.*') ? 'is-active' : '' }}" href="{{ route('projects.index') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 20V8l9-5 9 5v12M3 11h18M8 20v-5h8v5M8 8h.01M12 8h.01M16 8h.01"/></svg>
            <span>{{ __('site.nav.projects') }}</span>
        </a>
        @auth
            <a href="{{ auth()->user()->role === 'super_admin' ? route('admin.dashboard') : route('account.dashboard') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                <span>{{ __('site.auth.dashboard') }}</span>
            </a>
        @else
            <a href="{{ route('login') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/></svg>
                <span>{{ __('site.auth.login') }}</span>
            </a>
        @endauth
        <form action="{{ route('language.update') }}" method="post">
            @csrf
            <button type="submit" name="locale" value="{{ app()->getLocale() === 'en' ? 'bn' : 'en' }}" aria-label="{{ __('site.nav.language') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18m0-18a15 15 0 0 0 0 18"/></svg>
                <span>{{ app()->getLocale() === 'en' ? 'বাংলা' : 'EN' }}</span>
            </button>
        </form>
    </nav>
</body>
</html>
