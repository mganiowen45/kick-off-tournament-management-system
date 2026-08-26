<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireCompletedPlayerProfile();
requireGet();
$matchId = (int)($_GET['match_id'] ?? 0);
if (!$matchId) jsonError('Match ID required.');

$stmt = db()->prepare("
    SELECT mr.*,u.username AS submitted_by_username
    FROM match_results mr JOIN users u ON u.id=mr.submitted_by
    WHERE mr.match_id=:id
");
$stmt->execute([':id'=>$matchId]);
$results = $stmt->fetchAll();

$match = db()->prepare("SELECT m.*,p1.username AS player1_name,p2.username AS player2_name FROM matches m JOIN users p1 ON p1.id=m.player1_id JOIN users p2 ON p2.id=m.player2_id WHERE m.id=:id");
$match->execute([':id'=>$matchId]);
jsonSuccess(['results'=>$results,'match'=>$match->fetch()]);
