<?php
$balance = $balance ?? 0;
?>
<section class="wallet-hero">
  <div>
    <div class="wh-balance"><?= number_format((float)$balance, 2) ?></div>
    <div class="wh-label">TZS Available Balance</div>
  </div>
</section>
