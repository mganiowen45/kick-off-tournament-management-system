<?php
require_once __DIR__ . '/auth.php';

function kickoff_redirect(string $target): void {
    header('Location: ' . $target, true, 302);
    exit();
}

function safeReturnTo(?string $value): string {
    $value = trim((string) $value);
    if ($value === '' || strlen($value) > 300) return '';
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) || str_contains($value, '//') || str_contains($value, '\\')) return '';
    if (!preg_match('/^[A-Za-z0-9_.-]+\.html(?:\?[A-Za-z0-9%_.~=&+-]*)?$/', $value)) return '';
    if (str_starts_with($value, 'admin_')) return '';
    return $value;
}

function currentSafeReturnTo(): string {
    $path = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    $query = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    return safeReturnTo($path . ($query !== '' ? '?' . $query : ''));
}

function returnToQuery(string $target): string {
    $safe = safeReturnTo($target);
    return $safe !== '' ? '?return_to=' . rawurlencode($safe) : '';
}

function playerProfileCompleted(): bool {
    $userId = currentUserId();
    if (!$userId) {
        return false;
    }
    try {
        $stmt = db()->prepare('SELECT profile_setup_completed FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        return (int) $stmt->fetchColumn() === 1;
    } catch (Throwable) {
        return true;
    }
}

function requirePlayerPage(bool $requireCompletedProfile = true): void {
    if (!isLoggedIn()) {
        kickoff_redirect('login.html' . returnToQuery(currentSafeReturnTo()));
    }
    if (currentUserRole() === 'admin') {
        kickoff_redirect('admin_dashboard.html');
    }
    if ($requireCompletedProfile && !playerProfileCompleted()) {
        kickoff_redirect('profile_setup.html' . returnToQuery(currentSafeReturnTo()));
    }
    header('Content-Type: text/html; charset=utf-8');
}

function requirePlayerOnboardingPage(): void {
    requirePlayerPage(false);
    if (playerProfileCompleted()) {
        kickoff_redirect(safeReturnTo($_GET['return_to'] ?? '') ?: 'dashboard.html');
    }
}

function requireAdminPage(): void {
    if (!isLoggedIn()) {
        kickoff_redirect('login.html' . returnToQuery(currentSafeReturnTo()));
    }
    if (currentUserRole() !== 'admin') {
        kickoff_redirect('dashboard.html');
    }
    header('Content-Type: text/html; charset=utf-8');
}
