<?php
$title = 'Entries';
ob_start();
?>

<h1>All Entries</h1>

<?php if (empty($entries)): ?>
    <article>
        <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No entries found.</p>
    </article>
<?php else: ?>
    <?php foreach ($entries as $entry): ?>
        <article class="entry-card">
            <header style="display: flex; align-items: center; gap: 1rem;">
                <img src="<?= $entry['avatar_url'] ?>" 
                     alt="<?= htmlspecialchars($entry['user_name']) ?>" 
                     class="avatar" 
                     width="40" 
                     height="40">
                <div style="flex: 1;">
                    <div>
                        <strong style="color: var(--text-primary);"><?= htmlspecialchars($entry['user_name']) ?></strong>
                        <small style="color: var(--text-secondary); margin-left: 0.5rem;"><?= htmlspecialchars($entry['user_email']) ?></small>
                    </div>
                    <small style="color: var(--text-secondary);"><?= date('M j, Y g:i A', strtotime($entry['created_at'])) ?></small>
                </div>
            </header>
            <div style="margin: 1rem 0;">
                <p style="color: var(--text-primary);"><?= htmlspecialchars($entry['text']) ?></p>
            </div>
            <footer>
                <button class="danger" onclick="deleteEntry(<?= $entry['id'] ?>)">Delete</button>
            </footer>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<script>
async function deleteEntry(id) {
    if (!confirm('Are you sure you want to delete this entry?')) {
        return;
    }

    try {
        const response = await fetch(`/admin/entries/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('trail_jwt')
            }
        });

        if (response.ok) {
            location.reload();
        } else {
            alert('Failed to delete entry');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
