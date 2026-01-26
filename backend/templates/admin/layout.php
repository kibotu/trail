<!DOCTYPE html>
<html lang="en" data-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Trail Admin') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        .avatar {
            border-radius: 50%;
            vertical-align: middle;
        }
        .entry-card {
            margin-bottom: 1rem;
        }
        .entry-url {
            word-break: break-all;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            background: var(--card-background-color);
            border-radius: var(--border-radius);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        .stat-label {
            color: var(--muted-color);
            text-transform: uppercase;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <nav class="container-fluid">
        <ul>
            <li><strong>Trail Admin</strong></li>
        </ul>
        <ul>
            <li><a href="/admin">Dashboard</a></li>
            <li><a href="/admin/entries">Entries</a></li>
            <li><a href="/admin/users">Users</a></li>
        </ul>
    </nav>

    <main class="container">
        <?= $content ?? '' ?>
    </main>

    <footer class="container-fluid">
        <small>Trail Admin &copy; <?= date('Y') ?></small>
    </footer>
</body>
</html>
