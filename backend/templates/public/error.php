<?php
/**
 * Error Page Template
 * 
 * Variables expected:
 * - $statusCode (int): HTTP status code (e.g., 404, 500)
 * - $isLoggedIn (bool): Whether user is authenticated
 * - $jwtToken (string|null): JWT token if logged in
 * - $userName (string|null): User email if logged in
 * - $userPhotoUrl (string|null): User photo URL if logged in
 * - $adminNickname (string|null): Admin nickname for changelog link
 * - $hasChangelog (bool): Whether changelog is available
 */

// Error messages mapping - human, informal, relatable
$errorMessages = [
    400 => ['title' => 'Hmm, that didn\'t work', 'message' => 'Something about that request didn\'t quite make sense to us.'],
    401 => ['title' => 'Hold up there', 'message' => 'You\'ll need to log in first to see this.'],
    403 => ['title' => 'Not your trail', 'message' => 'This area is off-limits, sorry about that.'],
    404 => ['title' => 'Well, this is awkward', 'message' => 'We looked everywhere, but this page doesn\'t exist. Maybe it never did?'],
    429 => ['title' => 'Whoa, slow down!', 'message' => 'You\'re going too fast. Take a breath, we\'ll be here when you\'re ready.'],
    500 => ['title' => 'Our bad', 'message' => 'Something broke on our end. We\'re on it, promise.'],
    502 => ['title' => 'Connection issues', 'message' => 'Having trouble reaching our servers. Give it a moment?'],
    503 => ['title' => 'Taking a quick break', 'message' => 'We\'re doing some maintenance. Back shortly!'],
];

// Get error details or use default
$errorInfo = $errorMessages[$statusCode] ?? ['title' => 'Oops', 'message' => 'Something unexpected happened. Not sure what, but here we are.'];
$errorTitle = $errorInfo['title'];
$errorMessage = $errorInfo['message'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Trail - <?= htmlspecialchars($errorTitle) ?></title>
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-error"
      data-is-logged-in="<?= json_encode($isLoggedIn ?? false) ?>"
      data-user-id="<?= json_encode($userId ?? null) ?>"
      data-user-email="<?= json_encode($userName ?? null) ?>"
      data-is-admin="<?= json_encode($isAdmin ?? false) ?>">

<div class="container">

  <?php if ($isLoggedIn): ?>
  <div class="card composer-card">
    <textarea id="postBox" maxlength="280" placeholder="What's happening on your trail?"></textarea>
    <div class="composer-footer">
      <span id="count">280</span>
      <button id="postBtn">Post</button>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($adminNickname) && $hasChangelog): ?>
  <div class="card">
    <div class="post-user">
      <a href="/@<?= htmlspecialchars($adminNickname) ?>">@<?= htmlspecialchars($adminNickname) ?></a>
    </div>
    <div>Check out the latest updates and improvements to Trail.</div>
    <a href="/@<?= htmlspecialchars($adminNickname) ?>" class="link-preview">trail.services/updates</a>
  </div>
  <?php endif; ?>

  <div class="card error-box">
    <img src="/assets/fail-trail.webp" alt="Fail Trail" class="error-image">

    <div class="error-title"><?= htmlspecialchars($errorTitle) ?></div>
    <div class="error-sub"><?= htmlspecialchars($errorMessage) ?></div>
    <div class="error-code"><?= htmlspecialchars((string)$statusCode) ?> — Error Code</div>

    <div class="error-actions">
      <button id="home">Back Home</button>
    </div>
  </div>

</div>

<!-- Core JavaScript Modules -->
<script src="/js/snackbar.js"></script>
<script src="/js/celebrations.js"></script>
<script src="/js/ui-interactions.js"></script>
<script src="/js/entries-manager.js"></script>

<!-- Page Initialization -->
<script src="/js/error-page.js"></script>

</body>
</html>
