<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Profile - Trail</title>
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-profile">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <a href="/" class="logo">
                <i class="fa-solid fa-link"></i>
                <span>Trail</span>
            </a>
            <div class="header-actions">
                <a href="/api" class="nav-link" aria-label="API Documentation">
                    <i class="fa-solid fa-book"></i>
                </a>
                <?php if (isset($isAdmin) && $isAdmin): ?>
                    <a href="/admin" class="nav-link" aria-label="Admin Dashboard">
                        <i class="fa-solid fa-gear"></i>
                    </a>
                <?php endif; ?>
                <a href="/admin/logout.php" class="logout-button" aria-label="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="profile-card">
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>Loading profile...</p>
            </div>

            <div id="profile-content" style="display: none;">
                <div class="profile-header">
                    <img id="profile-avatar" class="profile-avatar" src="" alt="Profile">
                    <div class="profile-info">
                        <h1 id="profile-name"></h1>
                        <p class="email" id="profile-email"></p>
                    </div>
                </div>

                <div id="alert-container"></div>

                <form id="profile-form">
                    <div class="form-group">
                        <label for="nickname">Nickname</label>
                        <input 
                            type="text" 
                            id="nickname" 
                            name="nickname" 
                            placeholder="Enter your nickname"
                            pattern="[a-zA-Z0-9_-]{3,50}"
                            minlength="3"
                            maxlength="50"
                            required
                        >
                        <p class="form-hint">3-50 characters. Letters, numbers, underscore, and hyphen only.</p>
                    </div>

                    <div class="form-group">
                        <label>Profile Image</label>
                        <div id="profile-image-upload"></div>
                        <p class="form-hint">Max 20MB. Formats: JPEG, PNG, GIF, WebP, SVG, AVIF</p>
                    </div>

                    <div class="form-group">
                        <label>Header Image</label>
                        <div id="header-image-upload"></div>
                        <p class="form-hint">Max 20MB. Recommended: 1920x400px</p>
                    </div>

                    <div class="form-group" id="profile-link-group" style="display: none;">
                        <label>Your Profile URL</label>
                        <a id="profile-url" class="profile-link" href="#" target="_blank">
                            <i class="fa-solid fa-link"></i>
                            <span id="profile-url-text"></span>
                        </a>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary" id="save-btn">
                            Save Changes
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='/'">
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Muted Users Section -->
                <div class="muted-users-section">
                    <div class="muted-users-header">
                        <h3>Muted Users</h3>
                        <span class="muted-count" id="muted-count">0</span>
                    </div>
                    <div id="muted-users-list" class="muted-users-list">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Loading muted users...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api';
        // JWT token is stored in httpOnly cookie - not accessible to JavaScript for security
        let currentProfile = null;

        // Load profile data
        async function loadProfile() {
            try {
                const response = await fetch(`${API_BASE}/profile`, {
                    credentials: 'same-origin' // Include httpOnly cookie with JWT
                });

                if (!response.ok) {
                    throw new Error('Failed to load profile');
                }

                currentProfile = await response.json();
                displayProfile(currentProfile);
            } catch (error) {
                console.error('Error loading profile:', error);
                showAlert('Failed to load profile. Please try again.', 'error');
            } finally {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('profile-content').style.display = 'block';
            }
        }

        // Display profile data
        function displayProfile(profile) {
            document.getElementById('profile-name').textContent = profile.name || 'User';
            document.getElementById('profile-email').textContent = profile.email;
            document.getElementById('nickname').value = profile.nickname || '';
            
            // Set avatar
            const avatarUrl = profile.photo_url || 
                `https://www.gravatar.com/avatar/${profile.gravatar_hash}?s=160&d=mp`;
            document.getElementById('profile-avatar').src = avatarUrl;

            // Show profile link if nickname exists
            if (profile.nickname) {
                const profileUrl = `${window.location.origin}/@${profile.nickname}`;
                document.getElementById('profile-url').href = profileUrl;
                document.getElementById('profile-url-text').textContent = `@${profile.nickname}`;
                document.getElementById('profile-link-group').style.display = 'block';
            }
        }

        // Save profile changes
        document.getElementById('profile-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const nickname = document.getElementById('nickname').value.trim();
            const saveBtn = document.getElementById('save-btn');

            // Validate nickname format
            if (!/^[a-zA-Z0-9_-]{3,50}$/.test(nickname)) {
                showAlert('Invalid nickname format. Use 3-50 characters (letters, numbers, underscore, hyphen only)', 'error');
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const response = await fetch(`${API_BASE}/profile`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin', // Include httpOnly cookie with JWT
                    body: JSON.stringify({ nickname })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to update profile');
                }

                currentProfile = data;
                displayProfile(data);
                showAlert('Profile updated successfully!', 'success');
            } catch (error) {
                console.error('Error updating profile:', error);
                showAlert(error.message || 'Failed to update profile. Please try again.', 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        });

        // Show alert message
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `
                <div class="alert alert-${type}">
                    ${message}
                </div>
            `;

            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 5000);
            }
        }

        // Load profile on page load
        loadProfile();
    </script>
    
    <!-- Snackbar and Image Upload Scripts -->
    <script src="/js/snackbar.js"></script>
    <script src="/js/image-upload.js"></script>
    <script>
        // Initialize image uploaders after page load
        let profileImageUploader, headerImageUploader;
        let profileImageId = null;
        let headerImageId = null;
        
        window.addEventListener('DOMContentLoaded', () => {
            // Profile image uploader
            profileImageUploader = createImageUploadUI(
                'profile',
                'profile-image-upload',
                (result) => {
                    console.log('Profile image uploaded:', result);
                    profileImageId = result.image_id;
                    // Auto-save profile with new image
                    updateProfileWithImage('profile', result.image_id);
                }
            );
            
            // Header image uploader
            headerImageUploader = createImageUploadUI(
                'header',
                'header-image-upload',
                (result) => {
                    console.log('Header image uploaded:', result);
                    headerImageId = result.image_id;
                    // Auto-save profile with new image
                    updateProfileWithImage('header', result.image_id);
                }
            );
        });
        
        async function updateProfileWithImage(imageType, imageId) {
            try {
                const payload = {
                    nickname: document.getElementById('nickname').value
                };
                
                if (imageType === 'profile') {
                    payload.profile_image_id = imageId;
                } else if (imageType === 'header') {
                    payload.header_image_id = imageId;
                }
                
                const response = await fetch(`${API_BASE}/profile`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin', // Include httpOnly cookie with JWT
                    body: JSON.stringify(payload)
                });
                
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || 'Failed to update profile');
                }
                
                showAlert(`${imageType === 'profile' ? 'Profile' : 'Header'} image updated successfully!`, 'success');
            } catch (error) {
                console.error('Error updating profile image:', error);
                showAlert(error.message || 'Failed to update image. Please try again.', 'error');
            }
        }

        // Muted Users Management
        let mutedUsersData = [];

        async function loadMutedUsers() {
            try {
                const response = await fetch(`${API_BASE}/filters`, {
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Failed to load muted users');
                }

                const data = await response.json();
                const mutedUserIds = data.muted_users || [];

                // Update count
                document.getElementById('muted-count').textContent = mutedUserIds.length;

                if (mutedUserIds.length === 0) {
                    displayEmptyMutedState();
                    return;
                }

                // Fetch user details for each muted user
                const userPromises = mutedUserIds.map(userId => 
                    fetch(`${API_BASE}/users/${userId}/info`, { credentials: 'same-origin' })
                        .then(r => r.ok ? r.json() : null)
                        .catch(() => null)
                );

                const users = await Promise.all(userPromises);
                mutedUsersData = users.filter(u => u !== null).map((user, index) => ({
                    ...user,
                    id: mutedUserIds[index]
                }));

                displayMutedUsers(mutedUsersData);
            } catch (error) {
                console.error('Error loading muted users:', error);
                document.getElementById('muted-users-list').innerHTML = `
                    <div class="empty-muted">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <p>Failed to load muted users</p>
                    </div>
                `;
            }
        }

        function displayEmptyMutedState() {
            document.getElementById('muted-users-list').innerHTML = `
                <div class="empty-muted">
                    <i class="fa-solid fa-volume-xmark"></i>
                    <p>No muted users</p>
                    <p style="font-size: 0.875rem; margin-top: 0.5rem;">You haven't muted anyone yet.</p>
                </div>
            `;
        }

        function displayMutedUsers(users) {
            const container = document.getElementById('muted-users-list');
            
            if (users.length === 0) {
                displayEmptyMutedState();
                return;
            }

            container.innerHTML = users.map(user => {
                const avatarUrl = user.photo_url || user.avatar_url || 
                    `https://www.gravatar.com/avatar/${user.gravatar_hash || '00000000000000000000000000000000'}?s=96&d=mp`;
                const displayName = user.nickname || user.name || 'Unknown User';
                
                return `
                    <div class="muted-user-item" data-user-id="${user.id}">
                        <div class="muted-user-info">
                            <img src="${avatarUrl}" alt="${displayName}" class="muted-user-avatar" loading="lazy">
                            <div class="muted-user-details">
                                <span class="muted-user-name">${escapeHtml(displayName)}</span>
                                ${user.nickname ? `<span class="muted-user-date">@${escapeHtml(user.nickname)}</span>` : ''}
                            </div>
                        </div>
                        <button class="btn-unmute" onclick="unmuteUser(${user.id})" data-user-id="${user.id}">
                            <i class="fa-solid fa-volume-high"></i>
                            <span>Unmute</span>
                        </button>
                    </div>
                `;
            }).join('');
        }

        async function unmuteUser(userId) {
            try {
                const response = await fetch(`${API_BASE}/users/${userId}/mute`, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Failed to unmute user');
                }

                // Show success message
                if (typeof showSnackbar === 'function') {
                    showSnackbar('User unmuted successfully', 'success');
                }

                // Remove from list with animation
                const item = document.querySelector(`.muted-user-item[data-user-id="${userId}"]`);
                if (item) {
                    item.style.transition = 'opacity 0.3s, transform 0.3s';
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.95)';
                    
                    setTimeout(() => {
                        item.remove();
                        
                        // Update data and count
                        mutedUsersData = mutedUsersData.filter(u => u.id !== userId);
                        document.getElementById('muted-count').textContent = mutedUsersData.length;
                        
                        // Show empty state if no more muted users
                        if (mutedUsersData.length === 0) {
                            displayEmptyMutedState();
                        }
                    }, 300);
                }
            } catch (error) {
                console.error('Error unmuting user:', error);
                if (typeof showSnackbar === 'function') {
                    showSnackbar('Failed to unmute user. Please try again.', 'error');
                }
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Load muted users when profile loads
        window.addEventListener('DOMContentLoaded', () => {
            loadMutedUsers();
        });
    </script>
</body>
</html>
