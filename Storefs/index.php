<?php
/**
 * Final Space Store — index.php v9.1
 * - Unified with assets/style.css v9.0 and app.js v9.0
 * - No inline CSS (to avoid layout collisions)
 * - Wallet bar + centered info + My Purchases modal support
 * - License protection intact
 */
require_once __DIR__ . '/inc/license_core.php';
$CFG = require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Final Space Store</title>

<meta name="merchant_address" content="<?= htmlspecialchars($CFG['merchant_address'] ?? $CFG['wallet'] ?? '') ?>">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">

<script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.umd.min.js"></script>
<script src="/assets/app.js" defer></script>
</head>

<body>

<header class="fs-header">
  <div class="fs-header-wrap">
    <!-- Left side -->
    <div class="fs-brand">Final Space Store</div>

    <!-- Center wallet info -->
    <div class="fs-center">
      <span class="fs-tag">Wallet:</span>
      <span id="fs-header-addr" class="fs-addr">Not Connected</span>
      <span class="fs-tag">|</span>
      <span id="fs-header-bal" class="fs-bal">0.000000 BNB</span>
    </div>

    <!-- Right buttons -->
    <div class="fs-right">
      <button id="fs-btn-owned" class="fs-btn fs-btn-ghost">My Purchases</button>
      <button id="fs-btn-disconnect" class="fs-btn fs-btn-ghost" style="display:none">Disconnect</button>
      <button id="fs-btn-connect" class="fs-btn fs-btn-blue">Connect Wallet</button>
    </div>
  </div>
</header>

<main class="container">
  <div id="store" class="grid">
    <!-- Products loaded dynamically -->
  </div>
</main>

<!-- License and toast containers will auto-create by JS -->
</body>
</html>
