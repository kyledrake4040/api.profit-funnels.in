# Fable 5 Trading Bot for Coinbase

An autonomous crypto trading bot that uses **Claude Fable 5** as its market-analysis
brain and the **Coinbase Advanced Trade API** for execution.

## ⚠️ Read this first — honest expectations

- **No algorithm can guarantee profit.** Most retail trading bots lose money.
  Crypto is extremely volatile and past performance predicts nothing.
- **Only ever trade money you can afford to lose completely.**
- This bot ships in **paper-trading mode** (`DRY_RUN=true`). It simulates trades
  with fake money using real live market prices. Run it in paper mode for at
  least a few weeks and look at the results before even thinking about live mode.
- The AI analysis costs real money too: each decision cycle calls the Anthropic
  API (Fable 5 is $10/$50 per million tokens — roughly 1–3 cents per cycle).

## What it does

Every cycle (default: every 15 minutes) the bot:

1. **Fetches live market data** from Coinbase (candles, price, volume) for each
   configured product (default: BTC-USD and ETH-USD). No API keys needed for this.
2. **Computes technical indicators** — moving averages, RSI, momentum, volatility.
3. **Asks Claude Fable 5** for a structured decision: `buy`, `sell`, or `hold`,
   with a confidence score, position size, and reasoning.
4. **Runs the decision through a risk manager** that can veto or shrink any trade:
   - minimum confidence threshold (default 0.7)
   - max position size per trade (default 10% of portfolio)
   - daily loss limit — trading halts for the day if equity drops 5%
   - cooldown between trades on the same product (default 60 min)
5. **Executes** — in paper mode it updates a simulated portfolio in
   `state/portfolio.json`; in live mode it places real market orders on Coinbase.
6. **Logs every decision and trade** to `state/trades.csv` so you can audit it.

## Setup

```bash
cd trading-bot
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# edit .env — you only NEED ANTHROPIC_API_KEY to run paper mode
```

Get an Anthropic API key at https://platform.claude.com/ and put it in `.env`.

## Run it (paper mode — the default)

```bash
python bot.py --once     # single decision cycle, then exit
python bot.py            # run continuously (every DECISION_INTERVAL_MINUTES)
```

Watch `state/trades.csv` and the console output. The paper portfolio starts with
$1,000 fake dollars (configurable via `PAPER_STARTING_USD`).

## Going live (please don't rush this)

Live mode places **real orders with real money**. To enable it you must do all of:

1. Create an API key at https://www.coinbase.com/settings/api with **trade**
   permission (View + Trade; do NOT grant transfer/withdrawal permission).
2. Put `COINBASE_API_KEY` and `COINBASE_API_SECRET` in `.env`.
3. Set `DRY_RUN=false`.
4. Set `LIVE_TRADING_CONFIRMED=I understand I can lose money` (exact text).

If any of those are missing the bot refuses to trade live and stays in paper mode.
Start with a small `MAX_POSITION_PCT` and a portfolio you truly can afford to lose.

## Configuration

Everything is set via `.env` — see `.env.example` for all options and defaults.

## Files

| File | Purpose |
|---|---|
| `bot.py` | Main loop — wire everything together |
| `market_data.py` | Coinbase public market data (candles, prices) |
| `indicators.py` | SMA, RSI, momentum, volatility |
| `analyst.py` | Claude Fable 5 decision engine (structured output) |
| `risk.py` | Risk manager — vetoes/clamps every decision |
| `executor.py` | Paper + live order execution, portfolio state |
| `config.py` | All settings, loaded from `.env` |

## Disclaimer

This software is provided for educational purposes, as-is, with no warranty.
It is not financial advice. You are solely responsible for any trades it makes
and any losses you incur.
