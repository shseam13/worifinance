@extends('layouts.admin')

@section('title', __('site.admin_audit_logs.admin_title'))
@section('admin_breadcrumb', __('site.admin_audit_logs.admin_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.admin_audit_logs.eyebrow') }}</span>
            <h1>{{ __('site.admin_audit_logs.admin_title') }}</h1>
            <p>{{ __('site.admin_audit_logs.description') }}</p>
        </div>

        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
        @endif

        <section class="portal-panel">
            <form class="admin-filter-form" method="get" action="{{ route('admin.audit-logs.index') }}">
                <label class="admin-form-field">
                    <span>{{ __('site.admin_audit_logs.search') }}</span>
                    <input type="search" name="search" value="{{ $search }}" maxlength="100">
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.admin_audit_logs.action') }}</span>
                    <select name="action">
                        <option value="">{{ __('site.admin_audit_logs.all_actions') }}</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.admin_audit_logs.admin_filter') }}</span>
                    <select name="admin">
                        <option value="">{{ __('site.admin_audit_logs.all_administrators') }}</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected((string) ($filters['admin'] ?? '') === (string) $admin->id)>{{ $admin->name }} ({{ $admin->email }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.admin_audit_logs.from') }}</span>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.admin_audit_logs.to') }}</span>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                </label>
                <div class="admin-form-actions">
                    <button class="button button-dark button-small" type="submit">{{ __('site.admin_audit_logs.apply') }}</button>
                    <a class="button button-outline button-small" href="{{ route('admin.audit-logs.index') }}">{{ __('site.admin_audit_logs.clear') }}</a>
                </div>
            </form>
        </section>

        <section class="portal-panel">
            @if ($logs->isEmpty())
                <p class="portal-empty">{{ __('site.admin_audit_logs.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>{{ __('site.auth.date') }}</th>
                                <th>{{ __('site.admin_audit_logs.actor') }}</th>
                                <th>{{ __('site.admin_audit_logs.action') }}</th>
                                <th>{{ __('site.admin_audit_logs.subject') }}</th>
                                <th>{{ __('site.admin_audit_logs.ip_address') }}</th>
                                <th>{{ __('site.admin_audit_logs.changes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('M j, Y H:i:s') }}</td>
                                    <td>
                                        <span class="table-primary">{{ $log->admin_name ?: __('site.admin_audit_logs.system_or_investor') }}</span>
                                        @if ($log->admin_email)
                                            <span class="table-secondary">{{ $log->admin_email }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->action }}</td>
                                    <td>{{ $log->subject_type ?: '—' }}@if ($log->subject_id) #{{ $log->subject_id }}@endif</td>
                                    <td>{{ $log->ip_address ?: '—' }}</td>
                                    <td>
                                        @if ($log->before_values !== null)
                                            <details class="profit-allocation-details">
                                                <summary>{{ __('site.admin_audit_logs.before') }}</summary>
                                                <pre>{{ $log->before_values }}</pre>
                                            </details>
                                        @endif
                                        @if ($log->after_values !== null)
                                            <details class="profit-allocation-details">
                                                <summary>{{ __('site.admin_audit_logs.after') }}</summary>
                                                <pre>{{ $log->after_values }}</pre>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $logs->links() }}</div>
            @endif
        </section>
    </section>
@endsection
