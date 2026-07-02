"""Technical indicators computed from candle closes (no numpy needed)."""


def sma(closes: list, period: int) -> float | None:
    if len(closes) < period:
        return None
    return sum(closes[-period:]) / period


def rsi(closes: list, period: int = 14) -> float | None:
    if len(closes) < period + 1:
        return None
    gains, losses = [], []
    for prev, cur in zip(closes[-(period + 1):-1], closes[-period:]):
        change = cur - prev
        gains.append(max(change, 0.0))
        losses.append(max(-change, 0.0))
    avg_gain = sum(gains) / period
    avg_loss = sum(losses) / period
    if avg_loss == 0:
        return 100.0
    rs = avg_gain / avg_loss
    return 100.0 - (100.0 / (1.0 + rs))


def pct_change(closes: list, lookback: int) -> float | None:
    if len(closes) < lookback + 1 or closes[-(lookback + 1)] == 0:
        return None
    return (closes[-1] - closes[-(lookback + 1)]) / closes[-(lookback + 1)] * 100.0


def volatility_pct(closes: list, period: int = 20) -> float | None:
    """Std deviation of per-candle returns over the period, as a percentage."""
    if len(closes) < period + 1:
        return None
    returns = [
        (b - a) / a for a, b in zip(closes[-(period + 1):-1], closes[-period:]) if a != 0
    ]
    if not returns:
        return None
    mean = sum(returns) / len(returns)
    var = sum((r - mean) ** 2 for r in returns) / len(returns)
    return (var ** 0.5) * 100.0


def summarize(closes: list) -> dict:
    """Bundle of indicators for the AI analyst. Values may be None on thin data."""
    return {
        "last_price": closes[-1] if closes else None,
        "sma_10": sma(closes, 10),
        "sma_30": sma(closes, 30),
        "rsi_14": rsi(closes, 14),
        "pct_change_4h": pct_change(closes, 16),   # 16 x 15min candles
        "pct_change_24h": pct_change(closes, 96),  # 96 x 15min candles
        "volatility_20": volatility_pct(closes, 20),
    }
