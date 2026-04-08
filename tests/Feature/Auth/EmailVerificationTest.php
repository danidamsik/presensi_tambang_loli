<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_prompt_redirects_when_feature_is_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_verify_email_link_redirects_when_feature_is_disabled(): void
    {
        $user = User::factory()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_send_verification_notification_redirects_when_feature_is_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertRedirect(RouteServiceProvider::HOME);
    }
}
