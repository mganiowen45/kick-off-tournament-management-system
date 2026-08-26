<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireGet();
['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPaginationParams();

$total = (int) db()->query("SELECT COUNT(*) FROM users WHERE status='active' AND role='player'")->fetchColumn();
$stmt  = db()->prepare("SELECT * FROM vw_leaderboard LIMIT :lim OFFSET :off");
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$players = $stmt->fetchAll();

jsonSuccess(['players' => $players, 'pagination' => paginationMeta($total,$page,$limit)]);
