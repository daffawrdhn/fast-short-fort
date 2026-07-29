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
        echo $this->view->renderString('admin.settings', ['title' => 'Settings - FORT']);
    }

    public function update(Request $req, Response $res): void
    {
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/settings')->send();
            return;
        }
        $this->session->flash('success', 'Settings updated successfully.');
        $res->redirect('/settings')->send();
    }
}
