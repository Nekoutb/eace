@php
    $ink = '#0f231a'; $soft = '#5b6b62'; $line = '#e2ece6';
    $accent = $r->status === 'failed' ? '#b91c1c' : ($r->warnings() !== [] ? '#b45309' : '#15803d');
    $fmt = fn ($n): string => number_format((int) $n);
    $mb = $r->fileSizeBytes ? round($r->fileSizeBytes / 1048576, 1).' MB' : '—';
    $delta = $r->delta();
    $deltaTxt = $delta === null ? '—' : ($delta >= 0 ? '+'.$fmt($delta) : $fmt($delta));
    $row = fn (string $k, string $v): string => '<tr><td style="padding:.35rem .6rem;color:'.$soft.';border-bottom:1px solid '.$line.';font-size:13px">'.$k.'</td><td style="padding:.35rem .6rem;color:'.$ink.';border-bottom:1px solid '.$line.';font-size:13px;font-weight:600">'.$v.'</td></tr>';
@endphp
<div style="font-family:Arial,Helvetica,sans-serif;color:{{ $ink }};max-width:660px;margin:0 auto;line-height:1.5">
    <div style="background:{{ $accent }};color:#fff;padding:14px 18px;border-radius:10px 10px 0 0;font-size:16px;font-weight:700">
        @if($r->status === 'failed') Taxpayer register download FAILED
        @elseif($r->status === 'unchanged') Taxpayer register — unchanged (verified)
        @elseif($r->warnings() !== []) Taxpayer register refreshed — please review
        @else Taxpayer register refreshed successfully @endif
    </div>

    <div style="border:1px solid {{ $line }};border-top:0;border-radius:0 0 10px 10px;padding:16px 18px">
        <p style="margin:0 0 .6rem;font-size:14px"><strong>{{ $r->period }}</strong> · {{ ucfirst($r->status) }}@if($r->status !== 'failed') · <strong>{{ $fmt($r->distinctNiu) }}</strong> taxpayers @endif</p>

        @if($r->status === 'failed')
            <p style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 12px;font-size:13px;color:#991b1b;margin:.4rem 0">
                {{ $r->error ?? 'The download or ingest failed; the existing register was left untouched.' }}
            </p>
        @elseif($r->status === 'unchanged')
            <p style="font-size:13px;color:{{ $soft }};margin:.4rem 0">
                This download was byte-for-byte identical to the last verified file (SHA-256 match), so the register is confirmed current and was not re-ingested.
            </p>
            <table style="width:100%;border-collapse:collapse;margin-top:.4rem">
                {!! $row('Taxpayers', $fmt($r->distinctNiu)) !!}
                {!! $row('File hash (SHA-256)', '<span style="font-family:monospace;font-size:11px">'.($r->fileHash ?? '—').'</span>') !!}
            </table>
        @else
            <h3 style="font-size:13px;color:{{ $accent }};margin:1rem 0 .3rem;text-transform:uppercase;letter-spacing:.04em">Data-integrity checks</h3>
            <table style="width:100%;border-collapse:collapse">
                {!! $row('Total rows', $fmt($r->totalRows)) !!}
                {!! $row('Distinct NIU (loaded)', $fmt($r->distinctNiu)) !!}
                {!! $row('Change vs previous', $deltaTxt.($r->previousRowCount ? ' (was '.$fmt($r->previousRowCount).')' : '')) !!}
                {!! $row('Duplicate NIU in file', $fmt($r->duplicateNiu()).' (collapsed on import)') !!}
                {!! $row('Rows with NIU', $fmt($r->rowsWithNiu).' ('.$r->niuCompleteness().'%)') !!}
                {!! $row('NIU format valid', $fmt($r->niuFormatValid).' ('.$r->niuValidity().'%)') !!}
                {!! $row('Rows with name', $fmt($r->rowsWithName).' ('.$r->nameCompleteness().'%)') !!}
                {!! $row('Columns found', count($r->columnsFound).' / '.(count($r->columnsFound) + count($r->columnsMissing)).(($r->columnsMissing !== []) ? ' — missing: '.implode(', ', $r->columnsMissing) : '')) !!}
                {!! $row('File size', $mb) !!}
                {!! $row('File hash (SHA-256)', '<span style="font-family:monospace;font-size:11px">'.($r->fileHash ?? '—').'</span>') !!}
            </table>

            @if($r->changes && $r->changes->hasChanges())
                @php($c = $r->changes)
                <h3 style="font-size:13px;color:{{ $accent }};margin:1.2rem 0 .3rem;text-transform:uppercase;letter-spacing:.04em">Register changes vs previous file</h3>
                <table style="width:100%;border-collapse:collapse">
                    {!! $row('NIU added', $fmt($c->addedTotal).' &nbsp;(<span style="color:#15803d">'.$fmt($c->addedFirstTime).' first-time ever</span> · '.$fmt($c->addedReturning).' returning)') !!}
                    {!! $row('NIU removed (fell off)', $fmt($c->removedTotal)) !!}
                </table>
                <p style="font-size:12px;color:{{ $soft }};margin:.5rem 0 0">
                    <strong>First-time ever</strong> = never appeared in any file we have ingested.
                    <strong>Returning</strong> = seen in an earlier file, had dropped off, now back.
                </p>
                @if($c->added !== [])
                    <p style="font-size:12px;color:{{ $soft }};margin:.5rem 0 0"><strong>Sample added:</strong> <span style="font-family:monospace">{{ implode(', ', array_slice($c->added, 0, 10)) }}</span>@if($c->addedTotal > 10) … @endif</p>
                @endif
                @if($c->removed !== [])
                    <p style="font-size:12px;color:{{ $soft }};margin:.25rem 0 0"><strong>Sample removed:</strong> <span style="font-family:monospace">{{ implode(', ', array_slice($c->removed, 0, 10)) }}</span>@if($c->removedTotal > 10) … @endif</p>
                @endif
                <p style="font-size:12px;color:{{ $soft }};margin:.5rem 0 0">
                    The complete added/removed NIU lists (each added NIU labelled first-time vs returning) are attached as <strong>register-changes.csv</strong>.@if($c->capped) Lists are capped at 50,000 rows each; the totals above are exact. @endif
                </p>
            @elseif($r->changes)
                <p style="font-size:12px;color:{{ $soft }};margin:.8rem 0 0">No NIUs were added or removed versus the previous file — the population is identical.</p>
            @endif
        @endif

        @if($r->warnings() !== [])
            <h3 style="font-size:13px;color:#b45309;margin:1rem 0 .3rem;text-transform:uppercase;letter-spacing:.04em">Findings to review</h3>
            <ul style="margin:.2rem 0;padding-left:1.1rem;font-size:13px;color:#92400e">
                @foreach($r->warnings() as $w)
                    <li style="margin:.2rem 0">{{ $w }}</li>
                @endforeach
            </ul>
        @endif

        <p style="font-size:11px;color:{{ $soft }};margin:1.1rem 0 0;border-top:1px solid {{ $line }};padding-top:.6rem">
            Elite Advisors Compliance Engine — automated taxpayer-register refresh. The register is replaced in place each run; a corrupt or collapsed download is rejected and the previous register is kept.
        </p>
    </div>
</div>
