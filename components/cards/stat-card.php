<?php
$label = $label ?? '';
$value = $value ?? '-';
$accent = $accent ?? 'var(--primary)';
?>
<div class="stat-card">
  <span class="stat-value" style="color:<?= htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?></span>
  <span class="stat-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
</div>
