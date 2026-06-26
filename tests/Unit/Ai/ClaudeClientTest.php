<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\ClaudeClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClaudeClientTest extends TestCase
{
    public function test_it_reports_unconfigured_without_a_key(): void
    {
        self::assertFalse((new ClaudeClient(''))->isConfigured());
        self::assertTrue((new ClaudeClient('sk-ant-x'))->isConfigured());
    }

    public function test_complete_sends_the_expected_payload_and_returns_text(): void
    {
        $captured = null;
        $transport = function (array $payload, string $key) use (&$captured): array {
            $captured = ['payload' => $payload, 'key' => $key];

            return [
                'status' => 200,
                'body'   => json_encode(['content' => [
                    ['type' => 'text', 'text' => 'Hi Sarah, thanks for reaching out!'],
                ]]),
            ];
        };

        $client = new ClaudeClient('sk-ant-test', 'claude-opus-4-8', $transport);
        $text   = $client->complete('You are helpful.', 'Draft a reply.', 600);

        self::assertSame('Hi Sarah, thanks for reaching out!', $text);
        self::assertSame('sk-ant-test', $captured['key']);
        self::assertSame('claude-opus-4-8', $captured['payload']['model']);
        self::assertSame('You are helpful.', $captured['payload']['system']);
        self::assertSame('Draft a reply.', $captured['payload']['messages'][0]['content']);
    }

    public function test_complete_surfaces_api_errors(): void
    {
        $transport = static fn (): array => [
            'status' => 401,
            'body'   => json_encode(['error' => ['message' => 'invalid x-api-key']]),
        ];

        $this->expectExceptionMessage('invalid x-api-key');

        (new ClaudeClient('sk-ant-bad', 'claude-opus-4-8', $transport))->complete('s', 'u');
    }

    public function test_complete_without_a_key_throws(): void
    {
        $this->expectException(RuntimeException::class);

        (new ClaudeClient(''))->complete('s', 'u');
    }
}
