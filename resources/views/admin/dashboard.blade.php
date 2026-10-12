@extends('layouts.admin')

@section('title', __('site.auth.admin_dashboard'))
@section('admin_breadcrumb', __('site.auth.admin_dashboard'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading portal-heading-row">
            <div>
                <span class="section-eyebrow">{{ __('site.auth.dashboard_overview') }}</span>
                <h1>{{ __('site.auth.admin_dashboard') }}</h1>
                <p>{{ __('site.auth.admin_welcome', ['name' => auth()->user()->name]) }} · {{ now()->format('M j, Y') }}</p>
            </div>
        </div>

        <div class="portal-stat-grid admin-stat-grid" id="financial-summary">
            <article class="portal-stat-card">
                <span>{{ __('site.auth.total_investors') }}</span>
                <strong>{{ number_format($totalInvestors) }}</strong>
                <small>{{ number_format($activeInvestors) }} {{ __('site.auth.active') }} · {{ number_format($suspendedInvestors) }} {{ __('site.auth.suspended') }}</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.published_projects') }}</span>
                <strong>{{ number_format($publishedProjects) }}</strong>
                <small>{{ __('site.auth.currently_published') }}</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.confirmed_investments') }}</span>
                <strong>@money($confirmedInvestmentTotal, 'BDT')</strong>
                <small>{{ number_format($confirmedInvestmentCount) }} {{ __('site.auth.confirmed_investments_count') }}</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.payable_profit') }} · BDT</span>
                <strong>@money($payableProfits->get('BDT')->total ?? 0, 'BDT')</strong>
                <small>{{ __('site.auth.approved_awaiting_posting') }}</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.payable_profit') }} · USDT</span>
                <strong>@money($payableProfits->get('USDT')->total ?? 0, 'USDT')</strong>
                <small>{{ __('site.auth.approved_awaiting_posting') }}</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.profit_paid') }} · BDT</span>
                <strong>@money($paidProfits->get('BDT')->total ?? 0, 'BDT')</strong>
                <small>@money($paidProfits->get('USDT')->total ?? 0, 'USDT') {{ __('site.auth.profit_paid') }}</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.commission_payable') }}</span>
                <strong>@money($commissionTotals->get('pending')->total ?? 0, 'BDT')</strong>
                <small>{{ __('site.auth.credited') }}: @money($commissionTotals->get('credited')->total ?? 0, 'BDT')</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.wallet_balances') }} · BDT</span>
                <strong>@money($walletTotals->get('BDT')->balance ?? 0, 'BDT')</strong>
                <small>{{ __('site.auth.reserved') }}: @money($walletTotals->get('BDT')->reserved_balance ?? 0, 'BDT')</small>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.auth.wallet_balances') }} · USDT</span>
                <strong>@money($walletTotals->get('USDT')->balance ?? 0, 'USDT')</strong>
                <small>{{ __('site.auth.reserved') }}: @money($walletTotals->get('USDT')->reserved_balance ?? 0, 'USDT')</small>
            </article>
        </div>

        <div class="portal-notice portal-notice-warning">
            <div>
                <h2>{{ __('site.auth.admin_tools_title') }}</h2>
                <p>{{ __('site.auth.admin_tools_notice') }}</p>
            </div>
            <a class="button button-dark button-small" href="{{ route('admin.projects.index') }}">{{ __('site.auth.nav_project_catalogue') }} <span aria-hidden="true">→</span></a>
        </div>

        <section class="portal-panel" id="review-queues">
            <div class="portal-panel-heading">
                <div>
                    <span class="section-eyebrow">{{ __('site.auth.needs_attention') }}</span>
                    <h2>{{ __('site.auth.review_queues') }}</h2>
                </div>
            </div>
            <div class="queue-count-grid">
                <article><span>{{ __('site.auth.pending_deposits') }}</span><strong>{{ number_format($pendingDeposits) }}</strong></article>
                <article><span>{{ __('site.auth.pending_withdrawals') }}</span><strong>{{ number_format($pendingWithdrawals) }}</strong><a class="text-link" href="{{ route('admin.withdrawals.index') }}">{{ __('site.admin_referrals.review') }} →</a></article>
                <article><span>{{ __('site.membership_charges.due_count') }}</span><strong>{{ number_format($dueMembershipCharges) }}</strong><a class="text-link" href="{{ route('admin.membership-charges.index') }}">{{ __('site.admin_referrals.review') }} →</a></article>
                <article><span>{{ __('site.auth.pending_investments') }}</span><strong>{{ number_format($pendingInvestments) }}</strong></article>
                <article><span>{{ __('site.auth.pending_profit_distributions') }}</span><strong>{{ number_format($pendingProfitDistributions) }}</strong></article>
                <article><span>{{ __('site.admin_referrals.pending_commissions') }}</span><strong>{{ number_format($pendingReferralCommissions) }}</strong><a class="text-link" href="{{ route('admin.referrals.index') }}">{{ __('site.admin_referrals.review') }} →</a></article>
            </div>
        </section>

        <div class="portal-two-column">
            <section class="portal-panel">
                <div class="portal-panel-heading">
                    <div>
                        <span class="section-eyebrow">{{ __('site.auth.latest_activity') }}</span>
                        <h2>{{ __('site.auth.deposit_requests') }}</h2>
                    </div>
                </div>
                @if ($recentDeposits->isEmpty())
                    <p class="portal-empty">{{ __('site.auth.no_admin_deposits') }}</p>
                @else
                    <div class="portal-table-wrap">
                        <table class="portal-table">
                            <thead><tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.auth.amount') }}</th><th>{{ __('site.auth.reference') }}</th><th>{{ __('site.auth.status') }}</th></tr></thead>
                            <tbody>
                                @foreach ($recentDeposits as $deposit)
                                    <tr>
                                        <td><span class="table-primary">{{ $deposit->investor_name }}</span><span class="table-secondary">{{ $deposit->investor_email }}</span></td>
                                        <td>@money($deposit->amount, $deposit->currency)</td>
                                        <td>{{ $deposit->payment_reference }}</td>
                                        <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($deposit->status) }}">{{ __('site.auth.statuses.'.$deposit->status) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="portal-panel">
                <div class="portal-panel-heading">
                    <div>
                        <span class="section-eyebrow">{{ __('site.auth.latest_activity') }}</span>
                        <h2>{{ __('site.auth.withdrawal_requests') }}</h2>
                    </div>
                </div>
                @if ($recentWithdrawals->isEmpty())
                    <p class="portal-empty">{{ __('site.auth.no_admin_withdrawals') }}</p>
                @else
                    <div class="portal-table-wrap">
                        <table class="portal-table">
                            <thead><tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.auth.amount') }}</th><th>{{ __('site.auth.destination') }}</th><th>{{ __('site.auth.status') }}</th></tr></thead>
                            <tbody>
                                @foreach ($recentWithdrawals as $withdrawal)
                                    <tr>
                                        <td><span class="table-primary">{{ $withdrawal->investor_name }}</span><span class="table-secondary">{{ $withdrawal->investor_email }}</span></td>
                                        <td>@money($withdrawal->amount, $withdrawal->currency)</td>
                                        <td>{{ $withdrawal->destination }}</td>
                                        <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($withdrawal->status) }}">{{ __('site.auth.statuses.'.$withdrawal->status) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <section class="portal-panel" id="recent-investments">
            <div class="portal-panel-heading">
                <div>
                    <span class="section-eyebrow">{{ __('site.auth.latest_activity') }}</span>
                    <h2>{{ __('site.auth.recent_investments') }}</h2>
                </div>
            </div>
            @if ($recentInvestments->isEmpty())
                <p class="portal-empty">{{ __('site.auth.no_admin_investments') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.auth.project') }}</th><th>{{ __('site.auth.units') }}</th><th>{{ __('site.auth.principal_bdt') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.auth.date') }}</th></tr></thead>
                        <tbody>
                            @foreach ($recentInvestments as $investment)
                                <tr>
                                    <td>{{ $investment->investor_name }}</td>
                                    <td>{{ app()->getLocale() === 'bn' && $investment->project_name_bn ? $investment->project_name_bn : $investment->project_name }}</td>
                                    <td>{{ number_format((int) $investment->unit_count) }}</td>
                                    <td>@money($investment->amount_bdt, 'BDT')</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($investment->status) }}">{{ __('site.auth.statuses.'.$investment->status) }}</span></td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($investment->created_at)->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="portal-two-column">
            <section class="portal-panel">
                <div class="portal-panel-heading">
                    <div>
                        <span class="section-eyebrow">{{ __('site.auth.project_overview') }}</span>
                        <h2>{{ __('site.auth.top_projects') }}</h2>
                    </div>
                </div>
                @if ($topProjects->isEmpty())
                    <p class="portal-empty">{{ __('site.auth.no_confirmed_project_investments') }}</p>
                @else
                    <div class="project-summary-list">
                        @foreach ($topProjects as $project)
                            <div class="project-summary-row">
                                <span>{{ app()->getLocale() === 'bn' && $project->project_name_bn ? $project->project_name_bn : $project->project_name }}<small>{{ number_format((int) $project->investment_count) }} {{ __('site.auth.confirmed_investments_count') }}</small></span>
                                <strong>@money($project->invested_bdt, 'BDT')</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="portal-panel">
                <div class="portal-panel-heading">
                    <div>
                        <span class="section-eyebrow">{{ __('site.auth.platform_status') }}</span>
                        <h2>{{ __('site.auth.admin_tools_title') }}</h2>
                    </div>
                </div>
                <p class="portal-empty">{{ __('site.auth.admin_read_only_notice') }}</p>
                <a class="text-link" href="{{ route('projects.index') }}">{{ __('site.actions.all_projects') }} <span aria-hidden="true">→</span></a>
            </section>
        </div>

        <form action="{{ route('logout') }}" method="post" class="portal-logout">
            @csrf
            <button class="button button-outline" type="submit">{{ __('site.auth.logout') }}</button>
        </form>
    </section>
@endsection
