<?php
// ============================================================
//  KICKOFF — Shared Helper Functions
// ============================================================

// ── JSON response helpers ──────────────────────────────────
// Content-Type header MUST be set here — without it, any PHP
// notice/warning that fires before output will be sent as HTML,
// causing "Unexpected token '<'" in the browser fetch call.

function jsonSuccess(array $data = [], string $message = 'OK'): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit();
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

// ── Sanitize input ─────────────────────────────────────────
function sanitize(mixed $value): string {
    return htmlspecialchars(strip_tags(trim((string) $value)), ENT_QUOTES, 'UTF-8');
}

// ── Require POST method ────────────────────────────────────
function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed. Use POST.', 405);
    }
}

// ── Require GET method ─────────────────────────────────────
function requireGet(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Method not allowed. Use GET.', 405);
    }
}

// ── Get POST body (JSON or form-data) ─────────────────────
function getPostData(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
    return $_POST;
}

// ── Validate required fields ───────────────────────────────
function requireFields(array $data, array $fields): void {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            jsonError("Missing required field: $field");
        }
    }
}

// ── Calculate win rate ─────────────────────────────────────
function calcWinRate(int $wins, int $total): float {
    if ($total === 0) return 0.0;
    return round(($wins / $total) * 100, 1);
}

// ── Format date for display ────────────────────────────────
function formatDate(string $date): string {
    return date('M j, Y', strtotime($date));
}

// ── Format datetime ────────────────────────────────────────
function formatDateTime(string $dt): string {
    return date('M j, Y g:i A', strtotime($dt));
}

// ── Time ago string ────────────────────────────────────────
function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

// ── Generate unique filename for uploads ───────────────────
function generateFilename(string $prefix, string $ext): string {
    return $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
}

// ── Paginate query helper ──────────────────────────────────
function getPaginationParams(): array {
    $page  = max(1, (int) ($_GET['page']  ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? DEFAULT_PAGE_LIMIT)));
    $offset = ($page - 1) * $limit;
    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}

// ── Build pagination meta ──────────────────────────────────
function paginationMeta(int $total, int $page, int $limit): array {
    return [
        'total'        => $total,
        'page'         => $page,
        'limit'        => $limit,
        'total_pages'  => (int) ceil($total / $limit),
        'has_next'     => ($page * $limit) < $total,
        'has_prev'     => $page > 1,
    ];
}

// ── Validate email ─────────────────────────────────────────
function isValidEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ── Validate username ──────────────────────────────────────
function isValidUsername(string $username): bool {
    return (bool) preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username);
}