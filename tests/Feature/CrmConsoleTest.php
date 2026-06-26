<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CrmConsoleTest extends TestCase
{
    public function test_console_page_renders(): void
    {
        $this->get('/app')
            ->assertOk()
            ->assertSee('CRM Console')
            ->assertSee('Sign in');
    }

    public function test_console_wires_to_the_token_auth_api(): void
    {
        // The SPA authenticates client-side against the API login endpoint.
        $this->get('/app')
            ->assertOk()
            ->assertSee('/auth/login')
            ->assertSee('/accounts');
    }
}
