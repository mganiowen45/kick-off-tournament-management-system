<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin(); requireGet();
$db = db();

$totalUsers      = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='player'")->fetchColumn();
$activeTourn     = (int)$db->query("SELECT COUNT(*) FROM tournaments WHERE status IN ('open','active')")->fetchColumn();
$openDisputes    = (int)$db->query("SELECT COUNT(*) FROM disputes WHERE status IN ('open','under_review')")->fetchColumn();
$matchesToday    = (int)$db->query("SELECT COUNT(*) FROM matches WHERE DATE(played_at)=CURDATE()")->fetchColumn();
$newUsersToday   = (int)$db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$bannedUsers     = (int)$db->query("SELECT COUNT(*) FROM users WHERE status='banned'")->fetchColumn();
$matchesThisWeek = (int)$db->query("SELECT COUNT(*) FROM matches WHERE played_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
$autoConfirmed   = (int)$db->query("SELECT COUNT(*) FROM matches WHERE status='confirmed' AND id NOT IN (SELECT match_id FROM disputes)")->fetchColumn();

// Weekly match counts per day
$weekly = $db->query("
    SELECT DATE(played_at) AS day, COUNT(*) AS count
    FROM matches WHERE played_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND played_at IS NOT NULL
    GROUP BY DATE(played_at) ORDER BY day ASC
")->fetchAll();

// Recent activity
$activity = $db->query("
    (SELECT 'match_confirmed' AS type, CONCAT('Match confirmed: Match #',id) AS detail, confirmed_at AS time FROM matches WHERE status='confirmed' ORDER BY confirmed_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'dispute_raised' AS type, CONCAT('Dispute raised: DISP-',id) AS detail, created_at AS time FROM disputes ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'new_user' AS type, CONCAT('New user registered: ',username) AS detail, created_at AS time FROM users WHERE role='player' ORDER BY created_at DESC LIMIT 5)
    ORDER BY time DESC LIMIT 15
")->fetchAll();

jsonSuccess(compact('totalUsers','activeTourn','openDisputes','matchesToday','newUsersToday','bannedUsers','matchesThisWeek','autoConfirmed','weekly','activity'));
