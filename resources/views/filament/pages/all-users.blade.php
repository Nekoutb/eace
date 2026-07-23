<x-filament-panels::page>
    @php($rows = $this->rows())

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
                        <td style="padding:.55rem .8rem;white-space:nowrap">{{ filled($row['phone'] ?? null) ? $row['phone'] : '—' }}</td>
                        <td style="padding:.55rem .8rem">{{ $row['role'] !== null ? __('app.role.'.$row['role']) : '—' }}</td>
                        <td style="padding:.55rem .8rem">{{ $row['two_fa'] ? '✓' : '—' }}</td>
                        <td style="padding:.55rem .8rem;white-space:nowrap">{{ $row['last_login'] ? \Illuminate\Support\Carbon::parse($row['last_login'])->format('d/m/Y H:i') : __('app.user.never') }}</td>
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
                    <tr><td colspan="7" style="padding:1.1rem .8rem;color:#6b8278">{{ __('app.allusers.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
