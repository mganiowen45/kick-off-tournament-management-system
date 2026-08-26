<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin(); requirePost();
$data  = getPostData();
$uid   = currentUserId();
$id    = sanitize($data['id'] ?? 'all');

if ($id === 'all') {
    db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=:uid")->execute([':uid'=>$uid]);
    jsonSuccess([],'All notifications marked as read.');
} else {
    db()->prepare("UPDATE notifications SET is_read=1 WHERE id=:id AND user_id=:uid")->execute([':id'=>(int)$id,':uid'=>$uid]);
    jsonSuccess([],'Notification marked as read.');
}
