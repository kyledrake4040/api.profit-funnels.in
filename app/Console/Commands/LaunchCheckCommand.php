<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * `php artisan funnel:launch-check`
 *
 * One command that answers a single question in plain English: "Can this
 * install actually take a customer's money yet, and if not, what's left?"
 *
 * Every check maps to a real link in the get-paid chain — a visitor lands,
 * clicks a plan, Stripe charges them, and the webhook provisions their
 * account. A gap anywhere in that chain means either a dead checkout button
 * or (worse) a customer who is charged but never provisioned. The command
 * exits non-zero when any blocker remains, so it doubles as a deploy gate.
 */
final class LaunchCheckCommand extends Command
{
    protected $signature = 'funnel:launch-check';

    protected $description = 'Check whether the app is configured to take payments, and report what is missing';

    /** @var array<int,array{status:string,label:string,detail:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('  <options=bold>ProfitProof — launch readiness</>');
        $this->line('  <fg=gray>Can this install take a paying customer right now?</>');
        $this->line('');

        $this->checkAppKey();
        $this->checkAppUrl();
        $this->checkDatabase();
        $this->checkCoreTables();
        $this->checkAttributionStore();
        $this->checkStripeCharging();
        $this->checkStripeProvisioning();
        $this->checkPlans();
        $this->checkLoginAccount();

        return $this->report();
    }

    private function checkAppKey(): void
    {
        $this->record(
            (string) config('app.key') !== '' ? 'ok' : 'fail',
            'Application key',
            (string) config('app.key') !== ''
                ? 'set'
                : 'missing — run `php artisan key:generate` (sessions & encryption need it)',
        );
    }

    private function checkAppUrl(): void
    {
        $url = (string) config('app.url');
        if ($url === '') {
            $this->record('fail', 'Public URL', 'APP_URL is empty — Stripe needs reachable success/cancel URLs');

            return;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if (str_starts_with($url, 'https://')) {
            $this->record('ok', 'Public URL', $url);
        } elseif ($isLocal) {
            $this->record('warn', 'Public URL', $url . ' — fine for local testing; live payments need https');
        } else {
            $this->record('fail', 'Public URL', $url . ' — must be https in production for live Stripe checkout');
        }
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->record('ok', 'Database connection', 'connected (' . config('database.default') . ')');
        } catch (Throwable $e) {
            $this->record('fail', 'Database connection', 'cannot connect — check DB_* in .env (' . $e->getMessage() . ')');
        }
    }

    private function checkCoreTables(): void
    {
        $required = ['users', 'plans', 'subscriptions'];

        try {
            $missing = array_values(array_filter($required, fn (string $t): bool => ! Schema::hasTable($t)));
        } catch (Throwable $e) {
            $this->record('fail', 'Database migrated', 'could not inspect schema — ' . $e->getMessage());

            return;
        }

        $this->record(
            $missing === [] ? 'ok' : 'fail',
            'Database migrated',
            $missing === []
                ? 'core tables present'
                : 'missing tables (' . implode(', ', $missing) . ') — run `php artisan migrate`',
        );
    }

    private function checkAttributionStore(): void
    {
        $driver = (string) config('funnel.attribution_driver', 'json');

        if ($driver !== 'eloquent') {
            $this->record('ok', 'Attribution store', 'json (zero-infra)');

            return;
        }

        try {
            $exists = Schema::hasTable('funnel_attribution');
        } catch (Throwable $e) {
            $this->record('fail', 'Attribution store', 'eloquent driver, but schema check failed — ' . $e->getMessage());

            return;
        }

        $this->record(
            $exists ? 'ok' : 'fail',
            'Attribution store',
            $exists
                ? 'eloquent (funnel_attribution table present)'
                : 'eloquent driver selected but funnel_attribution table is missing — run `php artisan migrate`',
        );
    }

    private function checkStripeCharging(): void
    {
        $this->record(
            (string) config('services.stripe.secret') !== '' ? 'ok' : 'fail',
            'Stripe charging',
            (string) config('services.stripe.secret') !== ''
                ? 'STRIPE_SECRET set — pricing buttons can start checkout'
                : 'STRIPE_SECRET missing — pricing buttons fall back to the lead form (no charges possible)',
        );
    }

    private function checkStripeProvisioning(): void
    {
        $this->record(
            (string) config('services.stripe.webhook_secret') !== '' ? 'ok' : 'fail',
            'Stripe provisioning',
            (string) config('services.stripe.webhook_secret') !== ''
                ? 'STRIPE_WEBHOOK_SECRET set — paid customers get provisioned'
                : 'STRIPE_WEBHOOK_SECRET missing — customers could be CHARGED but never provisioned (the webhook rejects every event). Set it before going live.',
        );
    }

    private function checkPlans(): void
    {
        $plans = (array) config('funnel.plans', []);
        if ($plans === []) {
            $this->record('fail', 'Plans', 'no plans configured in config/funnel.php');

            return;
        }

        $currency = strtoupper((string) config('funnel.currency', 'usd'));
        $summary = [];
        foreach ($plans as $slug => $cents) {
            $summary[] = $slug . ' ' . number_format(((int) $cents) / 100, 2) . ' ' . $currency;
        }

        $this->record('ok', 'Plans', implode(' · ', $summary));
    }

    private function checkLoginAccount(): void
    {
        try {
            $count = DB::table('users')->count();
        } catch (Throwable $e) {
            $this->record('warn', 'Owner login', 'could not count users — ' . $e->getMessage());

            return;
        }

        $this->record(
            $count > 0 ? 'ok' : 'warn',
            'Owner login',
            $count > 0
                ? $count . ' user account(s) exist'
                : 'no users yet — run `php artisan db:seed` so you can log into the console',
        );
    }

    private function record(string $status, string $label, string $detail): void
    {
        $this->results[] = compact('status', 'label', 'detail');

        [$icon, $color] = match ($status) {
            'ok' => ['✔', 'green'],
            'warn' => ['!', 'yellow'],
            default => ['x', 'red'],
        };

        $this->line(sprintf('  <fg=%s>%s</>  <options=bold>%s</> — %s', $color, $icon, $label, $detail));
    }

    private function report(): int
    {
        $fails = count(array_filter($this->results, fn (array $r): bool => $r['status'] === 'fail'));
        $warns = count(array_filter($this->results, fn (array $r): bool => $r['status'] === 'warn'));

        $this->line('');

        if ($fails > 0) {
            $this->line(sprintf(
                '  <bg=red;fg=white;options=bold> NOT READY </> %d blocker(s) between you and your first paid customer.',
                $fails,
            ));
            $this->line('  <fg=gray>Fix the red items above, then re-run this command.</>');
            $this->line('');

            return self::FAILURE;
        }

        if ($warns > 0) {
            $this->line(sprintf(
                '  <bg=yellow;fg=black;options=bold> READY </> Payments can be taken. %d warning(s) worth a look.',
                $warns,
            ));
            $this->line('');

            return self::SUCCESS;
        }

        $this->line('  <bg=green;fg=white;options=bold> READY </> Everything checks out — this install can take money.');
        $this->line('');

        return self::SUCCESS;
    }
}
