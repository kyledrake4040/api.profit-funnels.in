<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

/**
 * Minimal Claude (Anthropic) Messages API client — raw HTTPS via curl, no SDK
 * dependency, mirroring App\Funnel\Payments\StripePaymentGateway. A $transport
 * hook makes it testable without network access.
 *
 * Set CLAUDE_API_KEY (sk-ant-...) to enable. Without it the client reports
 * isConfigured() === false so AI features degrade gracefully, exactly like the
 * Stripe checkout does without a secret key.
 */
final class ClaudeClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    /** @param callable|null $transport for tests: fn(array $payload, string $key): array{status:int,body:string} */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-4-8',
        private $transport = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Single-turn completion: a system prompt + one user message, returns the
     * concatenated text of the response.
     */
    public function complete(string $system, string $userMessage, int $maxTokens = 600): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('CLAUDE_API_KEY is not configured.');
        }

        $payload = [
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        $response = $this->send($payload);
        $decoded  = json_decode($response['body'], true);

        if ($response['status'] < 200 || $response['status'] >= 300 || ! is_array($decoded)) {
            $message = is_array($decoded) && isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : ('HTTP ' . $response['status']);
            throw new RuntimeException('Claude API error: ' . $message);
        }

        $text = '';
        foreach ($decoded['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return trim($text);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:string}
     */
    private function send(array $payload): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($payload, $this->apiKey);
        }

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'anthropic-version: ' . self::API_VERSION,
                'x-api-key: ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException($err !== '' ? $err : 'curl error');
        }

        return ['status' => $status, 'body' => (string) $body];
    }
}
