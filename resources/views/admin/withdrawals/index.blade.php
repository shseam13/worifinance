@extends('layouts.admin')

@section('title', __('site.withdrawals.admin_title'))
@section('admin_breadcrumb', __('site.withdrawals.admin_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.withdrawals.eyebrow') }}</span>
            <h1>{{ __('site.withdrawals.admin_title') }}</h1>
            <p>{{ __('site.withdrawals.admin_description') }}</p>
        </div>

        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
        @endif

        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.withdrawals.manual_review_title') }}</h2><p>{{ __('site.withdrawals.admin_manual_review_notice') }}</p></div>
        </div>

        <nav class="deposit-status-tabs" aria-label="{{ __('site.withdrawals.filter') }}">
            @foreach (['pending', 'paid', 'rejected', 'all'] as $filter)
                <a class="@if ($status === $filter) is-active @endif" href="{{ route('admin.withdrawals.index', ['status' => $filter]) }}">{{ __('site.withdrawals.filters.'.$filter) }}@if ($filter === 'pending') <span>{{ $pendingWithdrawals }}</span>@endif</a>
            @endforeach
        </nav>

        <section class="portal-panel">
            @if ($withdrawals->isEmpty())
                <p class="portal-empty">{{ __('site.withdrawals.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.withdrawals.amount') }}</th><th>{{ __('site.withdrawals.destination') }}</th><th>{{ __('site.withdrawals.network') }}</th><th>{{ __('site.withdrawals.payout_reference') }}</th><th>{{ __('site.auth.date') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.withdrawals.review') }}</th></tr></thead>
                        <tbody>
                            @foreach ($withdrawals as $withdrawal)
                                <tr>
                                    <td><span class="table-primary">{{ $withdrawal->investor_name }}</span><span class="table-secondary">{{ $withdrawal->investor_email }}</span></td>
                                    <td>
                                        {{ number_format((float) $withdrawal->amount, $withdrawal->currency === 'BDT' ? 2 : 8) }} {{ $withdrawal->currency }}
                                        <small class="table-secondary">
                                            {{ __('site.withdrawals.minimum_snapshot') }}: {{ number_format((float) $withdrawal->minimum_amount_snapshot, $withdrawal->currency === 'BDT' ? 2 : 8) }} {{ $withdrawal->currency }}
                                            @if ($withdrawal->bdt_per_usdt_snapshot)
                                                <br>{{ __('site.withdrawals.rate_snapshot') }}: 1 USDT = {{ number_format((float) $withdrawal->bdt_per_usdt_snapshot, 8) }} BDT
                                            @endif
                                        </small>
                                    </td>
                                    <td>{{ $withdrawal->destination }}</td>
                                    <td>{{ $withdrawal->network ?: '—' }}</td>
                                    <td>{{ $withdrawal->payout_reference ?: '—' }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($withdrawal->created_at)->format('M j, Y H:i') }}</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($withdrawal->status) }}">{{ __('site.auth.statuses.'.$withdrawal->status) }}</span></td>
                                    <td>
                                        @if ($withdrawal->status === 'pending')
                                            <form class="deposit-review-form" action="{{ route('admin.withdrawals.review', $withdrawal->id) }}" method="post">
                                                @csrf
                                                <label class="admin-form-field"><span>{{ __('site.withdrawals.payout_reference') }}</span><input name="payout_reference" maxlength="150"></label>
                                                <label class="admin-form-field"><span>{{ __('site.deposits.review_note') }}</span><textarea name="review_note" rows="2" maxlength="2000"></textarea></label>
                                                <div class="deposit-review-actions">
                                                    <button class="button button-dark button-small" type="submit" name="decision" value="paid">{{ __('site.withdrawals.mark_paid') }}</button>
                                                    <button class="button button-outline button-small" type="submit" name="decision" value="rejected">{{ __('site.deposits.reject') }}</button>
                                                </div>
                                            </form>
                                        @else
                                            <span>{{ $withdrawal->review_note ?: '—' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $withdrawals->links() }}</div>
            @endif
        </section>
    </section>
@endsection
