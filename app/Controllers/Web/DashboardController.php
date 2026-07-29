<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use PDO;

class DashboardController
{
    private View $view;
    private Session $session;
    private ?PDO $db = null;

    public function __construct()
    {
        $this->view = View::getInstance();
        $this->session = Session::getInstance();
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::connection();
        }
        return $this->db;
    }

    public function index(Request $request, Response $response): void
    {
        $workspaceId = $this->session->get('workspace_id');
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        $totalLinks = 0;
        $totalClicks = 0;
        $activeLinks = 0;
        $expiredLinks = 0;
        $recentLinks = [];

        try {
            $stmt = $this->db()->prepare('SELECT COUNT(*) FROM links WHERE workspace_id = :ws');
            $stmt->execute([':ws' => $workspaceId]);
            $totalLinks = (int) $stmt->fetchColumn();

            $stmt = $this->db()->prepare('
                SELECT COUNT(*) FROM link_clicks lc
                JOIN links l ON l.id = lc.link_id
                WHERE l.workspace_id = :ws
            ');
            $stmt->execute([':ws' => $workspaceId]);
            $totalClicks = (int) $stmt->fetchColumn();

            $stmt = $this->db()->prepare("SELECT COUNT(*) FROM links WHERE workspace_id = :ws AND is_active = 1 AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)");
            $stmt->execute([':ws' => $workspaceId]);
            $activeLinks = (int) $stmt->fetchColumn();

            $stmt = $this->db()->prepare("SELECT COUNT(*) FROM links WHERE workspace_id = :ws AND expires_at IS NOT NULL AND expires_at <= CURRENT_TIMESTAMP");
            $stmt->execute([':ws' => $workspaceId]);
            $expiredLinks = (int) $stmt->fetchColumn();

            $stmt = $this->db()->prepare('
                SELECT l.*, (SELECT COUNT(*) FROM link_clicks WHERE link_id = l.id) AS clicks
                FROM links l
                WHERE l.workspace_id = :ws
                ORDER BY l.created_at DESC
                LIMIT 5
            ');
            $stmt->execute([':ws' => $workspaceId]);
            $recentLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
        }

        $response->status(200)->view('dashboard.index', [
            'title' => 'Dashboard - FORT (Fast Short)',
            'activeNav' => 'dashboard',
            'totalLinks' => $totalLinks,
            'totalClicks' => $totalClicks,
            'activeLinks' => $activeLinks,
            'expiredLinks' => $expiredLinks,
            'recentLinks' => $recentLinks,
        ]);
    }
}
