#!/usr/bin/env python3
"""Fable 5 trading bot — main loop.

Usage:
    python bot.py --once   # one decision cycle, then exit
    python bot.py          # run continuously
"""

import argparse
import sys
import time

import indicators
import market_data
from analyst import Analyst
from config import Config
from executor import Executor
from risk import RiskManager


def run_cycle(cfg: Config, analyst: Analyst, risk: RiskManager, executor: Executor):
    prices = {}
    closes_by_product = {}
    for product_id in cfg.products:
        try:
            closes = market_data.get_recent_closes(product_id)
        except Exception as e:
            print(f"[{product_id}] market data error, skipping: {e}")
            continue
        if len(closes) < 30:
            print(f"[{product_id}] not enough candle data, skipping")
            continue
        closes_by_product[product_id] = closes
        prices[product_id] = closes[-1]

    if not prices:
        print("No market data this cycle.")
        return

    day_start_equity = executor.roll_day(prices)
    equity = executor.equity(prices)
    mode = "LIVE" if cfg.live_mode else "PAPER"
    print(f"\n=== cycle [{mode}] equity ${equity:,.2f} "
          f"(day start ${day_start_equity:,.2f}, cash ${executor.state['usd']:,.2f}) ===")

    for product_id, closes in closes_by_product.items():
        price = prices[product_id]
        position = executor.position(product_id, price)
        ind = indicators.summarize(closes)

        decision = analyst.decide(product_id, ind, position, executor.state["usd"], equity)
        print(f"[{product_id}] ${price:,.2f} | AI: {decision['action']} "
              f"(conf {decision['confidence']:.2f}, size {decision['size_pct']:.1f}%) "
              f"— {decision['reasoning']}")

        trade, reason = risk.review(
            decision, product_id, position, executor.state["usd"], equity,
            day_start_equity, executor.last_trade_at(product_id),
        )
        if trade is None:
            if reason != "hold":
                print(f"[{product_id}] risk manager: {reason}")
            continue

        outcome = executor.execute(product_id, trade, price, decision)
        print(f"[{product_id}] {outcome}")


def main():
    parser = argparse.ArgumentParser(description="Fable 5 Coinbase trading bot")
    parser.add_argument("--once", action="store_true", help="run one cycle and exit")
    args = parser.parse_args()

    cfg = Config()
    problems = cfg.validate()
    for p in problems:
        print(f"CONFIG: {p}")
    if not cfg.anthropic_api_key:
        sys.exit(1)

    if cfg.live_mode:
        print("*** LIVE TRADING ENABLED — real orders will be placed on Coinbase. ***")
    else:
        print("Paper-trading mode (simulated portfolio, real market data).")

    analyst = Analyst(cfg.anthropic_api_key, cfg.anthropic_model)
    risk = RiskManager(cfg)
    executor = Executor(cfg)

    while True:
        try:
            run_cycle(cfg, analyst, risk, executor)
        except KeyboardInterrupt:
            print("\nStopped.")
            break
        except Exception as e:
            print(f"Cycle error (bot keeps running): {e}")
        if args.once:
            break
        time.sleep(cfg.decision_interval_minutes * 60)


if __name__ == "__main__":
    main()
