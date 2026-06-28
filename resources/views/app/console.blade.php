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
        .pill.Scheduled { color:var(--accent); } .pill.InProgress { color:#fbbf24; }
        .pill.Completed { color:var(--brand); } .pill.Cancelled { color:var(--danger); }
        .pill.Draft { color:var(--muted); } .pill.Sent { color:var(--accent); }
        .pill.Accepted { color:var(--brand); } .pill.Declined { color:var(--danger); }
        .pill.Paid { color:var(--brand); } .pill.Void { color:var(--danger); }
        .inline-form { display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; margin-top:1rem; }
        .inline-form > div { flex:1; min-width:140px; }
        .inline-form label { margin-top:0; }
        .stages { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.3rem; }
        .stage { background:#0e1626; border:1px solid var(--line); border-radius:.5rem; padding:.25rem .6rem; font-size:.82rem; }
        .empty { color:var(--muted); font-size:.9rem; padding:.4rem 0; }
        .muted { color:var(--muted); }

        /* Pipeline board */
        .board { display:flex; gap:.8rem; overflow-x:auto; padding-bottom:.5rem; }
        .col { flex:0 0 220px; background:#0e1626; border:1px solid var(--line); border-radius:.7rem; padding:.7rem; }
        .col h3 { margin:0 0 .15rem; font-size:.9rem; }
        .col .colsum { color:var(--muted); font-size:.76rem; margin-bottom:.6rem; }
        .deal { background:var(--panel); border:1px solid var(--line); border-radius:.55rem; padding:.55rem .6rem; margin-bottom:.5rem; }
        .deal .dn { font-weight:650; font-size:.88rem; }
        .deal .dv { color:var(--brand); font-size:.82rem; margin-top:.15rem; }
        .deal .dm { display:flex; justify-content:space-between; align-items:center; margin-top:.5rem; gap:.3rem; }
        .deal .dm button { padding:.15rem .5rem; font-size:.8rem; }
        .deal.Won { border-color:var(--brand); } .deal.Lost { border-color:var(--danger); opacity:.7; }
        .col .empty { font-size:.8rem; }
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

            <div style="display:flex;align-items:center;gap:.7rem;margin:-.6rem 0 1.4rem;flex-wrap:wrap">
                <button class="btn-ghost" id="insightBtn" onclick="getInsight()">✨ Today's insight</button>
                <div id="insightOut" class="muted" style="white-space:pre-line;font-size:.9rem;flex:1;min-width:240px"></div>
            </div>

            <section class="block">
                <h2>Contacts <span class="count" id="contactsCount"></span></h2>
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Status</th><th></th></tr></thead>
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

            <section class="block">
                <h2>Jobs <span class="count" id="jobsCount"></span></h2>
                <table>
                    <thead><tr><th>Job</th><th>Client</th><th>Scheduled</th><th>Value</th><th>Status</th><th></th></tr></thead>
                    <tbody id="jobsBody"></tbody>
                </table>
                <form class="inline-form" id="jobForm">
                    <div><label>Job title</label><input id="jTitle" placeholder="e.g. Exterior repaint" required></div>
                    <div style="flex:0;min-width:160px"><label>Client</label><select id="jContact"></select></div>
                    <div style="flex:0;min-width:170px"><label>Scheduled</label><input id="jWhen" type="date"></div>
                    <div style="flex:0;min-width:110px"><label>Value</label><input id="jValue" type="number" min="0" step="50" value="0"></div>
                    <div style="flex:0"><button class="btn-primary" type="submit">Schedule job</button></div>
                </form>
            </section>

            <section class="block">
                <h2>Quotes &amp; Invoices <span class="count" id="invCount"></span></h2>
                <p class="muted" style="margin:0 0 .8rem;font-size:.85rem">Quote a client → convert to an invoice → mark it paid. Paid totals feed the dashboard.</p>

                <h3 style="margin:.4rem 0 .3rem;font-size:.95rem">Quotes</h3>
                <table>
                    <thead><tr><th>#</th><th>Client</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody id="quotesBody"></tbody>
                </table>

                <h3 style="margin:1.1rem 0 .3rem;font-size:.95rem">Invoices</h3>
                <table>
                    <thead><tr><th>#</th><th>Client</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody id="invoicesBody"></tbody>
                </table>

                <form class="inline-form" id="quoteForm">
                    <div style="flex:0;min-width:160px"><label>New quote — client</label><select id="qContact"></select></div>
                    <div><label>Line item</label><input id="qDesc" placeholder="e.g. Exterior repaint" required></div>
                    <div style="flex:0;min-width:90px"><label>Qty</label><input id="qQty" type="number" min="0" step="1" value="1"></div>
                    <div style="flex:0;min-width:120px"><label>Unit price</label><input id="qPrice" type="number" min="0" step="50" value="0"></div>
                    <div style="flex:0"><button class="btn-primary" type="submit">Create quote</button></div>
                </form>
            </section>

            <section class="block">
                <h2>Automations <span class="count" id="autoCount"></span></h2>
                <p class="muted" style="margin:0 0 .8rem;font-size:.85rem">When something happens, do something — automatically.</p>
                <div id="autoList"></div>
                <form class="inline-form" id="autoForm">
                    <div><label>Name</label><input id="aName" placeholder="e.g. Won deal books a job" required></div>
                    <div style="flex:0;min-width:190px"><label>When… (trigger)</label>
                        <select id="aTrigger">
                            <option value="contact.created">A contact is created</option>
                            <option value="opportunity.won">A deal is won</option>
                            <option value="job.completed">A job is completed</option>
                        </select>
                    </div>
                    <div style="flex:0;min-width:180px"><label>…do this (action)</label>
                        <select id="aAction">
                            <option value="add_tag">Add a tag</option>
                            <option value="set_contact_status">Set contact status</option>
                            <option value="create_job">Create a job</option>
                        </select>
                    </div>
                    <div style="flex:0;min-width:170px"><label id="aValueLabel">Tag</label><input id="aValue" placeholder="value"></div>
                    <div style="flex:0"><button class="btn-primary" type="submit">Create</button></div>
                </form>
            </section>

            <section class="block" id="boardBlock">
                <h2>Pipeline board
                    <select id="boardPipeline" style="width:auto;margin-left:.4rem"></select>
                </h2>
                <div class="board" id="board"></div>
                <form class="inline-form" id="dealForm">
                    <div><label>Deal name</label><input id="dName" placeholder="e.g. Exterior repaint" required></div>
                    <div style="flex:0;min-width:120px"><label>Value</label><input id="dValue" type="number" min="0" step="100" value="0"></div>
                    <div style="flex:0;min-width:150px"><label>Stage</label><select id="dStage"></select></div>
                    <div style="flex:0"><button class="btn-primary" type="submit">Add deal</button></div>
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
let pipelines = [];
let boardPipelineId = null;

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
    const [dash, contacts, pl, jobs, autos, quotes, invoices] = await Promise.all([
        api(`/accounts/${currentAccountId}/dashboard`),
        api(`/accounts/${currentAccountId}/contacts`),
        api(`/accounts/${currentAccountId}/pipelines`),
        api(`/accounts/${currentAccountId}/jobs`),
        api(`/accounts/${currentAccountId}/automations`),
        api(`/accounts/${currentAccountId}/quotes`),
        api(`/accounts/${currentAccountId}/invoices`),
    ]);
    pipelines = pl;
    renderStats(dash); renderContacts(contacts); renderPipelines(pipelines);
    renderJobs(jobs, contacts);
    renderInvoicing(quotes, invoices, contacts);
    renderAutomations(autos);
    setupBoard();
    await renderBoard();
}

function renderInvoicing(quotes, invoices, contacts) {
    $('#invCount').textContent = (quotes.length + invoices.length) ? `(${quotes.length}q / ${invoices.length}i)` : '';
    const nm = c => c ? `${c.first_name} ${c.last_name||''}`.trim() : '—';

    $('#quotesBody').innerHTML = quotes.length ? quotes.map(q => `
        <tr>
            <td>${esc(q.number)}</td>
            <td class="muted">${esc(nm(q.contact))}</td>
            <td>${money(q.total, q.currency)}</td>
            <td><span class="pill ${esc(q.status)}">${esc(q.status)}</span></td>
            <td>
                ${q.accept_token && q.status !== 'Accepted' && q.status !== 'Declined' ? `<button class="btn-ghost" onclick="copyAcceptLink('${q.accept_token}')">Copy accept link</button>` : ''}
                ${q.status !== 'Accepted' ? `<button class="btn-ghost" onclick="convertQuote(${q.id})">→ Invoice</button>` : ''}
            </td>
        </tr>`).join('') : `<tr><td colspan="5" class="empty">No quotes yet.</td></tr>`;

    $('#invoicesBody').innerHTML = invoices.length ? invoices.map(v => `
        <tr>
            <td>${esc(v.number)}</td>
            <td class="muted">${esc(nm(v.contact))}</td>
            <td>${money(v.total, v.currency)}</td>
            <td><span class="pill ${esc(v.status)}">${esc(v.status)}</span></td>
            <td>
                ${v.pay_token ? `<button class="btn-ghost" onclick="copyPayLink('${v.pay_token}')">Copy pay link</button>` : ''}
                ${v.contact?.email && v.status !== 'Paid' ? `<button class="btn-ghost" onclick="emailInvoice(${v.id})">Email client</button>` : ''}
                ${v.status === 'Paid' ? '' : `<button class="btn-primary" onclick="payInvoice(${v.id})">Mark paid</button>`}
            </td>
        </tr>`).join('') : `<tr><td colspan="5" class="empty">No invoices yet.</td></tr>`;

    $('#qContact').innerHTML = `<option value="">— no client —</option>` +
        contacts.map(c => `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name||'')}</option>`).join('');
}

window.convertQuote = async (id) => {
    await api(`/accounts/${currentAccountId}/quotes/${id}/convert`, { method:'POST' });
    await loadAccountView();
};
window.payInvoice = async (id) => {
    await api(`/accounts/${currentAccountId}/invoices/${id}/pay`, { method:'POST' });
    await loadAccountView();
};
window.emailInvoice = async (id) => {
    const res = await api(`/accounts/${currentAccountId}/invoices/${id}/email`, { method:'POST' });
    if (res?.success) alert(res.message || 'Invoice emailed to client.');
};
window.copyPayLink = async (token) => {
    const url = `${location.origin}/pay/${token}`;
    try { await navigator.clipboard.writeText(url); alert('Pay link copied:\n' + url); }
    catch (e) { prompt('Copy this pay link and send it to your client:', url); }
};
window.copyAcceptLink = async (token) => {
    const url = `${location.origin}/quote/${token}`;
    try { await navigator.clipboard.writeText(url); alert('Accept link copied:\n' + url); }
    catch (e) { prompt('Copy this accept link and send it to your client:', url); }
};

document.getElementById('quoteForm').addEventListener('submit', async e => {
    e.preventDefault();
    await api(`/accounts/${currentAccountId}/quotes`, { method:'POST', body: JSON.stringify({
        contact_id: $('#qContact').value || null,
        items: [{ description: $('#qDesc').value, quantity: Number($('#qQty').value||1), unit_price: Number($('#qPrice').value||0) }],
    })});
    e.target.reset();
    await loadAccountView();
});

const TRIGGER_LABEL = {
    'contact.created': 'a contact is created',
    'opportunity.won': 'a deal is won',
    'job.completed': 'a job is completed',
};
const ACTION_LABEL = {
    add_tag: c => `add tag “${c?.tag ?? ''}”`,
    set_contact_status: c => `set status to “${c?.status ?? ''}”`,
    create_job: c => `create job “${c?.title ?? 'Follow up'}”`,
};

function renderAutomations(list) {
    $('#autoCount').textContent = list.length ? `(${list.length})` : '';
    $('#autoList').innerHTML = list.length ? list.map(a => {
        const acts = (a.actions||[]).map(x => (ACTION_LABEL[x.type]?.(x.config)||x.type)).join(', ');
        return `<div style="display:flex;align-items:center;gap:.6rem;padding:.55rem 0;border-bottom:1px solid var(--line)">
            <span class="pill ${a.is_active?'Won':'Lost'}">${a.is_active?'On':'Off'}</span>
            <div style="flex:1">
                <strong>${esc(a.name)}</strong>
                <div class="muted" style="font-size:.82rem">When ${esc(TRIGGER_LABEL[a.trigger_event]||a.trigger_event)} → ${esc(acts||'—')}</div>
            </div>
            <button class="btn-ghost" onclick="toggleAutomation(${a.id}, ${a.is_active?0:1})">${a.is_active?'Pause':'Activate'}</button>
            <button class="btn-ghost" onclick="deleteAutomation(${a.id})">Delete</button>
        </div>`;
    }).join('') : `<div class="empty">No automations yet — create your first below.</div>`;
}

const aActionEl = () => document.getElementById('aAction');
function syncAutoValueLabel() {
    const t = aActionEl().value;
    $('#aValueLabel').textContent = t==='add_tag' ? 'Tag' : t==='set_contact_status' ? 'Status (Lead/Customer/Inactive)' : 'Job title';
    $('#aValue').placeholder = t==='add_tag' ? 'e.g. from-website' : t==='set_contact_status' ? 'e.g. Customer' : 'e.g. Kickoff visit';
}
document.getElementById('aAction').addEventListener('change', syncAutoValueLabel);

window.toggleAutomation = async (id, active) => {
    await api(`/accounts/${currentAccountId}/automations/${id}`, { method:'PUT', body: JSON.stringify({ is_active: !!active })});
    await loadAccountView();
};
window.deleteAutomation = async (id) => {
    await api(`/accounts/${currentAccountId}/automations/${id}`, { method:'DELETE' });
    await loadAccountView();
};

document.getElementById('autoForm').addEventListener('submit', async e => {
    e.preventDefault();
    const type = $('#aAction').value, val = $('#aValue').value.trim();
    const key = type==='add_tag' ? 'tag' : type==='set_contact_status' ? 'status' : 'title';
    const config = {}; if (val) config[key] = val;
    await api(`/accounts/${currentAccountId}/automations`, { method:'POST', body: JSON.stringify({
        name: $('#aName').value,
        trigger_event: $('#aTrigger').value,
        actions: [{ type, config }],
    })});
    e.target.reset(); syncAutoValueLabel();
    await loadAccountView();
});

function renderJobs(list, contacts) {
    $('#jobsCount').textContent = list.length ? `(${list.length})` : '';
    const name = c => c ? `${c.first_name} ${c.last_name||''}`.trim() : '—';
    $('#jobsBody').innerHTML = list.length ? list.map(j => `
        <tr>
            <td>${esc(j.title)}</td>
            <td class="muted">${esc(name(j.contact))}</td>
            <td class="muted">${j.scheduled_at ? esc(j.scheduled_at.slice(0,10)) : '—'}</td>
            <td>${money(j.value, j.currency)}</td>
            <td><span class="pill ${esc(j.status).replace(' ','')}">${esc(j.status)}</span></td>
            <td>${j.status === 'Completed' ? '' : `<button class="btn-ghost" onclick="completeJob(${j.id})">Complete</button>`}</td>
        </tr>`).join('') : `<tr><td colspan="6" class="empty">No jobs yet — schedule one below.</td></tr>`;
    // contact picker for the add-job form
    $('#jContact').innerHTML = `<option value="">— no client —</option>` +
        contacts.map(c => `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name||'')}</option>`).join('');
}

window.completeJob = async (jobId) => {
    const job = (await api(`/accounts/${currentAccountId}/jobs`)).find(j => j.id == jobId);
    if (!job) return;
    await api(`/accounts/${currentAccountId}/jobs/${jobId}`, { method:'PUT', body: JSON.stringify({
        title: job.title, status: 'Completed', contact_id: job.contact_id,
        scheduled_at: job.scheduled_at, value: job.value,
    })});
    await loadAccountView();
};

document.getElementById('jobForm').addEventListener('submit', async e => {
    e.preventDefault();
    await api(`/accounts/${currentAccountId}/jobs`, { method:'POST', body: JSON.stringify({
        title: $('#jTitle').value,
        contact_id: $('#jContact').value || null,
        scheduled_at: $('#jWhen').value || null,
        value: Number($('#jValue').value||0),
    })});
    e.target.reset();
    await loadAccountView();
});

function renderStats(d) {
    const o = d.opportunities || {};
    const inv = d.invoices || {};
    const cards = [
        ['Contacts', d.contacts?.total ?? 0, false],
        ['Open deals', o.open_count ?? 0, false],
        ['Won value', money(o.won_value), true],
        ['Paid', money(inv.paid_total), true],
        ['Outstanding', money(inv.outstanding_total), false],
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
            <td><button class="btn-ghost" onclick="aiReply(${c.id})">✨ AI reply</button></td>
        </tr>`).join('') : `<tr><td colspan="5" class="empty">No contacts yet — add your first below.</td></tr>`;
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

/* ---- Pipeline board ---- */
function currentBoardPipeline() {
    return pipelines.find(p => p.id == boardPipelineId) || pipelines[0] || null;
}

function setupBoard() {
    const sel = $('#boardPipeline');
    if (!pipelines.length) {
        $('#boardBlock').classList.add('hidden');
        return;
    }
    $('#boardBlock').classList.remove('hidden');
    sel.innerHTML = pipelines.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
    if (!pipelines.some(p => p.id == boardPipelineId)) boardPipelineId = pipelines[0].id;
    sel.value = boardPipelineId;
    const p = currentBoardPipeline();
    $('#dStage').innerHTML = (p.stages||[]).map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
}

async function renderBoard() {
    const p = currentBoardPipeline();
    if (!p) return;
    const deals = await api(`/accounts/${currentAccountId}/opportunities?pipeline_id=${p.id}`);
    const stages = p.stages || [];
    $('#board').innerHTML = stages.map((s, idx) => {
        const inStage = deals.filter(d => d.stage_id == s.id);
        const sum = inStage.reduce((t, d) => t + Number(d.value||0), 0);
        const cards = inStage.length ? inStage.map(d => `
            <div class="deal ${esc(d.status)}">
                <div class="dn">${esc(d.name)}</div>
                <div class="dv">${money(d.value, d.currency)}</div>
                <div class="dm">
                    <button class="btn-ghost" ${idx===0?'disabled':''} onclick="moveDeal(${d.id},-1)">◀</button>
                    <span class="pill ${esc(d.status)}" style="font-size:.7rem">${esc(d.status)}</span>
                    <button class="btn-ghost" ${idx===stages.length-1?'disabled':''} onclick="moveDeal(${d.id},1)">▶</button>
                </div>
            </div>`).join('') : `<div class="empty">—</div>`;
        return `<div class="col">
            <h3>${esc(s.name)}</h3>
            <div class="colsum">${inStage.length} · ${money(sum)}</div>
            ${cards}
        </div>`;
    }).join('');
}

$('#boardPipeline').addEventListener('change', e => { boardPipelineId = e.target.value; setupBoard(); renderBoard(); });

$('#dealForm').addEventListener('submit', async e => {
    e.preventDefault();
    const p = currentBoardPipeline();
    if (!p) return;
    await api(`/accounts/${currentAccountId}/opportunities`, { method:'POST', body: JSON.stringify({
        pipeline_id: p.id, stage_id: $('#dStage').value,
        name: $('#dName').value, value: Number($('#dValue').value||0),
    })});
    $('#dName').value=''; $('#dValue').value='0';
    await loadAccountView();
});

// Move a deal to the previous/next stage. If the target stage is named Won/Lost,
// reflect that in the deal's status so the dashboard totals stay accurate.
window.moveDeal = async (dealId, dir) => {
    const p = currentBoardPipeline();
    const stages = p.stages || [];
    const deals = await api(`/accounts/${currentAccountId}/opportunities?pipeline_id=${p.id}`);
    const deal = deals.find(d => d.id == dealId);
    if (!deal) return;
    const i = stages.findIndex(s => s.id == deal.stage_id);
    const target = stages[i + dir];
    if (!target) return;
    let status = 'Open';
    if (/^won$/i.test(target.name)) status = 'Won';
    else if (/^lost$/i.test(target.name)) status = 'Lost';
    await api(`/accounts/${currentAccountId}/opportunities/${dealId}`, { method:'PUT', body: JSON.stringify({
        pipeline_id: p.id, stage_id: target.id, name: deal.name, value: deal.value, status,
    })});
    await loadAccountView();
};

window.getInsight = async () => {
    const out = $('#insightOut'), btn = $('#insightBtn');
    btn.disabled = true; out.textContent = 'Thinking…';
    try {
        const res = await api(`/accounts/${currentAccountId}/dashboard/insight`);
        out.textContent = res.insight;
    } catch (err) {
        out.textContent = err.message;
    } finally {
        btn.disabled = false;
    }
};

window.aiReply = async (contactId) => {
    try {
        const res = await api(`/accounts/${currentAccountId}/contacts/${contactId}/ai-reply`, { method:'POST' });
        prompt('AI-drafted reply (copy & send):', res.draft);
    } catch (err) {
        alert(err.message);
    }
};

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
