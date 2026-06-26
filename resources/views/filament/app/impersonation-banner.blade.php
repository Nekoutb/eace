@php($ctx = \App\Support\Impersonation::context())
@if ($ctx)
    <div style="position:sticky;top:0;z-index:9999;display:flex;align-items:center;justify-content:space-between;gap:12px;background:#b45309;color:#fff;padding:7px 16px;font-size:13px;line-height:1.3;box-shadow:0 1px 5px rgba(0,0,0,.22);">
        <span style="display:flex;align-items:center;gap:8px;font-weight:700;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            {{ __('app.impersonation.banner', ['client' => $ctx['tenant_name'] ?? $ctx['tenant']]) }}
        </span>
        <form method="POST" action="{{ route('impersonation.exit') }}" style="margin:0;flex:none;">
            @csrf
            <button type="submit" style="background:#fff;color:#b45309;border:0;border-radius:6px;padding:5px 14px;font-weight:800;font-size:12px;cursor:pointer;white-space:nowrap;">
                {{ __('app.impersonation.exit') }}
            </button>
        </form>
    </div>
@endif
