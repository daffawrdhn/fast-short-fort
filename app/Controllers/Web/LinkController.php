<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Link;
use App\Services\AnalyticsService;
use App\Services\LinkService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PDO;

class LinkController
{
    private View $view;
    private Session $session;
    private LinkService $linkService;
    private AnalyticsService $analytics;
    private ?PDO $db = null;

    public function __construct()
    {
        $this->view = View::getInstance();
        $this->session = Session::getInstance();
        $this->linkService = new LinkService();
        $this->analytics = new AnalyticsService();
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::connection();
        }
        return $this->db;
    }

    private function getWorkspaceId(): ?int
    {
        return $this->session->get('workspace_id');
    }

    public function index(Request $request, Response $response): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        $search = trim($request->query('search', ''));
        $sort = $request->query('sort', 'created_at');
        $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $filter = $request->query('filter', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $allowedSort = ['created_at', 'clicks', 'title'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $conditions = ['l.workspace_id = :workspace_id'];
        $params = [':workspace_id' => $workspaceId];

        if ($search !== '') {
            $conditions[] = '(l.original_url LIKE :search OR l.slug LIKE :search2 OR l.title LIKE :search3)';
            $params[':search'] = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }

        if ($filter === 'active') {
            $conditions[] = "l.is_active = 1 AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)";
        } elseif ($filter === 'expired') {
            $conditions[] = "l.expires_at IS NOT NULL AND l.expires_at <= CURRENT_TIMESTAMP";
        } elseif ($filter === 'inactive') {
            $conditions[] = 'l.is_active = 0';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        try {
            $countSql = "SELECT COUNT(*) FROM links l {$where}";
            $stmt = $this->db()->prepare($countSql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $orderClause = match ($sort) {
                'clicks' => "(SELECT COUNT(*) FROM link_clicks WHERE link_id = l.id) {$order}",
                'title' => "COALESCE(l.original_url, '') {$order}",
                default => "l.created_at {$order}",
            };

            $sql = "
                SELECT l.*, (SELECT COUNT(*) FROM link_clicks WHERE link_id = l.id) AS clicks
                FROM links l
                {$where}
                ORDER BY {$orderClause}
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db()->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalPages = max(1, (int) ceil($total / $perPage));
        } catch (\Throwable $e) {
            $links = [];
            $total = 0;
            $totalPages = 1;
        }

        $response->status(200)->view('links.index', [
            'title' => 'Links - FORT (Fast Short)',
            'activeNav' => 'links',
            'links' => $links,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'filter' => $filter,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => $perPage,
        ]);
    }

    public function create(Request $request, Response $response): void
    {
        $response->status(200)->view('links.create', [
            'title' => 'Create Link - FORT (Fast Short)',
            'activeNav' => 'links',
        ]);
    }

    public function store(Request $request, Response $response): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        if (!$request->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $response->redirect('/links');
            return;
        }

        $originalUrl = trim($request->input('original_url', ''));
        if ($originalUrl !== '' && !str_starts_with(strtolower($originalUrl), 'http://') && !str_starts_with(strtolower($originalUrl), 'https://')) {
            $originalUrl = 'https://' . $originalUrl;
        }
        $customSlug = trim($request->input('slug', ''));
        $expiration = $request->input('expires_at', null);
        $password = $request->input('password', '');
        $isCloaked = !empty($request->input('is_cloaked'));
        $linkType = $request->input('link_type', 'direct');
        $deepLinkScheme = trim($request->input('deep_link_scheme', ''));
        $clickLimit = $request->input('click_limit', null);

        $utmParams = [
            'utm_source' => trim($request->input('utm_source', '')),
            'utm_medium' => trim($request->input('utm_medium', '')),
            'utm_campaign' => trim($request->input('utm_campaign', '')),
            'utm_term' => trim($request->input('utm_term', '')),
            'utm_content' => trim($request->input('utm_content', '')),
        ];

        $errors = [];

        if ($originalUrl === '') {
            $errors[] = 'The original URL is required.';
        } elseif (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'The original URL must be a valid URL.';
        } elseif (!$this->linkService->validateURL($originalUrl)) {
            $errors[] = 'The original URL is not allowed.';
        }

        if ($customSlug !== '') {
            if (!$this->linkService->validateSlug($customSlug)) {
                $errors[] = 'The custom slug must be 3-50 characters and contain only letters, numbers, hyphens, and underscores.';
            } elseif (!$this->linkService->isSlugAvailable($customSlug, $workspaceId)) {
                $errors[] = 'This custom slug is already taken.';
            }
        }

        if ($expiration !== null && $expiration !== '') {
            $ts = strtotime($expiration);
            if ($ts === false) {
                $errors[] = 'Invalid expiration date.';
            } elseif ($ts < time()) {
                $errors[] = 'Expiration must be a future date.';
            }
        }

        if ($password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
        }

        if (!in_array($linkType, ['direct', 'interstitial', 'deep_link'], true)) {
            $linkType = 'direct';
        }

        if ($linkType === 'deep_link' && $deepLinkScheme === '') {
            $errors[] = 'Deep link scheme is required when link type is deep_link.';
        }

        if (!empty($errors)) {
            $this->session->flash('error', implode('<br>', $errors));
            $response->redirect('/links/create');
            return;
        }

        $slug = $customSlug !== '' ? $customSlug : $this->linkService->generateSlug();

        $userId = $this->session->get('user_id');

        try {
            Link::create([
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'original_url' => $originalUrl,
                'slug' => $slug,
                'expires_at' => $expiration,
                'password' => $password,
                'is_active' => 1,
                'is_cloaked' => $isCloaked,
                'click_limit' => $clickLimit !== '' && $clickLimit !== null ? (int) $clickLimit : null,
                'link_type' => $linkType,
                'deep_link_scheme' => $linkType === 'deep_link' ? $deepLinkScheme : null,
                'utm_source' => $utmParams['utm_source'] ?: null,
                'utm_medium' => $utmParams['utm_medium'] ?: null,
                'utm_campaign' => $utmParams['utm_campaign'] ?: null,
                'utm_term' => $utmParams['utm_term'] ?: null,
                'utm_content' => $utmParams['utm_content'] ?: null,
            ]);

            Logger::info('Link created', ['slug' => $slug, 'workspace_id' => $workspaceId]);
            $this->session->flash('success', 'Link created successfully.');
        } catch (\Throwable $e) {
            Logger::error('Failed to create link: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->session->flash('error', 'Failed to create link.');
        }

        $response->redirect('/links');
    }

    public function show(Request $request, Response $response, array $params): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $link = Link::findById($id);

        if ($link === null || $link->workspace_id !== $workspaceId) {
            $response->status(404)->view('errors.404');
            return;
        }

        $stats = $this->analytics->getLinkStats($id);
        $recentClicks = $this->analytics->getRecentClicks($id, 20);

        $shortUrl = $this->buildShortUrl($link->slug);

        $response->status(200)->view('links.show', [
            'title' => 'Link Details - FORT (Fast Short)',
            'activeNav' => 'links',
            'link' => $link,
            'stats' => $stats,
            'recentClicks' => $recentClicks,
            'shortUrl' => $shortUrl,
        ]);
    }

    public function edit(Request $request, Response $response, array $params): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $link = Link::findById($id);

        if ($link === null || $link->workspace_id !== $workspaceId) {
            $response->status(404)->view('errors.404');
            return;
        }

        $shortUrl = $this->buildShortUrl($link->slug);

        $response->status(200)->view('links.edit', [
            'title' => 'Edit Link - FORT (Fast Short)',
            'activeNav' => 'links',
            'link' => $link,
            'shortUrl' => $shortUrl,
        ]);
    }

    public function update(Request $request, Response $response, array $params): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        if (!$request->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $response->redirect('/links');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $link = Link::findById($id);

        if ($link === null || $link->workspace_id !== $workspaceId) {
            $response->status(404)->view('errors.404');
            return;
        }

        $originalUrl = trim($request->input('original_url', ''));
        if ($originalUrl !== '' && !str_starts_with(strtolower($originalUrl), 'http://') && !str_starts_with(strtolower($originalUrl), 'https://')) {
            $originalUrl = 'https://' . $originalUrl;
        }
        $slug = trim($request->input('slug', ''));
        $expiration = $request->input('expires_at', null);
        $password = $request->input('password', '');
        $passwordEnabled = !empty($request->input('password_enabled'));
        $isCloaked = !empty($request->input('is_cloaked'));
        $linkType = $request->input('link_type', 'direct');
        $deepLinkScheme = trim($request->input('deep_link_scheme', ''));
        $clickLimit = $request->input('click_limit', null);
        $isActive = $request->input('is_active', null);

        $errors = [];

        if ($originalUrl === '') {
            $errors[] = 'The original URL is required.';
        } elseif (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'The original URL must be a valid URL.';
        }

        $slugChanged = $slug !== '' && $slug !== $link->slug;
        if ($slugChanged) {
            if (!$this->linkService->validateSlug($slug)) {
                $errors[] = 'The custom slug must be 3-50 characters and contain only letters, numbers, hyphens, and underscores.';
            } elseif (!$this->linkService->isSlugAvailable($slug, $workspaceId)) {
                $errors[] = 'This custom slug is already taken.';
            }
        }

        if ($expiration !== null && $expiration !== '') {
            $ts = strtotime($expiration);
            if ($ts === false) {
                $errors[] = 'Invalid expiration date.';
            } elseif ($ts < time()) {
                $errors[] = 'Expiration must be a future date.';
            }
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if (!in_array($linkType, ['direct', 'interstitial', 'deep_link'], true)) {
            $linkType = 'direct';
        }

        if ($linkType === 'deep_link' && $deepLinkScheme === '') {
            $errors[] = 'Deep link scheme is required when link type is deep_link.';
        }

        if (!empty($errors)) {
            $this->session->flash('error', implode('<br>', $errors));
            $response->redirect("/links/{$id}/edit");
            return;
        }

        $updateData = [
            'original_url' => $originalUrl,
            'expires_at' => $expiration ?: null,
            'is_cloaked' => $isCloaked ? 1 : 0,
            'link_type' => $linkType,
            'deep_link_scheme' => $linkType === 'deep_link' ? $deepLinkScheme : null,
            'click_limit' => $clickLimit !== '' && $clickLimit !== null ? (int) $clickLimit : null,
            'utm_source' => trim($request->input('utm_source', '')) ?: null,
            'utm_medium' => trim($request->input('utm_medium', '')) ?: null,
            'utm_campaign' => trim($request->input('utm_campaign', '')) ?: null,
            'utm_term' => trim($request->input('utm_term', '')) ?: null,
            'utm_content' => trim($request->input('utm_content', '')) ?: null,
        ];

        if ($slugChanged) {
            $updateData['slug'] = $slug;
        }

        if (!$passwordEnabled) {
            $updateData['password_hash'] = null;
        } elseif ($password !== '') {
            $updateData['password'] = $password;
        }

        if ($isActive !== null) {
            $updateData['is_active'] = !empty($isActive) ? 1 : 0;
        }

        try {
            $link->update($updateData);
            $this->session->flash('success', 'Link updated successfully.');
        } catch (\Throwable $e) {
            Logger::error('Failed to update link: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->session->flash('error', 'Failed to update link.');
        }

        $response->redirect('/links');
    }

    public function delete(Request $request, Response $response, array $params): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        if (!$request->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $response->redirect('/links');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $link = Link::findById($id);

        if ($link !== null && $link->workspace_id === $workspaceId) {
            $link->update(['is_active' => 0]);
            Logger::warning('Link moved to trash', ['link_id' => $id, 'slug' => $link->slug]);
            $this->session->flash('success', 'Link moved to trash.');
        } else {
            $this->session->flash('error', 'Link not found.');
        }

        $response->redirect('/links');
    }

    public function forceDelete(Request $request, Response $response, array $params): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        if (!$request->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $response->redirect('/links');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $link = Link::findById($id);

        if ($link !== null && $link->workspace_id === $workspaceId) {
            $this->db()->prepare('DELETE FROM link_clicks WHERE link_id = :id')->execute([':id' => $link->id]);
            $link->forceDelete();
            Logger::warning('Link permanently deleted', ['link_id' => $id, 'slug' => $link->slug]);
            $this->session->flash('success', 'Link permanently deleted.');
        } else {
            $this->session->flash('error', 'Link not found.');
        }

        $response->redirect('/links');
    }

    public function bulkAction(Request $request, Response $response): void
    {
        $action = $request->input('action', '');
        if ($action === 'delete') {
            $this->bulkDelete($request, $response);
        } elseif ($action === 'enable') {
            $this->bulkEnable($request, $response);
        } elseif ($action === 'disable') {
            $this->bulkDisable($request, $response);
        } else {
            $response->redirect('/links');
        }
    }

    public function bulkDelete(Request $request, Response $response): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        if (!$request->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $response->redirect('/links');
            return;
        }

        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            $this->session->flash('error', 'No links selected.');
            $response->redirect('/links');
            return;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $stmt = $this->db()->prepare("UPDATE links SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id IN ({$placeholders}) AND workspace_id = ?");
            $stmt->execute(array_merge($ids, [$workspaceId]));
            $this->session->flash('success', count($ids) . ' links moved to trash.');
        } catch (\Throwable $e) {
            Logger::error('Failed to bulk delete links: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->session->flash('error', 'Failed to delete links.');
        }

        $response->redirect('/links');
    }

    public function bulkEnable(Request $request, Response $response): void
    {
        $this->bulkSetActive($request, $response, 1);
    }

    public function bulkDisable(Request $request, Response $response): void
    {
        $this->bulkSetActive($request, $response, 0);
    }

    private function bulkSetActive(Request $request, Response $response, int $active): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        if (!$request->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $response->redirect('/links');
            return;
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            $this->session->flash('error', 'No links selected.');
            $response->redirect('/links');
            return;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $stmt = $this->db()->prepare("UPDATE links SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ({$placeholders}) AND workspace_id = ?");
            $stmt->execute(array_merge([$active], $ids, [$workspaceId]));
            $label = $active ? 'enabled' : 'disabled';
            $this->session->flash('success', count($ids) . " links {$label}.");
        } catch (\Throwable $e) {
            Logger::error('Failed to bulk update active status: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->session->flash('error', 'Failed to update links.');
        }

        $response->redirect('/links');
    }

    public function bulkExport(Request $request, Response $response): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        $format = $request->query('format', 'csv');

        try {
            $stmt = $this->db()->prepare('
                SELECT l.id, l.original_url, l.slug, l.is_active, l.link_type, l.expires_at,
                       l.created_at, l.updated_at,
                       (SELECT COUNT(*) FROM link_clicks WHERE link_id = l.id) AS clicks
                FROM links l
                WHERE l.workspace_id = :ws
                ORDER BY l.created_at DESC
            ');
            $stmt->execute([':ws' => $workspaceId]);
            $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            Logger::error('Failed to bulk export links: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->session->flash('error', 'Failed to export links.');
            $response->redirect('/links');
            return;
        }

        if ($format === 'json') {
            $response->json(['links' => $links])->header('Content-Disposition', 'attachment; filename="links-export.json"');
            return;
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['ID', 'Original URL', 'Slug', 'Short URL', 'Active', 'Type', 'Expires At', 'Clicks', 'Created At', 'Updated At']);

        $baseUrl = $this->getBaseUrl();

        foreach ($links as $link) {
            fputcsv($csv, [
                $link['id'],
                $link['original_url'],
                $link['slug'],
                $baseUrl . '/' . $link['slug'],
                $link['is_active'] ? 'Yes' : 'No',
                $link['link_type'],
                $link['expires_at'] ?? 'N/A',
                $link['clicks'],
                $link['created_at'],
                $link['updated_at'],
            ]);
        }

        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);

        $response->header('Content-Type', 'text/csv; charset=utf-8');
        $response->header('Content-Disposition', 'attachment; filename="links-export.csv"');
        $response->body($output);
    }

    public function toggleActive(Request $request, Response $response, array $params): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        if (!$request->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $response->redirect('/links');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $link = Link::findById($id);

        if ($link !== null && $link->workspace_id === $workspaceId) {
            $link->update(['is_active' => $link->is_active ? 0 : 1]);
            $this->session->flash('success', 'Link status toggled.');
        } else {
            $this->session->flash('error', 'Link not found.');
        }

        $response->back();
    }

    public function downloadQRCode(Request $request, Response $response, array $params): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->redirect('/login');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $format = $params['format'] ?? 'png';

        $link = Link::findById($id);
        if ($link === null || $link->workspace_id !== $workspaceId) {
            $response->status(404)->view('errors.404');
            return;
        }

        $shortUrl = $this->buildShortUrl($link->slug);

        if ($format === 'svg') {
            $renderer = new ImageRenderer(
                new RendererStyle(400),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString($shortUrl);

            $response->header('Content-Type', 'image/svg+xml');
            $response->header('Content-Disposition', 'attachment; filename="qr-' . $link->slug . '.svg"');
            $response->body($svg);
            return;
        }

        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new \BaconQrCode\Renderer\Image\PngImageBackEnd()
        );
        $writer = new Writer($renderer);
        $png = $writer->writeString($shortUrl);

        $response->header('Content-Type', 'image/png');
        $response->header('Content-Disposition', 'attachment; filename="qr-' . $link->slug . '.png"');
        $response->body($png);
    }

    public function redirect(Request $request, Response $response, array $params): void
    {
        $slug = $params['slug'] ?? '';

        $link = Link::findBySlug($slug, null);

        if ($link === null) {
            $response->status(404)->view('errors.404');
            return;
        }

        if (!$link->is_active) {
            $response->status(410)->view('errors.410');
            return;
        }

        if ($this->linkService->checkExpiration($link)) {
            $response->status(410)->view('errors.410');
            return;
        }

        if ($this->linkService->checkClickLimit($link)) {
            $response->status(410)->view('errors.410');
            return;
        }

        if ($link->password_hash !== null) {
            $verified = $this->session->get('link_password_' . $link->id, false);
            if (!$verified) {
                $response->redirect('/p/' . $link->slug);
                return;
            }
        }

        $this->linkService->recordClick($link->id, $request);

        if ($link->link_type === 'deep_link' && $link->deep_link_scheme !== null) {
            $targetUrl = $this->linkService->buildUTMUrl($link->deep_link_scheme, [
                'utm_source' => $link->utm_source,
                'utm_medium' => $link->utm_medium,
                'utm_campaign' => $link->utm_campaign,
                'utm_term' => $link->utm_term,
                'utm_content' => $link->utm_content,
            ]);

            $webUrl = $this->linkService->buildUTMUrl($link->original_url, [
                'utm_source' => $link->utm_source,
                'utm_medium' => $link->utm_medium,
                'utm_campaign' => $link->utm_campaign,
                'utm_term' => $link->utm_term,
                'utm_content' => $link->utm_content,
            ]);

            $html = '<!DOCTYPE html><html><head><script>'
                . 'window.location.href = ' . json_encode($targetUrl) . ';'
                . 'setTimeout(function(){ window.location.href = ' . json_encode($webUrl) . '; }, 500);'
                . '</script></head><body></body></html>';
            $response->body($html);
            return;
        }

        if ($link->link_type === 'interstitial') {
            $targetUrl = $this->linkService->buildUTMUrl($link->original_url, [
                'utm_source' => $link->utm_source,
                'utm_medium' => $link->utm_medium,
                'utm_campaign' => $link->utm_campaign,
                'utm_term' => $link->utm_term,
                'utm_content' => $link->utm_content,
            ]);

            $html = $this->view->renderString('links.interstitial', [
                'targetUrl' => $targetUrl,
                'link' => $link,
            ]);
            $response->body($html);
            return;
        }

        $finalUrl = $this->linkService->buildUTMUrl($link->original_url, [
            'utm_source' => $link->utm_source,
            'utm_medium' => $link->utm_medium,
            'utm_campaign' => $link->utm_campaign,
            'utm_term' => $link->utm_term,
            'utm_content' => $link->utm_content,
        ]);

        $response->redirect($finalUrl, 301);
    }

    public function showPasswordForm(Request $request, Response $response, array $params): void
    {
        $slug = $params['slug'] ?? '';
        $link = Link::findBySlug($slug, null);

        if ($link === null) {
            $response->status(404)->view('errors.404');
            return;
        }

        $response->status(200)->view('links.password', [
            'title' => 'Password Protected Link - FORT (Fast Short)',
            'link' => $link,
            'slug' => $slug,
        ]);
    }

    public function verifyPassword(Request $request, Response $response, array $params): void
    {
        $slug = $params['slug'] ?? '';
        $link = Link::findBySlug($slug, null);

        if ($link === null) {
            $response->status(404)->view('errors.404');
            return;
        }

        if (!$request->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $response->redirect('/links/password/' . $slug);
            return;
        }

        $password = $request->input('password', '');

        if ($link->password_hash !== null && password_verify($password, $link->password_hash)) {
            $this->session->set('link_password_' . $link->id, true);
            $response->redirect('/' . $link->slug);
            return;
        }

        $this->session->flash('error', 'Invalid password.');
        $response->redirect('/links/password/' . $slug);
    }

    public function checkSlug(Request $request, Response $response): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId === null) {
            $response->status(401)->json(['success' => false, 'message' => 'Unauthenticated.']);
            return;
        }

        $slug = trim($request->query('slug', ''));
        if ($slug === '') {
            $response->json(['success' => false, 'message' => 'Slug is required.']);
            return;
        }

        if (!$this->linkService->validateSlug($slug)) {
            $response->json(['success' => false, 'message' => 'Invalid slug format.']);
            return;
        }

        $available = $this->linkService->isSlugAvailable($slug, $workspaceId);
        $response->json([
            'success' => true,
            'available' => $available,
            'message' => $available ? 'Slug is available.' : 'Slug is already taken.'
        ]);
    }

    private function buildShortUrl(string $slug): string
    {
        return $this->getBaseUrl() . '/' . $slug;
    }

    private function getBaseUrl(): string
    {
        $base = \App\Core\Env::get('APP_URL', '');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return rtrim($base, '/');
    }
}
