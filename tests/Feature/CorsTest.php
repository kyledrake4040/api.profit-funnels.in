<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_allowed_origins_are_not_a_wildcard(): void
    {
        $origins = config('cors.allowed_origins');

        self::assertIsArray($origins);
        self::assertNotContains('*', $origins, 'CORS must not allow all origins');
        self::assertNotEmpty($origins);
    }

    public function test_preflight_echoes_an_allowed_origin(): void
    {
        $allowed = config('cors.allowed_origins')[0];

        $this->call('OPTIONS', '/api/auth/login', [], [], [], [
            'HTTP_ORIGIN' => $allowed,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', $allowed);
    }

    public function test_preflight_rejects_an_unlisted_origin(): void
    {
        $response = $this->call('OPTIONS', '/api/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        // The middleware must not echo a non-allow-listed origin back.
        self::assertNotSame('https://evil.example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
