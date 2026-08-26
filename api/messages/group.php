<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin(); requireGet();
$uid = currentUserId();
$tid = (int)($_GET['tournament_id'] ?? 0);
if (!$tid) jsonError('Tournament ID required.');

// Verify membership
$mem = db()->prepare("SELECT id FROM tournament_players WHERE tournament_id=:tid AND user_id=:uid");
$mem->execute([':tid'=>$tid,':uid'=>$uid]);
if (!$mem->fetch()) jsonError('You are not a member of this tournament.',403);

['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPaginationParams();

$stmt = db()->prepare("
    SELECT m.*,u.username AS sender_username,u.country
    FROM messages m JOIN users u ON u.id=m.sender_id
    WHERE m.type='group' AND m.tournament_id=:tid
    ORDER BY m.sent_at ASC LIMIT :lim OFFSET :off
");
$stmt->bindValue(':tid', $tid,   PDO::PARAM_INT);
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,PDO::PARAM_INT);
$stmt->execute();

// Tournament info
$t = db()->prepare("SELECT id,name,status,format FROM tournaments WHERE id=:id");
$t->execute([':id'=>$tid]);

// Members list
$members = db()->prepare("SELECT u.id,u.username,u.country,tp.status FROM tournament_players tp JOIN users u ON u.id=tp.user_id WHERE tp.tournament_id=:tid");
$members->execute([':tid'=>$tid]);

jsonSuccess(['messages'=>$stmt->fetchAll(),'tournament'=>$t->fetch(),'members'=>$members->fetchAll()]);
