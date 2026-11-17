
<?php
date_default_timezone_set('Asia/Manila');
$pageTitle = $pageTitle;
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Basic Metadata -->
    <meta name="title" content="<?= qflow_escape($pageTitle) ?>">
    <meta name="description" content="QueueFlow is the RMMC-MI Finance Queuing Management System. Manage finance transactions, monitor queues, and receive real-time queue updates.">
    <meta name="keywords" content="QueueFlow, RMMC-MI, Finance Queuing System, Queue Management, Cashier, Receivables">
    <meta name="author" content="RMMC-MI">
    <meta name="robots" content="index, follow">
    <meta name="language" content="English">
    <meta name="theme-color" content="#c8102e">

    <!-- Open Graph Metadata (Facebook, Messenger, etc.) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= qflow_escape($pageTitle) ?>">
    <meta property="og:description" content="RMMC-MI Finance Queuing Management System. Manage finance transactions and monitor queues through QueueFlow.">
    <meta property="og:image" content="assets/img/icon.png">
    <meta property="og:site_name" content="QueueFlow">
    <meta property="og:locale" content="en_PH">

    <!-- Twitter Metadata -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= qflow_escape($pageTitle) ?>">
    <meta name="twitter:description" content="RMMC-MI Finance Queuing Management System.">
    <meta name="twitter:image" content="assets/img/icon.png">

    <!-- Page Title -->
    <title><?= qflow_escape($pageTitle) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/icon.png">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <!-- Iconify -->
    <script src="https://code.iconify.design/iconify-icon/3.0.0/iconify-icon.min.js"></script>
</head>
<body>
<div class="app-shell">
