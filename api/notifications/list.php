<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin(); requireGet();
$uid    = currentUserId();
$unread = isset($_GET['unread_only']) && $_GET['unread_only'] === '1';

$where = $unread ? 'AND is_read=0' : '';
$stmt  = db()->prepare("SELECT * FROM notifications WHERE user_id=:uid $where ORDER BY created_at DESC LIMIT 50");
$stmt->execute([':uid'=>$uid]);

$count = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND is_read=0");
$count->execute([':uid'=>$uid]);

jsonSuccess(['notifications'=>$stmt->fetchAll(),'unread_count'=>(int)$count->fetchColumn()]);
