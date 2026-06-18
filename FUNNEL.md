# Profit Funnel Engine

An automated content + sales engine for **Gulf Coast Painting PEI**.

It does four things:

1. **Plans** an alternating content calendar — every 2nd video is your business
   (a value-first tip that *solves a problem*, never a sales pitch), the others
   are viral-style formats.
2. **Builds** each video automatically — real 9:16 (1080×1920) scene-card frames
   + a watchable HTML slideshow you can drop into Canva or screen-record. (If the
   server has `ffmpeg`, it also outputs a finished `.mp4`.)
3. **Publishes** due posts to TikTok / Instagram / YouTube on a schedule.
4. **Monetizes** with a Stripe checkout link for your offer.

Everything runs **today in dry-run mode** with zero setup. You flip it to live by
adding credentials — nothing else changes.

---

## Run it now (no setup)

```bash
php bin/funnel demo 6      # plan + build + publish (dry-run) + checkout link
```

Other commands:

```bash
php bin/funnel plan 6      # just plan the calendar
php bin/funnel build       # render the videos (frames + player.html [+ mp4])
php bin/funnel run         # publish any posts that are due now
php bin/funnel checkout    # create a checkout link
```

Output lands in `storage/funnel/`:

```
storage/funnel/
  queue.json                     # the content calendar
  output/<post-id>/
    frame_00.png ... frame_NN.png  # 1080x1920 scene cards (ready for Canva)
    player.html                    # open in a browser to watch the video
    post.json                      # script, caption, hashtags, Canva brief
    video.mp4                      # only if ffmpeg is installed
```

**What you do:** open `player.html` (or use the frames/brief in Canva), finish
the video with your own b-roll + trending audio, and post it. The captions,
hashtags, and scene-by-scene brief are already written for you.

---

## What's automated vs. what's you

| Step | Automated by the engine | Needs you |
|------|-------------------------|-----------|
| Content ideas, hooks, scripts, captions, hashtags | ✅ | — |
| Scene-card frames + slideshow per video | ✅ | — |
| Finished `.mp4` | ✅ *(if ffmpeg installed)* | b-roll / trending audio for best results |
| Scheduling + posting | ✅ | connect your social accounts (below) |
| Checkout / taking money | ✅ | connect Stripe (below) |
| Actual filming of real before/after clips | — | ✅ your footage makes it convert |

The engine will **not** copy other people's videos — it generates original briefs
based on formats that tend to perform.

---

## Go live

### 1. Connect your socials

Posting to TikTok/Instagram/YouTube needs your account authorization. The
`ApiPublisher` posts to any HTTP posting API with a bearer token — either a
platform's own API or a multi-platform posting service. Set, per platform:

```bash
export FUNNEL_TIKTOK_ENDPOINT="https://<posting-api>/post"
export FUNNEL_TIKTOK_TOKEN="<your token>"
export FUNNEL_INSTAGRAM_ENDPOINT="..."
export FUNNEL_INSTAGRAM_TOKEN="..."
export FUNNEL_YOUTUBE_ENDPOINT="..."
export FUNNEL_YOUTUBE_TOKEN="..."
```

When both an endpoint and token are set for a platform, the engine switches that
platform from dry-run to **real posting** automatically.

### 2. Connect Stripe (take payments)

```bash
export STRIPE_SECRET="sk_test_..."     # use a test key first, then sk_live_...
export FUNNEL_OFFER_NAME="Free Pressure Washing Quote — Priority Booking"
export FUNNEL_OFFER_AMOUNT_CENTS="4900"
export FUNNEL_CURRENCY="cad"
export FUNNEL_SUCCESS_URL="https://yoursite.com/thanks"
export FUNNEL_CANCEL_URL="https://yoursite.com/"
```

`php bin/funnel checkout` then returns a real, payable Stripe Checkout link to put
in your bio / pinned comment.

### 3. Automate the schedule

Run the publisher every few minutes via cron (or Laravel's scheduler):

```cron
*/5 * * * * cd /path/to/app && php bin/funnel run >> storage/funnel/run.log 2>&1
```

### Optional config

```bash
export FUNNEL_BUSINESS_NAME="Gulf Coast Painting PEI"
export FUNNEL_LOCATION="Prince Edward Island"
export FUNNEL_SERVICES="golf course painting,pressure washing"
export FUNNEL_PLATFORMS="tiktok,instagram,youtube"
export FUNNEL_FONT="/path/to/a/Bold.ttf"   # nicer frame text
```

---

## A note on expectations

This engine removes the *busywork* — ideas, scripts, captions, draft videos,
scheduling, and the checkout. It can't guarantee a video goes viral or that money
arrives automatically; that still depends on real footage, consistent posting, and
an offer people want. What it does guarantee is that you can ship a steady stream
of on-brand, problem-solving videos with a working "buy" link, without starting
from a blank page every time.

---

## Code map

```
app/Funnel/
  FunnelConfig.php             # config from env (business profile, offer, keys)
  VideoPost.php                # one scheduled post
  Scheduler.php                # publishes due posts
  Content/ContentPlanner.php   # alternating value/viral calendar
  Content/VideoBuilder.php     # renders frames + player.html [+ mp4]
  Publishing/                  # PlatformPublisher, DryRun, ApiPublisher, results
  Payments/                    # PaymentGateway, Stripe, Fake, CheckoutLink
  Storage/JsonVideoStore.php   # JSON-backed queue
bin/funnel                     # CLI entrypoint
tests/Unit/Funnel/             # 20 tests covering the engine
```

Run the tests:

```bash
./vendor/bin/phpunit --testsuite Unit
```
