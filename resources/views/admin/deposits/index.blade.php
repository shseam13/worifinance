@extends('layouts.admin')

@section('title', __('site.deposits.admin_title'))
@section('admin_breadcrumb', __('site.deposits.admin_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading portal-heading-row">
            <div>
                <span class="section-eyebrow">{{ __('site.deposits.eyebrow') }}</span>
                <h1>{{ __('site.deposits.admin_title') }}</h1>
                <p>{{ __('site.deposits.admin_description') }}</p>
            </div>
            <a class="button button-outline" href="{{ route('admin.settings.edit') }}#payment-instructions">{{ __('site.deposits.configure_instructions') }}</a>
        </div>

        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif

        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.deposits.manual_review_title') }}</h2><p>{{ __('site.deposits.manual_review_notice') }}</p></div>
        </div>

        <nav class="deposit-status-tabs" aria-label="{{ __('site.deposits.filter_requests') }}">
            @foreach (['pending', 'approved', 'rejected', 'all'] as $filter)
                <a class="@if ($status === $filter) is-active @endif" href="{{ route('admin.deposits.index', ['status' => $filter]) }}">{{ __('site.deposits.filters.'.$filter) }}@if ($filter === 'pending') <span>{{ $pendingDeposits }}</span>@endif</a>
            @endforeach
        </nav>

        <section class="portal-panel">
            @if ($deposits->isEmpty())
                <p class="portal-empty">{{ __('site.deposits.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.deposits.amount') }}</th><th>{{ __('site.deposits.payment_reference') }}</th><th>{{ __('site.auth.date') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.deposits.review') }}</th></tr></thead>
                        <tbody>
                            @foreach ($deposits as $deposit)
                                <tr>
                                    <td><span class="table-primary">{{ $deposit->investor_name }}</span><span class="table-secondary">{{ $deposit->investor_email }}</span></td>
                                    <td>@money($deposit->amount, $deposit->currency)<small class="table-secondary">{{ $deposit->currency }} {{ __('site.deposits.source_currency') }}</small></td>
                                    <td>{{ $deposit->payment_reference }}
                                        @if ($deposit->payment_instructions_snapshot)
                                            <details class="deposit-instruction-snapshot">
                                                <summary>{{ __('site.deposits.instructions_used') }} · v{{ $deposit->payment_instructions_version }}</summary>
                                                <p class="deposit-instructions">{!! nl2br(e($deposit->payment_instructions_snapshot)) !!}</p>
                                                @if ($deposit->payment_qr_path_snapshot)
                                                    <figure class="payment-qr payment-qr-history">
                                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($deposit->payment_qr_path_snapshot) }}" alt="{{ __('site.deposits.usdt_qr_alt') }}">
                                                        <figcaption>{{ __('site.deposits.qr_used') }}</figcaption>
                                                    </figure>
                                                @endif
                                            </details>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($deposit->created_at)->format('M j, Y H:i') }}</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($deposit->status) }}">{{ __('site.auth.statuses.'.$deposit->status) }}</span></td>
                                    <td>
                                        @if ($deposit->status === 'pending')
                                            <form class="deposit-review-form" action="{{ route('admin.deposits.review', $deposit->id) }}" method="post">
                                                @csrf
                                                <label class="admin-form-field">
                                                    <span>{{ __('site.deposits.review_note') }}</span>
                                                    <textarea name="review_note" rows="2" maxlength="2000"></textarea>
                                                </label>
                                                <div class="deposit-review-actions">
                                                    <button class="button button-dark button-small" type="submit" name="decision" value="approved">{{ __('site.deposits.approve') }}</button>
                                                    <button class="button button-outline button-small" type="submit" name="decision" value="rejected">{{ __('site.deposits.reject') }}</button>
                                                </div>
                                                @error('review_note')<small class="auth-error">{{ $message }}</small>@enderror
                                            </form>
                                        @else
                                            <span>{{ $deposit->review_note ?: '—' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $deposits->links() }}</div>
            @endif
        </section>
    </section>
@endsection
