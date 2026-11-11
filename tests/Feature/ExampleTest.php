<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example verifying root redirect to login when unauthenticated.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    /**
     * Test that logging out redirects the user to the login page.
     */
    public function test_user_can_logout_and_redirects_to_login(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
