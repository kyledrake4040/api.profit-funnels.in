"""Claude Fable 5 decision engine — returns a structured trade decision."""

import json

import anthropic

SYSTEM_PROMPT = """You are the market-analysis engine inside an automated crypto \
trading bot. On each cycle you receive a JSON snapshot for one product: recent \
technical indicators and the bot's current position and cash.

Decide one of: buy, sell, hold.

Guidelines:
- Be conservative. "hold" is the correct call most of the time; only trade when \
several indicators agree.
- Never recommend buying when RSI is heavily overbought (>75) or selling purely \
on short-term noise.
- confidence is your honest probability (0 to 1) that the trade will be profitable \
over the next few hours to days. Use values below 0.7 freely — the bot ignores \
low-confidence trades by design, and a wrong high-confidence call is worse than \
a missed opportunity.
- size_pct is the percentage of total portfolio equity to deploy (1-10). Scale it \
down when volatility is high.
- Keep reasoning to 2-3 sentences."""

DECISION_SCHEMA = {
    "type": "object",
    "properties": {
        "action": {"type": "string", "enum": ["buy", "sell", "hold"]},
        "confidence": {
            "type": "number",
            "description": "Probability 0-1 that this decision is profitable.",
        },
        "size_pct": {
            "type": "number",
            "description": "Percent of portfolio equity to deploy, 1-10. Use 0 for hold.",
        },
        "reasoning": {"type": "string"},
    },
    "required": ["action", "confidence", "size_pct", "reasoning"],
    "additionalProperties": False,
}

HOLD = {"action": "hold", "confidence": 0.0, "size_pct": 0.0, "reasoning": ""}


class Analyst:
    def __init__(self, api_key: str, model: str = "claude-fable-5"):
        self.client = anthropic.Anthropic(api_key=api_key)
        self.model = model

    def decide(self, product_id: str, indicators: dict, position: dict, cash_usd: float,
               equity_usd: float) -> dict:
        snapshot = {
            "product": product_id,
            "indicators": indicators,
            "current_position": position,
            "cash_usd": round(cash_usd, 2),
            "portfolio_equity_usd": round(equity_usd, 2),
        }

        try:
            # Fable 5: thinking is always on (omit the param). Server-side fallback
            # to Opus 4.8 handles the rare safety-classifier false positive.
            response = self.client.beta.messages.create(
                model=self.model,
                max_tokens=2048,
                betas=["server-side-fallback-2026-06-01"],
                fallbacks=[{"model": "claude-opus-4-8"}],
                output_config={
                    "effort": "high",
                    "format": {"type": "json_schema", "schema": DECISION_SCHEMA},
                },
                system=SYSTEM_PROMPT,
                messages=[{"role": "user", "content": json.dumps(snapshot)}],
            )
        except anthropic.APIError as e:
            return {**HOLD, "reasoning": f"API error, holding: {e}"}

        if response.stop_reason == "refusal":
            return {**HOLD, "reasoning": "Model declined the request; holding."}

        text = next((b.text for b in response.content if b.type == "text"), None)
        if text is None:
            return {**HOLD, "reasoning": "Empty model response; holding."}

        try:
            decision = json.loads(text)
        except json.JSONDecodeError:
            return {**HOLD, "reasoning": "Unparseable model response; holding."}

        # Clamp to sane ranges regardless of what the model said.
        decision["confidence"] = max(0.0, min(1.0, float(decision.get("confidence", 0))))
        decision["size_pct"] = max(0.0, float(decision.get("size_pct", 0)))
        if decision.get("action") not in ("buy", "sell", "hold"):
            decision = {**HOLD, "reasoning": "Unknown action from model; holding."}
        return decision
