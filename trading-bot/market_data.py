"""Market data from Coinbase Advanced Trade public endpoints (no auth needed)."""

import time

from coinbase.rest import RESTClient

# Unauthenticated client — public endpoints only (candles, product info).
_public = RESTClient()

CANDLE_GRANULARITY = "FIFTEEN_MINUTE"
CANDLE_SECONDS = 15 * 60
CANDLE_COUNT = 120  # ~30 hours of 15-minute candles


def get_recent_closes(product_id: str) -> list:
    """Return recent candle close prices, oldest first."""
    end = int(time.time())
    start = end - CANDLE_COUNT * CANDLE_SECONDS
    resp = _public.get_public_candles(
        product_id=product_id,
        start=str(start),
        end=str(end),
        granularity=CANDLE_GRANULARITY,
    )
    candles = resp.to_dict().get("candles", [])
    # API returns newest first; sort oldest-first by start time.
    candles.sort(key=lambda c: int(c["start"]))
    return [float(c["close"]) for c in candles]


def get_spot_price(product_id: str) -> float:
    resp = _public.get_public_product(product_id=product_id)
    return float(resp.to_dict()["price"])
