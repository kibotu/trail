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
