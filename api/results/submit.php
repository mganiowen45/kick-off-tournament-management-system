<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/upload.php';

requireCompletedPlayerProfile();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed.');
}

// --- INPUTS ---
$match_id       = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
$my_score       = isset($_POST['my_score']) ? (int)$_POST['my_score'] : 0;
$opponent_score = isset($_POST['opponent_score']) ? (int)$_POST['opponent_score'] : 0;
$claimed_result = isset($_POST['claimed_result']) ? trim($_POST['claimed_result']) : '';
$notes          = isset($_POST['notes']) ? trim(htmlspecialchars($_POST['notes'], ENT_QUOTES, 'UTF-8')) : '';

$uid = currentUserId();

// --- VALIDATION ---
if (!$match_id) jsonError('Invalid match.');
if (!in_array($claimed_result, ['win','loss','draw'], true)) jsonError('Invalid result.');
if ($my_score < 0 || $opponent_score < 0 || $my_score > 99 || $opponent_score > 99) jsonError('Invalid scores.');
if (strlen($notes) > 1000) jsonError('Notes too long (max 1000 characters).');

// --- MATCH OWNERSHIP CHECK (do this BEFORE uploading the file) ---
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch();
if (!$match) jsonError('Match not found.');
if ($match['player1_id'] != $uid && $match['player2_id'] != $uid) jsonError('Not your match.');

// --- PREVENT DUPLICATE ---
$stmt = $pdo->prepare("SELECT id FROM match_results WHERE match_id=? AND submitted_by=?");
$stmt->execute([$match_id, $uid]);
if ($stmt->fetch()) jsonError('You have already submitted a result for this match.');

// --- SCREENSHOT UPLOAD (using shared handler with real MIME check) ---
$upload = handleScreenshotUpload('screenshot', 'results', 'match' . $match_id . '_user' . $uid);
if (!$upload['success']) {
    jsonError($upload['error'] ?? 'Screenshot upload failed.');
}
$screenshot_url = $upload['url'];

// --- INSERT RESULT ---
try {
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO match_results
        (match_id, submitted_by, claimed_result, my_score, opponent_score, screenshot_url, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $match_id, $uid, $claimed_result, $my_score, $opponent_score, $screenshot_url, $notes ?: null
    ]);

    $pdo->prepare("
        UPDATE matches SET status='pending_result', played_at=NOW()
        WHERE id=? AND status='scheduled'
    ")->execute([$match_id]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[KICKOFF results/submit] ' . $e->getMessage());
    jsonError('Could not save result. Please try again.');
}

// --- AUTO VERIFY ---
$verification = ['status'=>'pending','message'=>'Waiting for opponent'];

$stmt = $pdo->prepare("SELECT * FROM match_results WHERE match_id=?");
$stmt->execute([$match_id]);
$results = $stmt->fetchAll();

if (count($results) == 2) {
    $r1 = $results[0];
    $r2 = $results[1];

    $scoresMatch =
        ((int)$r1['my_score'] == (int)$r2['opponent_score']) &&
        ((int)$r1['opponent_score'] == (int)$r2['my_score']);

    $resultsMatch =
        ($r1['claimed_result']==='win'  && $r2['claimed_result']==='loss') ||
        ($r1['claimed_result']==='loss' && $r2['claimed_result']==='win')  ||
        ($r1['claimed_result']==='draw' && $r2['claimed_result']==='draw');

    if ($scoresMatch && $resultsMatch) {
        if ($r1['submitted_by'] == $match['player1_id']) {
            $p1_score = (int)$r1['my_score'];
            $p2_score = (int)$r1['opponent_score'];
        } else {
            $p1_score = (int)$r1['opponent_score'];
            $p2_score = (int)$r1['my_score'];
        }

        $winner_id = null;
        $is_draw   = 0;
        if ($p1_score > $p2_score)       $winner_id = $match['player1_id'];
        elseif ($p2_score > $p1_score)   $winner_id = $match['player2_id'];
        else                             $is_draw = 1;

        $pdo->prepare("
            UPDATE matches SET status='confirmed', winner_id=?, is_draw=?,
                player1_score=?, player2_score=?, confirmed_at=NOW()
            WHERE id=?
        ")->execute([$winner_id, $is_draw, $p1_score, $p2_score, $match_id]);

        $pdo->prepare("UPDATE match_results SET verification_status='confirmed' WHERE match_id=?")
            ->execute([$match_id]);

        if ($is_draw) {
            $pdo->prepare("UPDATE users SET draws=draws+1, total_matches=total_matches+1, points=points+1 WHERE id IN (?,?)")
                ->execute([$match['player1_id'], $match['player2_id']]);
        } else {
            $loser = ($winner_id == $match['player1_id']) ? $match['player2_id'] : $match['player1_id'];
            $pdo->prepare("UPDATE users SET wins=wins+1, total_matches=total_matches+1, points=points+3 WHERE id=?")
                ->execute([$winner_id]);
            $pdo->prepare("UPDATE users SET losses=losses+1, total_matches=total_matches+1 WHERE id=?")
                ->execute([$loser]);
        }

        checkTournamentComplete($pdo, $match['tournament_id']);
        $verification = ['status'=>'confirmed','message'=>'Result confirmed'];

    } else {
        $pdo->prepare("UPDATE matches SET status='disputed' WHERE id=?")->execute([$match_id]);
        $pdo->prepare("UPDATE match_results SET verification_status='conflicted' WHERE match_id=?")->execute([$match_id]);
        $pdo->prepare("INSERT IGNORE INTO disputes (match_id, raised_by, reason, status) VALUES (?, ?, 'Conflicting results', 'open')")
            ->execute([$match_id, $uid]);
        $verification = ['status'=>'conflicted','message'=>'Dispute created — an admin will review'];
    }
}

jsonSuccess(['verification' => $verification], 'Submitted successfully.');


function checkTournamentComplete(PDO $pdo, int $tournament_id): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE tournament_id=:tid AND status NOT IN ('confirmed','walkover','cancelled')");
    $stmt->execute([':tid' => $tournament_id]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->prepare("UPDATE tournaments SET status='completed', completed_at=NOW() WHERE id=:id AND status!='completed'")
            ->execute([':id' => $tournament_id]);
    }
}
