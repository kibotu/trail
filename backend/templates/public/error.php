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
    padding:24px;
    display:flex;
    flex-direction:column;
    gap:24px;
  }

  .card{
    background:linear-gradient(180deg,#141e36,#10182b);
    border:1px solid var(--border);
    border-radius:20px;
    padding:24px;
    box-shadow:0 20px 40px rgba(0,0,0,.35);
  }

  textarea{
    width:100%;
    background:#0e1628;
    color:var(--text);
    border:1px solid var(--border);
    border-radius:14px;
    padding:14px 16px;
    resize:none;
    font-size:15px;
    outline:none;
    line-height:1.5;
    transition:border-color 0.2s ease;
  }

  textarea:focus{
    border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(79,140,255,0.1);
  }

  .composer-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:12px;
    font-size:13px;
    color:var(--muted);
  }

  button{
    background:var(--accent);
    border:none;
    color:white;
    border-radius:999px;
    padding:10px 20px;
    font-weight:600;
    cursor:pointer;
    transition:all 0.2s ease;
    font-size:14px;
  }

  button:hover{
    opacity:0.9;
    transform:translateY(-1px);
    box-shadow:0 4px 12px rgba(79,140,255,0.3);
  }

  button:active{
    transform:translateY(0);
  }

  button:disabled{
    opacity:0.5;
    cursor:not-allowed;
    transform:none;
  }

  button.secondary{
    background:#1b2642;
    color:var(--text);
  }

  button.secondary:hover{
    background:#22304d;
    box-shadow:0 4px 12px rgba(0,0,0,0.3);
  }

  .post-user{
    font-weight:700;
    margin-bottom:10px;
    font-size:15px;
  }

  .post-user a{
    color:var(--text);
    text-decoration:none;
    transition:color 0.2s ease;
  }

  .post-user a:hover{
    color:var(--accent);
  }

  .card > div:not(.composer-footer):not(.error-box):not(.error-actions){
    font-size:15px;
    line-height:1.6;
    margin-bottom:10px;
  }

  .link-preview{
    background:#0e1628;
    border:1px solid var(--border);
    border-radius:12px;
    padding:12px 14px;
    font-size:13px;
    margin-top:10px;
    color:#b6c6ff;
    text-decoration:none;
    display:inline-block;
    transition:all 0.2s ease;
  }

  .link-preview:hover{
    border-color:var(--accent);
    background:#121b30;
    transform:translateY(-1px);
  }

  .error-box{
    text-align:center;
    animation:floatIn .6s ease-out;
    position:relative;
    padding:32px 24px;
  }

  @keyframes floatIn{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}

  .confetti{
    position:fixed;
    width:10px;
    height:10px;
    background:#4f8cff;
    pointer-events:none;
    z-index:9999;
    border-radius:2px;
  }

  @keyframes confetti-fall{
    0%{
      opacity:1;
      transform:translate(0, 0) rotate(0deg) scale(1);
    }
    50%{
      opacity:0.8;
    }
    100%{
      opacity:0;
      transform:translate(var(--tx, 0), var(--ty, 200px)) rotate(720deg) scale(0.5);
    }
  }

  .error-image{
    width:100%;
    max-width:420px;
    height:auto;
    margin:0 auto 32px;
    display:block;
  }

  .error-title{
    font-size:28px;
    font-weight:700;
    line-height:1.3;
    margin-bottom:12px;
    letter-spacing:-0.02em;
  }

  .error-sub{
    color:var(--muted);
    font-size:16px;
    line-height:1.6;
    margin:0 auto;
    max-width:480px;
    padding:0 16px;
  }

  .error-code{
    font-size:13px;
    color:#5a6b8a;
    margin-top:16px;
    font-weight:500;
    opacity:0.7;
  }

  .error-actions{
    display:flex;
    justify-content:center;
    gap:12px;
    margin-top:28px;
  }

  .error-actions button{
    padding:12px 28px;
    font-size:15px;
    font-weight:600;
  }

  .composer-card{display:<?= $isLoggedIn ? 'block' : 'none' ?>}

  @media(max-width:600px){
    .container{
      max-width:100%;
      padding:16px;
    }
    
    .card{
      padding:20px;
      border-radius:16px;
    }
    
    .error-box{
      padding:24px 16px;
    }
    
    .error-image{
      max-width:100%;
      margin-bottom:24px;
    }
    
    .error-title{
      font-size:24px;
      margin-bottom:10px;
    }
    
    .error-sub{
      font-size:15px;
      padding:0 8px;
    }
    
    .error-code{
      font-size:12px;
      margin-top:12px;
    }
    
    .error-actions{
      margin-top:24px;
    }
    
    .error-actions button{
      padding:10px 24px;
      font-size:14px;
    }
  }
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
    <img src="/assets/fail-trail.png" alt="Fail Trail" class="error-image">

    <div class="error-title"><?= htmlspecialchars($errorTitle) ?></div>
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

  // Confetti celebration for finding an error - viewport-aware
  function createConfetti() {
    const colors = ['#4f8cff', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6'];
    const errorBox = document.querySelector('.error-box');
    if (!errorBox) return;
    
    const rect = errorBox.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + 100; // Near the image
    
    // Use viewport dimensions for radius calculation
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const maxRadius = Math.min(viewportWidth, viewportHeight) * 0.6; // 60% of smaller dimension

    // Create more confetti pieces for better coverage
    const confettiCount = Math.min(50, Math.floor(viewportWidth / 20)); // Scale with viewport
    
    for (let i = 0; i < confettiCount; i++) {
      const confetti = document.createElement('div');
      confetti.className = 'confetti';
      confetti.style.left = centerX + 'px';
      confetti.style.top = centerY + 'px';
      confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
      
      // Random size between 4-8px (slightly larger for visibility)
      const size = Math.random() * 4 + 4;
      confetti.style.width = size + 'px';
      confetti.style.height = size + 'px';
      
      // Random direction - full circle spread
      const angle = (Math.PI * 2 * i) / confettiCount + (Math.random() - 0.5) * 0.3;
      
      // Velocity based on viewport size - much larger radius
      const minVelocity = maxRadius * 0.4;
      const maxVelocity = maxRadius * 0.8;
      const velocity = Math.random() * (maxVelocity - minVelocity) + minVelocity;
      
      const tx = Math.cos(angle) * velocity;
      const ty = Math.sin(angle) * velocity + Math.random() * (viewportHeight * 0.3);
      
      confetti.style.setProperty('--tx', tx + 'px');
      confetti.style.setProperty('--ty', ty + 'px');
      
      // Longer animation for larger distances
      const duration = Math.random() * 1.5 + 2; // 2-3.5 seconds
      confetti.style.animation = `confetti-fall ${duration}s ease-out ${Math.random() * 0.3}s forwards`;
      
      document.body.appendChild(confetti);
      
      // Remove after animation
      setTimeout(() => confetti.remove(), (duration + 0.5) * 1000);
    }
  }

  // Trigger confetti after page loads (subtle delay)
  window.addEventListener('load', () => {
    setTimeout(createConfetti, 600);
  });
</script>

</body>
</html>
