<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;

/**
 * Sent to a newly provisioned user the moment their Stripe subscription clears.
 * Contains a one-time password-set link so they can log in without knowing their
 * (randomly generated) password.
 */
final class WelcomeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $resetLink;

    public function __construct(public readonly User $user)
    {
        $token = Password::createToken($user);

        $this->resetLink = url('/password/reset') . '?' . http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Maritime Geo — set your password to get started',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome',
        );
    }
}
