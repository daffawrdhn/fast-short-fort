<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class LinkClick
{
    public ?int $id = null;
    public ?int $link_id = null;
    public ?string $ip_hash = null;
    public ?string $country = null;
    public ?string $city = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?string $device_type = null;
    public ?string $browser = null;
    public ?string $browser_version = null;
    public ?string $os = null;
    public ?string $referrer = null;
    public ?string $user_agent = null;
    public ?string $clicked_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function create(array $data): self
    {
        $stmt = self::db()->prepare('
            INSERT INTO link_clicks (link_id, ip_hash, country, city, latitude, longitude,
                                     device_type, browser, browser_version, os,
                                     referrer, user_agent, clicked_at)
            VALUES (:link_id, :ip_hash, :country, :city, :latitude, :longitude,
                    :device_type, :browser, :browser_version, :os,
                    :referrer, :user_agent, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':link_id' => $data['link_id'],
            ':ip_hash' => $data['ip_hash'],
            ':country' => $data['country'] ?? null,
            ':city' => $data['city'] ?? null,
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':device_type' => $data['device_type'] ?? null,
            ':browser' => $data['browser'] ?? null,
            ':browser_version' => $data['browser_version'] ?? null,
            ':os' => $data['os'] ?? null,
            ':referrer' => $data['referrer'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
        ]);

        $id = (int) self::db()->lastInsertId();
        $stmt = self::db()->prepare('SELECT * FROM link_clicks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return self::hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public static function getStats(int $linkId): array
    {
        $stats = [];

        $stmt = self::db()->prepare('SELECT COUNT(*) AS total FROM link_clicks WHERE link_id = :link_id');
        $stmt->execute([':link_id' => $linkId]);
        $stats['total_clicks'] = (int) $stmt->fetchColumn();

        $stmt = self::db()->prepare('SELECT COUNT(DISTINCT ip_hash) AS unique_clicks FROM link_clicks WHERE link_id = :link_id');
        $stmt->execute([':link_id' => $linkId]);
        $stats['unique_clicks'] = (int) $stmt->fetchColumn();

        return $stats;
    }

    public static function getClicksByCountry(int $linkId): array
    {
        $stmt = self::db()->prepare('
            SELECT country, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id AND country IS NOT NULL
            GROUP BY country
            ORDER BY count DESC
        ');
        $stmt->execute([':link_id' => $linkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getClicksByDevice(int $linkId): array
    {
        $stmt = self::db()->prepare('
            SELECT device_type, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id AND device_type IS NOT NULL
            GROUP BY device_type
            ORDER BY count DESC
        ');
        $stmt->execute([':link_id' => $linkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getClicksByBrowser(int $linkId): array
    {
        $stmt = self::db()->prepare('
            SELECT browser, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id AND browser IS NOT NULL
            GROUP BY browser
            ORDER BY count DESC
        ');
        $stmt->execute([':link_id' => $linkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getClicksByOs(int $linkId): array
    {
        $stmt = self::db()->prepare('
            SELECT os, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id AND os IS NOT NULL
            GROUP BY os
            ORDER BY count DESC
        ');
        $stmt->execute([':link_id' => $linkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getClicksByTime(int $linkId, string $grouping = 'daily'): array
    {
        $format = match ($grouping) {
            'hourly' => '%Y-%m-%d %H:00:00',
            'monthly' => '%Y-%m-01',
            default => '%Y-%m-%d',
        };

        if (self::db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $dateExpr = match ($grouping) {
                'hourly' => "strftime('%Y-%m-%d %H:00:00', clicked_at)",
                'monthly' => "strftime('%Y-%m-01', clicked_at)",
                default => "strftime('%Y-%m-%d', clicked_at)",
            };
        } else {
            $dateExpr = match ($grouping) {
                'hourly' => "TO_CHAR(clicked_at, 'YYYY-MM-DD HH24:00:00')",
                'monthly' => "TO_CHAR(clicked_at, 'YYYY-MM-01')",
                default => "TO_CHAR(clicked_at, 'YYYY-MM-DD')",
            };
        }

        $stmt = self::db()->prepare("
            SELECT {$dateExpr} AS period, COUNT(*) AS count
            FROM link_clicks
            WHERE link_id = :link_id
            GROUP BY period
            ORDER BY period ASC
        ");
        $stmt->execute([':link_id' => $linkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecentClicks(int $linkId, int $limit = 50): array
    {
        $stmt = self::db()->prepare('
            SELECT * FROM link_clicks
            WHERE link_id = :link_id
            ORDER BY clicked_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function exportToCSV(int $linkId): string
    {
        $stmt = self::db()->prepare('
            SELECT ip_hash, country, city, device_type, browser, browser_version, os,
                   referrer, user_agent, clicked_at
            FROM link_clicks
            WHERE link_id = :link_id
            ORDER BY clicked_at DESC
        ');
        $stmt->execute([':link_id' => $linkId]);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['IP Hash', 'Country', 'City', 'Device Type', 'Browser',
                           'Browser Version', 'OS', 'Referrer', 'User Agent', 'Clicked At']);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private static function hydrate(array $data): self
    {
        $click = new self();
        $click->id = (int) $data['id'];
        $click->link_id = (int) ($data['link_id'] ?? 0);
        $click->ip_hash = $data['ip_hash'] ?? null;
        $click->country = $data['country'] ?? null;
        $click->city = $data['city'] ?? null;
        $click->latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $click->longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $click->device_type = $data['device_type'] ?? null;
        $click->browser = $data['browser'] ?? null;
        $click->browser_version = $data['browser_version'] ?? null;
        $click->os = $data['os'] ?? null;
        $click->referrer = $data['referrer'] ?? null;
        $click->user_agent = $data['user_agent'] ?? null;
        $click->clicked_at = $data['clicked_at'] ?? null;
        return $click;
    }
}
