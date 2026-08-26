<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin(); requireGet();
$uid      = currentUserId();
$otherId  = (int)($_GET['with'] ?? 0);
if (!$otherId) jsonError('User ID required (?with=)');

['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPaginationParams();

$stmt = db()->prepare("
    SELECT m.*, u.username AS sender_username
    FROM messages m JOIN users u ON u.id=m.sender_id
    WHERE m.type='direct'
      AND ((m.sender_id=:uid AND m.receiver_id=:oid)
        OR (m.sender_id=:oid2 AND m.receiver_id=:uid2))
    ORDER BY m.sent_at ASC LIMIT :lim OFFSET :off
");
$stmt->bindValue(':uid',  $uid,     PDO::PARAM_INT);
$stmt->bindValue(':oid',  $otherId, PDO::PARAM_INT);
$stmt->bindValue(':oid2', $otherId, PDO::PARAM_INT);
$stmt->bindValue(':uid2', $uid,     PDO::PARAM_INT);
$stmt->bindValue(':lim',  $limit,   PDO::PARAM_INT);
$stmt->bindValue(':off',  $offset,  PDO::PARAM_INT);
$stmt->execute();

// Mark as read
db()->prepare("UPDATE messages SET is_read=1 WHERE sender_id=:oid AND receiver_id=:uid AND type='direct' AND is_read=0")
    ->execute([':oid'=>$otherId,':uid'=>$uid]);

// Other user info
$other = db()->prepare("SELECT id,username,country,status FROM users WHERE id=:id");
$other->execute([':id'=>$otherId]);

jsonSuccess(['messages'=>$stmt->fetchAll(),'other_user'=>$other->fetch()]);
