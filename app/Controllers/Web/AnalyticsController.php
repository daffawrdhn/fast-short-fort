<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\AnalyticsService;
use App\Core\Logger;
use App\Models\Workspace;

class AnalyticsController
{
    private AnalyticsService $analytics;
    private View $view;

    public function __construct()
    {
        $this->analytics = new AnalyticsService();
        $this->view = View::getInstance();
    }

    private function getWorkspaceId(): ?int
    {
        $ws = session()->get('workspace_id');
        if ($ws === null) {
            $user = session()->get('user_id');
            if ($user !== null) {
                $workspaces = Workspace::findByOwner((int) $user);
                if (!empty($workspaces)) {
                    session()->set('workspace_id', $workspaces[0]->id);
                    return $workspaces[0]->id;
                }
            }
        }
        return $ws ? (int) $ws : null;
    }

    public function index(Request $request, Response $response, array $params = []): void
    {
        $workspaceId = $request->query('workspace_id', '');
        if ($workspaceId === '') {
            $resolvedId = $this->getWorkspaceId();
            if ($resolvedId !== null) {
                $workspaceId = (string) $resolvedId;
            }
        }

        $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = $request->query('end_date', date('Y-m-d'));

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
        $linkId = (int) ($params['linkId'] ?? $params['id'] ?? 0);
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
        $linkId = (int) ($params['linkId'] ?? $params['id'] ?? 0);
        $since = $request->query('since', '');

        $stmt = \App\Core\Database::connection()->prepare('
            SELECT id, ip_hash, ip_address, country, city, latitude, longitude, device_type, browser, os, referrer, user_language, user_agent, clicked_at,
                   isp, org, connection_type, is_vpn, visitor_uuid, client_hints, dnt_status
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

    public function exportWorkspace(Request $request, Response $response): void
    {
        $workspaceId = $request->query('workspace_id', '');
        if ($workspaceId === '') {
            $resolvedId = $this->getWorkspaceId();
            if ($resolvedId !== null) {
                $workspaceId = (string) $resolvedId;
            }
        }

        if (!$workspaceId || !is_numeric($workspaceId)) {
            $response->redirect('/analytics');
            return;
        }

        $format = $request->query('export', 'csv');
        $startDate = $request->query('start_date', null);
        $endDate = $request->query('end_date', null);

        try {
            $output = $this->analytics->exportWorkspaceAnalytics((int) $workspaceId, $format, $startDate, $endDate);
            if ($format === 'json') {
                $response->header('Content-Type', 'application/json; charset=utf-8');
                $response->header('Content-Disposition', 'attachment; filename="workspace-' . $workspaceId . '-analytics.json"');
            } else {
                $response->header('Content-Type', 'text/csv; charset=utf-8');
                $response->header('Content-Disposition', 'attachment; filename="workspace-' . $workspaceId . '-analytics.csv"');
            }
            $response->body($output);
        } catch (\Throwable $e) {
            Logger::error('Failed to export workspace analytics: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $response->redirect('/analytics');
        }
    }

    public function exportLink(Request $request, Response $response, array $params = []): void
    {
        $linkId = (int) ($params['linkId'] ?? $params['id'] ?? 0);
        if ($linkId <= 0) {
            $response->status(404)->view('errors.404');
            return;
        }

        $format = $request->query('format', 'csv');
        $startDate = $request->query('start_date', null);
        $endDate = $request->query('end_date', null);

        try {
            $output = $this->analytics->exportAnalytics($linkId, $format, $startDate, $endDate);
            if ($format === 'json') {
                $response->header('Content-Type', 'application/json; charset=utf-8');
                $response->header('Content-Disposition', 'attachment; filename="link-' . $linkId . '-analytics.json"');
            } else {
                $response->header('Content-Type', 'text/csv; charset=utf-8');
                $response->header('Content-Disposition', 'attachment; filename="link-' . $linkId . '-analytics.csv"');
            }
            $response->body($output);
        } catch (\Throwable $e) {
            Logger::error('Failed to export link analytics: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $response->redirect('/analytics/' . $linkId);
        }
    }
}
