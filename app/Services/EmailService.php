<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Mailer;
use App\Models\User;

class EmailService
{
    private Mailer $mailer;

    public function __construct()
    {
        $this->mailer = Mailer::getInstance();
    }

    public function sendVerificationEmail(User $user): bool
    {
        $token = bin2hex(random_bytes(32));
        // Use dedicated email_verification_token column — NOT remember_token.
        // Sharing remember_token would allow verification links to authenticate as
        // remember-me cookies and vice versa (security vulnerability).
        $user->update(['email_verification_token' => $token]);

        $appUrl = rtrim(Env::get('APP_URL', 'http://localhost'), '/');
        $link = $appUrl . '/verify-email?token=' . urlencode($token);

        $subject = 'Verify your email address - ' . Env::get('APP_NAME', 'FORT (Fast Short)');
        $body = $this->buildEmailHtml(
            'Verify Your Email',
            "Hello {$user->name},",
            'Thank you for registering. Please click the button below to verify your email address.',
            $link,
            'Verify Email'
        );

        $this->mailer->to($user->email, $user->name ?? '');
        $this->mailer->subject($subject);
        $this->mailer->body($body);
        $result = $this->mailer->send();
        $this->mailer->reset();

        return $result;
    }

    public function sendPasswordResetEmail(User $user, string $token): bool
    {
        $appUrl = rtrim(Env::get('APP_URL', 'http://localhost'), '/');
        $link = $appUrl . '/reset-password?token=' . urlencode($token);

        $subject = 'Reset your password - ' . Env::get('APP_NAME', 'FORT (Fast Short)');
        $body = $this->buildEmailHtml(
            'Reset Your Password',
            "Hello {$user->name},",
            'You requested a password reset. Click the button below to set a new password. This link expires in 1 hour.',
            $link,
            'Reset Password'
        );

        $this->mailer->to($user->email, $user->name ?? '');
        $this->mailer->subject($subject);
        $this->mailer->body($body);
        $result = $this->mailer->send();
        $this->mailer->reset();

        return $result;
    }

    public function sendInviteEmail(string $email, string $workspaceName, string $inviterName, string $token): bool
    {
        $appUrl = rtrim(Env::get('APP_URL', 'http://localhost'), '/');
        $link = $appUrl . '/invite/accept?token=' . urlencode($token);

        $subject = "You've been invited to {$workspaceName} - " . Env::get('APP_NAME', 'FORT (Fast Short)');
        $body = $this->buildEmailHtml(
            'Workspace Invitation',
            'Hello,',
            "{$inviterName} has invited you to join the workspace \"{$workspaceName}\". Click the button below to accept.",
            $link,
            'Accept Invitation'
        );

        $this->mailer->to($email);
        $this->mailer->subject($subject);
        $this->mailer->body($body);
        $result = $this->mailer->send();
        $this->mailer->reset();

        return $result;
    }

    private function buildEmailHtml(string $title, string $greeting, string $message, string $link, string $buttonText): string
    {
        $appName = Env::get('APP_NAME', 'FORT (Fast Short)');
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:40px 20px">
<table role="presentation" class="container" align="center" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto">
<tr><td style="background:#ffffff;border-radius:12px;padding:40px 30px;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
<h1 style="margin:0 0 8px;font-size:24px;color:#18181b;text-align:center">{$appName}</h1>
<hr style="border:none;border-top:1px solid #e4e4e7;margin:24px 0">
<h2 style="margin:0 0 16px;font-size:20px;color:#18181b">{$title}</h2>
<p style="margin:0 0 12px;color:#52525b;font-size:15px;line-height:1.6">{$greeting}</p>
<p style="margin:0 0 24px;color:#52525b;font-size:15px;line-height:1.6">{$message}</p>
<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:#18181b;border-radius:8px;padding:12px 32px"><a href="{$link}" style="color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;display:inline-block">{$buttonText}</a></td></tr></table>
<p style="margin:24px 0 0;color:#a1a1aa;font-size:13px">If you did not request this, please ignore this email.</p>
</td></tr>
<tr><td style="padding:20px;text-align:center"><p style="margin:0;color:#a1a1aa;font-size:12px">&copy; {$appName}</p></td></tr>
</table>
</td></tr></table>
</body>
</html>
HTML;
    }
}
