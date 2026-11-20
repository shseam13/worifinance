@extends('layouts.app')

@section('title', __('site.investor_portfolio.title'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.investor_portfolio.eyebrow') }}</span>
            <h1>{{ __('site.investor_portfolio.title') }}</h1>
            <p>{{ __('site.investor_portfolio.description') }}</p>
        </div>

        <form class="admin-filter-form" method="get" action="{{ route('account.investments.index') }}">
            <label class="admin-form-field">
                <span>{{ __('site.investor_portfolio.filter') }}</span>
                <select name="status">
                    @foreach (__('site.investor_portfolio.filters') as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-dark" type="submit">{{ __('site.investor_activity.apply_filter') }}</button>
        </form>

        <section class="portal-panel">
            @if ($investments->isEmpty())
                <p class="portal-empty">{{ __('site.investor_portfolio.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>{{ __('site.investor_activity.project') }}</th>
                                <th>{{ __('site.investor_portfolio.units') }}</th>
                                <th>{{ __('site.investor_portfolio.principal') }}</th>
                                <th>{{ __('site.investor_portfolio.paid_amount') }}</th>
                                <th>{{ __('site.investor_portfolio.duration') }}</th>
                                <th>{{ __('site.investor_portfolio.maturity') }}</th>
                                <th>{{ __('site.investor_portfolio.progress') }}</th>
                                <th>{{ __('site.auth.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($investments as $investment)
                                <tr>
                                    <td>{{ app()->getLocale() === 'bn' && $investment->project_name_bn ? $investment->project_name_bn : $investment->project_name }}</td>
                                    <td>{{ number_format((int) $investment->unit_count) }}</td>
                                    <td>@money($investment->amount_bdt, 'BDT')</td>
                                    <td>@money($investment->paid_amount, $investment->payment_currency)</td>
                                    <td>{{ $investment->duration_months ? $investment->duration_months.' '.__('site.projects.months') : '—' }}</td>
                                    <td>{{ $investment->matures_at ? \Illuminate\Support\Carbon::parse($investment->matures_at)->format('M j, Y') : '—' }}</td>
                                    <td>
                                        <progress value="{{ $investment->progress_percent }}" max="100" aria-label="{{ __('site.investor_portfolio.progress') }}">{{ $investment->progress_percent }}%</progress>
                                        <small class="table-secondary">{{ $investment->progress_percent }}%</small>
                                    </td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($investment->status) }}">{{ in_array($investment->display_status, ['active', 'completed'], true) ? __('site.investor_portfolio.filters.'.$investment->display_status) : __('site.auth.statuses.'.$investment->display_status) }}</span></td>
                                    <td><a class="table-link" href="{{ route('account.investments.show', $investment->id) }}">{{ __('site.investor_portfolio.details') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $investments->links() }}
            @endif
        </section>
    </section>
@endsection
