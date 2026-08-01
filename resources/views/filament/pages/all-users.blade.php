<x-filament-panels::page>
    @php($rows = $this->rows())
    @php($inactive = $this->inactiveCount())

    {{-- Inactive-customer filter: turn this console into the team's follow-up list. --}}
    <label style="display:inline-flex;align-items:center;gap:.5rem;margin-bottom:.9rem;font-size:.85rem;cursor:pointer">
        <input type="checkbox" wire:model.live="onlyInactive" style="width:1rem;height:1rem;accent-color:#b45309">
        <span style="font-weight:600">{{ __('app.allusers.filter_inactive') }}</span>
        @if ($inactive > 0)
            <span style="font-size:.72rem;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:999px;padding:.05rem .5rem">{{ $inactive }}</span>
        @endif
    </label>

    <div style="overflow-x:auto;border:1px solid rgba(21,128,61,.16);border-radius:14px;background:rgba(255,255,255,.55)">
        <table style="width:100%;border-collapse:collapse;font-size:.86rem">
            <thead>
                <tr>
                    @foreach ([
                        __('app.allusers.col_workspace'),
                        __('app.user.name'),
                        __('app.user.email'),
                        __('app.user.phone'),
                        __('app.user.role'),
                        __('app.allusers.col_two_fa'),
                        __('app.user.last_login'),
                        __('app.allusers.col_actions'),
                    ] as $h)
                        <th style="text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;padding:.6rem .8rem;border-bottom:1px solid rgba(21,128,61,.16);white-space:nowrap">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="au-{{ $row['tenant_id'] }}-{{ $row['user_id'] }}" style="border-bottom:1px solid rgba(21,128,61,.08)">
                        <td style="padding:.55rem .8rem;font-weight:600">{{ $row['tenant_name'] ?? $row['tenant_id'] }}</td>
                        <td style="padding:.55rem .8rem">{{ $row['name'] }}
                            @if ($row['is_last_admin'])
                                <span title="{{ __('app.allusers.last_admin_hint') }}" style="font-size:.66rem;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:999px;padding:.05rem .45rem;margin-left:.3rem;white-space:nowrap">{{ __('app.allusers.last_admin') }}</span>
                            @endif
                        </td>
                        <td style="padding:.55rem .8rem;font-family:ui-monospace,Consolas,monospace">{{ $row['email'] }}</td>
                        <td style="padding:.55rem .8rem;white-space:nowrap">
                            <button type="button"
                                wire:click="openPhone('{{ $row['tenant_id'] }}', {{ $row['user_id'] }}, @js($row['phone']), @js($row['name']))"
                                title="{{ __('app.allusers.edit_phone') }}"
                                style="border:0;background:none;cursor:pointer;padding:0;font:inherit;color:{{ filled($row['phone'] ?? null) ? '#111' : '#15803d' }}">
                                {{ filled($row['phone'] ?? null) ? $row['phone'] : '+ '.__('app.allusers.add_phone') }}
                            </button>
                        </td>
                        <td style="padding:.55rem .8rem">{{ $row['role'] !== null ? __('app.role.'.$row['role']) : '—' }}</td>
                        <td style="padding:.55rem .8rem">{{ $row['two_fa'] ? '✓' : '—' }}</td>
                        <td style="padding:.55rem .8rem;white-space:nowrap">
                            {{ $row['last_login'] ? \Illuminate\Support\Carbon::parse($row['last_login'])->format('d/m/Y H:i') : __('app.user.never') }}
                            @if ($row['inactive'] ?? false)
                                <span title="{{ __('app.allusers.inactive_hint') }}" style="font-size:.66rem;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:999px;padding:.05rem .45rem;margin-left:.3rem;white-space:nowrap">{{ __('app.allusers.inactive_badge', ['days' => $row['idle_working_days'] ?? '5+']) }}</span>
                            @endif
                        </td>
                        <td style="padding:.55rem .8rem;white-space:nowrap">
                            <button type="button"
                                wire:click="resend('{{ $row['tenant_id'] }}', {{ $row['user_id'] }})"
                                wire:confirm="{{ __('app.allusers.resend_confirm', ['email' => $row['email']]) }}"
                                style="font-size:.78rem;font-weight:600;color:#15803d;background:#f0fdf4;border:1px solid rgba(21,128,61,.25);border-radius:9px;padding:.3rem .65rem;cursor:pointer">
                                {{ __('app.allusers.resend') }}
                            </button>
                            @unless ($row['is_last_admin'])
                                <button type="button"
                                    wire:click="deleteUser('{{ $row['tenant_id'] }}', {{ $row['user_id'] }})"
                                    wire:confirm="{{ __('app.allusers.delete_confirm', ['email' => $row['email']]) }}"
                                    style="font-size:.78rem;font-weight:600;color:#991b1b;background:#fef2f2;border:1px solid rgba(153,27,27,.25);border-radius:9px;padding:.3rem .65rem;cursor:pointer;margin-left:.35rem">
                                    {{ __('app.allusers.delete') }}
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="padding:1.1rem .8rem;color:#6b8278">{{ $this->onlyInactive ? __('app.allusers.none_inactive') : __('app.allusers.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Set-phone modal --}}
    @if ($showPhoneModal)
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;z-index:50"
             wire:click.self="closePhone">
            <div style="background:#fff;border-radius:14px;padding:1.4rem 1.5rem;width:min(92vw,420px);box-shadow:0 20px 60px rgba(0,0,0,.25)">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:.2rem">{{ __('app.allusers.edit_phone') }}</h3>
                <p style="font-size:.82rem;color:#6b7280;margin-bottom:1rem">{{ $editUserLabel }}</p>

                <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.3rem">{{ __('app.user.phone') }}</label>
                <input type="tel" wire:model="phoneInput" maxlength="30" wire:keydown.enter="savePhone"
                       placeholder="+237 6XX XX XX XX" autofocus
                       style="width:100%;padding:.55rem .7rem;border:1px solid #d1d5db;border-radius:9px;font:inherit">
                <p style="font-size:.74rem;color:#6b7280;margin-top:.4rem">{{ __('app.user.phone_help') }}</p>
                @error('phoneInput')<p style="font-size:.74rem;color:#b91c1c;margin-top:.3rem">{{ $message }}</p>@enderror

                <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.2rem">
                    <button type="button" wire:click="closePhone"
                            style="font-size:.82rem;font-weight:600;color:#374151;background:#f3f4f6;border:1px solid #d1d5db;border-radius:9px;padding:.4rem .9rem;cursor:pointer">
                        {{ __('app.allusers.cancel') }}
                    </button>
                    <button type="button" wire:click="savePhone" wire:loading.attr="disabled"
                            style="font-size:.82rem;font-weight:700;color:#fff;background:#15803d;border:0;border-radius:9px;padding:.4rem 1.1rem;cursor:pointer">
                        {{ __('app.allusers.save') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
