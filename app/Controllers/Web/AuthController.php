<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Database;
use App\Core\Env;
use App\Core\Hash;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;
use App\Models\PasswordReset;
use App\Models\Workspace;
use App\Services\AuthService;
use App\Services\EmailService;
use PDO;

class AuthController
{
    private View $view;
    private Session $session;

    public function __construct()
    {
        $this->view = View::getInstance();
        $this->session = Session::getInstance();
    }

    private function renderAuthPage(Response $res, string $template, array $data = []): void
    {
        $flash = [];
        foreach (['success', 'error', 'info', 'warning'] as $type) {
            if ($this->session->hasFlash($type)) {
                $flash[$type] = $this->session->flash($type);
            }
        }
        $data['flash'] = $flash;
        $data['csrf'] = $this->session->csrfToken();
        $res->view($template, $data);
    }

    // --- Login ---

    public function showLoginForm(Request $req, Response $res): void
    {
        if ($this->session->has('user_id')) {
            $res->redirect('/dashboard')->send();
            return;
        }
        $this->renderAuthPage($res, 'auth.login', ['title' => 'Sign In - FORT']);
    }

    public function login(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/login')->send();
            return;
        }

        $email = trim($req->input('email', ''));
        $password = $req->input('password', '');
        $remember = !empty($req->input('remember_me'));

        if ($email === '' || $password === '') {
            $this->session->flash('error', 'Email and password are required.');
            $res->redirect('/login')->send();
            return;
        }

        $attemptsKey = 'login_attempts_' . $req->ip();
        $attempts = $this->session->get($attemptsKey, 0);
        $lockedUntil = $this->session->get($attemptsKey . '_locked_until', 0);

        if ($lockedUntil > time()) {
            $this->session->flash('error', 'Account temporarily locked. Try again in ' . ceil(($lockedUntil - time()) / 60) . ' minutes.');
            $res->redirect('/login')->send();
            return;
        }

        if ($attempts >= 5) {
            $this->session->set($attemptsKey . '_locked_until', time() + 900);
            $this->session->set($attemptsKey, 0);
            $this->session->flash('error', 'Too many failed attempts. Account locked for 15 minutes.');
            $res->redirect('/login')->send();
            return;
        }

        $authService = new AuthService();
        $user = $authService->authenticate($email, $password);

        if ($user === false) {
            $this->session->set($attemptsKey, $attempts + 1);
            Logger::warning('Failed login attempt', ['email' => $email, 'ip' => $req->ip()]);
            $remaining = 5 - ($attempts + 1);
            $msg = 'Invalid email or password.';
            if ($remaining > 0) {
                $msg .= ' ' . $remaining . ' attempt(s) remaining.';
            }
            $this->session->flash('error', $msg);
            $res->redirect('/login')->send();
            return;
        }

        $this->session->remove($attemptsKey);
        $this->session->remove($attemptsKey . '_locked_until');

        $twofaEnabled = Env::get('FEATURE_TWOFA', 'false') === 'true';

        if ($twofaEnabled && $user->two_fa_enabled && $user->two_fa_secret !== null) {
            $_SESSION['_2fa_user_id'] = $user->id;
            $res->redirect('/twofa/challenge')->send();
            return;
        }

        $authService->createSession($user);

        if ($remember) {
            $token = $authService->generateRememberToken($user);
            $secure = Env::get('SESSION_HTTPS_ONLY', 'false') === 'true';
            setcookie('remember_me', $token, time() + 86400 * 30, '/', '', $secure, true);
        }

        $this->session->regenerate();
        $this->session->flash('success', 'Welcome back, ' . $user->name . '!');
        $res->redirect('/dashboard')->send();
    }

    // --- Register ---

    public function showRegisterForm(Request $req, Response $res): void
    {
        if ($this->session->has('user_id')) {
            $res->redirect('/dashboard')->send();
            return;
        }
        $this->renderAuthPage($res, 'auth.register', ['title' => 'Create Account - FORT']);
    }

    public function register(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/register')->send();
            return;
        }

        $name = trim($req->input('name', ''));
        $email = trim($req->input('email', ''));
        $password = $req->input('password', '');
        $passwordConfirm = $req->input('password_confirm', '');
        $terms = !empty($req->input('terms'));

        if ($name === '' || $email === '' || $password === '') {
            $this->session->flash('error', 'All fields are required.');
            $res->redirect('/register')->send();
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Please enter a valid email address.');
            $res->redirect('/register')->send();
            return;
        }

        if (strlen($password) < 8) {
            $this->session->flash('error', 'Password must be at least 8 characters.');
            $res->redirect('/register')->send();
            return;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $this->session->flash('error', 'Password must contain at least one uppercase letter.');
            $res->redirect('/register')->send();
            return;
        }

        if (!preg_match('/[a-z]/', $password)) {
            $this->session->flash('error', 'Password must contain at least one lowercase letter.');
            $res->redirect('/register')->send();
            return;
        }

        if (!preg_match('/[0-9]/', $password)) {
            $this->session->flash('error', 'Password must contain at least one number.');
            $res->redirect('/register')->send();
            return;
        }

        if ($password !== $passwordConfirm) {
            $this->session->flash('error', 'Passwords do not match.');
            $res->redirect('/register')->send();
            return;
        }

        if (!$terms) {
            $this->session->flash('error', 'You must agree to the Terms of Service.');
            $res->redirect('/register')->send();
            return;
        }

        $existing = User::findByEmail($email);
        if ($existing !== null) {
            $this->session->flash('error', 'An account with this email already exists.');
            $res->redirect('/register')->send();
            return;
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            $passwordHash = Hash::make($password);
            $stmt = $db->prepare('
                INSERT INTO users (name, email, password_hash, created_at, updated_at)
                VALUES (:name, :email, :password_hash, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $passwordHash,
            ]);

            $userId = (int) $db->lastInsertId();

            $slug = 'my-workspace-' . $userId;
            Workspace::create([
                'name' => 'My Workspace',
                'slug' => $slug,
                'owner_id' => $userId,
                'plan' => 'free',
            ]);

            $db->commit();

            $user = User::findById($userId);

            $mailDriver = Env::get('MAIL_DRIVER', '');
            $emailVerificationEnabled = Env::get('FEATURE_EMAIL_VERIFICATION', 'false') === 'true';
            if ($mailDriver === 'smtp' && $emailVerificationEnabled && $user !== null) {
                try {
                    $emailService = new EmailService();
                    $emailService->sendVerificationEmail($user);
                } catch (\Throwable $e) {
                    // Email failure does not block registration
                }
            }

            $this->session->flash('success', 'Account created successfully! Please sign in.');
            $res->redirect('/login')->send();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->session->flash('error', 'Registration failed. Please try again.');
            $res->redirect('/register')->send();
        }
    }

    // --- Logout ---

    public function logout(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid request.');
            $res->redirect('/')->send();
            return;
        }

        $userId = $this->session->get('user_id');
        if ($userId !== null) {
            $user = User::findById($userId);
            if ($user !== null) {
                $authService = new AuthService();
                $authService->clearRememberToken($user);
            }
        }

        $this->session->destroy();
        $secure = Env::get('SESSION_HTTPS_ONLY', 'false') === 'true';
        setcookie('remember_me', '', time() - 3600, '/', '', $secure, true);
        $res->redirect('/login')->send();
    }

    // --- Email Verification ---

    public function showVerifyEmail(Request $req, Response $res): void
    {
        $this->renderAuthPage($res, 'auth.verify-email', ['title' => 'Verify Email - FORT']);
    }

    public function verifyEmail(Request $req, Response $res): void
    {
        $token = $req->query('token', '');

        if ($token === '') {
            $this->session->flash('error', 'Invalid verification link.');
            $res->redirect('/login')->send();
            return;
        }

        $db = Database::connection();
        // Use dedicated email_verification_token column, NOT remember_token.
        // Using remember_token for this was a security vulnerability.
        $stmt = $db->prepare('SELECT * FROM users WHERE email_verification_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            $this->session->flash('error', 'Invalid or expired verification link.');
            $res->redirect('/login')->send();
            return;
        }

        $user = User::findById((int) $data['id']);
        if ($user === null) {
            $this->session->flash('error', 'User not found.');
            $res->redirect('/login')->send();
            return;
        }

        if ($user->email_verified_at !== null) {
            $this->session->flash('info', 'Email is already verified.');
            $res->redirect('/login')->send();
            return;
        }

        $user->verifyEmail();
        // Clear the verification token after use (single-use token)
        $user->update(['email_verification_token' => null]);

        $this->session->flash('success', 'Email verified successfully! You can now sign in.');
        $res->redirect('/login')->send();
    }

    public function resendVerification(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid request.');
            $res->redirect('/login')->send();
            return;
        }

        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $this->session->flash('error', 'You must be signed in to resend verification.');
            $res->redirect('/login')->send();
            return;
        }

        $user = User::findById($userId);
        if ($user === null) {
            $this->session->flash('error', 'User not found.');
            $res->redirect('/login')->send();
            return;
        }

        if ($user->email_verified_at !== null) {
            $this->session->flash('info', 'Your email is already verified.');
            $res->redirect('/dashboard')->send();
            return;
        }

        try {
            $emailService = new EmailService();
            $emailService->sendVerificationEmail($user);
            $this->session->flash('success', 'Verification email sent. Please check your inbox.');
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to send verification email. Please try again later.');
        }

        $res->redirect('/verify-email')->send();
    }

    // --- Password Reset ---

    public function showForgotPassword(Request $req, Response $res): void
    {
        $this->renderAuthPage($res, 'auth.forgot-password', ['title' => 'Forgot Password - FORT']);
    }

    public function sendResetLink(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        $email = trim($req->input('email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Please enter a valid email address.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        $user = User::findByEmail($email);
        if ($user === null) {
            $this->session->flash('success', 'If an account exists with this email, a reset link has been sent.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        $token = bin2hex(random_bytes(32));
        PasswordReset::create($email, $token);

        try {
            $emailService = new EmailService();
            $emailService->sendPasswordResetEmail($user, $token);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to send reset email. Please try again later.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        $this->session->flash('success', 'If an account exists with this email, a reset link has been sent.');
        $res->redirect('/login')->send();
    }

    public function showResetPassword(Request $req, Response $res, array $params): void
    {
        if (empty($params['token'])) {
            if ($this->session->hasFlash('reset_token')) {
                $params['token'] = $this->session->flash('reset_token');
            }
        }

        $token = $req->query('token', $params['token'] ?? '');

        if ($token === '') {
            $this->session->flash('error', 'Invalid password reset link.');
            $res->redirect('/login')->send();
            return;
        }

        $reset = PasswordReset::findByToken($token);
        if ($reset === null) {
            $this->session->flash('error', 'Invalid or expired password reset link.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        $this->renderAuthPage($res, 'auth.reset-password', [
            'title' => 'Reset Password - FORT',
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/login')->send();
            return;
        }

        $token = $req->input('token', '');
        $password = $req->input('password', '');
        $passwordConfirm = $req->input('password_confirm', '');

        if ($token === '' || $password === '') {
            $this->session->flash('error', 'All fields are required.');
            $res->redirect('/login')->send();
            return;
        }

        if (strlen($password) < 8) {
            $this->session->flash('error', 'Password must be at least 8 characters.');
            $this->session->flash('reset_token', $token);
            $res->redirect('/reset-password')->send();
            return;
        }

        if ($password !== $passwordConfirm) {
            $this->session->flash('error', 'Passwords do not match.');
            $this->session->flash('reset_token', $token);
            $res->redirect('/reset-password')->send();
            return;
        }

        $reset = PasswordReset::findByToken($token);
        if ($reset === null) {
            $this->session->flash('error', 'Invalid or expired reset link.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        $user = User::findByEmail($reset->email);
        if ($user === null) {
            $this->session->flash('error', 'User not found.');
            $res->redirect('/login')->send();
            return;
        }

        $user->update(['password_hash' => Hash::make($password)]);
        PasswordReset::deleteByEmail($reset->email);

        $this->session->flash('success', 'Password reset successfully! Please sign in.');
        $res->redirect('/login')->send();
    }

    // --- Two-Factor Authentication ---

    public function showTwoFA(Request $req, Response $res): void
    {
        if (!isset($_SESSION['_2fa_user_id'])) {
            $res->redirect('/login')->send();
            return;
        }

        $this->renderAuthPage($res, 'auth.twofa-challenge', ['title' => 'Two-Factor Authentication - FORT']);
    }

    public function verifyTwoFA(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/twofa/challenge')->send();
            return;
        }

        $userId = $_SESSION['_2fa_user_id'] ?? null;
        if ($userId === null) {
            $res->redirect('/login')->send();
            return;
        }

        $user = User::findById($userId);
        if ($user === null || $user->two_fa_secret === null) {
            unset($_SESSION['_2fa_user_id']);
            $res->redirect('/login')->send();
            return;
        }

        $code = trim($req->input('code', ''));
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            $this->session->flash('error', 'Please enter a valid 6-digit code.');
            $res->redirect('/twofa/challenge')->send();
            return;
        }

        $authService = new AuthService();
        if (!$authService->verifyTwoFACode($user->two_fa_secret, $code)) {
            $this->session->flash('error', 'Invalid authentication code.');
            $res->redirect('/twofa/challenge')->send();
            return;
        }

        $authService->createSession($user);
        unset($_SESSION['_2fa_user_id']);

        if (!empty($_COOKIE['remember_me'])) {
            $token = $authService->generateRememberToken($user);
            $secure = Env::get('SESSION_HTTPS_ONLY', 'false') === 'true';
            setcookie('remember_me', $token, time() + 86400 * 30, '/', '', $secure, true);
        }

        $this->session->regenerate();
        $this->session->flash('success', 'Welcome back, ' . $user->name . '!');
        $res->redirect('/dashboard')->send();
    }

    public function showSetupTwoFA(Request $req, Response $res): void
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login')->send();
            return;
        }

        $user = User::findById($userId);
        if ($user === null) {
            $res->redirect('/login')->send();
            return;
        }

        $authService = new AuthService();
        $secret = $authService->generateTwoFASecret();
        $qrCode = $authService->getTwoFAQRCode($secret, $user->email);

        $_SESSION['_2fa_setup_secret'] = $secret;

        $this->renderAuthPage($res, 'auth.twofa-setup', [
            'title' => 'Set Up Two-Factor Authentication - FORT',
            'secret' => $secret,
            'qrCode' => $qrCode,
        ]);
    }

    public function setupTwoFA(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/twofa/setup')->send();
            return;
        }

        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login')->send();
            return;
        }

        $user = User::findById($userId);
        if ($user === null) {
            $res->redirect('/login')->send();
            return;
        }

        $secret = $req->input('secret', $_SESSION['_2fa_setup_secret'] ?? '');
        $code = trim($req->input('code', ''));

        if ($secret === '' || $code === '' || !preg_match('/^\d{6}$/', $code)) {
            $this->session->flash('error', 'Please enter a valid 6-digit code.');
            $res->redirect('/twofa/setup')->send();
            return;
        }

        $authService = new AuthService();
        if (!$authService->verifyTwoFACode($secret, $code)) {
            $this->session->flash('error', 'Invalid code. Please try again.');
            $res->redirect('/twofa/setup')->send();
            return;
        }

        $user->setTwoFA($secret);
        unset($_SESSION['_2fa_setup_secret']);

        $this->session->flash('success', 'Two-factor authentication has been enabled.');
        $res->redirect('/profile')->send();
    }

    public function disableTwoFA(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/profile')->send();
            return;
        }

        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login')->send();
            return;
        }

        $user = User::findById($userId);
        if ($user === null) {
            $res->redirect('/login')->send();
            return;
        }

        $user->disableTwoFA();

        $this->session->flash('success', 'Two-factor authentication has been disabled.');
        $res->redirect('/profile')->send();
    }
}
