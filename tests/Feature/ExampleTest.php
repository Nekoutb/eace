<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The bare host is the customer front door: it sends visitors into the
     * compliance application (/app), not the landlord admin console.
     */
    public function test_the_root_redirects_to_the_application(): void
    {
        $this->get('/')->assertRedirect('/app');
    }
}
