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

    public function test_console_includes_the_pipeline_board(): void
    {
        $this->get('/app')
            ->assertOk()
            ->assertSee('Pipeline board')
            ->assertSee('/opportunities')
            ->assertSee('moveDeal');
    }

    public function test_console_includes_the_jobs_module(): void
    {
        $this->get('/app')
            ->assertOk()
            ->assertSee('Schedule job')
            ->assertSee('/jobs')
            ->assertSee('completeJob');
    }

    public function test_console_includes_the_automations_builder(): void
    {
        $this->get('/app')
            ->assertOk()
            ->assertSee('Automations')
            ->assertSee('/automations')
            ->assertSee('toggleAutomation');
    }
}
