<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    public function test_logout_invalidates_session_and_logs_out_user(): void
    {
        $pengguna = new Pengguna();

        $response = $this->actingAs($pengguna)
            ->withSession(['test_session' => 'aktif'])
            ->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
        $response->assertSessionMissing('test_session');
    }

    public function test_session_timeout_is_configured_to_30_minutes(): void
    {
        $this->assertSame(30, config('session.lifetime'));
    }

    public function test_session_cookie_has_http_only_enabled(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_logout_expires_session_cookie(): void
    {
        $pengguna = new Pengguna();

        $response = $this->actingAs($pengguna)
            ->post('/logout');

        $response->assertRedirect('/login');

        $response->assertCookieExpired(
            config('session.cookie')
        );
    }
}
