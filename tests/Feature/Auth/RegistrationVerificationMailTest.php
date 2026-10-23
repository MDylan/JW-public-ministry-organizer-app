<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 55.3: a new user gets exactly one verification mail.
 *
 * Laravel 11's EventServiceProvider subscribes
 * SendEmailVerificationNotification to Registered by itself
 * (configureEmailVerification()). This application had left that listener
 * out on purpose since 2022 and sent its own UserRegisteredNotification
 * from CreateNewUser, so from the TODO 55 hop on every registration mailed
 * twice - the framework's VerifyEmail and the application's own mail - and
 * the only registration test, asserting that the application's mail was
 * sent, stayed green throughout.
 *
 * The framework is now the one sender: its listener calls
 * User::sendEmailVerificationNotification(), which sends the application's
 * mail. These cases count the mails, not just their presence.
 */
class RegistrationVerificationMailTest extends FeatureTestCase
{
    public function test_registration_sends_exactly_one_verification_mail(): void
    {
        Notification::fake();

        $email = 'one-mail@example.test';

        $this->post('/register', [
            'name' => 'One Mail',
            'email' => $email,
            'phone_number' => '36201111111',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ])->assertStatus(302);

        $registered = User::where('email', $email)->firstOrFail();

        Notification::assertSentToTimes($registered, UserRegisteredNotification::class, 1);
        Notification::assertNotSentTo($registered, VerifyEmail::class);
    }

    public function test_the_resend_button_sends_the_registration_mail(): void
    {
        Notification::fake();

        $user = $this->createUser(['email_verified_at' => null]);

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertStatus(302);

        Notification::assertSentToTimes($user, UserRegisteredNotification::class, 1);
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_registered_has_the_frameworks_listener_and_only_once(): void
    {
        $listeners = Event::getRawListeners()[Registered::class] ?? [];

        $this->assertSame([SendEmailVerificationNotification::class], $listeners);
    }
}
