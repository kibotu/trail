<?php
$title = 'Users';
ob_start();
?>

<h1>All Users</h1>

<?php if (empty($users)): ?>
    <article>
        <p>No users found.</p>
    </article>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Avatar</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Admin</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <img src="<?= $user['avatar_url'] ?>" 
                                 alt="<?= htmlspecialchars($user['name']) ?>" 
                                 class="avatar" 
                                 width="50" 
                                 height="50">
                        </td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= $user['is_admin'] ? '✓' : '' ?></td>
                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <button class="secondary" onclick="deleteUser(<?= $user['id'] ?>)">Delete</button>
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
