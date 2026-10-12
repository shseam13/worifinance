@extends('layouts.app')

@section('title', __('site.auth.investor_dashboard'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading portal-heading-row">
            <div>
                <span class="section-eyebrow">{{ __('site.auth.investor_portal') }}</span>
                <h1>{{ __('site.auth.hello', ['name' => auth()->user()->name]) }}</h1>
                <p>{{ __('site.auth.account_ready') }}</p>
            </div>
            <div class="portal-heading-actions">
                <a class="button button-outline" href="{{ route('account.deposits.create') }}">{{ __('site.deposits.recharge') }} <span aria-hidden="true">+</span></a>
                <a class="button button-outline" href="{{ route('account.withdrawals.create') }}">{{ __('site.withdrawals.request_withdrawal') }} <span aria-hidden="true">→</span></a>
                <a class="button button-dark" href="{{ route('projects.index') }}">{{ __('site.auth.browse_projects') }} <span aria-hidden="true">↗</span></a>
            </div>
        </div>

        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif

        <div class="portal-stat-grid">
            <article class="portal-stat-card">
                <span>{{ __('site.auth.invested_principal') }}</span>
                <strong>@money($confirmedInvestment->amount_bdt ?? 0, 'BDT')</strong>
                <small>{{ number_format((int) ($confirmedInvestment->investment_count ?? 0)) }} {{ __('site.auth.confirmed_investments_count') }}</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.available_balance') }} · BDT</span>
                <strong>@money(($wallets->get('BDT')->balance ?? 0) - ($wallets->get('BDT')->reserved_balance ?? 0), 'BDT')</strong>
                <small>{{ __('site.auth.reserved') }}: @money($wallets->get('BDT')->reserved_balance ?? 0, 'BDT')</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.available_balance') }} · USDT</span>
                <strong>@money(($wallets->get('USDT')->balance ?? 0) - ($wallets->get('USDT')->reserved_balance ?? 0), 'USDT')</strong>
                <small>{{ __('site.auth.reserved') }}: @money($wallets->get('USDT')->reserved_balance ?? 0, 'USDT')</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.profit_paid') }}</span>
                <strong>@money($paidProfits->get('BDT')->total ?? 0, 'BDT')</strong>
                <small>@money($paidProfits->get('USDT')->total ?? 0, 'USDT')</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.referral_commission_paid') }}</span>
                <strong>@money($creditedCommissionBdt, 'BDT')</strong>
                <small>{{ __('site.auth.credited_commission_note') }}</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.pending_investments') }}</span>
                <strong>{{ number_format((int) ($pendingInvestment->investment_count ?? 0)) }}</strong>
                <small>@money($pendingInvestment->amount_bdt ?? 0, 'BDT') {{ __('site.auth.awaiting_confirmation') }}</small>
            </article>
        </div>

        <div class="portal-notice portal-notice-warning">
            <div>
                <h2>{{ __('site.auth.investor_tools_title') }}</h2>
                <p>{{ __('site.auth.investor_tools_notice') }}</p>
            </div>
        </div>

        <section class="portal-panel" id="referrals">
            <div class="portal-panel-heading">
                <div><span class="section-eyebrow">{{ __('site.auth.referral_program') }}</span><h2>{{ __('site.auth.invite_friends') }}</h2></div>
            </div>
            <p class="admin-form-hint">{{ __('site.auth.referral_explanation') }}</p>
            <label class="referral-link-field">
                <span>{{ __('site.auth.your_referral_link') }}</span>
                <input type="url" value="{{ $referralUrl }}" readonly aria-label="{{ __('site.auth.your_referral_link') }}">
            </label>
            <div class="portal-stat-grid referral-stat-grid">
                <article class="portal-stat-card"><span>{{ __('site.auth.referred_accounts') }}</span><strong>{{ number_format($referredCount) }}</strong><small>{{ __('site.auth.referral_accounts_help') }}</small></article>
                <article class="portal-stat-card"><span>{{ __('site.auth.referred_investments') }}</span><strong>@money($referralInvestmentTotal, 'BDT')</strong><small>{{ __('site.auth.confirmed_investments_count') }}</small></article>
                <article class="portal-stat-card"><span>{{ __('site.auth.referral_commission_paid') }}</span><strong>@money($referralCommissionTotals->get('credited')->total ?? 0, 'BDT')</strong><small>{{ __('site.auth.credited_commission_note') }}</small></article>
            </div>
            <div class="referral-commission-note">
                <span>{{ __('site.auth.pending_referral_commission') }}: @money($referralCommissionTotals->get('pending')->total ?? 0, 'BDT')</span>
                <p>{{ __('site.auth.referral_not_paid_notice') }}</p>
            </div>
            @if ($referredUsers->isNotEmpty())
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.auth.referred_investor') }}</th><th>{{ __('site.auth.date') }}</th><th>{{ __('site.auth.confirmed_investments') }}</th></tr></thead>
                        <tbody>
                            @foreach ($referredUsers as $referredUser)
                                <tr><td>{{ $referredUser->name }}</td><td>{{ \Illuminate\Support\Carbon::parse($referredUser->created_at)->format('M j, Y') }}</td><td>@money($referredUser->confirmed_bdt, 'BDT')</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="portal-empty">{{ __('site.auth.no_referrals') }}</p>
            @endif
        </section>

        <section class="portal-panel" id="my-investments">
            <div class="portal-panel-heading">
                <div>
                    <span class="section-eyebrow">{{ __('site.auth.portfolio') }}</span>
                    <h2>{{ __('site.auth.my_investments') }}</h2>
                </div>
                <a class="text-link" href="{{ route('projects.index') }}">{{ __('site.auth.browse_projects') }} <span aria-hidden="true">→</span></a>
            </div>
            @if ($investments->isEmpty())
                <p class="portal-empty">{{ __('site.auth.no_investments') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>{{ __('site.auth.project') }}</th>
                                <th>{{ __('site.auth.units') }}</th>
                                <th>{{ __('site.auth.principal_bdt') }}</th>
                                <th>{{ __('site.auth.paid_amount') }}</th>
                                <th>{{ __('site.auth.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($investments as $investment)
                                <tr>
                                    <td><a class="table-link" href="{{ route('projects.show', $investment->project_slug) }}">{{ app()->getLocale() === 'bn' && $investment->project_name_bn ? $investment->project_name_bn : $investment->project_name }}</a></td>
                                    <td>{{ number_format((int) $investment->unit_count) }}</td>
                                    <td>@money($investment->amount_bdt, 'BDT')</td>
                                    <td>@money($investment->paid_amount, $investment->payment_currency)</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($investment->status) }}">{{ __('site.auth.statuses.'.$investment->status) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="portal-two-column">
            <section class="portal-panel" id="funding-history">
                <div class="portal-panel-heading">
                    <div>
                        <span class="section-eyebrow">{{ __('site.auth.account_activity') }}</span>
                        <h2>{{ __('site.auth.deposit_requests') }}</h2>
                    </div>
                </div>
                @if ($depositRequests->isEmpty())
                    <p class="portal-empty">{{ __('site.auth.no_deposit_requests') }}</p>
                @else
                    <div class="portal-table-wrap">
                        <table class="portal-table">
                            <thead><tr><th>{{ __('site.auth.date') }}</th><th>{{ __('site.auth.amount') }}</th><th>{{ __('site.auth.reference') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.deposits.review_note') }}</th></tr></thead>
                            <tbody>
                                @foreach ($depositRequests as $request)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($request->created_at)->format('M j, Y') }}</td>
                                        <td>@money($request->amount, $request->currency)</td>
                                        <td>{{ $request->payment_reference }}</td>
                                        <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($request->status) }}">{{ __('site.auth.statuses.'.$request->status) }}</span></td>
                                        <td>{{ $request->review_note ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="portal-panel" id="withdrawal-history">
                <div class="portal-panel-heading">
                    <div>
                        <span class="section-eyebrow">{{ __('site.auth.account_activity') }}</span>
                        <h2>{{ __('site.auth.withdrawal_requests') }}</h2>
                    </div>
                </div>
                @if ($withdrawalRequests->isEmpty())
                    <p class="portal-empty">{{ __('site.auth.no_withdrawal_requests') }}</p>
                @else
                    <div class="portal-table-wrap">
                        <table class="portal-table">
                            <thead><tr><th>{{ __('site.auth.date') }}</th><th>{{ __('site.auth.amount') }}</th><th>{{ __('site.auth.destination') }}</th><th>{{ __('site.withdrawals.payout_reference') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.deposits.review_note') }}</th></tr></thead>
                            <tbody>
                                @foreach ($withdrawalRequests as $request)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($request->created_at)->format('M j, Y') }}</td>
                                        <td>@money($request->amount, $request->currency)</td>
                                        <td>{{ $request->destination }}@if ($request->network)<small class="table-secondary">{{ $request->network }}</small>@endif</td>
                                        <td>{{ $request->payout_reference ?: '—' }}</td>
                                        <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($request->status) }}">{{ __('site.auth.statuses.'.$request->status) }}</span></td>
                                        <td>{{ $request->review_note ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <section class="portal-panel" id="membership-charges">
                <div class="portal-panel-heading">
                    <div>
                        <span class="section-eyebrow">{{ __('site.auth.account_activity') }}</span>
                        <h2>{{ __('site.membership_charges.title') }}</h2>
                    </div>
                </div>
                @if ($membershipCharges->isEmpty())
                    <p class="portal-empty">{{ __('site.membership_charges.investor_empty') }}</p>
                @else
                    <div class="portal-table-wrap">
                        <table class="portal-table">
                            <thead><tr><th>{{ __('site.membership_charges.period') }}</th><th>{{ __('site.auth.amount') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.membership_charges.paid_at') }}</th></tr></thead>
                            <tbody>
                                @foreach ($membershipCharges as $charge)
                                    <tr>
                                        <td>{{ $charge->period_start }} — {{ $charge->period_end }}</td>
                                        <td>@money($charge->amount, $charge->currency)</td>
                                        <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($charge->status) }}">{{ __('site.membership_charges.statuses.'.$charge->status) }}</span></td>
                                        <td>{{ $charge->paid_at ? \Illuminate\Support\Carbon::parse($charge->paid_at)->format('M j, Y') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
        </section>

        <section class="portal-panel" id="profit-history">
            <div class="portal-panel-heading">
                <div>
                    <span class="section-eyebrow">{{ __('site.auth.account_activity') }}</span>
                    <h2>{{ __('site.auth.profit_history') }}</h2>
                </div>
            </div>
            @if ($profitHistory->isEmpty())
                <p class="portal-empty">{{ __('site.auth.no_profit_history') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.auth.project') }}</th><th>{{ __('site.auth.period') }}</th><th>{{ __('site.auth.amount') }}</th><th>{{ __('site.auth.status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($profitHistory as $profit)
                                <tr>
                                    <td>{{ app()->getLocale() === 'bn' && $profit->project_name_bn ? $profit->project_name_bn : $profit->project_name }}</td>
                                    <td>{{ $profit->period_start }} — {{ $profit->period_end }}</td>
                                    <td>@money($profit->amount, $profit->payout_currency)</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($profit->status) }}">{{ __('site.auth.statuses.'.$profit->status) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <form action="{{ route('logout') }}" method="post" class="portal-logout">
            @csrf
            <button class="button button-outline" type="submit">{{ __('site.auth.logout') }}</button>
        </form>
    </section>
@endsection
