@extends('layouts.admin')

@section('title', __('site.membership_charges.admin_title'))
@section('admin_breadcrumb', __('site.membership_charges.admin_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.membership_charges.eyebrow') }}</span>
            <h1>{{ __('site.membership_charges.admin_title') }}</h1>
            <p>{{ __('site.membership_charges.admin_description') }}</p>
        </div>
        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.membership_charges.retry_title') }}</h2><p>{{ __('site.membership_charges.retry_notice') }}</p></div>
        </div>
        <nav class="deposit-status-tabs" aria-label="{{ __('site.membership_charges.filter') }}">
            @foreach (['due', 'paid', 'all'] as $filter)
                <a class="@if ($status === $filter) is-active @endif" href="{{ route('admin.membership-charges.index', ['status' => $filter]) }}">{{ __('site.membership_charges.filters.'.$filter) }}@if ($filter === 'due') <span>{{ $dueMembershipCharges }}</span>@endif</a>
            @endforeach
        </nav>
        <section class="portal-panel">
            @if ($charges->isEmpty())
                <p class="portal-empty">{{ __('site.membership_charges.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead>
                            <tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.auth.amount') }}</th><th>{{ __('site.membership_charges.period') }}</th><th>{{ __('site.membership_charges.setting_version') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.membership_charges.paid_at') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($charges as $charge)
                                <tr>
                                    <td><span class="table-primary">{{ $charge->investor_name }}</span><span class="table-secondary">{{ $charge->investor_email }}</span></td>
                                    <td>@money($charge->amount, $charge->currency)</td>
                                    <td>{{ $charge->period_start }} — {{ $charge->period_end }}</td>
                                    <td>{{ $charge->setting_version }}</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($charge->status) }}">{{ __('site.membership_charges.statuses.'.$charge->status) }}</span></td>
                                    <td>{{ $charge->paid_at ? \Illuminate\Support\Carbon::parse($charge->paid_at)->format('M j, Y H:i') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $charges->links() }}</div>
            @endif
        </section>
    </section>
@endsection
