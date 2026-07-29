<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;
use PDO;

class AnalyticsService
{
    private PDO $db;
    private string $cachePath;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->cachePath = dirname(__DIR__, 2) . '/storage/cache/geo';
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function recordClick(int $linkId, Request $request): void
    {
        $ip = $request->ip();
        $ipHash = hash('sha256', $ip);
        $userAgent = $request->userAgent();
        $parsed = $this->parseUserAgent($userAgent);
        $referrer = $request->header('Referer', '');

        $geoEnabled = Env::get('FEATURE_GEOLOCATION', 'true') === 'true';
        $geo = $geoEnabled ? $this->lookupIP($ip) : ['country' => null, 'city' => null, 'lat' => null, 'lon' => null];

        $acceptLang = $request->header('Accept-Language', '');
        $lang = 'Unknown';
        if (!empty($acceptLang)) {
            $parts = explode(',', $acceptLang);
            if (isset($parts[0])) {
                $primaryLang = trim(explode(';', $parts[0])[0]);
                $lang = substr($primaryLang, 0, 10);
            }
        }

        $stmt = $this->db->prepare('
            INSERT INTO link_clicks
                (link_id, ip_hash, ip_address, country, city, latitude, longitude,
                 device_type, browser, browser_version, os, referrer, user_agent, user_language, clicked_at)
            VALUES
                (:link_id, :ip_hash, :ip_address, :country, :city, :latitude, :longitude,
                 :device_type, :browser, :browser_version, :os, :referrer, :user_agent, :user_language, CURRENT_TIMESTAMP)
        ');

        $stmt->execute([
            ':link_id'         => $linkId,
            ':ip_hash'         => $ipHash,
            ':ip_address'      => $ip,
            ':country'         => $geo['country'],
            ':city'            => $geo['city'],
            ':latitude'        => $geo['lat'],
            ':longitude'       => $geo['lon'],
            ':device_type'     => $parsed['deviceType'],
            ':browser'         => $parsed['browser'],
            ':browser_version' => $parsed['browserVersion'],
            ':os'              => $parsed['os'],
            ':referrer'        => $referrer,
            ':user_agent'      => $userAgent,
            ':user_language'   => $lang,
        ]);
    }

    public function getTotalClicks(int $linkId, ?string $startDate = null, ?string $endDate = null): int
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM link_clicks WHERE link_id = :link_id {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getUniqueClicks(int $linkId, ?string $startDate = null, ?string $endDate = null): int
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT ip_hash || browser || os) FROM link_clicks WHERE link_id = :link_id {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getClicksByCountry(int $linkId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("
            SELECT COALESCE(NULLIF(country, ''), 'Unknown') AS country, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            GROUP BY country
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClicksByCity(int $linkId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("
            SELECT COALESCE(NULLIF(city, ''), 'Unknown') AS city,
                   COALESCE(NULLIF(country, ''), 'Unknown') AS country,
                   COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            GROUP BY city, country
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClicksByDevice(int $linkId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("
            SELECT COALESCE(NULLIF(device_type, ''), 'Unknown') AS device_type, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            GROUP BY device_type
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClicksByBrowser(int $linkId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("
            SELECT COALESCE(NULLIF(browser, ''), 'Unknown') AS browser, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            GROUP BY browser
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClicksByOS(int $linkId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("
            SELECT COALESCE(NULLIF(os, ''), 'Unknown') AS os, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            GROUP BY os
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClicksByReferrer(int $linkId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("
            SELECT
                CASE
                    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                    WHEN referrer LIKE '%google.%' THEN 'Google'
                    WHEN referrer LIKE '%facebook.%' OR referrer LIKE '%fb.%' THEN 'Facebook'
                    WHEN referrer LIKE '%twitter.%' OR referrer LIKE '%x.%' THEN 'Twitter/X'
                    WHEN referrer LIKE '%linkedin.%' THEN 'LinkedIn'
                    WHEN referrer LIKE '%instagram.%' THEN 'Instagram'
                    WHEN referrer LIKE '%youtube.%' THEN 'YouTube'
                    WHEN referrer LIKE '%reddit.%' THEN 'Reddit'
                    WHEN referrer LIKE '%t.co%' THEN 'Twitter/X'
                    ELSE SUBSTR(referrer, 1, 100)
                END AS referrer_group,
                COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            GROUP BY referrer_group
            ORDER BY count DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClicksByTime(int $linkId, string $interval = 'day', ?string $startDate = null, ?string $endDate = null): array
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);

        $dateFormat = match ($interval) {
            'hour' => "%Y-%m-%d %H:00",
            'month' => "%Y-%m",
            default => "%Y-%m-%d",
        };

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $dateExpr = $driver === 'pgsql'
            ? "TO_CHAR(clicked_at, '{$dateFormat}')"
            : "STRFTIME('{$dateFormat}', clicked_at)";

        $stmt = $this->db->prepare("
            SELECT {$dateExpr} AS label, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            GROUP BY label
            ORDER BY label ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentClicks(int $linkId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT id, ip_hash, ip_address, country, city, device_type, browser, os, referrer, user_language, clicked_at
            FROM link_clicks
            WHERE link_id = :link_id
            ORDER BY clicked_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLinkStats(int $linkId): array
    {
        $total = $this->getTotalClicks($linkId);
        $unique = $this->getUniqueClicks($linkId);

        $stmt = $this->db->prepare('SELECT original_url, slug, created_at FROM links WHERE id = :id');
        $stmt->execute([':id' => $linkId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        $countries = $this->getClicksByCountry($linkId, null, null);
        $devices = $this->getClicksByDevice($linkId, null, null);

        return [
            'link' => $link ?: null,
            'total_clicks' => $total,
            'unique_clicks' => $unique,
            'countries' => count($countries),
            'countries_data' => $countries,
            'devices' => $devices,
            'browsers' => $this->getClicksByBrowser($linkId, null, null),
            'os' => $this->getClicksByOS($linkId, null, null),
            'referrers' => $this->getClicksByReferrer($linkId, null, null),
            'clicks_over_time' => $this->getClicksByTime($linkId, 'day', null, null),
            'recent_clicks' => $this->getRecentClicks($linkId, 10),
            'languages' => $this->getClicksByLanguage($linkId, null, null),
        ];
    }

    public function getClicksByLanguage(int $linkId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("
            SELECT COALESCE(NULLIF(user_language, ''), 'Unknown') AS language, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            GROUP BY language
            ORDER BY count DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWorkspaceStats(int $workspaceId, ?string $startDate = null, ?string $endDate = null): array
    {
        $params = [':workspace_id' => $workspaceId];
        $dateJoin = '';

        if ($startDate && $endDate) {
            $dateJoin = 'AND lc.clicked_at >= :start_date AND lc.clicked_at <= :end_date';
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        } elseif ($startDate) {
            $dateJoin = 'AND lc.clicked_at >= :start_date';
            $params[':start_date'] = $startDate;
        } elseif ($endDate) {
            $dateJoin = 'AND lc.clicked_at <= :end_date';
            $params[':end_date'] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total_clicks,
                   COUNT(DISTINCT lc.ip_hash || lc.browser || lc.os) AS unique_clicks,
                   COUNT(DISTINCT lc.link_id) AS clicked_links
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT l.id, l.slug, l.original_url, COUNT(lc.id) AS clicks
            FROM links l
            LEFT JOIN link_clicks lc ON lc.link_id = l.id {$this->dateJoinCondition($startDate, $endDate)}
            WHERE l.workspace_id = :workspace_id
            GROUP BY l.id
            ORDER BY clicks DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        $topLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Workspace countries breakdown
        $stmt = $this->db->prepare("
            SELECT lc.country, COUNT(*) AS count
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
            GROUP BY lc.country
            ORDER BY count DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Workspace devices breakdown
        $stmt = $this->db->prepare("
            SELECT lc.device_type, COUNT(*) AS count
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
            GROUP BY lc.device_type
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Workspace browsers breakdown
        $stmt = $this->db->prepare("
            SELECT lc.browser, COUNT(*) AS count
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
            GROUP BY lc.browser
            ORDER BY count DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        $browsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Workspace OS breakdown
        $stmt = $this->db->prepare("
            SELECT lc.os, COUNT(*) AS count
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
            GROUP BY lc.os
            ORDER BY count DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        $os = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Workspace referrers breakdown
        $stmt = $this->db->prepare("
            SELECT
                CASE
                    WHEN lc.referrer IS NULL OR lc.referrer = '' THEN 'Direct'
                    WHEN lc.referrer LIKE '%google.%' THEN 'Google'
                    WHEN lc.referrer LIKE '%facebook.%' OR lc.referrer LIKE '%fb.%' THEN 'Facebook'
                    WHEN lc.referrer LIKE '%twitter.%' OR lc.referrer LIKE '%x.%' THEN 'Twitter/X'
                    WHEN lc.referrer LIKE '%linkedin.%' THEN 'LinkedIn'
                    WHEN lc.referrer LIKE '%instagram.%' THEN 'Instagram'
                    WHEN lc.referrer LIKE '%youtube.%' THEN 'YouTube'
                    WHEN lc.referrer LIKE '%reddit.%' THEN 'Reddit'
                    WHEN lc.referrer LIKE '%t.co%' THEN 'Twitter/X'
                    ELSE SUBSTR(lc.referrer, 1, 100)
                END AS referrer_group,
                COUNT(*) AS count
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
            GROUP BY referrer_group
            ORDER BY count DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        $referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Workspace clicks over time
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $dateExpr = $driver === 'pgsql'
            ? "TO_CHAR(lc.clicked_at, 'YYYY-MM-DD')"
            : "STRFTIME('%Y-%m-%d', lc.clicked_at)";
        $stmt = $this->db->prepare("
            SELECT {$dateExpr} AS label, COUNT(*) AS count
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
            GROUP BY label
            ORDER BY label ASC
        ");
        $stmt->execute($params);
        $clicksOverTime = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Workspace languages breakdown
        $stmt = $this->db->prepare("
            SELECT COALESCE(NULLIF(lc.user_language, ''), 'Unknown') AS language, COUNT(*) AS count
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
            GROUP BY language
            ORDER BY count DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_clicks' => (int) ($summary['total_clicks'] ?? 0),
            'unique_clicks' => (int) ($summary['unique_clicks'] ?? 0),
            'clicked_links' => (int) ($summary['clicked_links'] ?? 0),
            'top_links' => $topLinks,
            'countries_data' => $countries,
            'devices' => $devices,
            'browsers' => $browsers,
            'os' => $os,
            'referrers' => $referrers,
            'clicks_over_time' => $clicksOverTime,
            'languages' => $languages,
        ];
    }

    public function exportAnalytics(int $linkId, string $format = 'csv', ?string $startDate = null, ?string $endDate = null): string
    {
        [$where, $params] = $this->buildDateFilter($linkId, $startDate, $endDate);
        $stmt = $this->db->prepare("
            SELECT ip_hash, ip_address, country, city, latitude, longitude, device_type, browser,
                   browser_version, os, referrer, user_language, clicked_at
            FROM link_clicks
            WHERE link_id = :link_id {$where}
            ORDER BY clicked_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($format === 'json') {
            return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, array_keys($rows[0] ?? []));
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);
        return $output;
    }

    public function lookupIP(string $ip): array
    {
        $default = ['country' => 'Unknown', 'city' => 'Unknown', 'lat' => null, 'lon' => null];

        $cacheFile = $this->cachePath . '/' . hash('sha256', $ip) . '.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return $default;
        }

        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city,lat,lon", false, stream_context_create([
                'http' => ['timeout' => 3, 'method' => 'GET'],
            ]));

            if ($response !== false) {
                $data = json_decode($response, true);
                if (is_array($data) && !empty($data['country'])) {
                    $result = [
                        'country' => $data['country'] ?? 'Unknown',
                        'city' => $data['city'] ?? 'Unknown',
                        'lat' => $data['lat'] ?? null,
                        'lon' => $data['lon'] ?? null,
                    ];
                    file_put_contents($cacheFile, json_encode($result));
                    return $result;
                }
            }
        } catch (\Throwable) {
        }

        return $default;
    }

    public function parseUserAgent(string $userAgent): array
    {
        $result = [
            'browser' => 'Unknown',
            'browserVersion' => '',
            'os' => 'Unknown',
            'deviceType' => 'Desktop',
        ];

        if (empty($userAgent)) {
            return $result;
        }

        $ua = $userAgent;

        if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) {
            $result['browser'] = 'Edge';
            preg_match('/(?:Edge|Edg)\/([\d.]+)/i', $ua, $m);
            $result['browserVersion'] = $m[1] ?? '';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $result['browser'] = 'Opera';
            preg_match('/(?:OPR|Opera)\s*\/?\s*([\d.]+)/i', $ua, $m);
            $result['browserVersion'] = $m[1] ?? '';
        } elseif (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false) {
            $result['browser'] = 'Chrome';
            preg_match('/Chrome\/([\d.]+)/i', $ua, $m);
            $result['browserVersion'] = $m[1] ?? '';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $result['browser'] = 'Firefox';
            preg_match('/Firefox\/([\d.]+)/i', $ua, $m);
            $result['browserVersion'] = $m[1] ?? '';
        } elseif (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome') === false) {
            $result['browser'] = 'Safari';
            preg_match('/Version\/([\d.]+)/i', $ua, $m);
            $result['browserVersion'] = $m[1] ?? '';
        } elseif (stripos($ua, 'Chromium') !== false) {
            $result['browser'] = 'Chromium';
            preg_match('/Chromium\/([\d.]+)/i', $ua, $m);
            $result['browserVersion'] = $m[1] ?? '';
        }

        if (stripos($ua, 'Windows') !== false) {
            $result['os'] = 'Windows';
        } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'macOS') !== false) {
            $result['os'] = 'macOS';
        } elseif (stripos($ua, 'Linux') !== false && stripos($ua, 'Android') === false) {
            $result['os'] = 'Linux';
        } elseif (stripos($ua, 'Android') !== false) {
            $result['os'] = 'Android';
        } elseif (stripos($ua, 'iOS') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $result['os'] = 'iOS';
        } elseif (stripos($ua, 'CrOS') !== false) {
            $result['os'] = 'Chrome OS';
        }

        if (stripos($ua, 'iPad') !== false || stripos($ua, 'tablet') !== false) {
            $result['deviceType'] = 'Tablet';
        } elseif (stripos($ua, 'Mobile') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'Android.*Mobile') !== false) {
            $result['deviceType'] = 'Mobile';
        }

        return $result;
    }

    public function exportWorkspaceAnalytics(int $workspaceId, string $format = 'csv', ?string $startDate = null, ?string $endDate = null): string
    {
        if ($startDate !== null && !strtotime($startDate)) {
            $startDate = null;
        }
        if ($endDate !== null && !strtotime($endDate)) {
            $endDate = null;
        }

        $params = [':workspace_id' => $workspaceId];
        $dateJoin = '';

        if ($startDate && $endDate) {
            $dateJoin = 'AND lc.clicked_at >= :start_date AND lc.clicked_at <= :end_date';
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        } elseif ($startDate) {
            $dateJoin = 'AND lc.clicked_at >= :start_date';
            $params[':start_date'] = $startDate . ' 00:00:00';
        } elseif ($endDate) {
            $dateJoin = 'AND lc.clicked_at <= :end_date';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $stmt = $this->db->prepare("
            SELECT l.slug, lc.ip_hash, lc.ip_address, lc.country, lc.city, lc.latitude, lc.longitude,
                   lc.device_type, lc.browser, lc.browser_version, lc.os, lc.referrer, lc.user_language, lc.clicked_at
            FROM link_clicks lc
            JOIN links l ON l.id = lc.link_id
            WHERE l.workspace_id = :workspace_id {$dateJoin}
            ORDER BY lc.clicked_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($format === 'json') {
            return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, array_keys($rows[0] ?? ['Slug', 'IP Hash', 'IP Address', 'Country', 'City', 'Latitude', 'Longitude', 'Device Type', 'Browser', 'Browser Version', 'OS', 'Referrer', 'Language', 'Clicked At']));
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);
        return $output;
    }

    private function buildDateFilter(int $linkId, ?string $startDate = null, ?string $endDate = null): array
    {
        if ($startDate !== null && !strtotime($startDate)) {
            $startDate = null;
        }
        if ($endDate !== null && !strtotime($endDate)) {
            $endDate = null;
        }

        $where = '';
        $params = [':link_id' => $linkId];

        if ($startDate && $endDate) {
            $where = 'AND clicked_at >= :start_date AND clicked_at <= :end_date';
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        } elseif ($startDate) {
            $where = 'AND clicked_at >= :start_date';
            $params[':start_date'] = $startDate;
        } elseif ($endDate) {
            $where = 'AND clicked_at <= :end_date';
            $params[':end_date'] = $endDate;
        }

        return [$where, $params];
    }

    private function dateJoinCondition(?string $startDate = null, ?string $endDate = null): string
    {
        if ($startDate && $endDate) {
            return "AND lc.clicked_at >= :start_date AND lc.clicked_at <= :end_date";
        }
        if ($startDate) {
            return "AND lc.clicked_at >= :start_date";
        }
        if ($endDate) {
            return "AND lc.clicked_at <= :end_date";
        }
        return '';
    }
}
