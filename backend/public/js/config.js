/**
 * Config - Application configuration loader
 * 
 * Fetches and caches configuration values from the API
 */

// Global config cache
window.appConfig = window.appConfig || null;

/**
 * Load configuration from API
 * @returns {Promise<Object>} Configuration object
 */
async function loadConfig() {
    // Return cached config if available
    if (window.appConfig) {
        return window.appConfig;
    }

    try {
        const response = await fetch('/api/config');
        if (!response.ok) {
            throw new Error('Failed to load config');
        }
        
        const config = await response.json();
        
        // Cache the config
        window.appConfig = config;
        
        return config;
    } catch (error) {
        console.error('Failed to load config:', error);
        
        // Return default config as fallback
        const defaultConfig = {
            max_text_length: 140
        };
        
        window.appConfig = defaultConfig;
        return defaultConfig;
    }
}

/**
 * Get maximum text length from config
 * @returns {Promise<number>} Maximum text length
 */
async function getMaxTextLength() {
    const config = await loadConfig();
    return config.max_text_length || 140;
}

/**
 * Get config value synchronously (requires config to be preloaded)
 * @param {string} key - Config key
 * @param {*} defaultValue - Default value if not found
 * @returns {*} Config value
 */
function getConfigSync(key, defaultValue) {
    if (!window.appConfig) {
        console.warn('Config not loaded yet, returning default value');
        return defaultValue;
    }
    return window.appConfig[key] ?? defaultValue;
}

// Export functions
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadConfig,
        getMaxTextLength,
        getConfigSync
    };
}
