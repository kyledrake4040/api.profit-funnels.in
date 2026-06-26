<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $site->business_name }}{{ $site->city ? ' · ' . $site->city : '' }}</title>
    <meta name="description" content="{{ $site->headline ?: $site->business_name }}">
    @php $brand = $site->theme_color ?: '#16a34a'; @endphp
    <style>
        :root { --brand: {{ $brand }}; }
        * { box-sizing: border-box; }
        body { margin:0; color:#0f172a; background:#f8fafc;
            font:17px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        a { color:var(--brand); }
        .wrap { max-width:760px; margin:0 auto; padding:0 20px; }
        header.hero { background:var(--brand); color:#fff; padding:4rem 0 3.2rem; }
        header.hero h1 { margin:0 0 .5rem; font-size:clamp(1.9rem,5vw,2.8rem); letter-spacing:-.02em; }
        header.hero p { margin:0; font-size:1.2rem; opacity:.95; }
        .btn { display:inline-block; margin-top:1.4rem; background:#fff; color:var(--brand);
            font-weight:800; padding:.85rem 1.4rem; border-radius:.6rem; text-decoration:none; }
        section { padding:2.6rem 0; }
        h2 { font-size:1.4rem; margin:0 0 .8rem; }
        .services { display:flex; flex-wrap:wrap; gap:.5rem; list-style:none; padding:0; margin:.5rem 0 0; }
        .services li { background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:.4rem .9rem; font-weight:600; }
        .contact { color:#475569; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:1rem; padding:1.6rem; }
        label { display:block; font-size:.85rem; font-weight:700; color:#334155; margin:.7rem 0 .25rem; }
        input, textarea { width:100%; padding:.7rem .8rem; border:1px solid #cbd5e1; border-radius:.5rem; font:inherit; }
        button.submit { margin-top:1rem; width:100%; background:var(--brand); color:#fff; border:0;
            font-weight:800; padding:.9rem; border-radius:.6rem; font-size:1rem; cursor:pointer; }
        .ok { background:#dcfce7; border:1px solid var(--brand); color:#14532d; padding:1rem 1.2rem;
            border-radius:.7rem; margin-bottom:1rem; font-weight:600; }
        .err { color:#dc2626; font-size:.85rem; margin-top:.25rem; }
        footer { color:#94a3b8; font-size:.85rem; padding:2rem 0 3rem; text-align:center; }
    </style>
</head>
<body>
<header class="hero">
    <div class="wrap">
        <h1>{{ $site->business_name }}</h1>
        @if ($site->headline)<p>{{ $site->headline }}</p>@endif
        <a href="#contact" class="btn">Get a free quote</a>
    </div>
</header>

@if ($site->about)
<section>
    <div class="wrap">
        <h2>About us</h2>
        <p>{{ $site->about }}</p>
    </div>
</section>
@endif

@if (!empty($site->services))
<section style="padding-top:0">
    <div class="wrap">
        <h2>What we do</h2>
        <ul class="services">
            @foreach ($site->services as $service)
                <li>{{ $service }}</li>
            @endforeach
        </ul>
    </div>
</section>
@endif

<section id="contact">
    <div class="wrap">
        <h2>Request a quote</h2>
        @if (session('site_lead_ok'))
            <div class="ok">Thanks, {{ session('site_lead_ok') }} — we got your message and will be in touch shortly.</div>
        @endif
        <div class="card">
            <form method="POST" action="{{ url('/s/' . $site->slug . '/lead') }}" novalidate>
                @csrf
                <label for="name">Your name</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
                @error('name') <div class="err">{{ $message }}</div> @enderror

                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}">
                @error('email') <div class="err">{{ $message }}</div> @enderror

                <label for="phone">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone') }}">

                <label for="message">What do you need?</label>
                <textarea id="message" name="message" rows="3">{{ old('message') }}</textarea>

                <button class="submit" type="submit">Send my request</button>
            </form>
        </div>
        @if ($site->phone || $site->email)
            <p class="contact" style="margin-top:1.2rem">
                Prefer to reach out directly?
                @if ($site->phone) Call <a href="tel:{{ $site->phone }}">{{ $site->phone }}</a>@endif
                @if ($site->phone && $site->email) · @endif
                @if ($site->email) Email <a href="mailto:{{ $site->email }}">{{ $site->email }}</a>@endif
            </p>
        @endif
    </div>
</section>

<footer>
    {{ $site->business_name }}{{ $site->city ? ' · ' . $site->city : '' }} — powered by ProfitProof
</footer>
</body>
</html>
