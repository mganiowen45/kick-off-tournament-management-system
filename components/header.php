<?php
$pageTitle = $pageTitle ?? 'KICKOFF';
$layout = $layout ?? 'app';
$page = $page ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="shared.css">
<link rel="stylesheet" href="assets/css/tournament-cards.css">
<script src="includes/nav.js" data-page="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" data-layout="<?= htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body>
<div id="page-main">
