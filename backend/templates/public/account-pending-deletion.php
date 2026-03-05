<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Account Pending Deletion - Trail</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/solid.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/regular.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-account-pending-deletion">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="deletion-blocker">
        <div class="deletion-blocker-card">
            <div class="deletion-blocker-mascot">
                <img src="/assets/undo-delete-whale.png" alt="Changed your mind? Keep my account!" />
            </div>

            <div class="deletion-blocker-body">
                <p class="deletion-blocker-subtitle">
                    You requested account deletion on
                    <strong><?= htmlspecialchars($deletionRequestedDate) ?></strong>.
                    Your data will be permanently removed
                    <strong><?= $daysRemaining > 0 ? "in {$daysRemaining} day" . ($daysRemaining !== 1 ? 's' : '') : 'soon' ?></strong>.
                </p>

                <div class="deletion-blocker-info">
                    <div class="deletion-blocker-info-item">
                        <i class="fa-solid fa-eye-slash"></i>
                        <span>Your profile, entries, and comments are currently hidden from public view.</span>
                    </div>
                    <div class="deletion-blocker-info-item">
                        <i class="fa-solid fa-clock"></i>
                        <span>Permanent deletion occurs 14 days after your request.</span>
                    </div>
                </div>

                <div class="deletion-blocker-actions">
                    <button type="button" class="btn btn-revert" id="revertDeletionBtn">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>Keep my account</span>
                    </button>
                    <a href="/api/auth/logout" class="btn btn-ghost" id="logoutBtn" onclick="event.preventDefault(); fetch('/api/auth/logout', {method:'POST',credentials:'same-origin'}).then(()=>window.location.href='/');">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Log out</span>
                    </a>
                </div>

                <p class="deletion-blocker-contact">
                    You can also restore your account by emailing
                    <a href="mailto:contact@kibotu.net">contact@kibotu.net</a>.
                </p>
            </div>
        </div>
    </div>

    <div id="snackbar" class="snackbar"></div>

    <script src="/assets/dist/account-deletion.bundle.js" defer></script>
    <script>
        document.getElementById('revertDeletionBtn').addEventListener('click', async function() {
            const btn = this;
            const icon = btn.querySelector('i');
            const label = btn.querySelector('span');
            const originalIconClass = icon.className;

            btn.disabled = true;
            icon.className = 'fa-solid fa-spinner fa-spin';
            label.textContent = 'Restoring your account…';

            try {
                const response = await fetch('/api/profile/revert-deletion', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to restore account');
                }

                if (typeof showSnackbar === 'function') {
                    showSnackbar('Welcome back! Your account has been restored.', 'success', 3000);
                }

                setTimeout(() => { window.location.href = '/'; }, 1500);
            } catch (err) {
                console.error('Revert error:', err);
                if (typeof showSnackbar === 'function') {
                    showSnackbar(err.message || 'Something went wrong. Please try again.', 'error');
                }
                btn.disabled = false;
                icon.className = originalIconClass;
                label.textContent = 'Keep my account';
            }
        });
    </script>
</body>
</html>
