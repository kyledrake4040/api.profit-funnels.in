"""Configuration loaded from environment / .env."""

import os
from dataclasses import dataclass, field

from dotenv import load_dotenv

load_dotenv()

LIVE_CONFIRMATION_PHRASE = "I understand I can lose money"


def _env_bool(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None or raw.strip() == "":
        return default
    return raw.strip().lower() in ("1", "true", "yes", "on")


def _env_float(name: str, default: float) -> float:
    raw = os.getenv(name)
    return float(raw) if raw not in (None, "") else default


@dataclass
class Config:
    anthropic_api_key: str = os.getenv("ANTHROPIC_API_KEY", "")
    anthropic_model: str = os.getenv("ANTHROPIC_MODEL", "claude-fable-5")

    dry_run: bool = _env_bool("DRY_RUN", True)
    live_trading_confirmed: str = os.getenv("LIVE_TRADING_CONFIRMED", "")
    paper_starting_usd: float = _env_float("PAPER_STARTING_USD", 1000.0)

    coinbase_api_key: str = os.getenv("COINBASE_API_KEY", "")
    coinbase_api_secret: str = os.getenv("COINBASE_API_SECRET", "")

    products: list = field(default_factory=lambda: [
        p.strip() for p in os.getenv("PRODUCTS", "BTC-USD,ETH-USD").split(",") if p.strip()
    ])
    decision_interval_minutes: float = _env_float("DECISION_INTERVAL_MINUTES", 15.0)

    max_position_pct: float = _env_float("MAX_POSITION_PCT", 10.0)
    max_daily_loss_pct: float = _env_float("MAX_DAILY_LOSS_PCT", 5.0)
    min_confidence: float = _env_float("MIN_CONFIDENCE", 0.7)
    trade_cooldown_minutes: float = _env_float("TRADE_COOLDOWN_MINUTES", 60.0)
    min_trade_usd: float = _env_float("MIN_TRADE_USD", 10.0)

    state_dir: str = os.path.join(os.path.dirname(os.path.abspath(__file__)), "state")

    @property
    def live_mode(self) -> bool:
        """True only when every live-trading guard is explicitly satisfied."""
        return (
            not self.dry_run
            and self.live_trading_confirmed.strip() == LIVE_CONFIRMATION_PHRASE
            and bool(self.coinbase_api_key)
            and bool(self.coinbase_api_secret)
        )

    def validate(self) -> list:
        problems = []
        if not self.anthropic_api_key:
            problems.append("ANTHROPIC_API_KEY is not set (required for the AI analyst).")
        if not self.dry_run and not self.live_mode:
            problems.append(
                "DRY_RUN=false but live trading is not fully enabled. Live mode needs "
                f"COINBASE_API_KEY, COINBASE_API_SECRET, and LIVE_TRADING_CONFIRMED set to "
                f"the exact phrase '{LIVE_CONFIRMATION_PHRASE}'. Staying in paper mode."
            )
        return problems
