"""Risk manager — every AI decision passes through here before execution."""

import datetime as dt

from config import Config


class RiskManager:
    def __init__(self, cfg: Config):
        self.cfg = cfg

    def review(self, decision: dict, product_id: str, position: dict, cash_usd: float,
               equity_usd: float, day_start_equity: float,
               last_trade_at: dt.datetime | None) -> tuple:
        """Return (approved_trade | None, reason).

        approved_trade is {"action", "notional_usd"} ready for the executor.
        """
        action = decision["action"]
        if action == "hold":
            return None, "hold"

        if decision["confidence"] < self.cfg.min_confidence:
            return None, (f"vetoed: confidence {decision['confidence']:.2f} < "
                          f"minimum {self.cfg.min_confidence}")

        # Daily circuit breaker.
        if day_start_equity > 0:
            drawdown_pct = (day_start_equity - equity_usd) / day_start_equity * 100.0
            if drawdown_pct >= self.cfg.max_daily_loss_pct:
                return None, (f"vetoed: daily loss {drawdown_pct:.1f}% >= limit "
                              f"{self.cfg.max_daily_loss_pct}% — trading halted for today")

        # Per-product cooldown.
        if last_trade_at is not None:
            elapsed_min = (dt.datetime.now(dt.timezone.utc) - last_trade_at).total_seconds() / 60
            if elapsed_min < self.cfg.trade_cooldown_minutes:
                return None, (f"vetoed: last {product_id} trade {elapsed_min:.0f}min ago, "
                              f"cooldown is {self.cfg.trade_cooldown_minutes:.0f}min")

        size_pct = min(decision["size_pct"], self.cfg.max_position_pct)
        notional = equity_usd * size_pct / 100.0

        if action == "buy":
            notional = min(notional, cash_usd)
        elif action == "sell":
            held_value = position.get("qty", 0.0) * position.get("last_price", 0.0)
            if held_value <= 0:
                return None, "vetoed: sell requested but no position held"
            notional = min(notional, held_value)

        if notional < self.cfg.min_trade_usd:
            return None, f"vetoed: trade size ${notional:.2f} below minimum ${self.cfg.min_trade_usd}"

        return {"action": action, "notional_usd": round(notional, 2)}, "approved"
