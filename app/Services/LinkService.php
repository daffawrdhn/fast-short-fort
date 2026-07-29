<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Models\Link;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PDO;

class LinkService
{
    private AnalyticsService $analytics;
    private PDO $db;

    public function __construct()
    {
        $this->analytics = new AnalyticsService();
        $this->db = Database::connection();
    }

    public function generateSlug(int $length = 7): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $slug = '';
        for ($i = 0; $i < $length; $i++) {
            $slug .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $slug;
    }

    public function validateSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9\-_]{3,50}$/', $slug);
    }

    public function isSlugAvailable(string $slug, int $workspaceId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM links WHERE slug = :slug AND workspace_id = :workspace_id');
        $stmt->execute([':slug' => $slug, ':workspace_id' => $workspaceId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    public function createLink(array $data): Link
    {
        return Link::create($data);
    }

    public function updateLink(int $id, array $data): bool
    {
        $link = Link::findById($id);
        if ($link === null) {
            return false;
        }
        return $link->update($data);
    }

    public function checkExpiration(Link $link): bool
    {
        if ($link->expires_at === null) {
            return false;
        }
        return strtotime($link->expires_at) < time();
    }

    public function checkClickLimit(Link $link): bool
    {
        if ($link->click_limit === null) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM link_clicks WHERE link_id = :link_id');
        $stmt->execute([':link_id' => $link->id]);
        $count = (int) $stmt->fetchColumn();
        return $count >= $link->click_limit;
    }

    public function recordClick(int $linkId, Request $request): void
    {
        $this->analytics->recordClick($linkId, $request);
    }

    public function getQRCode(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($url));
    }

    public function buildUTMUrl(string $url, array $utmParams): string
    {
        $params = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            if (!empty($utmParams[$key])) {
                $params[$key] = $utmParams[$key];
            }
        }
        if (empty($params)) {
            return $url;
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function validateURL(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM blocklist WHERE :url LIKE CONCAT(\'%\', pattern, \'%\')');
        $stmt->execute([':url' => $url]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }

        return true;
    }
}
