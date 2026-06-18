<?php

declare(strict_types=1);

namespace App\Funnel\Publishing;

use App\Funnel\VideoPost;

/**
 * Real HTTP publisher. Posts a video to a social platform via an HTTP posting
 * API using a bearer token. This works against a platform's own API or a
 * multi-platform posting service (the realistic way a small business automates
 * TikTok + Instagram + YouTube at once).
 *
 * It stays in a safe "not connected" state until BOTH an endpoint and a token
 * are configured, so it can never silently pretend to post.
 *
 * Env per platform, e.g. for tiktok:
 *   FUNNEL_TIKTOK_ENDPOINT=https://api.example.com/post
 *   FUNNEL_TIKTOK_TOKEN=xxxxx
 */
final class ApiPublisher implements PlatformPublisher
{
    /** @param callable|null $transport for tests: fn(string $url, array $opts): array{status:int,body:string} */
    public function __construct(
        private readonly string $platform,
        private readonly ?string $endpoint,
        private readonly ?string $token,
        private readonly ?string $mediaUrl = null,
        private $transport = null,
    ) {
    }

    public static function fromEnv(string $platform): self
    {
        $key = strtoupper($platform);

        return new self(
            $platform,
            self::env("FUNNEL_{$key}_ENDPOINT"),
            self::env("FUNNEL_{$key}_TOKEN"),
            self::env("FUNNEL_{$key}_MEDIA_URL"),
        );
    }

    public function name(): string
    {
        return $this->platform;
    }

    public function isConnected(): bool
    {
        return $this->endpoint !== null && $this->endpoint !== ''
            && $this->token !== null && $this->token !== '';
    }

    public function publish(VideoPost $post): PublishResult
    {
        if (! $this->isConnected()) {
            return PublishResult::fail(
                $this->platform,
                "not connected — set FUNNEL_" . strtoupper($this->platform) . "_ENDPOINT and _TOKEN"
            );
        }

        $payload = [
            'caption' => $post->caption,
            'hashtags' => implode(' ', $post->hashtags),
            'title' => $post->title,
            'media_url' => $this->mediaUrl,
            'platform' => $this->platform,
        ];

        try {
            $response = $this->send((string) $this->endpoint, $payload);
        } catch (\Throwable $e) {
            return PublishResult::fail($this->platform, 'request failed: ' . $e->getMessage());
        }

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $decoded = json_decode($response['body'], true);
            $ref = is_array($decoded) ? (string) ($decoded['id'] ?? $decoded['post_id'] ?? 'ok') : 'ok';

            return PublishResult::ok($this->platform, $ref);
        }

        return PublishResult::fail($this->platform, 'HTTP ' . $response['status'] . ': ' . substr($response['body'], 0, 200));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:string}
     */
    private function send(string $url, array $payload): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, ['token' => $this->token, 'payload' => $payload]);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException($err !== '' ? $err : 'curl error');
        }

        return ['status' => $status, 'body' => (string) $body];
    }

    private static function env(string $key): ?string
    {
        $v = getenv($key);

        return $v === false || $v === '' ? null : (string) $v;
    }
}
