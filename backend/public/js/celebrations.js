/**
 * Celebrations - Animation effects for successful actions
 * 
 * Provides confetti explosions, floating emojis, and other celebration animations
 * for successful user actions like creating posts.
 */

/**
 * Celebrate a successful post with confetti and emojis
 * @param {HTMLElement|string} targetElement - Element or selector to celebrate around
 * @param {Object} options - Celebration options
 * @param {boolean} options.showConfetti - Show confetti animation
 * @param {boolean} options.showEmojis - Show floating emojis
 * @param {boolean} options.pulseButton - Add pulse animation to button
 */
function celebratePost(targetElement, options = {}) {
    const {
        showConfetti = true,
        showEmojis = true,
        pulseButton = true
    } = options;

    // Get element
    const element = typeof targetElement === 'string' 
        ? document.querySelector(targetElement) || document.getElementById(targetElement)
        : targetElement;

    if (!element) {
        console.warn('Target element not found for celebration');
        return;
    }

    const rect = element.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    // Create confetti explosion
    if (showConfetti) {
        createPostConfetti(centerX, centerY);
    }

    // Show floating celebration emojis
    if (showEmojis) {
        createCelebrationEmojis(centerX, centerY);
    }

    // Add a subtle pulse to the element
    if (pulseButton && element) {
        element.classList.add('celebrate-pulse');
        setTimeout(() => {
            element.classList.remove('celebrate-pulse');
        }, 1000);
    }
}

/**
 * Create confetti explosion at specified coordinates
 * @param {number} centerX - X coordinate for explosion center
 * @param {number} centerY - Y coordinate for explosion center
 * @param {Object} options - Confetti options
 * @param {number} options.count - Number of confetti pieces
 * @param {Array<string>} options.colors - Array of color hex codes
 */
function createPostConfetti(centerX, centerY, options = {}) {
    const {
        count = 60,
        colors = ['#4f8cff', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4']
    } = options;

    // Inject styles if not present
    _injectConfettiStyles();

    for (let i = 0; i < count; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'post-confetti';
        confetti.style.left = centerX + 'px';
        confetti.style.top = centerY + 'px';
        confetti.style.background = colors[Math.floor(Math.random() * colors.length)];

        // Random size and shape
        const size = Math.random() * 6 + 4;
        confetti.style.width = size + 'px';
        confetti.style.height = size + 'px';
        confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';

        // Explosive spread in all directions
        const angle = (Math.PI * 2 * i) / count + (Math.random() - 0.5) * 0.4;
        const velocity = Math.random() * 300 + 200;

        const tx = Math.cos(angle) * velocity;
        const ty = Math.sin(angle) * velocity - 100; // Slight upward bias

        confetti.style.setProperty('--tx', tx + 'px');
        confetti.style.setProperty('--ty', ty + 'px');
        confetti.style.setProperty('--rotation', (Math.random() * 720 - 360) + 'deg');

        const duration = Math.random() * 0.8 + 1.2;
        confetti.style.animation = `post-confetti-burst ${duration}s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards`;

        document.body.appendChild(confetti);

        setTimeout(() => confetti.remove(), duration * 1000 + 100);
    }
}

/**
 * Create floating celebration emojis
 * @param {number} centerX - X coordinate for starting position
 * @param {number} centerY - Y coordinate for starting position
 * @param {Object} options - Emoji options
 * @param {number} options.count - Number of emojis
 * @param {Array<string>} options.emojis - Array of emoji characters
 */
function createCelebrationEmojis(centerX, centerY, options = {}) {
    const {
        count = null, // Auto-calculate if not provided
        emojis = ['🎉', '✨', '🚀', '💫', '⭐', '🌟', '🎊', '🔥', '💪', '👏']
    } = options;

    // Inject styles if not present
    _injectEmojiStyles();

    // Create 5-7 floating emojis
    const emojiCount = count || (Math.floor(Math.random() * 3) + 5);

    for (let i = 0; i < emojiCount; i++) {
        const emojiEl = document.createElement('div');
        emojiEl.className = 'celebration-emoji';
        emojiEl.textContent = emojis[Math.floor(Math.random() * emojis.length)];

        // Random starting position around the center
        const offsetX = (Math.random() - 0.5) * 100;
        emojiEl.style.left = (centerX + offsetX) + 'px';
        emojiEl.style.top = centerY + 'px';

        // Random float direction
        const floatX = (Math.random() - 0.5) * 150;
        const floatY = -(Math.random() * 200 + 150);

        emojiEl.style.setProperty('--float-x', floatX + 'px');
        emojiEl.style.setProperty('--float-y', floatY + 'px');

        const duration = Math.random() * 0.5 + 1.5;
        const delay = Math.random() * 0.3;
        emojiEl.style.animation = `celebration-emoji-float ${duration}s ease-out ${delay}s forwards`;

        document.body.appendChild(emojiEl);

        setTimeout(() => emojiEl.remove(), (duration + delay) * 1000 + 100);
    }
}

/**
 * Create viewport-aware confetti for page load celebrations
 * @param {HTMLElement|string} targetElement - Element to celebrate around (optional)
 * @param {Object} options - Confetti options
 */
function createPageLoadConfetti(targetElement = null, options = {}) {
    const {
        colors = ['#4f8cff', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6'],
        delay = 600
    } = options;

    // Inject styles if not present
    _injectPageLoadConfettiStyles();

    setTimeout(() => {
        let centerX, centerY;

        if (targetElement) {
            const element = typeof targetElement === 'string'
                ? document.querySelector(targetElement)
                : targetElement;

            if (element) {
                const rect = element.getBoundingClientRect();
                centerX = rect.left + rect.width / 2;
                centerY = rect.top + 100; // Near the top of element
            }
        }

        // Default to center of viewport
        if (!centerX || !centerY) {
            centerX = window.innerWidth / 2;
            centerY = window.innerHeight / 3;
        }

        // Use viewport dimensions for radius calculation
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const maxRadius = Math.min(viewportWidth, viewportHeight) * 0.6;

        // Create confetti pieces scaled to viewport
        const confettiCount = Math.min(50, Math.floor(viewportWidth / 20));

        for (let i = 0; i < confettiCount; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = centerX + 'px';
            confetti.style.top = centerY + 'px';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];

            // Random size
            const size = Math.random() * 4 + 4;
            confetti.style.width = size + 'px';
            confetti.style.height = size + 'px';

            // Random direction - full circle spread
            const angle = (Math.PI * 2 * i) / confettiCount + (Math.random() - 0.5) * 0.3;

            // Velocity based on viewport size
            const minVelocity = maxRadius * 0.4;
            const maxVelocity = maxRadius * 0.8;
            const velocity = Math.random() * (maxVelocity - minVelocity) + minVelocity;

            const tx = Math.cos(angle) * velocity;
            const ty = Math.sin(angle) * velocity + Math.random() * (viewportHeight * 0.3);

            confetti.style.setProperty('--tx', tx + 'px');
            confetti.style.setProperty('--ty', ty + 'px');

            // Longer animation for larger distances
            const duration = Math.random() * 1.5 + 2;
            confetti.style.animation = `confetti-fall ${duration}s ease-out ${Math.random() * 0.3}s forwards`;

            document.body.appendChild(confetti);

            // Remove after animation
            setTimeout(() => confetti.remove(), (duration + 0.5) * 1000);
        }
    }, delay);
}

/**
 * Inject confetti animation styles
 * @private
 */
function _injectConfettiStyles() {
    if (document.getElementById('confetti-styles')) return;

    const style = document.createElement('style');
    style.id = 'confetti-styles';
    style.textContent = `
        .post-confetti {
            position: fixed;
            pointer-events: none;
            z-index: 10000;
        }

        @keyframes post-confetti-burst {
            0% {
                transform: translate(0, 0) rotate(0deg) scale(1);
                opacity: 1;
            }
            50% {
                opacity: 1;
            }
            100% {
                transform: translate(var(--tx), var(--ty)) rotate(var(--rotation)) scale(0.3);
                opacity: 0;
            }
        }

        .celebrate-pulse {
            animation: celebrate-pulse 0.6s ease-in-out;
        }

        @keyframes celebrate-pulse {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.1); }
            50% { transform: scale(0.95); }
            75% { transform: scale(1.05); }
        }
    `;
    document.head.appendChild(style);
}

/**
 * Inject emoji animation styles
 * @private
 */
function _injectEmojiStyles() {
    if (document.getElementById('emoji-styles')) return;

    const style = document.createElement('style');
    style.id = 'emoji-styles';
    style.textContent = `
        .celebration-emoji {
            position: fixed;
            font-size: 2rem;
            pointer-events: none;
            z-index: 10000;
        }

        @keyframes celebration-emoji-float {
            0% {
                transform: translate(0, 0) scale(0.5) rotate(0deg);
                opacity: 0;
            }
            10% {
                transform: translate(0, -20px) scale(1) rotate(10deg);
                opacity: 1;
            }
            100% {
                transform: translate(var(--float-x), var(--float-y)) scale(1.5) rotate(360deg);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

/**
 * Inject page load confetti styles
 * @private
 */
function _injectPageLoadConfettiStyles() {
    if (document.getElementById('page-load-confetti-styles')) return;

    const style = document.createElement('style');
    style.id = 'page-load-confetti-styles';
    style.textContent = `
        .confetti {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 10000;
        }

        @keyframes confetti-fall {
            0% {
                transform: translate(0, 0) rotate(0deg) scale(1);
                opacity: 1;
            }
            50% {
                opacity: 1;
            }
            100% {
                transform: translate(var(--tx), var(--ty)) rotate(720deg) scale(0.5);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        celebratePost,
        createPostConfetti,
        createCelebrationEmojis,
        createPageLoadConfetti
    };
}
