<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Services\ApiService;
use PDO;

class AnalyticsController
{
    private ApiService $api;

    public function __construct()
    {
        $this->api = new ApiService();
    }

    public function linkAnalytics(Request $request, Response $response, array $params = []): void
    {
        $linkId = $params['linkId'] ?? null;
        $dateFrom = $request->query('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $linkId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link === false) {
            $this->api->errorResponse('Link not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $totalClicks = (int) $link['clicks'];

        $clicksStmt = $db->prepare(
            "SELECT strftime('%Y-%m-%d', clicked_at) AS date, COUNT(*) AS clicks
             FROM clicks WHERE link_id = :id AND clicked_at >= :date_from AND clicked_at <= :date_to
             GROUP BY date ORDER BY date"
        );
        $clicksStmt->execute([':id' => $linkId, ':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $clicksByDate = $clicksStmt->fetchAll(PDO::FETCH_ASSOC);

        $countryStmt = $db->prepare(
            'SELECT country, COUNT(*) AS count FROM clicks WHERE link_id = :id AND country IS NOT NULL GROUP BY country ORDER BY count DESC'
        );
        $countryStmt->execute([':id' => $linkId]);
        $countries = $countryStmt->fetchAll(PDO::FETCH_ASSOC);

        $osStmt = $db->prepare(
            'SELECT os, COUNT(*) AS count FROM clicks WHERE link_id = :id AND os IS NOT NULL GROUP BY os ORDER BY count DESC'
        );
        $osStmt->execute([':id' => $linkId]);
        $os = $osStmt->fetchAll(PDO::FETCH_ASSOC);

        $browserStmt = $db->prepare(
            'SELECT browser, COUNT(*) AS count FROM clicks WHERE link_id = :id AND browser IS NOT NULL GROUP BY browser ORDER BY count DESC'
        );
        $browserStmt->execute([':id' => $linkId]);
        $browsers = $browserStmt->fetchAll(PDO::FETCH_ASSOC);

        $refererStmt = $db->prepare(
            'SELECT referer, COUNT(*) AS count FROM clicks WHERE link_id = :id AND referer IS NOT NULL GROUP BY referer ORDER BY count DESC LIMIT 20'
        );
        $refererStmt->execute([':id' => $linkId]);
        $topReferrers = $refererStmt->fetchAll(PDO::FETCH_ASSOC);

        $rangeClicks = array_sum(array_column($clicksByDate, 'clicks'));

        $this->api->successResponse([
            'link_id' => (int) $linkId,
            'total_clicks' => $totalClicks,
            'clicks_in_range' => (int) $rangeClicks,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'clicks_by_date' => $clicksByDate,
            'countries' => $countries,
            'operating_systems' => $os,
            'browsers' => $browsers,
            'top_referrers' => $topReferrers,
        ])->send();
    }

    public function workspaceAnalytics(Request $request, Response $response, array $params = []): void
    {
        $workspaceId = $params['workspaceId'] ?? null;
        $dateFrom = $request->query('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $workspaceId]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            $this->api->errorResponse('Workspace not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $totalLinksStmt = $db->prepare('SELECT COUNT(*) FROM links WHERE workspace_id = :id');
        $totalLinksStmt->execute([':id' => $workspaceId]);
        $totalLinks = (int) $totalLinksStmt->fetchColumn();

        $totalClicksStmt = $db->prepare('SELECT COALESCE(SUM(clicks), 0) FROM links WHERE workspace_id = :id');
        $totalClicksStmt->execute([':id' => $workspaceId]);
        $totalClicks = (int) $totalClicksStmt->fetchColumn();

        $topLinksStmt = $db->prepare(
            'SELECT id, url, short_code, title, clicks, created_at FROM links WHERE workspace_id = :id ORDER BY clicks DESC LIMIT 10'
        );
        $topLinksStmt->execute([':id' => $workspaceId]);
        $topLinks = $topLinksStmt->fetchAll(PDO::FETCH_ASSOC);

        $clicksStmt = $db->prepare(
            "SELECT strftime('%Y-%m-%d', c.clicked_at) AS date, COUNT(*) AS clicks
             FROM clicks c JOIN links l ON l.id = c.link_id
             WHERE l.workspace_id = :id AND c.clicked_at >= :date_from AND c.clicked_at <= :date_to
             GROUP BY date ORDER BY date"
        );
        $clicksStmt->execute([':id' => $workspaceId, ':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $clicksByDate = $clicksStmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->successResponse([
            'workspace_id' => (int) $workspaceId,
            'total_links' => $totalLinks,
            'total_clicks' => $totalClicks,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'clicks_by_date' => $clicksByDate,
            'top_links' => $topLinks,
        ])->send();
    }

    public function export(Request $request, Response $response, array $params = []): void
    {
        $linkId = $params['linkId'] ?? null;
        $format = $params['format'] ?? 'csv';

        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $linkId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link === false) {
            $this->api->errorResponse('Link not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $dateFrom = $request->query('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        $clicksStmt = $db->prepare(
            'SELECT clicked_at, country, os, browser, referer, ip_address, user_agent
             FROM clicks WHERE link_id = :id AND clicked_at >= :date_from AND clicked_at <= :date_to
             ORDER BY clicked_at DESC'
        );
        $clicksStmt->execute([':id' => $linkId, ':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $clicks = $clicksStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($format === 'csv') {
            $this->exportCsv($response, $link, $clicks);
        } else {
            $this->api->errorResponse('Unsupported export format. Use "csv".', 400, 'INVALID_FORMAT')->send();
        }
    }

    private function exportCsv(Response $response, array $link, array $clicks): void
    {
        $output = fopen('php://temp', 'r+');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['Link', $link['url']]);
        fputcsv($output, ['Short Code', $link['short_code']]);
        fputcsv($output, ['Total Clicks', $link['clicks']]);
        fputcsv($output, []);
        fputcsv($output, ['Clicked At', 'Country', 'OS', 'Browser', 'Referer', 'IP Address', 'User Agent']);

        foreach ($clicks as $click) {
            fputcsv($output, [
                $click['clicked_at'],
                $click['country'] ?? '',
                $click['os'] ?? '',
                $click['browser'] ?? '',
                $click['referer'] ?? '',
                $click['ip_address'] ?? '',
                $click['user_agent'] ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response->header('Content-Type', 'text/csv; charset=utf-8');
        $response->header('Content-Disposition', "attachment; filename=\"analytics-{$link['short_code']}.csv\"");
        $response->json(['data' => base64_encode($csv), 'format' => 'csv', 'filename' => "analytics-{$link['short_code']}.csv"]);
        $response->send();
    }
}
