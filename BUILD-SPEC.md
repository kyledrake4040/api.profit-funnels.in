# Build Spec — All-in-One Marketing OS (white-label, resellable)

A multi-tenant, white-label marketing & CRM platform in the GoHighLevel category:
agencies resell the product under their own brand to client sub-accounts. This
spec is the roadmap; it is built in phases, each one production-deployable,
tested, and CI-gated before the next begins.

> **Original work only.** No third-party trademarks, branding, or proprietary
> assets/code. We build the *category and capabilities*, under our own brand.

## Tenancy model

```
Agency (reseller)  ──owns──▶  Account (client sub-account)  ──has──▶  Users (with role)
     │                              │
     │ white-label brand            │ funnels, pages, contacts,
     │ + markup pricing             │ conversations, automations…
```

- **Agency** — the reseller. Owns its branding (name, logo, color, custom domain)
  and sets markup pricing for its sub-accounts.
- **Account** — a single client workspace under an agency. All product data
  (funnels, contacts, campaigns…) is scoped to an account.
- **Membership** — users join accounts with a role (Owner / Admin / User).

## Phases

| # | Phase | Scope |
|---|-------|-------|
| **1** | **Tenancy foundation** | `agencies`, `accounts`, `account_user` membership + roles, white-label fields, relationships. **(this PR)** |
| 2 | Auth & access control | Agency/account-scoped auth, permission gates, account switching, audit log |
| 3 | Billing (reseller) | Stripe Connect agency onboarding, per-sub-account plans + agency markup |
| 4 | CRM core | Contacts, custom fields, tags, smart lists, pipelines & opportunities |
| 5 | Conversations | Omnichannel inbox (SMS/email), templates, Twilio number provisioning |
| 6 | Calendars | Team/round-robin booking, Google/Outlook sync, reminders |
| 7 | Builders | Funnel/landing-page builder (extends existing `funnels`/`funnel_pages`), forms, email campaigns |
| 8 | Automation engine | Event-driven visual workflows (trigger → condition → action), idempotent |
| 9 | Reputation | Review-request automations, Google Business Profile, review inbox |
| 10 | Reporting | Per-account dashboards + agency roll-up; lead-source attribution (reuses the funnel attribution engine) |
| 11 | Reseller console | Create/suspend sub-accounts, snapshots/templates, branded login |

## What already exists to build on

This repo is not starting from zero — several Phase 4/7/10 building blocks are live:

- **Attribution engine** (`app/Funnel/`) — lead→revenue tracking → Phase 10.
- **Funnels & pages** (`funnels`, `funnel_pages` tables, PR #28) → Phase 7.
- **Plans & subscriptions** (PR #28) + **Stripe checkout/provisioning** (PR #27/#29)
  → the seed of Phase 3 billing.
- **Payments + webhook** (`StripeWebhookController`) → Phase 3.

## Engineering standards

- Laravel 12 / PHP 8.3, `declare(strict_types=1)`, typed signatures.
- Every external integration behind an interface with a fake/sandbox driver so it
  is testable without live keys (see `App\Funnel\Payments\PaymentGateway`).
- Strict tenant isolation: queries always scoped by `account_id` (and `agency_id`).
- Automated tests for every module; CI green before merge.

---

### Phase 1 — delivered in this PR

`agencies` → `accounts` → `account_user` (role-bearing membership), the `Agency`
and `Account` models, `User` relationships (`ownedAgencies`, `accounts`), status
& role vocab in `config/custom.php`, and tenancy tests. This is the spine every
later phase scopes its data to.
