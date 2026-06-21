<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Publishing\ApiPublisher;
use App\Funnel\VideoPost;
use PHPUnit\Framework\TestCase;

/**
 * Covers how ApiPublisher interprets HTTP responses: a success body must carry
 * a real post id, and rate-limit / server errors are surfaced distinctly.
 */
final class ApiPublisherResponseTest extends TestCase
{
    private function post(): VideoPost
    {
        return new VideoPost('a', VideoPost::TYPE_BUSINESS, 'T', 'h', 's', 'cap', ['#x'], 'b', 100, ['tiktok']);
    }

    private function publisherReturning(int $status, string $body): ApiPublisher
    {
        $transport = static fn (): array => ['status' => $status, 'body' => $body];

        return new ApiPublisher('tiktok', 'https://api.example.com/post', 'tok', null, $transport);
    }

    public function test_200_without_a_post_id_is_treated_as_failure(): void
    {
        $result = $this->publisherReturning(200, json_encode(['ok' => true]))->publish($this->post());

        self::assertFalse($result->success);
        self::assertStringContainsString('no post id', $result->message);
    }

    public function test_200_with_empty_body_is_treated_as_failure(): void
    {
        $result = $this->publisherReturning(200, '')->publish($this->post());

        self::assertFalse($result->success);
        self::assertStringContainsString('no post id', $result->message);
    }

    public function test_200_with_post_id_field_succeeds(): void
    {
        $result = $this->publisherReturning(200, json_encode(['post_id' => 'p-42']))->publish($this->post());

        self::assertTrue($result->success);
        self::assertSame('p-42', $result->reference);
    }

    public function test_429_is_reported_as_rate_limited(): void
    {
        $result = $this->publisherReturning(429, 'slow down')->publish($this->post());

        self::assertFalse($result->success);
        self::assertStringContainsString('429', $result->message);
        self::assertStringContainsString('rate limited', $result->message);
    }

    public function test_503_is_reported_as_server_error(): void
    {
        $result = $this->publisherReturning(503, 'unavailable')->publish($this->post());

        self::assertFalse($result->success);
        self::assertStringContainsString('503', $result->message);
        self::assertStringContainsString('server error', $result->message);
    }

    public function test_4xx_other_than_429_is_a_plain_failure(): void
    {
        $result = $this->publisherReturning(400, 'bad request')->publish($this->post());

        self::assertFalse($result->success);
        self::assertStringContainsString('HTTP 400', $result->message);
    }
}
