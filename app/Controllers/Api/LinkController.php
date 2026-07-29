<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Env;
use App\Services\ApiService;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PDO;

class LinkController
{
    private ApiService $api;

    public function __construct()
    {
        $this->api = new ApiService();
    }

    public function index(Request $request, Response $response, array $params = []): void
    {
        $workspaceId = $_SERVER['auth_workspace_id'] ?? null;
        $userId = $_SERVER['auth_user_id'] ?? null;

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $status = $request->query('status');
        $search = $request->query('search');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $sort = $request->query('sort', 'created_at');
        $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $allowedSorts = ['created_at', 'updated_at', 'clicks', 'title', 'short_code'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $db = Database::connection();
        $where = [];
        $bindings = [];

        if ($workspaceId !== null) {
            $where[] = 'workspace_id = :workspace_id';
            $bindings[':workspace_id'] = $workspaceId;
        } elseif ($userId !== null) {
            $where[] = 'user_id = :user_id';
            $bindings[':user_id'] = $userId;
        }

        if ($status !== null) {
            if ($status === 'active') {
                $where[] = '(expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)';
            } elseif ($status === 'expired') {
                $where[] = 'expires_at IS NOT NULL AND expires_at <= CURRENT_TIMESTAMP';
            }
        }

        if ($search !== null) {
            $where[] = '(title LIKE :search OR short_code LIKE :search2 OR url LIKE :search3)';
            $bindings[':search'] = "%{$search}%";
            $bindings[':search2'] = "%{$search}%";
            $bindings[':search3'] = "%{$search}%";
        }

        if ($dateFrom !== null) {
            $where[] = 'created_at >= :date_from';
            $bindings[':date_from'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $where[] = 'created_at <= :date_to';
            $bindings[':date_to'] = $dateTo;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM links {$whereClause}");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare(
            "SELECT * FROM links {$whereClause} ORDER BY {$sort} {$order} LIMIT :limit OFFSET :offset"
        );
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->paginatedResponse($links, $total, $page, $perPage)->send();
    }

    public function store(Request $request, Response $response, array $params = []): void
    {
        $data = $request->only(['url', 'short_code', 'title', 'expires_at', 'password', 'type', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);

        $validation = $this->api->validateRequest($data, [
            'url' => 'required|url',
            'short_code' => 'slug|unique:links,short_code|max:255',
            'title' => 'max:255',
            'password' => 'max:255',
            'type' => 'max:50',
        ]);

        if ($validation !== null) {
            $validation->send();
            return;
        }

        $userId = $_SERVER['auth_user_id'] ?? null;
        $workspaceId = $_SERVER['auth_workspace_id'] ?? null;

        if ($userId === null) {
            $this->api->errorResponse('Unauthenticated.', 401, 'UNAUTHENTICATED')->send();
            return;
        }

        $db = Database::connection();

        $shortCode = $data['short_code'] ?? $this->generateShortCode();

        if (empty($data['short_code'])) {
            while ($this->shortCodeExists($db, $shortCode)) {
                $shortCode = $this->generateShortCode();
            }
        }

        $utmParams = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $param) {
            if (!empty($data[$param])) {
                $utmParams[$param] = $data[$param];
            }
        }

        $stmt = $db->prepare('
            INSERT INTO links (user_id, workspace_id, url, short_code, title, type, password, expires_at, utm_params, clicks, created_at, updated_at)
            VALUES (:user_id, :workspace_id, :url, :short_code, :title, :type, :password, :expires_at, :utm_params, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':workspace_id' => $workspaceId,
            ':url' => $data['url'],
            ':short_code' => $shortCode,
            ':title' => $data['title'] ?? null,
            ':type' => $data['type'] ?? 'direct',
            ':password' => !empty($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null,
            ':expires_at' => $data['expires_at'] ?? null,
            ':utm_params' => !empty($utmParams) ? json_encode($utmParams) : null,
        ]);

        $linkId = (int) $db->lastInsertId();
        $linkStmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $linkStmt->execute([':id' => $linkId]);
        $link = $linkStmt->fetch(PDO::FETCH_ASSOC);

        $this->api->createdResponse($link)->send();
    }

    public function show(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link === false) {
            $this->api->errorResponse('Link not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $this->api->successResponse($link)->send();
    }

    public function update(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link === false) {
            $this->api->errorResponse('Link not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $data = $request->only(['url', 'short_code', 'title', 'expires_at', 'password', 'type']);

        $fields = [];
        $bindings = [':id' => $id];

        foreach (['url', 'short_code', 'title', 'expires_at', 'type'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $fields[] = "{$field} = :{$field}";
                $bindings[":{$field}"] = $data[$field];
            }
        }

        if (array_key_exists('password', $data)) {
            if (!empty($data['password'])) {
                $fields[] = 'password = :password';
                $bindings[':password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            } else {
                $fields[] = 'password = NULL';
            }
        }

        if (!empty($fields)) {
            $fields[] = 'updated_at = CURRENT_TIMESTAMP';
            $sql = 'UPDATE links SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $updateStmt = $db->prepare($sql);
            $updateStmt->execute($bindings);
        }

        $linkStmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $linkStmt->execute([':id' => $id]);
        $updated = $linkStmt->fetch(PDO::FETCH_ASSOC);

        $this->api->successResponse($updated)->send();
    }

    public function destroy(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $hard = $request->query('hard') === 'true';
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link === false) {
            $this->api->errorResponse('Link not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        if ($hard) {
            $db->prepare('DELETE FROM clicks WHERE link_id = :id')->execute([':id' => $id]);
            $db->prepare('DELETE FROM links WHERE id = :id')->execute([':id' => $id]);
        } else {
            $db->prepare('UPDATE links SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id')->execute([':id' => $id]);
        }

        $this->api->noContentResponse()->send();
    }

    public function analytics(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $dateFrom = $request->query('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $groupBy = $request->query('group_by', 'day');

        $allowedGroups = ['day', 'week', 'month', 'hour', 'country', 'user_agent', 'referer'];
        if (!in_array($groupBy, $allowedGroups, true)) {
            $groupBy = 'day';
        }

        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link === false) {
            $this->api->errorResponse('Link not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $totalClicks = (int) $link['clicks'];

        $dateGroupExpr = match ($groupBy) {
            'hour' => "strftime('%Y-%m-%d %H:00:00', clicked_at)",
            'week' => "strftime('%Y-%W', clicked_at)",
            'month' => "strftime('%Y-%m', clicked_at)",
            default => "strftime('%Y-%m-%d', clicked_at)",
        };

        $groupStmt = $db->prepare(
            "SELECT {$dateGroupExpr} AS period, COUNT(*) AS count FROM clicks WHERE link_id = :id AND clicked_at >= :date_from AND clicked_at <= :date_to GROUP BY period ORDER BY period"
        );
        $groupStmt->execute([':id' => $id, ':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $clicksOverTime = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

        $countryStmt = $db->prepare(
            'SELECT country, COUNT(*) AS count FROM clicks WHERE link_id = :id AND country IS NOT NULL GROUP BY country ORDER BY count DESC LIMIT 20'
        );
        $countryStmt->execute([':id' => $id]);
        $countries = $countryStmt->fetchAll(PDO::FETCH_ASSOC);

        $uaStmt = $db->prepare(
            'SELECT user_agent, COUNT(*) AS count FROM clicks WHERE link_id = :id AND user_agent IS NOT NULL GROUP BY user_agent ORDER BY count DESC LIMIT 20'
        );
        $uaStmt->execute([':id' => $id]);
        $userAgents = $uaStmt->fetchAll(PDO::FETCH_ASSOC);

        $refererStmt = $db->prepare(
            'SELECT referer, COUNT(*) AS count FROM clicks WHERE link_id = :id AND referer IS NOT NULL GROUP BY referer ORDER BY count DESC LIMIT 20'
        );
        $refererStmt->execute([':id' => $id]);
        $referers = $refererStmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->successResponse([
            'link_id' => (int) $id,
            'total_clicks' => $totalClicks,
            'clicks_over_time' => $clicksOverTime,
            'countries' => $countries,
            'user_agents' => $userAgents,
            'referers' => $referers,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'group_by' => $groupBy,
        ])->send();
    }

    public function bulkStore(Request $request, Response $response, array $params = []): void
    {
        $urls = $request->input('urls', []);

        if (!is_array($urls) || empty($urls)) {
            $this->api->errorResponse('Provide an array of URLs.', 422, 'VALIDATION_ERROR', [
                'urls' => ['The urls field must be a non-empty array.'],
            ])->send();
            return;
        }

        $userId = $_SERVER['auth_user_id'] ?? null;
        $workspaceId = $_SERVER['auth_workspace_id'] ?? null;

        if ($userId === null) {
            $this->api->errorResponse('Unauthenticated.', 401, 'UNAUTHENTICATED')->send();
            return;
        }

        $db = Database::connection();
        $created = [];
        $errors = [];

        foreach ($urls as $index => $entry) {
            $url = is_array($entry) ? ($entry['url'] ?? '') : $entry;
            $customCode = is_array($entry) ? ($entry['short_code'] ?? null) : null;

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = ['index' => $index, 'url' => $url, 'error' => 'Invalid URL format.'];
                continue;
            }

            $shortCode = $customCode ?? $this->generateShortCode();
            if (empty($customCode)) {
                while ($this->shortCodeExists($db, $shortCode)) {
                    $shortCode = $this->generateShortCode();
                }
            }

            try {
                $stmt = $db->prepare('
                    INSERT INTO links (user_id, workspace_id, url, short_code, type, clicks, created_at, updated_at)
                    VALUES (:user_id, :workspace_id, :url, :short_code, :type, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ');
                $stmt->execute([
                    ':user_id' => $userId,
                    ':workspace_id' => $workspaceId,
                    ':url' => $url,
                    ':short_code' => $shortCode,
                    ':type' => 'direct',
                ]);

                $linkId = (int) $db->lastInsertId();
                $linkStmt = $db->prepare('SELECT * FROM links WHERE id = :id');
                $linkStmt->execute([':id' => $linkId]);
                $created[] = $linkStmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $errors[] = ['index' => $index, 'url' => $url, 'error' => $e->getMessage()];
            }
        }

        $this->api->successResponse([
            'created' => $created,
            'errors' => $errors,
            'total_created' => count($created),
            'total_errors' => count($errors),
        ])->send();
    }

    public function qrcode(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $format = $request->query('format', 'png');

        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link === false) {
            $this->api->errorResponse('Link not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $appUrl = Env::get('APP_URL', 'http://localhost');
        $shortUrl = rtrim($appUrl, '/') . '/' . $link['short_code'];

        try {
            $rendererStyle = new RendererStyle(400, 2);

            if ($format === 'svg') {
                $renderer = new ImageRenderer($rendererStyle, new SvgImageBackEnd());
                $writer = new Writer($renderer);
                $qrCode = $writer->writeString($shortUrl);

                $response->header('Content-Type', 'image/svg+xml');
                $response->header('Content-Disposition', "inline; filename=\"qr-{$link['short_code']}.svg\"");
                $response->json(base64_encode($qrCode));
                $response->send();
            } else {
                $renderer = new ImageRenderer($rendererStyle, new Png());
                $writer = new Writer($renderer);
                $qrCode = $writer->writeString($shortUrl);

                $response->header('Content-Type', 'image/png');
                $response->header('Content-Disposition', "inline; filename=\"qr-{$link['short_code']}.png\"");
                $response->json(['qr_code' => base64_encode($qrCode), 'format' => 'png']);
                $response->send();
            }
        } catch (\Throwable $e) {
            $this->api->errorResponse('Failed to generate QR code: ' . $e->getMessage(), 500, 'QR_ERROR')->send();
        }
    }

    private function generateShortCode(int $length = 6): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    private function shortCodeExists(PDO $db, string $code): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM links WHERE short_code = :code');
        $stmt->execute([':code' => $code]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
