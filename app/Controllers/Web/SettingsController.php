<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

class SettingsController
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
            $res->redirect('/login');
            return;
        }

        if ($this->session->get('user_is_admin')) {
            $res->redirect('/admin/settings');
        } else {
            $res->redirect('/profile');
        }
    }

    public function update(Request $req, Response $res): void
    {
        if ($this->session->get('user_is_admin')) {
            $res->redirect('/admin/settings');
        } else {
            $res->redirect('/profile');
        }
    }
}
