<?php
$title = 'Entries';
ob_start();
?>

<h1>All Entries</h1>

<?php if (empty($entries)): ?>
    <article>
        <p>No entries found.</p>
    </article>
<?php else: ?>
    <?php foreach ($entries as $entry): ?>
        <article class="entry-card">
            <header>
                <img src="<?= htmlspecialchars($entry['gravatar_url']) ?>" 
                     alt="<?= htmlspecialchars($entry['user_name']) ?>" 
                     class="avatar" 
                     width="40" 
                     height="40">
                <strong><?= htmlspecialchars($entry['user_name']) ?></strong>
                <small><?= htmlspecialchars($entry['user_email']) ?></small>
                <br>
                <small><?= date('M j, Y g:i A', strtotime($entry['created_at'])) ?></small>
            </header>
            <p><strong>Text:</strong> <?= htmlspecialchars($entry['text']) ?></p>
            <footer>
                <button class="secondary" onclick="deleteEntry(<?= $entry['id'] ?>)">Delete</button>
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
