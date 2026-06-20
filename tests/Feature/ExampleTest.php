<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * With self-service onboarding open, the root is the public landing page where
     * a prospective customer can create a workspace or sign in.
     */
    public function test_the_root_shows_the_public_landing_when_signup_is_open(): void
    {
        config(['niu.self_service_signup' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee(__('app.landing.create_cta'));
    }

    /**
     * With self-service closed, the bare host stays the landlord's front door.
     */
    public function test_the_root_redirects_to_admin_when_signup_is_closed(): void
    {
        config(['niu.self_service_signup' => false]);

        $this->get('/')->assertRedirect('/admin');
    }
}
