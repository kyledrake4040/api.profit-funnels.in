<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Publishing\ApiPublisher;
use App\Funnel\VideoPost;
use PHPUnit\Framework\TestCase;

final class ApiPublisherTest extends TestCase
{
    private function post(): VideoPost
    {
        return new VideoPost('a', VideoPost::TYPE_BUSINESS, 'T', 'h', 's', 'cap', ['#x'], 'b', 100, ['tiktok']);
    }

    public function test_it_is_not_connected_without_endpoint_and_token(): void
    {
        $publisher = new ApiPublisher('tiktok', null, null);

        self::assertFalse($publisher->isConnected());
        $result = $publisher->publish($this->post());
        self::assertFalse($result->success);
        self::assertStringContainsString('not connected', $result->message);
    }

    public function test_connected_publisher_posts_and_reads_the_returned_id(): void
    {
        $captured = [];
        $transport = static function (string $url, array $opts) use (&$captured): array {
            $captured = ['url' => $url, 'opts' => $opts];

            return ['status' => 201, 'body' => json_encode(['id' => 'remote-789'])];
        };

        $publisher = new ApiPublisher('tiktok', 'https://api.example.com/post', 'tok_123', 'https://cdn/v.mp4', $transport);

        self::assertTrue($publisher->isConnected());
        $result = $publisher->publish($this->post());

        self::assertTrue($result->success);
        self::assertSame('remote-789', $result->reference);
        self::assertSame('https://api.example.com/post', $captured['url']);
        self::assertSame('tok_123', $captured['opts']['token']);
        self::assertSame('cap', $captured['opts']['payload']['caption']);
    }

    public function test_non_2xx_response_is_a_failure(): void
    {
        $transport = static fn (): array => ['status' => 500, 'body' => 'server error'];
        $publisher = new ApiPublisher('youtube', 'https://api.example.com/post', 'tok', null, $transport);

        $result = $publisher->publish($this->post());

        self::assertFalse($result->success);
        self::assertStringContainsString('HTTP 500', $result->message);
    }
}
