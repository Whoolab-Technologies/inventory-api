<?php


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;

class PasswordResetLink extends Mailable
{
    use Queueable, SerializesModels;

    public string $token;
    public string $email;
    public string $userType;

    public function __construct($email, $token, $userType)
    {
        $this->email = $email;
        $this->token = $token;
        $this->userType = $userType;
    }

    public function build()
    {
        $payload = [
            'token' => $this->token,
            'email' => $this->email,
            'user_type' => $this->userType,
        ];
        $encoded = urlencode(Crypt::encryptString(json_encode($payload)));
        $frontendBaseUrl = config('app.frontend_url');
        $url = "{$frontendBaseUrl}/reset-password?payload={$encoded}";
        return $this->subject('Password Reset Request')
            ->view('emails.password_reset')
            ->with([
                'resetUrl' => $url,
            ]);
    }
}
