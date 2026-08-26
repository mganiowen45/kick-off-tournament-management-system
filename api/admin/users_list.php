<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin(); requireGet();
['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPaginationParams();

$where = ["role='player'"]; $params = [];
if (!empty($_GET['status'])) { $where[] = 'status=:status'; $params[':status'] = sanitize($_GET['status']); }
if (!empty($_GET['search'])) { $where[] = '(username LIKE :s OR email LIKE :s2 OR country LIKE :s3)';
    $s = '%'.sanitize($_GET['search']).'%'; $params[':s']=$s; $params[':s2']=$s; $params[':s3']=$s; }

$whereStr = implode(' AND ', $where);
$cntStmt  = db()->prepare("SELECT COUNT(*) FROM users WHERE $whereStr");
$cntStmt->execute($params); $total = (int)$cntStmt->fetchColumn();

$stmt = db()->prepare("SELECT id,username,email,country,preferred_game,status,points,wins,losses,total_matches,created_at,last_login FROM users WHERE $whereStr ORDER BY created_at DESC LIMIT :lim OFFSET :off");
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':lim',$limit,PDO::PARAM_INT);
$stmt->bindValue(':off',$offset,PDO::PARAM_INT);
$stmt->execute();

jsonSuccess(['users'=>$stmt->fetchAll(),'pagination'=>paginationMeta($total,$page,$limit)]);
