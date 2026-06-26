<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\Contact;

/**
 * Drafts the first reply a local service business sends to a new lead, using the
 * contact's details and the account's business name. Returns ready-to-send text
 * the owner can tweak and send in one tap.
 */
final class LeadReplyDrafter
{
    public function __construct(private readonly ClaudeClient $claude)
    {
    }

    public function draftFor(Contact $contact): string
    {
        $account  = $contact->account;
        $business = $account?->site?->business_name ?? $account?->name ?? 'our business';
        $name     = trim($contact->first_name . ' ' . (string) $contact->last_name) ?: 'there';

        $system = implode(' ', [
            "You write the first reply a local service business sends to a new lead.",
            "Tone: warm, professional, and concise — 3 to 5 sentences.",
            "Thank them, reference what they asked about if known, and invite a quick call or a free quote.",
            "Sign off as the team at {$business}.",
            "Respond with ONLY the message text — no preamble, no subject line, and no placeholders in square brackets.",
        ]);

        $details = "New lead:\nName: {$name}\n";
        if ($contact->email) {
            $details .= "Email: {$contact->email}\n";
        }
        if ($contact->phone) {
            $details .= "Phone: {$contact->phone}\n";
        }
        if ($contact->company) {
            $details .= "Company: {$contact->company}\n";
        }
        if ($contact->notes) {
            $details .= "Their message: {$contact->notes}\n";
        }
        $details .= "\nWrite the reply now.";

        return $this->claude->complete($system, $details, 600);
    }
}
