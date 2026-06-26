<?php

declare(strict_types=1);

namespace App\Automation;

use App\Models\Account;
use App\Models\AutomationAction;
use App\Models\Contact;

/**
 * Runs account-scoped automations when a trigger event fires. Each event carries
 * a Contact in context; an automation's ordered actions operate on it.
 *
 * Kept deliberately small and synchronous so it is easy to test and reason
 * about; heavier actions (email/SMS, external webhooks) can later be queued
 * behind the same action contract.
 */
final class AutomationEngine
{
    /**
     * Fire an event for an account. Returns the number of actions executed
     * (handy for logging/asserting).
     *
     * @param array{contact?: ?Contact} $context
     */
    public function fire(string $event, Account $account, array $context): int
    {
        $contact = $context['contact'] ?? null;
        if (! $contact instanceof Contact) {
            return 0;
        }

        $automations = $account->automations()
            ->where('trigger_event', $event)
            ->where('is_active', true)
            ->with('actions')
            ->get();

        $ran = 0;
        foreach ($automations as $automation) {
            foreach ($automation->actions as $action) {
                $this->run($action, $contact);
                $ran++;
            }
        }

        return $ran;
    }

    private function run(AutomationAction $action, Contact $contact): void
    {
        $config = $action->config ?? [];

        switch ($action->type) {
            case config('custom.automation.action_add_tag'):
                $tag = (string) ($config['tag'] ?? '');
                if ($tag !== '') {
                    $tags   = $contact->tags ?? [];
                    $tags[] = $tag;
                    $contact->tags = array_values(array_unique(array_filter($tags)));
                    $contact->save();
                }
                break;

            case config('custom.automation.action_set_contact_status'):
                $status = $config['status'] ?? null;
                if (is_string($status) && in_array($status, config('custom.contact.status'), true)) {
                    $contact->status = $status;
                    $contact->save();
                }
                break;

            case config('custom.automation.action_create_job'):
                $contact->account->jobs()->create([
                    'contact_id' => $contact->id,
                    'title'      => (string) ($config['title'] ?? 'Follow up'),
                    'status'     => config('custom.job.status_scheduled'),
                ]);
                break;
        }
    }
}
