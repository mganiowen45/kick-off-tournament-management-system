<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin(); requireGet();
$uid = currentUserId();

// Direct message threads (latest per conversation partner)
$direct = db()->prepare("
    SELECT m.*, u.username AS other_username, u.id AS other_user_id,
           SUM(CASE WHEN m.receiver_id=:uid AND m.is_read=0 THEN 1 ELSE 0 END) OVER (PARTITION BY LEAST(m.sender_id,m.receiver_id),GREATEST(m.sender_id,m.receiver_id)) AS unread_count
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id=:uid2 THEN m.receiver_id ELSE m.sender_id END
    WHERE m.type='direct' AND (m.sender_id=:uid3 OR m.receiver_id=:uid4)
    AND m.id IN (
        SELECT MAX(id) FROM messages
        WHERE type='direct' AND (sender_id=:uid5 OR receiver_id=:uid6)
        GROUP BY LEAST(sender_id,receiver_id), GREATEST(sender_id,receiver_id)
    )
    ORDER BY m.sent_at DESC
");
$direct->execute([':uid'=>$uid,':uid2'=>$uid,':uid3'=>$uid,':uid4'=>$uid,':uid5'=>$uid,':uid6'=>$uid]);

// Group chats for player's tournaments
$groups = db()->prepare("
    SELECT m.*, t.name AS tournament_name,
           u.username AS last_sender_username,
           (SELECT COUNT(*) FROM messages WHERE tournament_id=m.tournament_id AND sender_id!=:uid AND is_read=0) AS unread_count
    FROM messages m
    JOIN tournaments t ON t.id=m.tournament_id
    JOIN users u ON u.id=m.sender_id
    WHERE m.type='group'
      AND m.tournament_id IN (SELECT tournament_id FROM tournament_players WHERE user_id=:uid2)
      AND m.id IN (SELECT MAX(id) FROM messages WHERE type='group' GROUP BY tournament_id)
    ORDER BY m.sent_at DESC
");
$groups->execute([':uid'=>$uid,':uid2'=>$uid]);

jsonSuccess(['direct'=>$direct->fetchAll(),'groups'=>$groups->fetchAll()]);
