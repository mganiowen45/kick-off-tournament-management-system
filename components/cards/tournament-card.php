<?php
$tournament = $tournament ?? [];
$variant = $variant ?? 'full';
$esc = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$num = static fn(mixed $value): float => is_numeric($value) ? (float) $value : 0.0;
$formatLabels = [
    '1v1' => '1V1 Tournament',
    'full_knockout' => 'Full Knockout',
    'group_knockout' => 'Group Stage + Knockout',
];
$formatClasses = [
    '1v1' => 'tc-format-1v1',
    'full_knockout' => 'tc-format-ko',
    'group_knockout' => 'tc-format-group',
];
$statusClasses = [
    'active' => 'tc-status-live',
    'open' => 'tc-status-open',
    'draft' => 'tc-status-upcoming',
    'completed' => 'tc-status-completed',
    'cancelled' => 'tc-status-cancelled',
];
$statusLabels = [
    'active' => 'Live',
    'open' => 'Registration Open',
    'draft' => 'Upcoming',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
$cover = \App\Support\CoverImage::resolve(
    (string) ($tournament['cover_image_url'] ?? $tournament['cover_image_path'] ?? ''),
    (string) ($tournament['name'] ?? '')
);
$id = (int) ($tournament['id'] ?? 0);
$max = max(0, (int) $num($tournament['max_players'] ?? 0));
$current = max(0, (int) $num($tournament['current_players'] ?? 0));
$pct = $max > 0 ? max(0, min(100, (int) round($current / $max * 100))) : 0;
$format = (string) ($tournament['format'] ?? '1v1');
$status = (string) ($tournament['status'] ?? 'open');
$isCompact = in_array($variant, ['compact', 'dashboard'], true);
$href = 'tournament_detail.html?id=' . urlencode((string) $id);
$money = static function (mixed $amount, string $currency = 'TZS') use ($num): string {
    $value = $num($amount);
    return $value > 0 ? $currency . ' ' . number_format($value, 0) : '';
};
$funding = (string) ($tournament['funding_model'] ?? 'free_casual');
if ($funding === 'participant_funded') {
    $prizeLabel = 'EST. PRIZE POOL';
    $prizeValue = $money($tournament['prize_pool_amount'] ?? 0, (string) ($tournament['currency'] ?? 'TZS')) ?: (string) ($tournament['prize_pool'] ?? 'TBA');
    $entryValue = $money($tournament['entry_fee_amount'] ?? 0, (string) ($tournament['currency'] ?? 'TZS')) ?: 'TZS 0';
    $fundingNote = 'Participant-Funded Tournament';
} elseif ($funding === 'kickoff_sponsored') {
    $prizeLabel = 'PRIZE POOL';
    $prizeValue = $money($tournament['prize_pool_amount'] ?? $tournament['kickoff_contribution_amount'] ?? 0, (string) ($tournament['currency'] ?? 'TZS')) ?: (string) ($tournament['prize_pool'] ?? 'TBA');
    $entryValue = 'FREE';
    $fundingNote = 'KICKOFF-Sponsored Tournament';
} else {
    $prizeLabel = 'PRIZE';
    $prizeValue = 'NO CASH PRIZE';
    $entryValue = 'FREE';
    $fundingNote = 'Free Casual Tournament';
}
?>
<article class="tc-card <?= $isCompact ? 'tc-card-compact' : '' ?>">
  <a class="tc-hit" href="<?= $esc($href) ?>" aria-label="View <?= $esc($tournament['name'] ?? 'Tournament') ?>"></a>
  <div class="tc-hero">
    <img class="tc-cover-img" src="<?= $esc($cover) ?>" alt="" loading="lazy"
      onerror="this.onerror=null;this.classList.add('tc-cover-fallback');">
    <div class="tc-hero-scrim" aria-hidden="true"></div>
    <div class="tc-rails" aria-hidden="true"></div>
    <div class="tc-badge-row">
      <span class="tc-pill <?= $esc($formatClasses[$format] ?? 'tc-format-1v1') ?>"><?= $esc($tournament['format_label'] ?? $formatLabels[$format] ?? $format) ?></span>
      <span class="tc-pill <?= $esc($statusClasses[$status] ?? 'tc-status-upcoming') ?>"><?= $esc($statusLabels[$status] ?? $status) ?></span>
    </div>
    <div class="tc-mark" aria-hidden="true"><i class="fa-solid fa-trophy"></i></div>
    <div class="tc-title-wrap">
      <h3 class="tc-title"><?= $esc($tournament['name'] ?? 'Tournament') ?></h3>
      <p class="tc-host">Hosted by <strong><?= $esc($tournament['creator_username'] ?? 'KICKOFF') ?></strong></p>
    </div>
  </div>
  <div class="tc-content">
    <div class="tc-meta-grid">
      <div class="tc-meta-item"><i class="fa-solid fa-user-group" aria-hidden="true"></i><div><strong><?= $max ?: 'TBA' ?></strong><span>Players</span></div></div>
      <div class="tc-meta-item"><i class="fa-solid fa-gamepad" aria-hidden="true"></i><div><strong><?= $esc($tournament['platform_name'] ?? 'Platform') ?></strong><span>Platform</span></div></div>
      <div class="tc-meta-item"><i class="fa-solid fa-gamepad" aria-hidden="true"></i><div><strong><?= $esc($tournament['game_name'] ?? $tournament['game'] ?? 'Game') ?></strong><span>Game</span></div></div>
      <div class="tc-meta-item"><i class="fa-solid fa-calendar" aria-hidden="true"></i><div><strong><?= $esc(!empty($tournament['start_date']) ? date('M j', strtotime((string) $tournament['start_date'])) : 'TBA') ?></strong><span>Starts</span></div></div>
    </div>
    <?php if (!$isCompact): ?>
      <div class="tc-finance">
        <div><span><?= $esc($prizeLabel) ?></span><strong><?= $esc($prizeValue) ?></strong></div>
        <div><span>ENTRY</span><strong><?= $esc($entryValue) ?></strong></div>
      </div>
      <div class="tc-funding"><?= $esc($fundingNote) ?></div>
    <?php endif; ?>
    <div class="tc-capacity">
      <div class="tc-capacity-top"><span><?= $current ?> / <?= $max ?> SPOTS FILLED</span><strong><?= $pct ?>%</strong></div>
      <div class="tc-progress" aria-hidden="true"><div style="width:<?= $pct ?>%;"></div></div>
    </div>
    <a class="tc-action" href="<?= $esc($href) ?>">View Tournament</a>
  </div>
</article>
