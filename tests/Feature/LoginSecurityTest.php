<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Signing in: inactive accounts, brute force, forced password change, password strength, security headers. */
class LoginSecurityTest extends TestCase
{
    private function person(array $attrs = []): User
    {
        $u = $this->makeUser(array_merge(['username' => 'p.okello', 'first_name' => 'Peter', 'last_name' => 'Okello'], $attrs));
        $u->forceFill(['password' => Hash::make('Start-Pass-2026')])->save();
        RateLimiter::clear('login:' . sha1('p.okello|127.0.0.1'));
        RateLimiter::clear('login-ip:' . sha1('127.0.0.1'));

        return $u;
    }

    public function test_inactive_accounts_cannot_sign_in()
    {
        $this->person(['status' => 'Inactive']);
        $this->post('/auth/login', ['username' => 'p.okello', 'password' => 'Start-Pass-2026'])->assertSessionHasErrors('username');
        $this->assertFalse(auth('admin')->check());
    }

    public function test_repeated_failures_lock_the_account_for_a_while()
    {
        $this->person();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/auth/login', ['username' => 'p.okello', 'password' => 'wrong-' . $i]);
        }
        $this->post('/auth/login', ['username' => 'p.okello', 'password' => 'Start-Pass-2026'])
            ->assertSessionHasErrors(['username' => 'Too many failed attempts. Try again in 60 seconds.']);
        $this->assertFalse(auth('admin')->check(), 'even the right password waits out the lock');
    }

    public function test_weak_password_accounts_must_choose_a_new_one_first()
    {
        $u = $this->person(['must_change_password' => true]);
        $this->post('/auth/login', ['username' => 'p.okello', 'password' => 'Start-Pass-2026'])->assertRedirect(admin_url('auth/setting'));
        $this->actingAs($u, 'admin')->get('/')->assertRedirect(admin_url('auth/setting'));
        $this->actingAs($u, 'admin')->get('/auth/setting')->assertOk()->assertSee('Please choose a new password');

        $this->actingAs($u, 'admin')->put('/auth/setting', ['first_name' => 'Peter', 'last_name' => 'Okello', 'password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertSessionHasErrors('password');
        $this->assertTrue($u->fresh()->must_change_password);

        $this->actingAs($u, 'admin')->put('/auth/setting', ['first_name' => 'Peter', 'last_name' => 'Okello', 'password' => 'nile', 'password_confirmation' => 'nile']);
        $u = $u->fresh();
        $this->assertFalse($u->must_change_password);
        $this->assertTrue(Hash::check('nile', $u->password));
        $this->actingAs($u, 'admin')->get('/')->assertOk();
    }

    public function test_pages_carry_security_headers()
    {
        $res = $this->get('/auth/login');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
