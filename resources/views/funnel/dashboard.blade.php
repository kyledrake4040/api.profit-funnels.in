<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Funnel Attribution — last {{ $days }} day(s)</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; padding: 2rem; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        .muted { color: #94a3b8; font-size: .9rem; margin-bottom: 1.5rem; }
        form { margin-bottom: 1.5rem; }
        select, button { font: inherit; padding: .4rem .6rem; border-radius: .5rem; border: 1px solid #334155; background: #1e293b; color: inherit; }
        .cards { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: .75rem; padding: 1.25rem; }
        .card.funnel { border-color: #22c55e; }
        .card h2 { margin: 0 0 .75rem; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
        .metric { display: flex; justify-content: space-between; padding: .35rem 0; }
        .metric .v { font-variant-numeric: tabular-nums; font-weight: 600; }
        .share { background: #1e293b; border: 1px solid #334155; border-radius: .75rem; padding: 1rem 1.25rem; }
        .bar { height: 10px; background: #334155; border-radius: 999px; overflow: hidden; margin-top: .5rem; }
        .bar > span { display: block; height: 100%; background: #22c55e; width: {{ $share }}%; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Funnel Attribution</h1>
    <div class="muted">Leads &amp; revenue, funnel vs. other — last {{ $days }} day(s)</div>

    <form method="get">
        <label>Window:
            <select name="days" onchange="this.form.submit()">
                @foreach ([7, 14, 30, 90] as $d)
                    <option value="{{ $d }}" @selected($d === $days)>{{ $d }} days</option>
                @endforeach
            </select>
        </label>
        <noscript><button type="submit">Apply</button></noscript>
    </form>

    @php
        $money = fn (int $cents) => '$' . number_format($cents / 100, 2);
    @endphp

    <div class="cards">
        <div class="card funnel">
            <h2>Funnel</h2>
            <div class="metric"><span>Leads</span><span class="v">{{ $summary['funnel']['leads'] }}</span></div>
            <div class="metric"><span>Revenue</span><span class="v">{{ $money($summary['funnel']['revenue_cents']) }}</span></div>
        </div>
        <div class="card">
            <h2>Other</h2>
            <div class="metric"><span>Leads</span><span class="v">{{ $summary['other']['leads'] }}</span></div>
            <div class="metric"><span>Revenue</span><span class="v">{{ $money($summary['other']['revenue_cents']) }}</span></div>
        </div>
    </div>

    <div class="share">
        <strong>{{ $share }}%</strong> of leads attributed to the funnel
        <div class="bar"><span></span></div>
    </div>
</div>
</body>
</html>
