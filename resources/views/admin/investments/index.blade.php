@extends('layouts.admin')

@section('title', __('site.investments.admin_title'))
@section('admin_breadcrumb', __('site.investments.admin_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.investments.eyebrow') }}</span>
            <h1>{{ __('site.investments.admin_title') }}</h1>
            <p>{{ __('site.investments.admin_description') }}</p>
        </div>

        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif

        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.investments.review_notice_title') }}</h2><p>{{ __('site.investments.review_notice') }}</p></div>
        </div>

        <nav class="deposit-status-tabs" aria-label="{{ __('site.investments.filter') }}">
            @foreach (['pending', 'confirmed', 'rejected', 'all'] as $filter)
                <a class="@if ($status === $filter) is-active @endif" href="{{ route('admin.investments.index', ['status' => $filter]) }}">{{ __('site.investments.filters.'.$filter) }}@if ($filter === 'pending') <span>{{ $pendingInvestments }}</span>@endif</a>
            @endforeach
        </nav>

        <section class="portal-panel">
            @if ($investments->isEmpty())
                <p class="portal-empty">{{ __('site.investments.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.auth.project') }}</th><th>{{ __('site.auth.units') }}</th><th>{{ __('site.auth.principal_bdt') }}</th><th>{{ __('site.auth.paid_amount') }}</th><th>{{ __('site.auth.date') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.investments.review') }}</th></tr></thead>
                        <tbody>
                            @foreach ($investments as $investment)
                                <tr>
                                    <td><span class="table-primary">{{ $investment->investor_name }}</span><span class="table-secondary">{{ $investment->investor_email }}</span></td>
                                    <td>{{ app()->getLocale() === 'bn' && $investment->project_name_bn ? $investment->project_name_bn : $investment->project_name }}</td>
                                    <td>{{ number_format((int) $investment->unit_count) }}</td>
                                    <td>@money($investment->amount_bdt, 'BDT')</td>
                                    <td>@money($investment->paid_amount, $investment->payment_currency)</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($investment->created_at)->format('M j, Y H:i') }}</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($investment->status) }}">{{ __('site.auth.statuses.'.$investment->status) }}</span></td>
                                    <td>
                                        @if ($investment->status === 'pending')
                                            <form class="deposit-review-form" action="{{ route('admin.investments.review', $investment->id) }}" method="post">
                                                @csrf
                                                <label class="admin-form-field"><span>{{ __('site.deposits.review_note') }}</span><textarea name="review_note" rows="2" maxlength="2000"></textarea></label>
                                                @error('review_note')<small class="auth-error">{{ $message }}</small>@enderror
                                                <div class="deposit-review-actions">
                                                    <button class="button button-dark button-small" type="submit" name="decision" value="confirmed">{{ __('site.investments.confirm') }}</button>
                                                    <button class="button button-outline button-small" type="submit" name="decision" value="rejected">{{ __('site.deposits.reject') }}</button>
                                                </div>
                                            </form>
                                        @else
                                            <span>{{ __('site.investments.no_review_needed') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $investments->links() }}</div>
            @endif
        </section>
    </section>
@endsection
