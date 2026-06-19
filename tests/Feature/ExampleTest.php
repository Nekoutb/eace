<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root redirects to the right front door — the admin console on a central
     * (landlord) host like the test/localhost default.
     */
    public function test_the_root_redirects_to_the_admin_console(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }
}
