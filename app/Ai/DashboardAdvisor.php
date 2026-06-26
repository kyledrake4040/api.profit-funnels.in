<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\Account;

/**
 * Turns an account's CRM numbers into a short, prioritized "what to do today"
 * read for the business owner. Reuses ClaudeClient; off until a key is set.
 */
final class DashboardAdvisor
{
    public function __construct(private readonly ClaudeClient $claude)
    {
    }

    public function adviseFor(Account $account): string
    {
        $openStatus = config('custom.opportunity.status_open');
        $wonStatus  = config('custom.opportunity.status_won');

        $metrics = [
            'business'        => $account->site?->business_name ?? $account->name,
            'new_leads'       => $account->contacts()->where('status', config('custom.contact.status_lead'))->count(),
            'contacts_total'  => $account->contacts()->count(),
            'open_deals'      => $account->opportunities()->where('status', $openStatus)->count(),
            'open_value'      => (float) $account->opportunities()->where('status', $openStatus)->sum('value'),
            'won_value'       => (float) $account->opportunities()->where('status', $wonStatus)->sum('value'),
            'jobs_scheduled'  => $account->jobs()->where('status', config('custom.job.status_scheduled'))->count(),
            'invoices_outstanding' => (float) $account->invoices()->whereIn('status', [
                config('custom.invoice.status_draft'),
                config('custom.invoice.status_sent'),
            ])->sum('total'),
        ];

        $system = implode(' ', [
            "You are a sharp, practical advisor for a local service business owner.",
            "Given their current CRM numbers, give 2 to 4 short, specific, prioritized action items for today.",
            "Be concrete and encouraging. Plain language. One line per item, each starting with a verb.",
            "Respond with ONLY the list — no preamble, no headers, no markdown bullets beyond a leading dash.",
        ]);

        $summary = "Business: {$metrics['business']}\n"
            . "New leads waiting: {$metrics['new_leads']}\n"
            . "Total contacts: {$metrics['contacts_total']}\n"
            . "Open deals: {$metrics['open_deals']} (worth \${$metrics['open_value']})\n"
            . "Revenue won: \${$metrics['won_value']}\n"
            . "Jobs scheduled: {$metrics['jobs_scheduled']}\n"
            . "Unpaid/outstanding invoices: \${$metrics['invoices_outstanding']}\n\n"
            . "What should the owner focus on today?";

        return $this->claude->complete($system, $summary, 400);
    }
}
