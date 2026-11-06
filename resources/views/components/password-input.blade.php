@props(['id', 'name', 'autocomplete'])

<div class="password-field">
    <input id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}" required>
    <button
        class="password-toggle"
        type="button"
        data-password-toggle
        data-show-label="{{ __('site.auth.show_password') }}"
        data-hide-label="{{ __('site.auth.hide_password') }}"
        aria-label="{{ __('site.auth.show_password') }}"
        aria-controls="{{ $id }}"
        aria-pressed="false"
    >
        <svg data-password-eye viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg data-password-eye-off viewBox="0 0 24 24" aria-hidden="true" hidden><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a14.8 14.8 0 0 1-3.2 4.1M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.4 10.4 0 0 0 4.1-.8"/></svg>
    </button>
</div>