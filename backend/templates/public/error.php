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
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-error">

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

        // CELEBRATE! 🎉
        celebratePost();

        // Success - navigate to landing page after celebration
        setTimeout(() => {
          window.location.href = '/';
        }, 1200);
      } catch (error) {
        console.error('Error posting:', error);
        alert('Failed to post. Please try again.');
        postBtn.disabled = false;
        postBtn.textContent = 'Post';
      }
    });
  }

  // Celebration animation for successful post! 🎉
  function celebratePost() {
    const colors = ['#4f8cff', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4'];
    const emojis = ['🎉', '✨', '🚀', '💫', '⭐', '🌟', '🎊', '🔥', '💪', '👏'];
    
    const postBtn = document.getElementById('post');
    if (!postBtn) return;
    
    const rect = postBtn.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    
    // Confetti burst
    for (let i = 0; i < 50; i++) {
      const confetti = document.createElement('div');
      confetti.style.position = 'fixed';
      confetti.style.left = centerX + 'px';
      confetti.style.top = centerY + 'px';
      confetti.style.width = (Math.random() * 6 + 4) + 'px';
      confetti.style.height = (Math.random() * 6 + 4) + 'px';
      confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
      confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
      confetti.style.pointerEvents = 'none';
      confetti.style.zIndex = '10000';
      
      const angle = (Math.PI * 2 * i) / 50 + (Math.random() - 0.5) * 0.4;
      const velocity = Math.random() * 250 + 150;
      const tx = Math.cos(angle) * velocity;
      const ty = Math.sin(angle) * velocity - 80;
      
      confetti.animate([
        { transform: 'translate(0, 0) rotate(0deg) scale(1)', opacity: 1 },
        { opacity: 1, offset: 0.5 },
        { transform: `translate(${tx}px, ${ty}px) rotate(${Math.random() * 720 - 360}deg) scale(0.3)`, opacity: 0 }
      ], {
        duration: Math.random() * 800 + 1000,
        easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
      });
      
      document.body.appendChild(confetti);
      setTimeout(() => confetti.remove(), 2000);
    }
    
    // Celebration emojis
    for (let i = 0; i < 6; i++) {
      const emojiEl = document.createElement('div');
      emojiEl.textContent = emojis[Math.floor(Math.random() * emojis.length)];
      emojiEl.style.position = 'fixed';
      emojiEl.style.left = (centerX + (Math.random() - 0.5) * 80) + 'px';
      emojiEl.style.top = centerY + 'px';
      emojiEl.style.fontSize = '2rem';
      emojiEl.style.pointerEvents = 'none';
      emojiEl.style.zIndex = '10000';
      
      const floatX = (Math.random() - 0.5) * 120;
      const floatY = -(Math.random() * 180 + 120);
      
      emojiEl.animate([
        { transform: 'translate(0, 0) scale(0.5) rotate(0deg)', opacity: 0 },
        { transform: 'translate(0, -20px) scale(1) rotate(10deg)', opacity: 1, offset: 0.1 },
        { transform: `translate(${floatX}px, ${floatY}px) scale(1.5) rotate(360deg)`, opacity: 0 }
      ], {
        duration: Math.random() * 500 + 1500,
        delay: Math.random() * 300,
        easing: 'ease-out'
      });
      
      document.body.appendChild(emojiEl);
      setTimeout(() => emojiEl.remove(), 2200);
    }
    
    // Button pulse
    postBtn.animate([
      { transform: 'scale(1)' },
      { transform: 'scale(1.1)' },
      { transform: 'scale(0.95)' },
      { transform: 'scale(1.05)' },
      { transform: 'scale(1)' }
    ], {
      duration: 600,
      easing: 'ease-in-out'
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
