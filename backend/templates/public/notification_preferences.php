<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Preferences - Trail</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/notifications.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
</head>
<body>
    <div class="preferences-page">
        <div class="preferences-header">
            <h1>Notification Preferences</h1>
            <a href="/notifications" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Notifications
            </a>
        </div>
        
        <form id="notification-preferences-form" onsubmit="savePreferences(event)">
            <div class="preference-section">
                <h2>Email Notifications</h2>
                <p class="section-description">Choose when you want to receive email notifications</p>
                
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="email_on_mention" 
                           <?= $data['preferences']['email_on_mention'] ? 'checked' : '' ?>>
                    <div class="checkbox-content">
                        <strong>Mentions</strong>
                        <p>When someone mentions you with @username</p>
                    </div>
                </label>
                
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="email_on_comment" 
                           <?= $data['preferences']['email_on_comment'] ? 'checked' : '' ?>>
                    <div class="checkbox-content">
                        <strong>Comments</strong>
                        <p>When someone comments on your post</p>
                    </div>
                </label>
                
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="email_on_clap" 
                           <?= $data['preferences']['email_on_clap'] ? 'checked' : '' ?>>
                    <div class="checkbox-content">
                        <strong>Claps</strong>
                        <p>When someone claps for your post (grouped per entry)</p>
                    </div>
                </label>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Save Preferences
            </button>
        </form>
        
        <div id="save-success" class="success-message hidden">
            <i class="fas fa-check-circle"></i> Preferences saved successfully
        </div>
        
        <div id="save-error" class="error-message hidden">
            <i class="fas fa-exclamation-circle"></i> Failed to save preferences
        </div>
    </div>
    
    <script src="/js/notifications.js"></script>
</body>
</html>
