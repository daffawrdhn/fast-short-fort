<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\AnalyticsService;

class AnalyticsController
{
    private AnalyticsService $analytics;
    private View $view;

    public function __construct()
    {
        $this->analytics = new AnalyticsService();
        $this->view = View::getInstance();
    }

    public function index(Request $request, Response $response, array $params = []): void
    {
        $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = $request->query('end_date', date('Y-m-d'));
        $workspaceId = $request->query('workspace_id', '');

        if ($workspaceId && is_numeric($workspaceId)) {
            $stats = $this->analytics->getWorkspaceStats((int) $workspaceId, $startDate . ' 00:00:00', $endDate . ' 23:59:59');
        } else {
            $stats = [
                'total_clicks' => 0,
                'unique_clicks' => 0,
                'countries_data' => [],
                'devices' => [],
                'browsers' => [],
                'os' => [],
                'referrers' => [],
                'clicks_over_time' => [],
                'top_links' => [],
            ];
        }

        $response->status(200)->view('analytics.index', [
            'title' => 'Analytics - FORT (Fast Short)',
            'activeNav' => 'analytics',
            'stats' => $stats,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'workspaceId' => $workspaceId,
        ]);
    }

    public function show(Request $request, Response $response, array $params = []): void
    {
        $linkId = (int) ($params['id'] ?? 0);
        if ($linkId <= 0) {
            $response->status(404)->view('errors.404');
            return;
        }

        $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = $request->query('end_date', date('Y-m-d'));

        $stats = $this->analytics->getLinkStats($linkId);
        if ($stats['link'] === null) {
            $response->status(404)->view('errors.404');
            return;
        }

        $response->status(200)->view('analytics.show', [
            'title' => 'Link Analytics - FORT (Fast Short)',
            'activeNav' => 'analytics',
            'stats' => $stats,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'linkId' => $linkId,
        ]);
    }

    public function realtime(Request $request, Response $response, array $params = []): void
    {
        $linkId = (int) ($params['id'] ?? 0);
        $since = $request->query('since', '');

        $stmt = \App\Core\Database::connection()->prepare('
            SELECT id, ip_hash, country, city, device_type, browser, os, referrer, clicked_at
            FROM link_clicks
            WHERE link_id = :link_id' . ($since ? ' AND clicked_at > :since' : '') . '
            ORDER BY clicked_at DESC
            LIMIT 20
        ');
        $stmt->bindValue(':link_id', $linkId, \PDO::PARAM_INT);
        if ($since) {
            $stmt->bindValue(':since', $since);
        }
        $stmt->execute();
        $clicks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $response->json(['success' => true, 'data' => $clicks]);
    }
}
