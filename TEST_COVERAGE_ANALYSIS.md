# Test Coverage Analysis & Improvement Plan

_Branch: `claude/test-coverage-analysis-p3ycqj` · Generated 2026-06-17_

## TL;DR

The project has **no meaningful automated test coverage**. The only tests are the
two stock Laravel example tests (`tests/Unit/ExampleTest.php` and
`tests/Feature/ExampleTest.php`), and the Feature one (`GET /` returns 200)
doesn't even match the app — there is no `/` route registered for the API, so it
would fail if it could run. Coverage is configured in `phpunit.xml` but nothing
real is being measured.

Separately, **the suite cannot currently execute** in a modern PHP environment
(see Finding 0), which has to be fixed before any new tests are worth writing.

This document inventories what exists, what is testable today, and a prioritized
plan for building up coverage.

---

## Current state

| Area | Files | Tests covering them |
|------|-------|---------------------|
| API controllers | `Api/AuthController` | none |
| Auth scaffolding controllers | `Auth/*` (Login, Register, Reset, Verify, …) | none |
| Form requests / validation | `Api/AuthRequest` | none |
| Models | `User`, `Country`, `Currency`, `Role`, `Permission` | none |
| Migrations / schema | 7 migrations (incl. custom users columns) | none |
| Seeders | `Role`, `Country`, `Currency`, `User`, `Database` | none |
| Factories | `UserFactory` only | none |
| Helpers | `HelperServiceProvider` (autoloads `app/Helpers/*.php`) | none |
| Routes | `routes/api.php` (`auth/login`, `GET /user`) | none |

**Two example tests, zero assertions about real behavior.**

---

## Finding 0 (blocker): the test suite does not run

Running the suite in this environment (PHP 8.4) fails: PHPUnit boots, the Unit
example test passes, then the framework crashes while bootstrapping the Feature
suite. Laravel 8's bundled Symfony components trigger "Implicitly marking
parameter as nullable is deprecated" errors that Laravel's error handler
promotes to fatal on PHP 8.1+.

```
Symfony\Component\Console\Output\ConsoleOutput::__construct():
Implicitly marking parameter $decorated as nullable is deprecated ...
```

Until the runtime/dependency mismatch is resolved, **no test can be relied on**.
Options, cheapest first:

1. **Pin a compatible PHP** (7.4 / 8.0) in CI and local dev (`.tool-versions` /
   Docker image), matching `composer.json`'s `^7.3|^8.0`.
2. **Upgrade the framework** to a Laravel version that supports PHP 8.2–8.4
   (Laravel 10/11) — larger effort, but removes the EOL-framework risk.

Recommendation: do (1) now so tests can run, schedule (2) separately. There is
**no CI workflow** in the repo (`.github/workflows` absent); adding one is part
of this work so regressions are caught automatically.

---

## Finding 1: the one piece of real logic is an unfinished stub

`Api/AuthController::login()` is not implemented — it is a debug dump:

```php
public function login(AuthRequest $request)
{
    dd($request->all());
}
```

This means there is nothing meaningful to assert about login behavior yet
(tokens, credential checks, error responses). Tests should be written
**alongside** the real implementation, not before — otherwise they'd just pin
the `dd()` stub. This is the single highest-value place to add behavior + tests
together.

---

## Finding 2: validation rules are testable today

`Api/AuthRequest` is real, self-contained, and has a custom `failedValidation()`
that returns a specific JSON envelope:

```json
{ "code": 422, "success": false, "errors": { ... } }
```

The rules (`password` required/string/min:8/max:20) and that envelope shape are a
contract worth locking down now, independent of the unfinished controller. Note
the rules currently validate only `password` — there is **no `email`/identifier
rule**, which is likely a gap the tests will surface.

---

## Finding 3: seeders contain untested business logic

`UserSeeder`, `RoleSeeder`, `CountrySeeder`, `CurrencySeeder` aren't trivial —
they create roles, assign them to users, set status/verification flags, and guard
against duplicates (`if ... first() === null`). These run in production
provisioning, so a bug here is a real outage. They are straightforward to test
with `RefreshDatabase`.

There is also a latent bug worth a regression test: `UserSeeder` sets
`$user->status = config('custom.status_active')`, but the active-status value is
defined under `custom.user.status_active`. `config('custom.status_active')`
**does** exist (top level), so it happens to resolve — but the inconsistent key
nesting between `RoleSeeder` (uses `custom.user.*`) and `UserSeeder` (uses
`custom.*`) is exactly the kind of thing a test should pin.

---

## Finding 4: models are bare; factories are missing

- `Country`, `Currency`, `Role`, `Permission` have no `$fillable`, casts, or
  relationships yet, and **no factories** — only `UserFactory` exists.
- The `users` schema is non-trivial (self-referential `parent` FK, unique
  `(dialing_code, phone)`, soft deletes, `status`, `otp`, `is_phone_verified`),
  but `UserFactory` only fills the default Laravel columns. It produces no
  `phone`/`status`/`parent`, so any test needing those must hand-roll them.

---

## Prioritized improvement plan

### P0 — Make tests runnable & enforced
1. Fix the runtime mismatch (Finding 0): pin PHP 7.4/8.0 in CI + dev.
2. Enable the SQLite in-memory test DB in `phpunit.xml` (the lines are present
   but commented out):
   ```xml
   <server name="DB_CONNECTION" value="sqlite"/>
   <server name="DB_DATABASE" value=":memory:"/>
   ```
3. Add a CI workflow (`.github/workflows/tests.yml`) running `composer install`
   + `php artisan test` on push/PR.
4. Delete/replace the stock `Feature/ExampleTest` (`GET /` → 200) which asserts
   behavior the app doesn't have.

### P1 — Lock down what exists
5. **`AuthRequest` validation tests** (Finding 2): missing password → 422 with
   the exact `{code, success, errors}` envelope; too-short / too-long / non-string
   password rejected; valid password passes. These don't depend on the
   controller body.
6. **Seeder tests** (Finding 3): `RoleSeeder` creates the three configured roles
   and is idempotent; `UserSeeder` creates super-admin/admins, assigns roles,
   sets verification flags, and is idempotent; `Country`/`Currency` seeders
   populate rows. Add a regression assertion for the `status` config-key issue.
7. **Migration/schema test**: assert the custom `users` columns and the unique
   `(dialing_code, phone)` constraint exist and are enforced.

### P2 — Build coverage as features land
8. Implement `AuthController::login()` and write Feature tests in the same PR:
   valid credentials → token + 200; bad credentials → 401/422; inactive/
   unverified user handling; the `auth:api` guard on `GET /user` (401 without
   token, 200 with token).
9. Add factories for `Country`, `Currency`, and a `RoleFactory`; extend
   `UserFactory` with states for `phone`, `status`, verified/unverified, and a
   `withParent()` state.
10. Add a smoke test for `HelperServiceProvider` once helpers exist (drop a file
    in `app/Helpers/`, assert the function is globally available).

### P3 — Guardrails
11. Add a coverage threshold to CI (start low, e.g. 40%, ratchet up) so coverage
    can't silently regress.
12. Add static analysis (PHPStan/Larastan) and the existing StyleCI config to CI
    to catch issues tests don't.

---

## Suggested first test (illustrative)

This is the kind of low-risk, high-value test to start with — it depends only on
the already-finished `AuthRequest`, not the stubbed controller. (It requires P0
items 1–2 to actually run.)

```php
<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_a_password(): void
    {
        $this->postJson(route('auth.login'), [])
            ->assertStatus(422)
            ->assertJson(['code' => 422, 'success' => false])
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_login_rejects_a_too_short_password(): void
    {
        $this->postJson(route('auth.login'), ['password' => 'short'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
```

> Note: this test exercises validation only. A full `login` happy-path test
> can't be written until `AuthController::login()` is implemented (Finding 1),
> since the method currently `dd()`s the request.

---

## Summary of recommendations

- **Unblock first:** the suite can't run on modern PHP and there's no CI — fix
  the runtime and add a workflow before writing tests.
- **Test what's real now:** `AuthRequest` validation and the seeders.
- **Test features as they're built:** start with `login` + the `auth:api` guard.
- **Add infrastructure:** SQLite test DB, factories/states, coverage threshold,
  static analysis.
