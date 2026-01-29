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

// Error messages mapping
$errorMessages = [
    400 => ['title' => 'Bad Request', 'message' => 'Something went wrong with your request.'],
    401 => ['title' => 'Unauthorized', 'message' => 'You need to be logged in to access this.'],
    403 => ['title' => 'Access Denied', 'message' => 'You don\'t have permission to view this.'],
    404 => ['title' => 'Trail Lost the Path', 'message' => 'This trail doesn\'t exist.'],
    429 => ['title' => 'Slow Down', 'message' => 'Too many requests. Take a breather.'],
    500 => ['title' => 'Server Error', 'message' => 'Our servers wandered into the woods.'],
    502 => ['title' => 'Bad Gateway', 'message' => 'The server is having trouble connecting.'],
    503 => ['title' => 'Service Unavailable', 'message' => 'We\'re temporarily offline for maintenance.'],
];

// Get error details or use default
$errorInfo = $errorMessages[$statusCode] ?? ['title' => 'Something Went Wrong', 'message' => 'An unexpected error occurred.'];
$errorTitle = $errorInfo['title'];
$errorMessage = $errorInfo['message'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Trail - <?= htmlspecialchars($errorTitle) ?></title>
<style>
  :root{
    --bg:#0b1220;
    --card:#121a2b;
    --muted:#7c8aa5;
    --accent:#4f8cff;
    --border:#1f2a44;
    --text:#e5ecff;
  }

  *{box-sizing:border-box;font-family:system-ui,-apple-system,BlinkMacSystemFont,sans-serif}

  body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:radial-gradient(1200px 600px at 20% -10%,#1a2550,transparent),var(--bg);
    color:var(--text);
  }

  .container{
    width:100%;
    max-width:760px;
    padding:16px;
    display:flex;
    flex-direction:column;
    gap:18px;
  }

  .card{
    background:linear-gradient(180deg,#141e36,#10182b);
    border:1px solid var(--border);
    border-radius:18px;
    padding:14px;
    box-shadow:0 20px 40px rgba(0,0,0,.35);
  }

  textarea{
    width:100%;
    background:#0e1628;
    color:var(--text);
    border:1px solid var(--border);
    border-radius:14px;
    padding:12px;
    resize:none;
    font-size:15px;
    outline:none;
  }

  textarea:focus{border-color:var(--accent)}

  .composer-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:10px;
    font-size:13px;
    color:var(--muted);
  }

  button{
    background:var(--accent);
    border:none;
    color:white;
    border-radius:999px;
    padding:8px 16px;
    font-weight:600;
    cursor:pointer;
    transition:opacity 0.2s;
  }

  button:hover{opacity:0.9}
  button:disabled{opacity:0.5;cursor:not-allowed}

  button.secondary{
    background:#1b2642;
    color:var(--text);
  }

  .post-user{
    font-weight:700;
    margin-bottom:6px;
  }

  .post-user a{
    color:var(--text);
    text-decoration:none;
  }

  .post-user a:hover{
    text-decoration:underline;
  }

  .link-preview{
    background:#0e1628;
    border:1px solid var(--border);
    border-radius:12px;
    padding:10px;
    font-size:13px;
    margin-top:6px;
    color:#b6c6ff;
    text-decoration:none;
    display:inline-block;
  }

  .link-preview:hover{
    border-color:var(--accent);
  }

  .error-box{text-align:center;animation:floatIn .6s ease-out}

  @keyframes floatIn{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}

  .whale{width:160px;margin:0 auto 12px}

  .error-title{font-size:24px;font-weight:800}
  .error-sub{color:var(--muted);margin-top:6px}
  .error-code{font-size:13px;color:var(--muted);margin-top:4px}

  .error-actions{display:flex;justify-content:center;gap:10px;margin-top:14px}

  .composer-card{display:<?= $isLoggedIn ? 'block' : 'none' ?>}

  @media(max-width:600px){.container{max-width:420px}}
</style>
</head>
<body>

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
    <svg class="whale" viewBox="0 0 160 120">
      <ellipse cx="80" cy="70" rx="60" ry="35" fill="#4f8cff" />
      <circle cx="110" cy="60" r="5" fill="#0b1220" />
      <path d="M20 70 Q5 60 20 50" stroke="#4f8cff" stroke-width="6" fill="none" />
    </svg>

    <div class="error-title"><?= htmlspecialchars($errorTitle) ?> 🐋</div>
    <div class="error-sub"><?= htmlspecialchars($errorMessage) ?></div>
    <div class="error-code"><?= htmlspecialchars((string)$statusCode) ?> — Error Code</div>

    <div class="error-actions">
      <button id="home">Back Home</button>
    </div>
  </div>

</div>

<script>
  const LIMIT = 280;
  const API_BASE = '/api';
  const isLoggedIn = <?= json_encode($isLoggedIn) ?>;

  // Character counter
  const box = document.getElementById('postBox');
  const count = document.getElementById('count');
  const postBtn = document.getElementById('postBtn');

  function remainingChars(text, limit = LIMIT){
    return limit - text.length;
  }

  if (box && count) {
    box.addEventListener('input', () => {
      count.textContent = remainingChars(box.value);
    });
  }

  // Home button
  document.getElementById('home').addEventListener('click', () => {
    window.location.href = '/';
  });

  // Post functionality (only if logged in)
  if (isLoggedIn && postBtn && box) {
    postBtn.addEventListener('click', async () => {
      const content = box.value.trim();
      
      if (!content) {
        return;
      }

      if (content.length > LIMIT) {
        alert('Post is too long!');
        return;
      }

      postBtn.disabled = true;
      postBtn.textContent = 'Posting...';

      try {
        const response = await fetch(`${API_BASE}/entries`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin', // Include httpOnly cookie with JWT
          body: JSON.stringify({ text: content })
        });

        if (!response.ok) {
          throw new Error('Failed to post');
        }

        // Success - navigate to landing page
        window.location.href = '/';
      } catch (error) {
        console.error('Error posting:', error);
        alert('Failed to post. Please try again.');
        postBtn.disabled = false;
        postBtn.textContent = 'Post';
      }
    });
  }
</script>

</body>
</html>
