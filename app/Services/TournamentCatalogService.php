<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\CoverImage;
use PDO;

final class TournamentCatalogService
{
    public const COVER_DIR = CoverImage::COVER_DIR;
    public const COVER_EXTENSIONS = CoverImage::ALLOWED_EXTENSIONS;

    public const FORMATS = ['1v1', 'full_knockout', 'group_knockout'];

    public const COVER_CATEGORIES = ['Arena', 'Stadium', 'Bracket', 'Console', 'Mobile', 'World Stage', 'Neon', 'General'];

    public const FORMAT_LABELS = [
        '1v1' => '1V1 Tournament',
        'full_knockout' => 'Full Knockout',
        'group_knockout' => 'Group Stage + Knockout',
    ];

    public const FUNDING_MODELS = ['free_casual', 'participant_funded', 'kickoff_sponsored'];

    public const FUNDING_LABELS = [
        'free_casual' => 'Free Casual Tournament',
        'participant_funded' => 'Participant-Funded Tournament',
        'kickoff_sponsored' => 'KICKOFF-Sponsored Tournament',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function games(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, name, slug, image_path FROM games WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static function (array $row): array {
            $fallbacks = [
                'efootball' => 'assets/supported_games/eFootball.jpg',
                'ea-sports-fc' => 'assets/supported_games/EA_sports.jpg',
                'dream-league-soccer' => 'assets/supported_games/DLS.jpg',
            ];
            $row['image_path'] = $row['image_path'] ?: ($fallbacks[$row['slug']] ?? 'assets/supported_games/eFootball.jpg');
            return $row;
        }, $rows);
    }

    public function platforms(): array
    {
        return $this->pdo->query(
            "SELECT id, name, slug FROM platforms WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Active covers available for the "choose a cover" picker in tournament creation. */
    public function covers(): array
    {
        $this->syncCovers();
        $rows = $this->pdo->query(
            "SELECT id, name, category, file_path FROM tournament_cover_images
             WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        // Only offer covers that genuinely exist on disk - no point letting someone
        // pick a cover that would immediately need a placeholder.
        return array_values(array_filter(array_map(static function (array $row): ?array {
            if (!CoverImage::fileExists((string) $row['file_path'])) {
                return null;
            }
            $row['asset_missing'] = false;
            return $row;
        }, $rows)));
    }

    public function gameExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM games WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function platformExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM platforms WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function coverExists(int $id): bool
    {
        $this->syncCovers();
        $stmt = $this->pdo->prepare('SELECT file_path FROM tournament_cover_images WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        $path = $stmt->fetchColumn();
        return $path !== false && CoverImage::fileExists((string) $path);
    }

    /** Picks up any image files dropped into assets/tournament-covers/ that aren't in the DB yet. */
    public function syncCovers(): void
    {
        $dir = $this->coverDirectoryAbsolute();
        if (!is_dir($dir)) return;
        $maxSort = (int) ($this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM tournament_cover_images')->fetchColumn() ?: 0);
        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO tournament_cover_images (name, category, file_path, is_active, sort_order)
             VALUES (:name, :category, :file_path, 1, :sort_order)'
        );
        $files = scandir($dir) ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = CoverImage::COVER_DIR . '/' . $file;
            if (!CoverImage::fileExists($path)) continue;
            $maxSort += 10;
            $insert->execute([
                ':name' => self::humanName(pathinfo($file, PATHINFO_FILENAME)),
                ':category' => 'General',
                ':file_path' => $path,
                ':sort_order' => $maxSort,
            ]);
        }
    }

    /**
     * Full cover catalog for the admin management screen, including rows whose
     * files are missing. `asset_missing` lets the admin UI flag those clearly
     * instead of silently substituting something else and hiding the problem.
     */
    public function allCoversForAdmin(): array
    {
        $this->syncCovers();
        $rows = $this->pdo->query(
            'SELECT * FROM tournament_cover_images ORDER BY is_active DESC, sort_order ASC, name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static function (array $row): array {
            $missing = !CoverImage::fileExists((string) $row['file_path']);
            $row['asset_missing'] = $missing;
            // Preview exactly what a player would see for this cover: the real
            // file if present, or the same generated placeholder as everywhere else.
            $row['resolved_file_path'] = $missing
                ? CoverImage::placeholderDataUri((string) $row['name'])
                : (string) $row['file_path'];
            return $row;
        }, $rows);
    }

    private function coverDirectoryAbsolute(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, CoverImage::COVER_DIR);
    }

    private static function humanName(string $name): string
    {
        $words = preg_split('/[-_]+/', $name) ?: [$name];
        return implode(' ', array_map(static fn(string $word): string => ucfirst(strtolower($word)), $words));
    }

    public function setting(string $key, string $default): string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    public static function formatLabel(?string $format): string
    {
        return self::FORMAT_LABELS[$format ?? ''] ?? (string) $format;
    }

    public static function fundingLabel(?string $model): string
    {
        return self::FUNDING_LABELS[$model ?? ''] ?? (string) $model;
    }
}
