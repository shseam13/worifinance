@extends('layouts.admin')

@section('title', __('site.admin_referrals.title'))
@section('admin_breadcrumb', __('site.admin_referrals.title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.admin_referrals.eyebrow') }}</span>
            <h1>{{ __('site.admin_referrals.title') }}</h1>
            <p>{{ __('site.admin_referrals.description') }}</p>
        </div>
        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
        @endif
        <div class="portal-stat-grid admin-stat-grid">
            <article class="portal-stat-card"><span>{{ __('site.admin_referrals.referrers') }}</span><strong>{{ number_format($totalReferrers) }}</strong><small>{{ __('site.admin_referrals.referrers_help') }}</small></article>
            <article class="portal-stat-card"><span>{{ __('site.admin_referrals.referred_accounts') }}</span><strong>{{ number_format($totalReferred) }}</strong><small>{{ __('site.admin_referrals.referred_help') }}</small></article>
            <article class="portal-stat-card"><span>{{ __('site.admin_referrals.pending_commissions') }}</span><strong>@money($pendingCommissions, 'BDT')</strong><small>{{ __('site.admin_referrals.pending_help') }}</small></article>
            <article class="portal-stat-card"><span>{{ __('site.admin_referrals.credited_commissions') }}</span><strong>@money($creditedCommissions, 'BDT')</strong><small>{{ __('site.admin_referrals.credited_help') }}</small></article>
            <article class="portal-stat-card"><span>{{ __('site.admin_referrals.current_rate') }}</span><strong>{{ number_format((float) $currentRate, 4) }}%</strong><small>{{ __('site.admin_referrals.rate_help') }}</small></article>
        </div>
        <div class="portal-notice">
            <div><h2>{{ __('site.admin_referrals.no_payout_title') }}</h2><p>{{ __('site.admin_referrals.no_payout_notice') }}</p></div>
        </div>
        <section class="portal-panel">
            <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.admin_referrals.review') }}</span><h2>{{ __('site.admin_referrals.pending_queue') }}</h2></div></div>
            @if ($pendingCommissionItems->isEmpty())
                <p class="portal-empty">{{ __('site.admin_referrals.no_pending') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.admin_referrals.referrer') }}</th><th>{{ __('site.admin_referrals.referred_investor') }}</th><th>{{ __('site.auth.project') }}</th><th>{{ __('site.admin_referrals.basis') }}</th><th>{{ __('site.admin_referrals.rate_snapshot') }}</th><th>{{ __('site.admin_referrals.commission_amount') }}</th><th>{{ __('site.auth.date') }}</th><th>{{ __('site.admin_referrals.review') }}</th></tr></thead>
                        <tbody>
                            @foreach ($pendingCommissionItems as $commission)
                                <tr>
                                    <td><span class="table-primary">{{ $commission->referrer_name }}</span><span class="table-secondary">{{ $commission->referrer_email }}</span></td>
                                    <td><span class="table-primary">{{ $commission->referred_name }}</span><span class="table-secondary">{{ $commission->referred_email }}</span></td>
                                    <td>{{ app()->getLocale() === 'bn' && $commission->project_name_bn ? $commission->project_name_bn : $commission->project_name }}</td>
                                    <td>@money($commission->basis_amount_bdt, 'BDT')</td>
                                    <td>{{ number_format((float) $commission->commission_rate_snapshot, 4) }}%</td>
                                    <td>@money($commission->commission_amount_bdt, 'BDT')</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($commission->created_at)->format('M j, Y H:i') }}</td>
                                    <td>
                                        <form action="{{ route('admin.referrals.credit', $commission->id) }}" method="post">
                                            @csrf
                                            <input type="hidden" name="decision" value="credit">
                                            <button class="button button-dark button-small" type="submit">{{ __('site.admin_referrals.credit') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $pendingCommissionItems->links() }}</div>
            @endif
        </section>
        <section class="portal-panel">
            <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.admin_referrals.report') }}</span><h2>{{ __('site.admin_referrals.top_referrers') }}</h2></div></div>
            @if ($referrers->isEmpty())
                <p class="portal-empty">{{ __('site.admin_referrals.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.admin_referrals.referral_code') }}</th><th>{{ __('site.admin_referrals.referred_accounts') }}</th><th>{{ __('site.admin_referrals.confirmed_investments') }}</th><th>{{ __('site.admin_referrals.pending_commissions') }}</th><th>{{ __('site.admin_referrals.credited_commissions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($referrers as $referrer)
                                <tr>
                                    <td><span class="table-primary">{{ $referrer->name }}</span><span class="table-secondary">{{ $referrer->email }}</span></td>
                                    <td>{{ $referrer->referral_code ?? '—' }}</td>
                                    <td>{{ number_format((int) $referrer->referred_count) }}</td>
                                    <td>@money($referrer->confirmed_bdt, 'BDT')</td>
                                    <td>@money($referrer->pending_bdt, 'BDT')</td>
                                    <td>@money($referrer->credited_bdt, 'BDT')</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $referrers->links() }}</div>
            @endif
        </section>
    </section>
@endsection
