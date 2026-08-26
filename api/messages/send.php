<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

requireLogin(); requirePost();
$data    = getPostData();
$uid     = currentUserId();
$type    = sanitize($data['type'] ?? 'direct');
$content = sanitize($data['content'] ?? '');
if (empty($content)) jsonError('Message cannot be empty.');

if ($type === 'direct') {
    $receiverId = (int)($data['receiver_id'] ?? 0);
    if (!$receiverId) jsonError('Receiver ID required for direct messages.');
    if ($receiverId === $uid) jsonError('Cannot message yourself.');
    $recv = db()->prepare("SELECT id FROM users WHERE id=:id AND status!='banned'");
    $recv->execute([':id'=>$receiverId]);
    if (!$recv->fetch()) jsonError('Recipient not found.');

    db()->prepare("INSERT INTO messages (sender_id,receiver_id,type,content) VALUES (:s,:r,'direct',:c)")
        ->execute([':s'=>$uid,':r'=>$receiverId,':c'=>$content]);
    $msgId = db()->lastInsertId();

    // Notify recipient
    $sender = db()->prepare("SELECT username FROM users WHERE id=:id");
    $sender->execute([':id'=>$uid]); $sender = $sender->fetch();
    createNotification($receiverId,"New message from {$sender['username']}",$content,'new_message','chat.html');

    jsonSuccess(['message_id'=>$msgId],'Message sent.');

} elseif ($type === 'group') {
    $tid = (int)($data['tournament_id'] ?? 0);
    if (!$tid) jsonError('Tournament ID required for group messages.');
    $mem = db()->prepare("SELECT id FROM tournament_players WHERE tournament_id=:tid AND user_id=:uid");
    $mem->execute([':tid'=>$tid,':uid'=>$uid]);
    if (!$mem->fetch()) jsonError('You are not a member of this tournament.',403);

    db()->prepare("INSERT INTO messages (sender_id,tournament_id,type,content) VALUES (:s,:t,'group',:c)")
        ->execute([':s'=>$uid,':t'=>$tid,':c'=>$content]);
    jsonSuccess(['message_id'=>db()->lastInsertId()],'Message sent to group.');
} else {
    jsonError('Invalid message type.');
}
