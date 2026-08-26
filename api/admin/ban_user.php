<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

requireAdmin(); requirePost();
$data   = getPostData();
requireFields($data,['user_id','action']);
$uid    = (int)$data['user_id'];
$action = sanitize($data['action']);

if (!in_array($action,['ban','unban','warn'])) jsonError('Invalid action.');

$user = db()->prepare("SELECT id,username,role FROM users WHERE id=:id");
$user->execute([':id'=>$uid]); $user = $user->fetch();
if (!$user) jsonError('User not found.',404);
if ($user['role'] === 'admin') jsonError('Cannot ban an admin account.');

$statusMap = ['ban'=>'banned','unban'=>'active','warn'=>'warned'];
$newStatus  = $statusMap[$action];

db()->prepare("UPDATE users SET status=:s WHERE id=:id")->execute([':s'=>$newStatus,':id'=>$uid]);

$messages = ['ban'=>'Your account has been banned.','warn'=>'You have received an official warning.','unban'=>'Your account has been restored.'];
createNotification($uid,'Account status update',$messages[$action],'account_warning','profile.html');

jsonSuccess(['new_status'=>$newStatus],"User {$action}ned successfully.");
