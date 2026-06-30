<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    public function test_unknown_plan_is_not_found(): void
    {
        $this->get('/checkout/bogus')->assertNotFound();
    }

    public function test_checkout_falls_back_to_lead_form_when_stripe_unconfigured(): void
    {
        config(['services.stripe.secret' => '']);

        $this->get('/checkout/pro')
            ->assertStatus(302)
            ->assertSessionHas('checkout_unavailable', 'pro');
    }

    public function test_success_page_renders(): void
    {
        $this->get('/checkout-success')
            ->assertOk()
            ->assertSee('Maritime Geo');
    }
}
