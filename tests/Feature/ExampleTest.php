<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_sent_to_the_sign_in_page()
    {
        $this->get('/')->assertRedirect(url('auth/login'));
    }

    public function test_the_sign_in_page_loads()
    {
        $this->get('/auth/login')->assertOk()->assertSee('Sign in');
    }
}
