@extends('layouts.admin')

@section('title', __('site.ledger_transactions.admin_title'))
@section('admin_breadcrumb', __('site.ledger_transactions.admin_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.ledger_transactions.eyebrow') }}</span>
            <h1>{{ __('site.ledger_transactions.admin_title') }}</h1>
            <p>{{ __('site.ledger_transactions.description') }}</p>
        </div>

        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
        @endif

        <section class="portal-panel">
            <form class="admin-filter-form" method="get" action="{{ route('admin.transactions.index') }}">
                <label class="admin-form-field">
                    <span>{{ __('site.ledger_transactions.search') }}</span>
                    <input type="search" name="search" value="{{ $search }}" maxlength="100">
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.ledger_transactions.type') }}</span>
                    <select name="type">
                        <option value="">{{ __('site.ledger_transactions.all_types') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.ledger_transactions.status') }}</span>
                    <select name="status">
                        @foreach (['all', 'posted', 'pending', 'reversed'] as $filter)
                            <option value="{{ $filter }}" @selected($status === $filter)>{{ __('site.ledger_transactions.statuses.'.$filter) }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="admin-form-actions">
                    <button class="button button-dark button-small" type="submit">{{ __('site.ledger_transactions.apply') }}</button>
                    <a class="button button-outline button-small" href="{{ route('admin.transactions.index') }}">{{ __('site.ledger_transactions.clear') }}</a>
                </div>
            </form>
        </section>

        <section class="portal-panel">
            @if ($transactions->isEmpty())
                <p class="portal-empty">{{ __('site.ledger_transactions.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>{{ __('site.ledger_transactions.transaction') }}</th>
                                <th>{{ __('site.ledger_transactions.type') }}</th>
                                <th>{{ __('site.ledger_transactions.actor') }}</th>
                                <th>{{ __('site.auth.date') }}</th>
                                <th>{{ __('site.auth.status') }}</th>
                                <th>{{ __('site.ledger_transactions.entries') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td>
                                        <span class="table-primary">#{{ $transaction->id }}</span>
                                        <span class="table-secondary">{{ $transaction->idempotency_key }}</span>
                                        @if ($transaction->description)
                                            <small class="table-secondary">{{ $transaction->description }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $transaction->type }}</td>
                                    <td>
                                        <span class="table-primary">{{ $transaction->actor_name ?: __('site.ledger_transactions.system') }}</span>
                                        @if ($transaction->actor_email)
                                            <span class="table-secondary">{{ $transaction->actor_email }}</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($transaction->created_at)->format('M j, Y H:i') }}</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($transaction->status) }}">{{ $transaction->status }}</span></td>
                                    <td>
                                        @foreach ($entries->get($transaction->id, collect()) as $entry)
                                            <div class="table-secondary">
                                                <strong>{{ $entry->investor_name }}</strong> · {{ $entry->investor_email }}<br>
                                                {{ $entry->entry_type }}:
                                                @money($entry->amount, $entry->currency)
                                                <small>{{ __('site.ledger_transactions.balance_after') }}: @money($entry->balance_after, $entry->currency)</small>
                                                <small>{{ __('site.ledger_transactions.reserved_after') }}: @money($entry->reserved_after, $entry->currency)</small>
                                            </div>
                                        @endforeach
                                        @if ($transaction->metadata)
                                            <details class="profit-allocation-details">
                                                <summary>{{ __('site.ledger_transactions.metadata') }}</summary>
                                                <pre>{{ $transaction->metadata }}</pre>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $transactions->links() }}</div>
            @endif
        </section>
    </section>
@endsection
