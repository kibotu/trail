<?php
$title = 'Users';
ob_start();
?>

<h1>All Users</h1>

<?php if (empty($users)): ?>
    <article>
        <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No users found.</p>
    </article>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th style="padding: 1rem; text-align: left; color: var(--text-secondary); font-weight: 600;">Avatar</th>
                    <th style="padding: 1rem; text-align: left; color: var(--text-secondary); font-weight: 600;">Name</th>
                    <th style="padding: 1rem; text-align: left; color: var(--text-secondary); font-weight: 600;">Email</th>
                    <th style="padding: 1rem; text-align: left; color: var(--text-secondary); font-weight: 600;">Admin</th>
                    <th style="padding: 1rem; text-align: left; color: var(--text-secondary); font-weight: 600;">Joined</th>
                    <th style="padding: 1rem; text-align: left; color: var(--text-secondary); font-weight: 600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem;">
                            <img src="<?= $user['avatar_url'] ?>" 
                                 alt="<?= htmlspecialchars($user['name']) ?>" 
                                 class="avatar" 
                                 width="40" 
                                 height="40">
                        </td>
                        <td style="padding: 1rem; color: var(--text-primary);"><?= htmlspecialchars($user['name']) ?></td>
                        <td style="padding: 1rem; color: var(--text-secondary);"><?= htmlspecialchars($user['email']) ?></td>
                        <td style="padding: 1rem;"><?= $user['is_admin'] ? '<span style="color: var(--accent);">✓ Admin</span>' : '' ?></td>
                        <td style="padding: 1rem; color: var(--text-secondary);"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                        <td style="padding: 1rem;">
                            <button class="danger" onclick="deleteUser(<?= $user['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user? This will also delete all their entries.')) {
        return;
    }

    try {
        const response = await fetch(`/admin/users/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('trail_jwt')
            }
        });

        if (response.ok) {
            location.reload();
        } else {
            alert('Failed to delete user');
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
