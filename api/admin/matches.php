<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();
requireGet();

$stmt = db()->query("
    SELECT m.id, m.tournament_id, m.stage, m.round_number, m.status,
           m.player1_score, m.player2_score, m.scheduled_at,
           t.name AS tournament_name,
           p1.username AS player1_username,
           p2.username AS player2_username
    FROM matches m
    JOIN tournaments t ON t.id = m.tournament_id
    JOIN users p1 ON p1.id = m.player1_id
    JOIN users p2 ON p2.id = m.player2_id
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT 100
");

jsonSuccess(['matches' => $stmt->fetchAll()]);
