<?php
$title = 'Dashboard';
ob_start();
?>

<h1>Dashboard</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= number_format($total_entries ?? 0) ?></div>
        <div class="stat-label">Total Entries</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($total_users ?? 0) ?></div>
        <div class="stat-label">Total Users</div>
    </div>
</div>

<article>
    <header><strong>Quick Actions</strong></header>
    <p>
        <a href="/admin/entries" role="button">View All Entries</a>
        <a href="/admin/users" role="button" class="secondary">Manage Users</a>
    </p>
</article>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
