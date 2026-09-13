<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for turning a tournament's stored cover reference
 * into something the browser can actually render.
 *
 * Design goal: cover_image_url returned by the API must NEVER point at a
 * file that doesn't exist. Instead of checking file existence in three
 * different places and hoping every caller remembers to do it (the old
 * bug), everything funnels through resolve() below. If the stored file is
 * missing, we don't fall back to another hardcoded filename that might
 * *also* be missing - we generate a real, self-contained image on the
 * fly (an inline SVG data URI) that can never 404, because nothing is
 * fetched over the network to render it.
 */
final class CoverImage
{
    public const COVER_DIR = 'assets/tournament-covers';
    public const ALLOWED_EXTENSIONS = ['svg', 'png', 'jpg', 'jpeg', 'webp'];

    /** Dark neon palettes matching the site's visual language (bg1, bg2, accent). */
    private const PALETTES = [
        ['#0b0f1a', '#152233', '#00d9f5'],
        ['#150b1a', '#231533', '#a78bfa'],
        ['#0b1a12', '#152b1c', '#b5f500'],
        ['#1a0b12', '#2d1726', '#ff4e7a'],
        ['#1a130b', '#2b2015', '#ffb020'],
    ];

    /**
     * Resolve a tournament's stored cover path into a safe, displayable URL.
     *
     * @param string|null $storedPath  The raw file_path value from tournament_cover_images (or null).
     * @param string      $seedText    Text used to make the generated placeholder deterministic
     *                                 and unique per tournament (normally the tournament name).
     */
    public static function resolve(?string $storedPath, string $seedText = ''): string
    {
        $real = self::realFilePath((string) $storedPath);
        if ($real !== null) {
            return $real;
        }
        return self::placeholderDataUri($seedText);
    }

    /**
     * Returns the normalized, web-relative path if (and only if) the file
     * genuinely exists inside the cover directory. Returns null otherwise -
     * callers must not assume a non-null hardcoded constant is always safe.
     */
    public static function realFilePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('#/+#', '/', $path) ?? '';
        if (!preg_match('#^assets/tournament-covers/[A-Za-z0-9._-]+\.(svg|png|jpe?g|webp)$#i', $path)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }
        $absolute = self::coverDirectoryAbsolute() . DIRECTORY_SEPARATOR . basename($path);
        $realFile = realpath($absolute);
        $realDir = realpath(self::coverDirectoryAbsolute());
        if ($realFile === false || $realDir === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return is_file($realFile) ? $path : null;
    }

    public static function fileExists(string $path): bool
    {
        return self::realFilePath($path) !== null;
    }

    /**
     * Deterministically generated inline SVG cover, base64-encoded as a
     * data: URI. Same seed always produces the same look, so a given
     * tournament's placeholder stays visually stable across reloads.
     * Never touches the filesystem or network - cannot 404.
     */
    public static function placeholderDataUri(string $seedText): string
    {
        $seed = trim($seedText) !== '' ? trim($seedText) : 'KICKOFF';
        $hash = crc32($seed);
        [$bg1, $bg2, $accent] = self::PALETTES[$hash % count(self::PALETTES)];
        $initial = self::initialFor($seed);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 675" role="img" aria-label="Tournament cover">'
            . '<defs>'
            . '<linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            . '<stop stop-color="' . $bg1 . '"/><stop offset="1" stop-color="' . $bg2 . '"/>'
            . '</linearGradient>'
            . '<radialGradient id="r" cx="50%" cy="42%" r="60%">'
            . '<stop stop-color="' . $accent . '" stop-opacity=".35"/><stop offset="1" stop-color="' . $accent . '" stop-opacity="0"/>'
            . '</radialGradient>'
            . '</defs>'
            . '<rect width="1200" height="675" fill="url(#g)"/>'
            . '<circle cx="600" cy="300" r="320" fill="url(#r)"/>'
            . '<circle cx="600" cy="300" r="130" fill="none" stroke="' . $accent . '" stroke-width="6" opacity=".5"/>'
            . '<text x="600" y="348" font-family="Arial, Helvetica, sans-serif" font-size="160" font-weight="700" '
            . 'fill="' . $accent . '" text-anchor="middle" opacity=".92">' . $initial . '</text>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** True when the given cover_image_url is one of our generated placeholders (useful for UI/telemetry). */
    public static function isPlaceholder(string $url): bool
    {
        return str_starts_with($url, 'data:image/svg+xml;base64,');
    }

    private static function initialFor(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '?';
        }
        $char = function_exists('mb_substr') ? mb_substr($text, 0, 1) : substr($text, 0, 1);
        $char = function_exists('mb_strtoupper') ? mb_strtoupper($char) : strtoupper($char);
        return htmlspecialchars($char, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function coverDirectoryAbsolute(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::COVER_DIR);
    }
}
