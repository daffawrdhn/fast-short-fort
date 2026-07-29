<?php

declare(strict_types=1);

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use RuntimeException;

class Mailer
{
    private static ?Mailer $instance = null;
    private PHPMailer $mailer;

    private function __construct()
    {
        $this->mailer = new PHPMailer(true);

        $driver = Env::get('MAIL_DRIVER', 'smtp');

        if ($driver === 'smtp') {
            $this->mailer->isSMTP();
            $this->mailer->Host = Env::get('MAIL_HOST', 'smtp.mailtrap.io');
            $this->mailer->Port = (int)Env::get('MAIL_PORT', '2525');
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = Env::get('MAIL_USERNAME', '');
            $this->mailer->Password = Env::get('MAIL_PASSWORD', '');
            $this->mailer->SMTPSecure = Env::get('MAIL_ENCRYPTION', 'tls');

            if (Env::get('APP_ENV', 'production') === 'local') {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            }
        } elseif ($driver === 'sendmail') {
            $this->mailer->isSendmail();
        } else {
            $this->mailer->isMail();
        }

        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->Encoding = 'base64';

        $fromAddress = Env::get('MAIL_FROM_ADDRESS', 'noreply@example.com');
        $fromName = Env::get('MAIL_FROM_NAME', 'FORT (Fast Short)');
        $this->mailer->setFrom($fromAddress, $fromName);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function to(string $address, string $name = ''): self
    {
        $this->mailer->addAddress($address, $name);
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->mailer->Subject = $subject;
        return $this;
    }

    public function body(string $body, bool $isHtml = true): self
    {
        if ($isHtml) {
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
        } else {
            $this->mailer->isHTML(false);
            $this->mailer->Body = $body;
        }
        return $this;
    }

    public function template(string $template, array $data = []): self
    {
        $view = View::getInstance();
        $html = $view->renderString($template, $data);
        $this->mailer->isHTML(true);
        $this->mailer->Body = $html;

        $text = strip_tags($html);
        $this->mailer->AltBody = $text;

        return $this;
    }

    public function attach(string $path, string $name = ''): self
    {
        $this->mailer->addAttachment($path, $name);
        return $this;
    }

    public function cc(string $address, string $name = ''): self
    {
        $this->mailer->addCC($address, $name);
        return $this;
    }

    public function bcc(string $address, string $name = ''): self
    {
        $this->mailer->addBCC($address, $name);
        return $this;
    }

    public function replyTo(string $address, string $name = ''): self
    {
        $this->mailer->addReplyTo($address, $name);
        return $this;
    }

    public function send(): bool
    {
        try {
            return $this->mailer->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Mail could not be sent: ' . $this->mailer->ErrorInfo);
        }
    }

    public function reset(): void
    {
        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearReplyTos();
    }
}
