<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ProfitProof — See which marketing actually books you paid jobs</title>
    <meta name="description" content="ProfitProof ties every lead to real revenue, so local service businesses know exactly which marketing books paid jobs — and stop wasting ad spend.">
    <style>
        :root {
            --bg: #0b1220; --panel: #131c2e; --line: #243049; --ink: #e7edf7;
            --muted: #93a1b8; --brand: #22c55e; --brand-ink: #052e16; --accent: #38bdf8;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink);
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 0 20px; }
        .btn { display: inline-block; padding: .8rem 1.3rem; border-radius: .6rem;
            font-weight: 700; cursor: pointer; border: 1px solid transparent; transition: .15s; }
        .btn-primary { background: var(--brand); color: var(--brand-ink); }
        .btn-primary:hover { filter: brightness(1.08); }
        .btn-ghost { border-color: var(--line); color: var(--ink); }
        .btn-ghost:hover { border-color: var(--brand); }

        nav { position: sticky; top: 0; z-index: 20; backdrop-filter: blur(8px);
            background: rgba(11,18,32,.82); border-bottom: 1px solid var(--line); }
        nav .wrap { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .logo { font-weight: 800; letter-spacing: -.02em; font-size: 1.15rem; }
        .logo b { color: var(--brand); }
        .navlinks a { color: var(--muted); margin: 0 .8rem; font-weight: 600; }
        .navlinks a:hover { color: var(--ink); }
        @media (max-width: 720px) { .navlinks { display: none; } }

        .hero { padding: 5rem 0 3rem; text-align: center; }
        .pill { display: inline-block; font-size: .8rem; font-weight: 700; color: var(--brand);
            border: 1px solid var(--line); border-radius: 999px; padding: .35rem .8rem; margin-bottom: 1.2rem; }
        h1 { font-size: clamp(2rem, 5vw, 3.2rem); line-height: 1.1; letter-spacing: -.03em; margin: 0 0 1rem; }
        h1 .hl { color: var(--brand); }
        .sub { color: var(--muted); font-size: 1.15rem; max-width: 640px; margin: 0 auto 1.8rem; }
        .cta-row { display: flex; gap: .8rem; justify-content: center; flex-wrap: wrap; }
        .reassure { color: var(--muted); font-size: .85rem; margin-top: 1rem; }

        section { padding: 3.5rem 0; border-top: 1px solid var(--line); }
        h2 { font-size: clamp(1.5rem, 3.5vw, 2.1rem); letter-spacing: -.02em; margin: 0 0 .5rem; }
        .lead { color: var(--muted); max-width: 640px; }
        .grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; margin-top: 2rem; }
        @media (max-width: 820px) { .grid3 { grid-template-columns: 1fr; } }
        .card { background: var(--panel); border: 1px solid var(--line); border-radius: .9rem; padding: 1.4rem; }
        .card h3 { margin: .2rem 0 .4rem; font-size: 1.1rem; }
        .card p { color: var(--muted); margin: 0; }
        .ic { width: 38px; height: 38px; border-radius: .6rem; background: rgba(34,197,94,.12);
            color: var(--brand); display: grid; place-items: center; font-size: 1.2rem; font-weight: 800; }

        .demo { display: grid; grid-template-columns: 1.1fr 1fr; gap: 1.5rem; align-items: center; margin-top: 1.5rem; }
        @media (max-width: 820px) { .demo { grid-template-columns: 1fr; } }
        .screen { background: var(--panel); border: 1px solid var(--line); border-radius: .9rem; padding: 1.2rem; }
        .screen .bar { display: flex; gap: .4rem; margin-bottom: 1rem; }
        .screen .bar i { width: 11px; height: 11px; border-radius: 50%; background: var(--line); display: inline-block; }
        .kpis { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
        .kpi { border: 1px solid var(--line); border-radius: .7rem; padding: .9rem; }
        .kpi.win { border-color: var(--brand); }
        .kpi .lbl { color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; }
        .kpi .val { font-size: 1.5rem; font-weight: 800; font-variant-numeric: tabular-nums; }
        .meter { height: 8px; border-radius: 999px; background: var(--line); margin-top: 1rem; overflow: hidden; }
        .meter > span { display: block; height: 100%; width: 71%; background: var(--brand); }
        .tag { font-size: .72rem; color: var(--muted); margin-top: .6rem; }

        .pricing { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; margin-top: 2rem; }
        @media (max-width: 820px) { .pricing { grid-template-columns: 1fr; } }
        .tier { background: var(--panel); border: 1px solid var(--line); border-radius: .9rem; padding: 1.6rem; display: flex; flex-direction: column; }
        .tier.feature { border-color: var(--brand); box-shadow: 0 0 0 1px var(--brand) inset; }
        .tier .name { font-weight: 700; }
        .tier .price { font-size: 2rem; font-weight: 800; margin: .3rem 0; }
        .tier .price span { font-size: .9rem; color: var(--muted); font-weight: 500; }
        .tier ul { list-style: none; padding: 0; margin: .8rem 0 1.4rem; color: var(--muted); }
        .tier li { padding: .3rem 0; }
        .tier li::before { content: "✓ "; color: var(--brand); font-weight: 800; }
        .tier .btn { margin-top: auto; text-align: center; }

        .faq details { border: 1px solid var(--line); border-radius: .7rem; padding: .2rem 1rem; margin-bottom: .7rem; background: var(--panel); }
        .faq summary { cursor: pointer; font-weight: 600; padding: .8rem 0; }
        .faq p { color: var(--muted); margin: 0 0 .9rem; }

        .signup { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: center; }
        @media (max-width: 820px) { .signup { grid-template-columns: 1fr; } }
        form .field { margin-bottom: .8rem; }
        label { display: block; font-size: .85rem; color: var(--muted); margin-bottom: .3rem; }
        input, select { width: 100%; padding: .75rem .9rem; border-radius: .6rem; border: 1px solid var(--line);
            background: #0e1626; color: var(--ink); font: inherit; }
        input:focus, select:focus { outline: none; border-color: var(--brand); }
        .err { color: #fca5a5; font-size: .8rem; margin-top: .25rem; }
        .ok { background: rgba(34,197,94,.12); border: 1px solid var(--brand); color: #bbf7d0;
            border-radius: .7rem; padding: 1rem 1.2rem; margin-bottom: 1.2rem; font-weight: 600; }
        footer { border-top: 1px solid var(--line); color: var(--muted); padding: 2rem 0 3rem; font-size: .85rem; }
    </style>
</head>
<body>
<nav>
    <div class="wrap">
        <div class="logo">Profit<b>Proof</b></div>
        <div class="navlinks">
            <a href="#how">How it works</a>
            <a href="#proof">Proof</a>
            <a href="#pricing">Pricing</a>
            <a href="#faq">FAQ</a>
        </div>
        <a href="#signup" class="btn btn-primary">Get started</a>
    </div>
</nav>

<div style="background:var(--brand);color:var(--brand-ink);text-align:center;font-weight:800;
    padding:.6rem 1rem;font-size:.95rem;">
    🎉 8 days free — then 50% off your first 3 months. New signups before Sept 1.
</div>

<header class="hero">
    <div class="wrap">
        <span class="pill">Attribution for local service businesses</span>
        <h1>Know <span class="hl">exactly</span> which marketing<br>books you paid jobs.</h1>
        <p class="sub">You spend on ads, posts, and SEO — but can't tell what actually puts money
            in the bank. ProfitProof ties every lead to real revenue, so you double down on what
            works and cut what doesn't.</p>
        <div class="cta-row">
            <a href="#signup" class="btn btn-primary">Start your 8-day free trial</a>
            <a href="#how" class="btn btn-ghost">See how it works</a>
        </div>
        <div class="reassure">8 days free · 30-day money-back guarantee · Cancel anytime · You own your data</div>
        <div style="margin-top:.9rem;display:inline-block;font-size:.85rem;font-weight:700;color:var(--brand);
            border:1px solid var(--brand);border-radius:999px;padding:.4rem .9rem;">
            ★ Founding offer — the first 10 businesses lock in launch pricing for life
        </div>
    </div>
</header>

<section id="how">
    <div class="wrap">
        <h2>Marketing you can't measure is just guessing</h2>
        <p class="lead">ProfitProof connects your lead source, your CRM, and your accounting into
            one number: revenue per channel.</p>
        <div class="grid3">
            <div class="card">
                <div class="ic">1</div>
                <h3>Track every lead</h3>
                <p>Each enquiry is captured with its source (Google, social, referral) the moment it
                    comes in — nothing slips through.</p>
            </div>
            <div class="card">
                <div class="ic">2</div>
                <h3>Tie it to revenue</h3>
                <p>When the invoice is paid, the job's dollars are matched back to the lead and the
                    channel that produced it — automatically.</p>
            </div>
            <div class="card">
                <div class="ic">3</div>
                <h3>One clear dashboard</h3>
                <p>See leads and revenue split by channel over any window. Stop paying for what
                    doesn't convert.</p>
            </div>
        </div>
    </div>
</section>

<section id="proof">
    <div class="wrap">
        <h2>The dashboard that ends the guesswork</h2>
        <div class="demo">
            <div class="screen" aria-hidden="true">
                <div class="bar"><i></i><i></i><i></i></div>
                <div class="kpis">
                    <div class="kpi win">
                        <div class="lbl">Funnel · leads</div>
                        <div class="val">38</div>
                    </div>
                    <div class="kpi win">
                        <div class="lbl">Funnel · revenue</div>
                        <div class="val">$27,500</div>
                    </div>
                    <div class="kpi">
                        <div class="lbl">Other · leads</div>
                        <div class="val">15</div>
                    </div>
                    <div class="kpi">
                        <div class="lbl">Other · revenue</div>
                        <div class="val">$6,200</div>
                    </div>
                </div>
                <div class="meter"><span></span></div>
                <div class="tag">71% of booked revenue attributed to the funnel · illustrative example</div>
            </div>
            <div>
                <h3 style="margin-top:0">Built on a real engine, not slides</h3>
                <p class="lead">This is the actual ProfitProof dashboard — the same attribution
                    engine that records leads, matches QuickBooks payments, and reports revenue by
                    channel. The numbers above are an example layout; yours fill in with your real
                    jobs.</p>
            </div>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <h2>Our founding case study</h2>
        <p class="lead">ProfitProof was built for and runs on <strong>Gulf Coast Painting PEI</strong>,
            a real soft-wash &amp; exterior painting business. It schedules their content, captures
            every lead from GoHighLevel, and matches paid QuickBooks invoices back to the channel
            that earned them — so they invest in what books jobs. We're onboarding our first
            outside clients now; honest results get published here as they come in (no made-up
            numbers).</p>
    </div>
</section>

<section id="pricing">
    <div class="wrap">
        <h2>Simple, honest pricing</h2>
        <p class="lead">All prices in USD. Cancel anytime. Every plan includes the attribution
            dashboard and your data export.</p>
        <p class="lead" style="font-weight:800;color:var(--brand);font-size:1.15rem;">
            🎉 Start with <strong>8 days free</strong> — then <strong>50% off your first 3 months</strong>
            for new signups before Sept 1.
        </p>
        <div style="max-width:640px;margin:0 auto 1.6rem;padding:1rem 1.2rem;border:1px solid var(--brand);
            border-radius:.8rem;background:rgba(34,197,94,.08);text-align:center;">
            <strong style="color:var(--brand)">The 30-day "show me" guarantee.</strong>
            If ProfitProof doesn't show you which channels actually book paid jobs in your first 30
            days, email us — we refund every dollar. The risk is ours, not yours.
        </div>
        <div class="pricing">
            <div class="tier">
                <div class="name">Starter</div>
                <div class="price">$94<span>/mo</span></div>
                <ul>
                    <li>Attribution dashboard</li>
                    <li>GoHighLevel + QuickBooks hookup</li>
                    <li>Weekly revenue-by-channel report</li>
                    <li>1 business</li>
                </ul>
                <a href="/checkout/starter" class="btn btn-ghost">Start with Starter</a>
            </div>
            <div class="tier feature">
                <div class="name">Pro · most popular</div>
                <div class="price">$294<span>/mo</span></div>
                <ul>
                    <li>Everything in Starter</li>
                    <li>Automated content scheduling</li>
                    <li>Before/after &amp; GBP posting</li>
                    <li>Priority support</li>
                </ul>
                <a href="/checkout/pro" class="btn btn-primary">Get Pro</a>
            </div>
            <div class="tier">
                <div class="name">Done-for-you</div>
                <div class="price">$494<span>/mo</span></div>
                <ul>
                    <li>Everything in Pro</li>
                    <li>We run it end to end</li>
                    <li>Monthly strategy review</li>
                    <li>Up to 3 locations</li>
                </ul>
                <a href="#signup" class="btn btn-ghost">Talk to us</a>
            </div>
        </div>
    </div>
</section>

<section id="faq" class="faq">
    <div class="wrap">
        <h2>Questions, answered</h2>
        <details><summary>What if it doesn't work for my business?</summary>
            <p>You start with 8 days free, so you can see it on your own numbers before paying a cent.
                After that, the 30-day money-back guarantee still has you covered — if it's not showing
                you which channels book paid jobs, we refund every dollar. You're risking an email,
                not your money.</p></details>
        <details><summary>I'm small / just getting going — is this for me?</summary>
            <p>Especially then. The less you can afford to waste on marketing that doesn't pay, the
                more it matters to know what actually books jobs. Starter is built for one business
                doing exactly this.</p></details>
        <details><summary>How is this different from Google Analytics?</summary>
            <p>Analytics tells you about clicks and traffic. ProfitProof tells you about <em>dollars</em> —
                it matches paid invoices back to the lead and channel that earned them, so you see
                revenue per channel, not vanity metrics.</p></details>
        <details><summary>I'm too busy to set up another tool.</summary>
            <p>Setup is on us — most businesses are live in a few days with nothing to build. On the
                Done-for-you plan we run the whole thing end to end and just send you the numbers.</p></details>
        <details><summary>Do I own my data? Is there a contract?</summary>
            <p>Your data is yours — export it any time. No lock-in: month to month, cancel anytime,
                and it stays yours when you go.</p></details>
        <details><summary>What do I need to have already?</summary>
            <p>A GoHighLevel account for leads and QuickBooks for invoicing. Don't use those yet? The
                Done-for-you plan covers setup.</p></details>
    </div>
</section>

<section id="signup">
    <div class="wrap">
        @if (session('lead_ok'))
            <div class="ok">Thanks, {{ session('lead_ok') }} — you're on the list. We'll reach out
                within one business day to book your free attribution audit.</div>
        @endif
        <div class="signup">
            <div>
                <h2>See your real numbers — free.</h2>
                <p class="lead">Tell us about your business and we'll show you, on your own numbers,
                    which channels are actually booking jobs. No cost, no obligation, no pressure —
                    just clarity you can act on this week.</p>
                <p class="lead" style="font-weight:700;color:var(--brand)">Every month you guess is a
                    month you're paying for marketing that may not be working. The audit is free and
                    the guarantee is 30 days — there's no reason to keep guessing.</p>
            </div>
            <form method="POST" action="{{ route('leads.capture') }}" novalidate>
                @csrf
                <input type="hidden" name="utm_source" value="{{ $utm['source'] }}">
                <input type="hidden" name="utm_medium" value="{{ $utm['medium'] }}">
                <input type="hidden" name="utm_campaign" value="{{ $utm['campaign'] }}">
                <div class="field">
                    <label for="name">Your name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required>
                    @error('name') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                    @error('email') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="business">Business name</label>
                    <input id="business" name="business" value="{{ old('business') }}">
                </div>
                <div class="field">
                    <label for="phone">Phone (optional)</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}">
                </div>
                <div class="field">
                    <label for="plan">Plan you're interested in</label>
                    <select id="plan" name="plan">
                        <option value="">Not sure yet</option>
                        <option value="starter" @selected(old('plan')==='starter')>Starter — $94/mo</option>
                        <option value="pro" @selected(old('plan')==='pro')>Pro — $294/mo</option>
                        <option value="done_for_you" @selected(old('plan')==='done_for_you')>Done-for-you — $494/mo</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Start my 8-day free trial →</button>
                <p style="text-align:center;font-size:.8rem;color:var(--muted);margin:.6rem 0 0">
                    8 days free · No credit card to start · We reply within one business day</p>
            </form>
        </div>
    </div>
</section>

<footer>
    <div class="wrap">
        ProfitProof — revenue attribution for local service businesses.
        Built on a real, open engine. © {{ date('Y') }}
    </div>
</footer>
</body>
</html>
