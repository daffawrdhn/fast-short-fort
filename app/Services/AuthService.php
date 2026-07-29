<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Hash;
use App\Models\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PDO;

class AuthService
{
    public function authenticate(string $email, string $password): User|false
    {
        $user = User::findByEmail($email);
        if ($user === null) {
            return false;
        }
        if (!Hash::check($password, $user->password_hash)) {
            return false;
        }
        return $user;
    }

    public function createSession(User $user): void
    {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_is_admin'] = $user->is_admin;
    }

    public function generateRememberToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $user->update(['remember_token' => $hash]);
        return $token;
    }

    public function validateRememberToken(string $token): ?User
    {
        $hash = hash('sha256', $token);
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM users WHERE remember_token = :token LIMIT 1');
        $stmt->execute([':token' => $hash]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        return User::findById((int) $data['id']);
    }

    public function clearRememberToken(User $user): void
    {
        $user->update(['remember_token' => null]);
    }

    public function generateTwoFASecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function getTwoFAQRCode(string $secret, string $email): string
    {
        $issuer = Env::get('TOTP_ISSUER', 'FORT (Fast Short)');
        $encodedIssuer = rawurlencode($issuer);
        $encodedEmail = rawurlencode($email);
        $uri = "otpauth://totp/{$encodedIssuer}:{$encodedEmail}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";

        $renderer = new ImageRenderer(
            new RendererStyle(200, 0),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($uri);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function verifyTwoFACode(string $secret, string $code): bool
    {
        $normalized = $this->normalizeBase32($secret);
        $decoded = $this->base32Decode($normalized);
        if ($decoded === false || $decoded === '') {
            return false;
        }

        $timeSlice = (int) floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals($this->generateTOTP($decoded, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateTOTP(string $key, int $timeSlice): string
    {
        $data = pack('J', $timeSlice);
        $hash = hash_hmac('sha1', $data, $key, true);

        $offset = ord($hash[19]) & 0xf;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
                | ((ord($hash[$offset + 1]) & 0xff) << 16)
                | ((ord($hash[$offset + 2]) & 0xff) << 8)
                | (ord($hash[$offset + 3]) & 0xff);

        $otp = $binary % 1000000;
        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }
        return $encoded;
    }

    private function base32Decode(string $data): string|false
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $binary = '';
        foreach (str_split($data) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                return false;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $decoded .= chr(bindec($chunk));
        }
        return $decoded;
    }

    private function normalizeBase32(string $secret): string
    {
        return strtoupper(str_replace([' ', '-', '='], '', $secret));
    }
}
