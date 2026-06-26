<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ProfitProof — CRM Console</title>
    <style>
        :root {
            --bg:#0b1220; --panel:#131c2e; --line:#243049; --ink:#e7edf7;
            --muted:#93a1b8; --brand:#22c55e; --brand-ink:#052e16; --accent:#38bdf8; --danger:#ef4444;
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink);
            font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        a { color:var(--accent); text-decoration:none; }
        .hidden { display:none !important; }
        button { font:inherit; cursor:pointer; border:1px solid transparent; border-radius:.5rem;
            padding:.55rem .9rem; font-weight:650; }
        .btn-primary { background:var(--brand); color:var(--brand-ink); }
        .btn-ghost { background:transparent; border-color:var(--line); color:var(--ink); }
        .btn-ghost:hover { border-color:var(--brand); }
        input,select { font:inherit; width:100%; padding:.55rem .7rem; border-radius:.5rem;
            border:1px solid var(--line); background:#0e1626; color:var(--ink); }
        label { display:block; font-size:.78rem; color:var(--muted); margin:.6rem 0 .25rem; font-weight:600; }

        /* Login */
        #login { min-height:100vh; display:grid; place-items:center; padding:20px; }
        #login .card { width:100%; max-width:380px; background:var(--panel); border:1px solid var(--line);
            border-radius:1rem; padding:2rem; }
        .logo { font-weight:800; letter-spacing:-.02em; font-size:1.2rem; }
        .logo b { color:var(--brand); }
        .err { color:var(--danger); font-size:.85rem; margin-top:.6rem; min-height:1.1em; }

        /* App shell */
        header { display:flex; align-items:center; gap:1rem; padding:.7rem 1.2rem;
            border-bottom:1px solid var(--line); background:rgba(11,18,32,.9); position:sticky; top:0; z-index:5; }
        header .spacer { flex:1; }
        header select { width:auto; min-width:200px; }
        .who { color:var(--muted); font-size:.85rem; }
        main { max-width:1100px; margin:0 auto; padding:1.4rem 1.2rem 4rem; }

        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1rem; margin-bottom:1.6rem; }
        .stat { background:var(--panel); border:1px solid var(--line); border-radius:.8rem; padding:1rem 1.1rem; }
        .stat .k { color:var(--muted); font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
        .stat .v { font-size:1.7rem; font-weight:800; margin-top:.2rem; }
        .stat .v.brand { color:var(--brand); }

        section.block { background:var(--panel); border:1px solid var(--line); border-radius:.8rem;
            padding:1.1rem 1.2rem; margin-bottom:1.4rem; }
        section.block h2 { margin:0 0 .9rem; font-size:1.05rem; display:flex; align-items:center; gap:.6rem; }
        section.block h2 .count { color:var(--muted); font-weight:600; font-size:.85rem; }
        table { width:100%; border-collapse:collapse; }
        th,td { text-align:left; padding:.5rem .4rem; border-bottom:1px solid var(--line); font-size:.9rem; }
        th { color:var(--muted); font-weight:600; font-size:.76rem; text-transform:uppercase; }
        .pill { display:inline-block; padding:.12rem .5rem; border-radius:999px; font-size:.74rem; font-weight:700;
            border:1px solid var(--line); }
        .pill.Lead { color:var(--accent); } .pill.Customer { color:var(--brand); }
        .pill.Open { color:var(--accent); } .pill.Won { color:var(--brand); } .pill.Lost { color:var(--danger); }
        .inline-form { display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; margin-top:1rem; }
        .inline-form > div { flex:1; min-width:140px; }
        .inline-form label { margin-top:0; }
        .stages { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.3rem; }
        .stage { background:#0e1626; border:1px solid var(--line); border-radius:.5rem; padding:.25rem .6rem; font-size:.82rem; }
        .empty { color:var(--muted); font-size:.9rem; padding:.4rem 0; }
        .muted { color:var(--muted); }
    </style>
</head>
<body>

<!-- LOGIN -->
<div id="login">
    <div class="card">
        <div class="logo" style="text-align:center;margin-bottom:1.2rem">Profit<b>Proof</b> · CRM</div>
        <form id="loginForm">
            <label>Email</label>
            <input type="email" id="email" autocomplete="username" required>
            <label>Password</label>
            <input type="password" id="password" autocomplete="current-password" required>
            <div style="margin-top:1.1rem"><button class="btn-primary" style="width:100%" type="submit">Sign in</button></div>
            <div class="err" id="loginErr"></div>
        </form>
    </div>
</div>

<!-- APP -->
<div id="app" class="hidden">
    <header>
        <div class="logo">Profit<b>Proof</b></div>
        <select id="accountSelect" title="Account"></select>
        <button class="btn-ghost" id="newAgencyBtn" title="Create a new agency">+ Agency</button>
        <button class="btn-ghost" id="newAccountBtn" title="Create a new sub-account">+ Account</button>
        <div class="spacer"></div>
        <span class="who" id="who"></span>
        <button class="btn-ghost" id="logoutBtn">Log out</button>
    </header>
    <main>
        <div id="noAccount" class="empty hidden" style="text-align:center;padding:3rem 0">
            No accounts yet. Create an <b>agency</b>, then an <b>account</b> under it to get started.
        </div>

        <div id="dashboard" class="hidden">
            <div class="cards" id="statCards"></div>

            <section class="block">
                <h2>Contacts <span class="count" id="contactsCount"></span></h2>
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Status</th></tr></thead>
                    <tbody id="contactsBody"></tbody>
                </table>
                <form class="inline-form" id="contactForm">
                    <div><label>First name</label><input id="cFirst" required></div>
                    <div><label>Last name</label><input id="cLast"></div>
                    <div><label>Email</label><input id="cEmail" type="email"></div>
                    <div><label>Company</label><input id="cCompany"></div>
                    <div style="flex:0"><button class="btn-primary" type="submit">Add contact</button></div>
                </form>
            </section>

            <section class="block">
                <h2>Pipelines <span class="count" id="pipelinesCount"></span></h2>
                <div id="pipelinesList"></div>
                <form class="inline-form" id="pipelineForm">
                    <div><label>New pipeline name</label><input id="pName" placeholder="e.g. Sales" required></div>
                    <div style="flex:0"><button class="btn-primary" type="submit">Create pipeline</button></div>
                </form>
            </section>
        </div>
    </main>
</div>

<script>
const API = '/api';
let token = localStorage.getItem('pp_token');
let accounts = [];
let currentAccountId = null;

async function api(path, opts = {}) {
    const headers = { 'Accept':'application/json', 'Content-Type':'application/json', ...(opts.headers||{}) };
    if (token) headers['Authorization'] = 'Bearer ' + token;
    const res = await fetch(API + path, { ...opts, headers });
    if (res.status === 401) { logout(); throw new Error('Session expired'); }
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) {
        throw new Error(json.message || ('Request failed (' + res.status + ')'));
    }
    return json.data;
}

const $ = sel => document.querySelector(sel);
const esc = s => (s ?? '').toString().replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const money = (n, c) => (c ? c.toUpperCase()+' ' : '$') + Number(n||0).toLocaleString(undefined,{minimumFractionDigits:0});

/* ---- Auth ---- */
$('#loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    $('#loginErr').textContent = '';
    try {
        const data = await api('/auth/login', { method:'POST', body: JSON.stringify({
            email: $('#email').value, password: $('#password').value
        })});
        token = data.access_token;
        localStorage.setItem('pp_token', token);
        await boot();
    } catch (err) { $('#loginErr').textContent = err.message; }
});

function logout() {
    token = null; localStorage.removeItem('pp_token');
    $('#app').classList.add('hidden'); $('#login').classList.remove('hidden');
}
$('#logoutBtn').addEventListener('click', logout);

/* ---- Boot ---- */
async function boot() {
    const me = await api('/auth/me');
    $('#who').textContent = me.email || me.name || '';
    $('#login').classList.add('hidden'); $('#app').classList.remove('hidden');
    await loadAccounts();
}

async function loadAccounts() {
    accounts = await api('/accounts');
    const sel = $('#accountSelect');
    sel.innerHTML = accounts.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('');
    if (!accounts.length) {
        $('#noAccount').classList.remove('hidden'); $('#dashboard').classList.add('hidden');
        sel.classList.add('hidden');
        return;
    }
    sel.classList.remove('hidden'); $('#noAccount').classList.add('hidden');
    currentAccountId = accounts.some(a => a.id == currentAccountId) ? currentAccountId : accounts[0].id;
    sel.value = currentAccountId;
    await loadAccountView();
}
$('#accountSelect').addEventListener('change', e => { currentAccountId = e.target.value; loadAccountView(); });

/* ---- Account view ---- */
async function loadAccountView() {
    $('#dashboard').classList.remove('hidden');
    const [dash, contacts, pipelines] = await Promise.all([
        api(`/accounts/${currentAccountId}/dashboard`),
        api(`/accounts/${currentAccountId}/contacts`),
        api(`/accounts/${currentAccountId}/pipelines`),
    ]);
    renderStats(dash); renderContacts(contacts); renderPipelines(pipelines);
}

function renderStats(d) {
    const o = d.opportunities || {};
    const cards = [
        ['Contacts', d.contacts?.total ?? 0, false],
        ['Open deals', o.open_count ?? 0, false],
        ['Open value', money(o.open_value), true],
        ['Won value', money(o.won_value), true],
        ['Pipelines', d.pipelines ?? 0, false],
    ];
    $('#statCards').innerHTML = cards.map(([k,v,b]) =>
        `<div class="stat"><div class="k">${k}</div><div class="v ${b?'brand':''}">${v}</div></div>`).join('');
}

function renderContacts(list) {
    $('#contactsCount').textContent = list.length ? `(${list.length})` : '';
    $('#contactsBody').innerHTML = list.length ? list.map(c => `
        <tr>
            <td>${esc(c.first_name)} ${esc(c.last_name)}</td>
            <td class="muted">${esc(c.email) || '—'}</td>
            <td class="muted">${esc(c.company) || '—'}</td>
            <td><span class="pill ${esc(c.status)}">${esc(c.status)}</span></td>
        </tr>`).join('') : `<tr><td colspan="4" class="empty">No contacts yet — add your first below.</td></tr>`;
}

function renderPipelines(list) {
    $('#pipelinesCount').textContent = list.length ? `(${list.length})` : '';
    $('#pipelinesList').innerHTML = list.length ? list.map(p => `
        <div style="padding:.6rem 0;border-bottom:1px solid var(--line)">
            <strong>${esc(p.name)}</strong>
            <span class="muted" style="font-size:.82rem"> · ${p.opportunities_count ?? 0} deals</span>
            <div class="stages">${(p.stages||[]).map(s => `<span class="stage">${esc(s.name)}</span>`).join('')}</div>
        </div>`).join('') : `<div class="empty">No pipelines yet — create one below (stages are seeded automatically).</div>`;
}

/* ---- Mutations ---- */
$('#contactForm').addEventListener('submit', async e => {
    e.preventDefault();
    await api(`/accounts/${currentAccountId}/contacts`, { method:'POST', body: JSON.stringify({
        first_name: $('#cFirst').value, last_name: $('#cLast').value,
        email: $('#cEmail').value || null, company: $('#cCompany').value || null,
    })});
    e.target.reset();
    await loadAccountView();
});

$('#pipelineForm').addEventListener('submit', async e => {
    e.preventDefault();
    await api(`/accounts/${currentAccountId}/pipelines`, { method:'POST', body: JSON.stringify({ name: $('#pName').value })});
    e.target.reset();
    await loadAccountView();
});

$('#newAgencyBtn').addEventListener('click', async () => {
    const name = prompt('Agency name (your reseller brand):');
    if (!name) return;
    try { await api('/agencies', { method:'POST', body: JSON.stringify({ name })});
        alert('Agency created. Now add an account under it with “+ Account”.'); }
    catch (err) { alert(err.message); }
});

$('#newAccountBtn').addEventListener('click', async () => {
    const agencies = await api('/agencies');
    if (!agencies.length) { alert('Create an agency first (“+ Agency”).'); return; }
    const name = prompt('New account (client) name:');
    if (!name) return;
    const agency = agencies[0];
    try { await api('/accounts', { method:'POST', body: JSON.stringify({ agency_id: agency.id, name })});
        await loadAccounts(); }
    catch (err) { alert(err.message); }
});

/* ---- Start ---- */
if (token) { boot().catch(() => logout()); }
</script>
</body>
</html>
