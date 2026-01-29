/**
 * Secure Authentication Client
 * 
 * Handles API authentication without exposing JWT tokens in JavaScript.
 * Tokens are stored in httpOnly cookies and automatically included in requests.
 */

class AuthClient {
    constructor() {
        this.userInfo = null;
        this.initialized = false;
    }

    /**
     * Initialize the auth client by fetching user info from the session.
     * This should be called once when the page loads.
     */
    async init() {
        if (this.initialized) {
            return this.userInfo;
        }

        try {
            // The JWT token is automatically sent via httpOnly cookie
            const response = await fetch('/api/auth/session', {
                credentials: 'same-origin' // Include cookies
            });

            if (response.ok) {
                const data = await response.json();
                this.userInfo = data.authenticated ? data.user : null;
            } else {
                this.userInfo = null;
            }
        } catch (error) {
            console.error('Failed to initialize auth client:', error);
            this.userInfo = null;
        }

        this.initialized = true;
        return this.userInfo;
    }

    /**
     * Check if user is authenticated.
     */
    isAuthenticated() {
        return this.userInfo !== null;
    }

    /**
     * Check if user is admin.
     */
    isAdmin() {
        return this.userInfo?.is_admin === true;
    }

    /**
     * Get current user's email.
     */
    getUserEmail() {
        return this.userInfo?.email || null;
    }

    /**
     * Get current user's ID.
     */
    getUserId() {
        return this.userInfo?.user_id || null;
    }

    /**
     * Make an authenticated API request.
     * The JWT token is automatically included via httpOnly cookie.
     * 
     * @param {string} url - API endpoint URL
     * @param {object} options - Fetch options (method, headers, body, etc.)
     * @returns {Promise<Response>}
     */
    async fetch(url, options = {}) {
        // Ensure credentials are included to send httpOnly cookies
        const fetchOptions = {
            ...options,
            credentials: 'same-origin',
            headers: {
                ...options.headers,
                // No need to add Authorization header - it's in the cookie!
            }
        };

        return fetch(url, fetchOptions);
    }

    /**
     * Make an authenticated API request and parse JSON response.
     * 
     * @param {string} url - API endpoint URL
     * @param {object} options - Fetch options
     * @returns {Promise<any>}
     */
    async fetchJSON(url, options = {}) {
        const response = await this.fetch(url, options);
        
        if (!response.ok) {
            const error = await response.json().catch(() => ({ error: 'Request failed' }));
            throw new Error(error.error || `HTTP ${response.status}`);
        }

        return response.json();
    }

    /**
     * Logout (clear session).
     */
    async logout() {
        try {
            await fetch('/api/auth/logout', {
                method: 'POST',
                credentials: 'same-origin'
            });
            this.userInfo = null;
            window.location.href = '/';
        } catch (error) {
            console.error('Logout failed:', error);
        }
    }
}

// Create a singleton instance
window.authClient = new AuthClient();
