<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();
requireGet();

$db = db();
$openStmt = $db->query("
    SELECT d.id AS dispute_id, d.status, d.outcome, d.reason, d.admin_note, d.created_at,
           m.id AS match_id, m.tournament_id, m.round_number, m.stage,
           t.name AS tournament_name, t.format,
           p1.id AS player1_id, p1.username AS player1_username,
           p2.id AS player2_id, p2.username AS player2_username,
           raiser.username AS raised_by_username
    FROM disputes d
    JOIN matches m ON m.id = d.match_id
    JOIN tournaments t ON t.id = m.tournament_id
    JOIN users p1 ON p1.id = m.player1_id
    JOIN users p2 ON p2.id = m.player2_id
    LEFT JOIN users raiser ON raiser.id = d.raised_by
    WHERE d.status IN ('open','under_review')
    ORDER BY d.created_at ASC
");
$open = array_map(static function (array $row): array {
    $row['allow_draw'] = ($row['format'] ?? '') === 'group_knockout' && ($row['stage'] ?? '') === 'group';
    return $row;
}, $openStmt->fetchAll());

$resolvedStmt = $db->query("
    SELECT d.*, m.tournament_id, m.stage, t.name AS tournament_name, t.format,
           p1.username AS player1_username, p2.username AS player2_username,
           adm.username AS resolved_by_username
    FROM disputes d
    JOIN matches m ON m.id = d.match_id
    JOIN tournaments t ON t.id = m.tournament_id
    JOIN users p1 ON p1.id = m.player1_id
    JOIN users p2 ON p2.id = m.player2_id
    LEFT JOIN users adm ON adm.id = d.resolved_by
    WHERE d.status = 'resolved'
    ORDER BY d.resolved_at DESC
    LIMIT 50
");

jsonSuccess(['open' => $open, 'resolved' => $resolvedStmt->fetchAll()]);
