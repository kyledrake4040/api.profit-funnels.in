"""Order execution + portfolio state. Paper mode simulates; live mode hits Coinbase."""

import csv
import datetime as dt
import json
import os
import uuid

from config import Config


class Executor:
    def __init__(self, cfg: Config):
        self.cfg = cfg
        os.makedirs(cfg.state_dir, exist_ok=True)
        self.state_path = os.path.join(cfg.state_dir, "portfolio.json")
        self.trades_path = os.path.join(cfg.state_dir, "trades.csv")
        self.state = self._load_state()

        self.live_client = None
        if cfg.live_mode:
            from coinbase.rest import RESTClient
            self.live_client = RESTClient(
                api_key=cfg.coinbase_api_key, api_secret=cfg.coinbase_api_secret
            )

    # ---------- state ----------

    def _load_state(self) -> dict:
        if os.path.exists(self.state_path):
            with open(self.state_path) as f:
                return json.load(f)
        return {
            "usd": self.cfg.paper_starting_usd,
            "positions": {},          # product_id -> {"qty": float, "avg_price": float}
            "last_trade_at": {},      # product_id -> ISO timestamp
            "day": None,              # {"date": "YYYY-MM-DD", "start_equity": float}
        }

    def _save_state(self):
        with open(self.state_path, "w") as f:
            json.dump(self.state, f, indent=2)

    def position(self, product_id: str, last_price: float) -> dict:
        pos = self.state["positions"].get(product_id, {"qty": 0.0, "avg_price": 0.0})
        return {**pos, "last_price": last_price}

    def last_trade_at(self, product_id: str) -> dt.datetime | None:
        raw = self.state["last_trade_at"].get(product_id)
        return dt.datetime.fromisoformat(raw) if raw else None

    def equity(self, prices: dict) -> float:
        total = self.state["usd"]
        for pid, pos in self.state["positions"].items():
            total += pos["qty"] * prices.get(pid, pos.get("avg_price", 0.0))
        return total

    def roll_day(self, prices: dict) -> float:
        """Track the day's starting equity for the daily-loss circuit breaker."""
        today = dt.datetime.now(dt.timezone.utc).date().isoformat()
        if not self.state["day"] or self.state["day"]["date"] != today:
            self.state["day"] = {"date": today, "start_equity": self.equity(prices)}
            self._save_state()
        return self.state["day"]["start_equity"]

    # ---------- execution ----------

    def execute(self, product_id: str, trade: dict, price: float, decision: dict) -> str:
        """Execute an approved trade. Returns a human-readable outcome string."""
        action = trade["action"]
        notional = trade["notional_usd"]
        qty = notional / price

        if self.live_client is not None:
            outcome = self._execute_live(product_id, action, notional, qty)
        else:
            outcome = self._execute_paper(product_id, action, notional, qty, price)

        self.state["last_trade_at"][product_id] = dt.datetime.now(dt.timezone.utc).isoformat()
        self._save_state()
        self._log_trade(product_id, action, notional, qty, price, decision, outcome)
        return outcome

    def _execute_paper(self, product_id, action, notional, qty, price) -> str:
        pos = self.state["positions"].setdefault(product_id, {"qty": 0.0, "avg_price": 0.0})
        if action == "buy":
            new_qty = pos["qty"] + qty
            pos["avg_price"] = (pos["qty"] * pos["avg_price"] + qty * price) / new_qty
            pos["qty"] = new_qty
            self.state["usd"] -= notional
            return f"PAPER BUY {qty:.6f} {product_id} @ ${price:,.2f} (${notional:,.2f})"
        qty = min(qty, pos["qty"])
        pos["qty"] -= qty
        self.state["usd"] += qty * price
        if pos["qty"] <= 1e-9:
            del self.state["positions"][product_id]
        return f"PAPER SELL {qty:.6f} {product_id} @ ${price:,.2f} (${qty * price:,.2f})"

    def _execute_live(self, product_id, action, notional, qty) -> str:
        order_id = str(uuid.uuid4())
        if action == "buy":
            resp = self.live_client.market_order_buy(
                client_order_id=order_id, product_id=product_id,
                quote_size=f"{notional:.2f}",
            )
        else:
            resp = self.live_client.market_order_sell(
                client_order_id=order_id, product_id=product_id,
                base_size=f"{qty:.8f}",
            )
        result = resp.to_dict()
        if result.get("success"):
            return f"LIVE {action.upper()} {product_id} ${notional:,.2f} — order accepted"
        return f"LIVE {action.upper()} {product_id} FAILED: {result.get('error_response')}"

    def _log_trade(self, product_id, action, notional, qty, price, decision, outcome):
        new_file = not os.path.exists(self.trades_path)
        with open(self.trades_path, "a", newline="") as f:
            writer = csv.writer(f)
            if new_file:
                writer.writerow(["timestamp", "product", "action", "notional_usd", "qty",
                                 "price", "confidence", "reasoning", "outcome"])
            writer.writerow([
                dt.datetime.now(dt.timezone.utc).isoformat(), product_id, action,
                f"{notional:.2f}", f"{qty:.8f}", f"{price:.2f}",
                f"{decision['confidence']:.2f}", decision["reasoning"], outcome,
            ])
