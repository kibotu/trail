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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --border: rgba(255, 255, 255, 0.1);
            --success: #10b981;
            --error: #ef4444;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* IBM Plex Sans for all headings and prominent text */
        h1, h2, h3, h4, h5, h6,
        .logo,
        .user-name,
        .user-name-link,
        .link-preview-title {
            font-family: 'IBM Plex Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            z-index: -1;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: rgba(59, 130, 246, 0.4);
            top: -100px;
            left: -100px;
            animation: float-1 25s infinite ease-in-out;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: rgba(236, 72, 153, 0.3);
            bottom: -100px;
            right: -100px;
            animation: float-2 30s infinite ease-in-out;
        }

        @keyframes float-1 {
            0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
            25% { transform: translate(120px, -80px) scale(1.15) rotate(5deg); }
            50% { transform: translate(200px, 50px) scale(1.25) rotate(-3deg); }
            75% { transform: translate(80px, -120px) scale(0.9) rotate(8deg); }
        }

        @keyframes float-2 {
            0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
            25% { transform: translate(-100px, 120px) scale(0.85) rotate(-6deg); }
            50% { transform: translate(-180px, -60px) scale(0.75) rotate(4deg); }
            75% { transform: translate(-60px, 140px) scale(1.1) rotate(-7deg); }
        }

        header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-link, .logout-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }

        .nav-link:hover, .logout-button:hover {
            background: var(--bg-primary);
            border-color: var(--accent);
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .profile-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--accent);
        }

        .profile-info h1 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .profile-info .email {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--bg-primary);
        }

        .form-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }

        .button-group {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--bg-primary);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .profile-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--accent);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .profile-link:hover {
            background: var(--bg-primary);
            border-color: var(--accent);
        }

        @media (max-width: 768px) {
            .container {
                padding: 2rem 1rem;
            }

            .profile-card {
                padding: 1.5rem;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <a href="/" class="logo">
                <i class="fa-solid fa-link"></i>
                <span>Trail</span>
            </a>
            <div class="header-actions">
                <a href="/api" class="nav-link">
                    <i class="fa-solid fa-book"></i>
                    <span>API</span>
                </a>
                <?php if (isset($isAdmin) && $isAdmin): ?>
                    <a href="/admin" class="nav-link">
                        <i class="fa-solid fa-gear"></i>
                        <span>Admin</span>
                    </a>
                <?php endif; ?>
                <a href="/admin/logout.php" class="logout-button">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
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
    
    <!-- Image Upload Script -->
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
    </script>
</body>
</html>
