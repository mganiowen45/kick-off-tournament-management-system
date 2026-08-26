<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

final class AvatarCatalog
{
    private const AVATARS = [
        'gamer-neon.svg' => ['label' => 'Neon Gamer', 'category' => 'male_character'],
        'gamer-cyber.svg' => ['label' => 'Cyber Gamer', 'category' => 'male_character'],
        'football-striker.svg' => ['label' => 'Star Striker', 'category' => 'club'],
        'football-keeper.svg' => ['label' => 'Goal Keeper', 'category' => 'club'],
        'cartoon-hero.svg' => ['label' => 'Cartoon Hero', 'category' => 'male_character'],
        'cartoon-mascot.svg' => ['label' => 'Club Mascot', 'category' => 'club'],
        'esports-viper.svg' => ['label' => 'Viper', 'category' => 'male_character'],
        'esports-titan.svg' => ['label' => 'Titan', 'category' => 'male_character'],
        'male-blue.svg' => ['label' => 'Blue Captain', 'category' => 'male_character'],
        'male-gold.svg' => ['label' => 'Gold Captain', 'category' => 'male_character'],
        'female-violet.svg' => ['label' => 'Violet Captain', 'category' => 'female_character'],
        'female-cyan.svg' => ['label' => 'Cyan Captain', 'category' => 'female_character'],
        'flag-tanzania.svg' => ['label' => 'Tanzania Flag', 'category' => 'country_flag'],
        'flag-kenya.svg' => ['label' => 'Kenya Flag', 'category' => 'country_flag'],
        'flag-uganda.svg' => ['label' => 'Uganda Flag', 'category' => 'country_flag'],
        'flag-nigeria.svg' => ['label' => 'Nigeria Flag', 'category' => 'country_flag'],
        'neutral-orbit.svg' => ['label' => 'Orbit', 'category' => 'male_character'],
        'neutral-flame.svg' => ['label' => 'Flame', 'category' => 'male_character'],
    ];

    public static function all(?PDO $pdo = null): array
    {
        if ($pdo) {
            try {
                $rows = $pdo->query(
                    "SELECT file_path AS filename, name AS label, category
                     FROM system_avatars WHERE is_active = 1
                     ORDER BY sort_order ASC, name ASC"
                )->fetchAll(PDO::FETCH_ASSOC);
                if ($rows) {
                    return array_map(static function (array $row): array {
                        $filename = basename((string) $row['filename']);
                        return [
                            'id' => $filename,
                            'filename' => $filename,
                            'label' => (string) $row['label'],
                            'category' => (string) $row['category'],
                            'url' => self::url($filename),
                        ];
                    }, $rows);
                }
            } catch (Throwable) {
            }
        }

        $items = [];
        foreach (self::AVATARS as $filename => $meta) {
            $items[] = [
                'id' => $filename,
                'filename' => $filename,
                'label' => $meta['label'],
                'category' => $meta['category'],
                'url' => self::url($filename),
            ];
        }
        return $items;
    }

    public static function isValid(?string $filename, ?PDO $pdo = null): bool
    {
        if ($filename === null) {
            return false;
        }
        if (isset(self::AVATARS[$filename])) {
            return true;
        }
        if ($pdo) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM system_avatars WHERE file_path = :file AND is_active = 1 LIMIT 1'
                );
                $stmt->execute([':file' => basename($filename)]);
                return (bool) $stmt->fetchColumn();
            } catch (Throwable) {
            }
        }
        return false;
    }

    public static function normalize(?string $filename): string
    {
        return self::isValid($filename) ? (string) $filename : DEFAULT_AVATAR;
    }

    public static function url(?string $filename): string
    {
        return AVATAR_URL . rawurlencode(self::normalize($filename));
    }

    public static function decorate(array $user): array
    {
        $filename = self::normalize($user['avatar_url'] ?? null);
        $user['avatar'] = $filename;
        $user['avatar_url'] = self::url($filename);
        return $user;
    }

    private function __construct()
    {
    }
}
