<?php

declare(strict_types=1);

namespace Trail\Services;

class EmailService
{
    private string $adminEmail;
    private string $baseUrl;

    public function __construct(string $adminEmail, string $baseUrl)
    {
        $this->adminEmail = $adminEmail;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Send report notification email to admin
     */
    public function sendReportNotification(array $entry, int $reportCount): bool
    {
        $subject = "Content Report Alert - Entry #{$entry['id']} ({$reportCount} reports)";
        
        $entryUrl = $this->baseUrl . '/status/' . ($entry['hash_id'] ?? $entry['id']);
        $reporterName = htmlspecialchars($entry['user_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $reporterEmail = htmlspecialchars($entry['user_email'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $entryText = htmlspecialchars($entry['text'] ?? '', ENT_QUOTES, 'UTF-8');
        $createdAt = htmlspecialchars($entry['created_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        
        $htmlBody = $this->buildReportEmailHtml(
            $entry['id'],
            $reportCount,
            $entryUrl,
            $reporterName,
            $reporterEmail,
            $entryText,
            $createdAt
        );

        $textBody = $this->buildReportEmailText(
            $entry['id'],
            $reportCount,
            $entryUrl,
            $reporterName,
            $reporterEmail,
            $entryText,
            $createdAt
        );

        return $this->sendEmail($this->adminEmail, $subject, $htmlBody, $textBody);
    }

    /**
     * Build HTML email body
     */
    private function buildReportEmailHtml(
        int $entryId,
        int $reportCount,
        string $entryUrl,
        string $reporterName,
        string $reporterEmail,
        string $entryText,
        string $createdAt
    ): string {
        $plural = $reportCount === 1 ? 'user has' : 'users have';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Report Alert</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .alert-badge {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
            margin-top: 8px;
        }
        .info-section {
            background-color: #f9fafb;
            border-left: 4px solid #3b82f6;
            padding: 16px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .info-value {
            color: #111827;
            font-size: 14px;
            margin-bottom: 12px;
        }
        .entry-content {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .warning-text {
            color: #dc2626;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Content Report Alert</h1>
            <div class="alert-badge">
                <strong>{$reportCount}</strong> {$plural} reported this content
            </div>
        </div>
        
        <p>A post on Trail has been flagged by multiple users and requires your attention.</p>
        
        <div class="info-section">
            <div class="info-label">Entry ID</div>
            <div class="info-value">#{$entryId}</div>
            
            <div class="info-label">Author</div>
            <div class="info-value">{$reporterName} ({$reporterEmail})</div>
            
            <div class="info-label">Posted</div>
            <div class="info-value">{$createdAt}</div>
            
            <div class="info-label">Total Reports</div>
            <div class="info-value"><span class="warning-text">{$reportCount} unique users</span></div>
        </div>
        
        <div class="info-label">Content</div>
        <div class="entry-content">{$entryText}</div>
        
        <a href="{$entryUrl}" class="action-button">
            View Entry →
        </a>
        
        <div class="footer">
            <p>This is an automated notification from Trail.<br>
            You're receiving this because content has been flagged by users.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build plain text email body (fallback)
     */
    private function buildReportEmailText(
        int $entryId,
        int $reportCount,
        string $entryUrl,
        string $reporterName,
        string $reporterEmail,
        string $entryText,
        string $createdAt
    ): string {
        $plural = $reportCount === 1 ? 'user has' : 'users have';
        
        return <<<TEXT
CONTENT REPORT ALERT
====================

{$reportCount} {$plural} reported this content on Trail.

Entry Details:
--------------
Entry ID: #{$entryId}
Author: {$reporterName} ({$reporterEmail})
Posted: {$createdAt}
Total Reports: {$reportCount} unique users

Content:
--------
{$entryText}

View Entry:
{$entryUrl}

---
This is an automated notification from Trail.
You're receiving this because content has been flagged by users.
TEXT;
    }

    /**
     * Send mention notification email
     */
    public function sendMentionNotification(array $recipient, array $actor, array $entry, ?string $hashId = null): bool
    {
        $actorNickname = htmlspecialchars($actor['nickname'] ?? $actor['name'], ENT_QUOTES, 'UTF-8');
        $actorName = htmlspecialchars($actor['name'], ENT_QUOTES, 'UTF-8');
        $recipientName = htmlspecialchars($recipient['name'], ENT_QUOTES, 'UTF-8');
        $entryText = htmlspecialchars(substr($entry['text'], 0, 200), ENT_QUOTES, 'UTF-8');
        
        // Use hash ID for permalink if available, otherwise fall back to numeric ID
        $entryIdentifier = $hashId ?? $entry['hash_id'] ?? $entry['id'];
        $entryUrl = $this->baseUrl . '/status/' . $entryIdentifier;
        $preferencesUrl = $this->baseUrl . '/notifications/preferences';
        
        $subject = "@{$actorNickname} mentioned you on Trail";
        
        $htmlBody = $this->buildMentionEmailHtml($recipientName, $actorName, $actorNickname, $entryText, $entryUrl, $preferencesUrl);
        $textBody = $this->buildMentionEmailText($recipientName, $actorName, $actorNickname, $entryText, $entryUrl, $preferencesUrl);
        
        return $this->sendEmail($recipient['email'], $subject, $htmlBody, $textBody);
    }

    /**
     * Send comment notification email
     */
    public function sendCommentNotification(array $recipient, array $actor, array $entry, array $comment, ?string $hashId = null): bool
    {
        $actorNickname = htmlspecialchars($actor['nickname'] ?? $actor['name'], ENT_QUOTES, 'UTF-8');
        $actorName = htmlspecialchars($actor['name'], ENT_QUOTES, 'UTF-8');
        $recipientName = htmlspecialchars($recipient['name'], ENT_QUOTES, 'UTF-8');
        $entryText = htmlspecialchars(substr($entry['text'], 0, 200), ENT_QUOTES, 'UTF-8');
        $commentText = htmlspecialchars(substr($comment['text'], 0, 200), ENT_QUOTES, 'UTF-8');
        
        // Use hash ID for permalink if available, otherwise fall back to numeric ID
        $entryIdentifier = $hashId ?? $entry['hash_id'] ?? $entry['id'];
        $entryUrl = $this->baseUrl . '/status/' . $entryIdentifier;
        $preferencesUrl = $this->baseUrl . '/notifications/preferences';
        
        $subject = "{$actorNickname} commented on your post";
        
        $htmlBody = $this->buildCommentEmailHtml($recipientName, $actorName, $actorNickname, $entryText, $commentText, $entryUrl, $preferencesUrl);
        $textBody = $this->buildCommentEmailText($recipientName, $actorName, $actorNickname, $entryText, $commentText, $entryUrl, $preferencesUrl);
        
        return $this->sendEmail($recipient['email'], $subject, $htmlBody, $textBody);
    }

    /**
     * Build mention email HTML
     */
    private function buildMentionEmailHtml(string $recipientName, string $actorName, string $actorNickname, string $entryText, string $entryUrl, string $preferencesUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mention Notification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👋 You were mentioned!</h1>
        </div>
        
        <p>Hi {$recipientName},</p>
        <p><strong>{$actorName}</strong> (@{$actorNickname}) mentioned you in a post:</p>
        
        <div class="content-box">
            "{$entryText}"
        </div>
        
        <a href="{$entryUrl}" class="action-button">View Post →</a>
        
        <div class="footer">
            <p>You're receiving this because you have email notifications enabled for mentions.<br>
            <a href="{$preferencesUrl}">Change your preferences</a></p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build mention email text
     */
    private function buildMentionEmailText(string $recipientName, string $actorName, string $actorNickname, string $entryText, string $entryUrl, string $preferencesUrl): string
    {
        return <<<TEXT
Hi {$recipientName},

{$actorName} (@{$actorNickname}) mentioned you in a post:

"{$entryText}"

View post: {$entryUrl}

---
You're receiving this because you have email notifications enabled for mentions.
Change your preferences: {$preferencesUrl}
TEXT;
    }

    /**
     * Build comment email HTML
     */
    private function buildCommentEmailHtml(string $recipientName, string $actorName, string $actorNickname, string $entryText, string $commentText, string $entryUrl, string $preferencesUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comment Notification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 16px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        .label {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 New Comment</h1>
        </div>
        
        <p>Hi {$recipientName},</p>
        <p><strong>{$actorName}</strong> (@{$actorNickname}) commented on your post:</p>
        
        <div class="label">Your Post:</div>
        <div class="content-box">
            "{$entryText}"
        </div>
        
        <div class="label">Their Comment:</div>
        <div class="content-box">
            "{$commentText}"
        </div>
        
        <a href="{$entryUrl}" class="action-button">View Conversation →</a>
        
        <div class="footer">
            <p>You're receiving this because you have email notifications enabled for comments.<br>
            <a href="{$preferencesUrl}">Change your preferences</a></p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build comment email text
     */
    private function buildCommentEmailText(string $recipientName, string $actorName, string $actorNickname, string $entryText, string $commentText, string $entryUrl, string $preferencesUrl): string
    {
        return <<<TEXT
Hi {$recipientName},

{$actorName} (@{$actorNickname}) commented on your post:

Your post: "{$entryText}"
Their comment: "{$commentText}"

View conversation: {$entryUrl}

---
You're receiving this because you have email notifications enabled for comments.
Change your preferences: {$preferencesUrl}
TEXT;
    }

    /**
     * Notify admin that a user has requested account deletion
     */
    public function sendDeletionRequestNotification(array $user): bool
    {
        $userName = htmlspecialchars($user['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $userEmail = htmlspecialchars($user['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $userId = (int) ($user['id'] ?? 0);
        $adminUrl = $this->baseUrl . '/admin/users.php';
        $requestedAt = date('M j, Y \a\t g:i A');

        $subject = "Account Deletion Request - {$userName}";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deletion Request</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .container { background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; margin: -30px -30px 30px -30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .info-section { background-color: #f9fafb; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0; border-radius: 4px; }
        .info-label { font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .info-value { color: #111827; font-size: 14px; margin-bottom: 12px; }
        .action-button { display: inline-block; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; text-align: center; }
        .notice { background-color: #fef3c7; border: 1px solid #f59e0b; padding: 12px 16px; border-radius: 6px; font-size: 14px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Account Deletion Request</h1>
        </div>
        <p>A user has requested to delete their Trail account.</p>
        <div class="info-section">
            <div class="info-label">User</div>
            <div class="info-value">{$userName} ({$userEmail})</div>
            <div class="info-label">User ID</div>
            <div class="info-value">#{$userId}</div>
            <div class="info-label">Requested At</div>
            <div class="info-value">{$requestedAt}</div>
        </div>
        <div class="notice">
            The user's content has been hidden from public view. Their account and data will be permanently deleted in <strong>14 days</strong> unless reverted from the admin panel.
        </div>
        <a href="{$adminUrl}" class="action-button">View in Admin Panel &rarr;</a>
        <div class="footer">
            <p>This is an automated notification from Trail.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $textBody = <<<TEXT
ACCOUNT DELETION REQUEST
========================

A user has requested to delete their Trail account.

User: {$userName} ({$userEmail})
User ID: #{$userId}
Requested At: {$requestedAt}

The user's content has been hidden from public view. Their account and data will be permanently deleted in 14 days unless reverted from the admin panel.

View in Admin Panel: {$adminUrl}

---
This is an automated notification from Trail.
TEXT;

        return $this->sendEmail($this->adminEmail, $subject, $htmlBody, $textBody);
    }

    /**
     * Send deletion confirmation email to the user
     */
    public function sendDeletionConfirmation(array $user, string $contactEmail): bool
    {
        $userName = htmlspecialchars($user['name'] ?? 'there', ENT_QUOTES, 'UTF-8');
        $userEmail = $user['email'] ?? '';

        if (empty($userEmail)) {
            return false;
        }

        $subject = "Your Trail account deletion request";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deletion Confirmation</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .container { background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; margin: -30px -30px 30px -30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .info-box { background-color: #f0fdf4; border: 1px solid #86efac; padding: 16px; border-radius: 8px; margin: 20px 0; }
        .info-box strong { color: #166534; }
        .timeline { background-color: #f9fafb; border-left: 4px solid #6366f1; padding: 16px; margin: 20px 0; border-radius: 4px; }
        .timeline-item { margin-bottom: 12px; font-size: 14px; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; vertical-align: middle; }
        .dot-now { background-color: #6366f1; }
        .dot-future { background-color: #d1d5db; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; text-align: center; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>We're sad to see you go</h1>
        </div>
        <p>Hi {$userName},</p>
        <p>We've received your request to delete your Trail account. We're sorry to see you go, and we want to make sure this process is as smooth as possible for you.</p>

        <div class="timeline">
            <div class="timeline-item"><span class="timeline-dot dot-now"></span> <strong>Now:</strong> Your profile, entries, and comments are hidden from public view.</div>
            <div class="timeline-item"><span class="timeline-dot dot-future"></span> <strong>Within 14 days:</strong> Your account and all associated data will be permanently deleted.</div>
        </div>

        <div class="info-box">
            <strong>Changed your mind?</strong> No worries! You can reverse this decision within the next 14 days by reaching out to us at <a href="mailto:{$contactEmail}">{$contactEmail}</a>. We'd be happy to restore your account.
        </div>

        <p>Thank you for being part of Trail. If you ever want to come back, you're always welcome.</p>
        <p>Take care,<br>The Trail Team</p>

        <div class="footer">
            <p>This is an automated message from Trail.<br>
            <a href="mailto:{$contactEmail}">{$contactEmail}</a></p>
        </div>
    </div>
</body>
</html>
HTML;

        $textBody = <<<TEXT
We're sad to see you go

Hi {$userName},

We've received your request to delete your Trail account. We're sorry to see you go.

What happens now:
- Now: Your profile, entries, and comments are hidden from public view.
- Within 14 days: Your account and all associated data will be permanently deleted.

Changed your mind? No worries! You can reverse this decision within the next 14 days by reaching out to us at {$contactEmail}. We'd be happy to restore your account.

Thank you for being part of Trail. If you ever want to come back, you're always welcome.

Take care,
The Trail Team

---
This is an automated message from Trail.
{$contactEmail}
TEXT;

        return $this->sendEmail($userEmail, $subject, $htmlBody, $textBody);
    }

    /**
     * Send feedback notification email to contact@kibotu.net with user's Reply-To
     */
    public function sendFeedbackNotification(array $user, string $feedbackText): bool
    {
        $rawName = $user['name'] ?? 'Unknown';
        $rawNickname = $user['nickname'] ?? $user['name'] ?? 'Unknown';
        $userName = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        $userEmail = $user['email'] ?? '';
        $userEmailHtml = htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8');
        $userNickname = htmlspecialchars($rawNickname, ENT_QUOTES, 'UTF-8');
        $userId = (int) ($user['id'] ?? 0);
        $submittedAt = date('M j, Y \a\t g:i A');
        $profileUrl = $this->baseUrl . '/@' . ($user['nickname'] ?? '');

        $plainFeedback = html_entity_decode($feedbackText, ENT_QUOTES, 'UTF-8');
        $quotedFeedbackPlain = implode("\n", array_map(fn($line) => '> ' . $line, explode("\n", $plainFeedback)));

        $subject = '=?UTF-8?B?' . base64_encode("Trail Feedback from @{$rawNickname}") . '?=';

        // The email is structured so the TOP is user-facing (clean when quoted in a reply)
        // and admin metadata goes BELOW the feedback (buried in the reply quote).

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Feedback</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .container { background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .feedback-content { background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 16px; border-radius: 4px; margin: 16px 0; font-size: 14px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; }
        .admin-section { margin-top: 32px; padding-top: 20px; border-top: 2px solid #e5e7eb; }
        .admin-section h3 { font-size: 13px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 12px 0; }
        .admin-meta { background-color: #f9fafb; padding: 12px 16px; border-radius: 6px; font-size: 12px; color: #6b7280; line-height: 1.8; }
        .admin-meta strong { color: #374151; }
        .action-button { display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 12px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <p>Hi {$userName},</p>
        <p>Thanks for your feedback on Trail!</p>

        <div class="feedback-content">{$feedbackText}</div>

        <p>Cheers,<br>Jan</p>

        <div class="admin-section">
            <h3>Admin Info</h3>
            <div class="admin-meta">
                <strong>From:</strong> {$userName} (@{$userNickname})<br>
                <strong>Email:</strong> {$userEmailHtml}<br>
                <strong>User ID:</strong> #{$userId}<br>
                <strong>Submitted:</strong> {$submittedAt}<br>
                <a href="{$profileUrl}" class="action-button">View Profile &rarr;</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

        $textBody = <<<TEXT
Hi {$rawName},

Thanks for your feedback on Trail!

{$quotedFeedbackPlain}

Cheers,
Jan

---
Admin Info
From: {$rawName} (@{$rawNickname})
Email: {$userEmail}
User ID: #{$userId}
Submitted: {$submittedAt}
Profile: {$profileUrl}
TEXT;

        $replyTo = null;
        if (!empty($userEmail) &&
            filter_var($userEmail, FILTER_VALIDATE_EMAIL) &&
            !preg_match('/[\r\n]/', $userEmail)) {
            $replyTo = $userEmail;
        }

        return $this->sendEmailWithReplyTo($this->adminEmail, $subject, $htmlBody, $textBody, $replyTo);
    }

    /**
     * Send email with custom Reply-To header
     */
    private function sendEmailWithReplyTo(string $to, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): bool
    {
        $replyToHeader = $replyTo ?? 'noreply@trail.services.kibotu.net';

        $boundary = md5(uniqid((string)time()));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: Trail Feedback <noreply@trail.services.kibotu.net>',
            'Reply-To: ' . $replyToHeader,
            'X-Mailer: PHP/' . phpversion()
        ];

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $textBody . "\r\n\r\n";

        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";

        $message .= "--{$boundary}--";

        try {
            $result = mail($to, $subject, $message, implode("\r\n", $headers));

            if (!$result) {
                error_log("EmailService: Failed to send feedback email to {$to}");
                return false;
            }

            error_log("EmailService: Successfully sent feedback email to {$to}");
            return true;
        } catch (\Throwable $e) {
            error_log("EmailService: Exception sending feedback email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using PHP mail() function
     */
    private function sendEmail(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        // Generate a boundary for multipart email
        $boundary = md5(uniqid((string)time()));
        
        // Headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: Trail Reports <noreply@trail.services.kibotu.net>',
            'Reply-To: noreply@trail.services.kibotu.net',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Build multipart message
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $textBody . "\r\n\r\n";
        
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";
        
        $message .= "--{$boundary}--";
        
        // Send email
        try {
            $result = mail($to, $subject, $message, implode("\r\n", $headers));
            
            if (!$result) {
                error_log("EmailService: Failed to send email to {$to}");
                return false;
            }
            
            error_log("EmailService: Successfully sent report email to {$to}");
            return true;
        } catch (\Throwable $e) {
            error_log("EmailService: Exception sending email: " . $e->getMessage());
            return false;
        }
    }
}
