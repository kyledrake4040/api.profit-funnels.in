<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Automation\AutomationEngine;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public-facing rendering of a client's micro-site and its lead form. A captured
 * lead becomes a Contact in the site's account and fires the account's
 * "contact.created" automations — so a brand-new business with no website starts
 * collecting leads straight into their CRM.
 */
final class SitePublicController extends Controller
{
    public function show(string $slug): View
    {
        $site = Site::where('slug', $slug)->where('published', true)->firstOrFail();

        return view('site.show', ['site' => $site]);
    }

    public function lead(Request $request, string $slug): RedirectResponse
    {
        $site = Site::where('slug', $slug)->where('published', true)->firstOrFail();

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:160'],
            'email'   => ['nullable', 'email', 'max:190'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $contact = $site->account->contacts()->create([
            'first_name' => Str::before($data['name'], ' '),
            'last_name'  => Str::contains($data['name'], ' ') ? Str::after($data['name'], ' ') : null,
            'email'      => $data['email'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'status'     => config('custom.contact.status_lead'),
            'source'     => 'Website',
            'notes'      => $data['message'] ?? null,
        ]);

        app(AutomationEngine::class)->fire(
            config('custom.automation.event_contact_created'),
            $site->account,
            ['contact' => $contact],
        );

        return back()->with('site_lead_ok', $data['name']);
    }
}
