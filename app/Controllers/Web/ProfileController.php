<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Hash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;

class ProfileController
{
    private View $view;
    private Session $session;

    public function __construct()
    {
        $this->view = View::getInstance();
        $this->session = Session::getInstance();
    }

    public function index(Request $req, Response $res): void
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login')->send();
            return;
        }
        $user = User::findById($userId);
        echo $this->view->renderString('profile.index', ['user' => $user, 'title' => 'Profile - FORT']);
    }

    public function update(Request $req, Response $res): void
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

        $name = trim($req->input('name', ''));
        $email = trim($req->input('email', ''));
        $password = $req->input('password', '');
        $currentPassword = $req->input('current_password', '');

        if ($name !== '') {
            $user->update(['name' => $name]);
        }

        if ($email !== '' && $email !== $user->email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->session->flash('error', 'Invalid email address.');
                $res->redirect('/profile')->send();
                return;
            }
            $existing = User::findByEmail($email);
            if ($existing !== null && $existing->id !== $user->id) {
                $this->session->flash('error', 'Email already in use.');
                $res->redirect('/profile')->send();
                return;
            }
            $user->update(['email' => $email]);
        }

        if ($password !== '') {
            if ($currentPassword === '' || !Hash::check($currentPassword, $user->password_hash)) {
                $this->session->flash('error', 'Current password is required to change password.');
                $res->redirect('/profile')->send();
                return;
            }
            if (strlen($password) < 8) {
                $this->session->flash('error', 'Password must be at least 8 characters.');
                $res->redirect('/profile')->send();
                return;
            }
            $user->update(['password_hash' => Hash::make($password)]);
        }

        $this->session->flash('success', 'Profile updated successfully.');
        $res->redirect('/profile')->send();
    }
}
