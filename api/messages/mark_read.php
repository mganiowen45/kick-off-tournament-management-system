<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin(); requirePost();
$data = getPostData();
$uid  = currentUserId();
$from = (int)($data['sender_id'] ?? 0);
if (!$from) jsonError('Sender ID required.');

$stmt = db()->prepare("UPDATE messages SET is_read=1 WHERE sender_id=:from AND receiver_id=:uid AND type='direct'");
$stmt->execute([':from'=>$from,':uid'=>$uid]);
jsonSuccess(['updated'=>$stmt->rowCount()]);
