<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('activity.matrix_heading') }}</x-slot>
        <x-slot name="description">{{ __('activity.matrix_desc') }}</x-slot>

        @if (count($clients) === 0)
            <p style="font-size:.85rem;color:#6b7280;margin:0">{{ __('activity.matrix_empty') }}</p>
        @else
            <div style="overflow-x:auto">
                <table style="border-collapse:collapse;min-width:100%;font-size:.8rem">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:.5rem .75rem;border-bottom:2px solid rgba(0,0,0,.08);position:sticky;left:0;background:inherit;min-width:15rem">
                                {{ __('activity.matrix_step') }}
                            </th>
                            @foreach ($clients as $client)
                                <th style="text-align:center;padding:.5rem .75rem;border-bottom:2px solid rgba(0,0,0,.08);min-width:8rem;font-weight:700">
                                    {{ $client['name'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($steps as $step)
                            <tr>
                                <td style="padding:.45rem .75rem;border-bottom:1px solid rgba(0,0,0,.05);position:sticky;left:0;background:inherit">
                                    <span style="font-weight:600">{{ __('activity.step_'.$step) }}</span>
                                    <br><span style="font-size:.68rem;color:#6b7280">{{ __('activity.hint_'.$step) }}</span>
                                </td>
                                @foreach ($clients as $client)
                                    @php($cell = $client['steps'][$step] ?? ['done' => false, 'detail' => null])
                                    <td style="text-align:center;padding:.45rem .75rem;border-bottom:1px solid rgba(0,0,0,.05)"
                                        @if ($cell['detail']) title="{{ $cell['detail'] }}" @endif>
                                        @if ($cell['done'])
                                            <span style="display:inline-flex;align-items:center;justify-content:center;width:1.45rem;height:1.45rem;border-radius:9999px;background:#dcfce7;color:#15803d;font-weight:800">✓</span>
                                            @if ($cell['detail'])<br><span style="font-size:.66rem;color:#6b7280">{{ $cell['detail'] }}</span>@endif
                                        @else
                                            <span style="display:inline-flex;align-items:center;justify-content:center;width:1.45rem;height:1.45rem;border-radius:9999px;background:#f3f4f6;color:#9ca3af;font-weight:800">–</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr>
                            <td style="padding:.55rem .75rem;font-weight:800;position:sticky;left:0;background:inherit">{{ __('activity.matrix_progress') }}</td>
                            @foreach ($clients as $client)
                                @php($pct = (int) round($client['completed'] / max(1, count($steps)) * 100))
                                <td style="text-align:center;padding:.55rem .75rem">
                                    <span style="font-weight:800;color:{{ $pct === 100 ? '#15803d' : ($pct >= 50 ? '#b45309' : '#b91c1c') }}">
                                        {{ $client['completed'] }}/{{ count($steps) }}
                                    </span>
                                    <br><span style="font-size:.66rem;color:#6b7280">{{ $pct }}%</span>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
