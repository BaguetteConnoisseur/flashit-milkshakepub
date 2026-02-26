// Cookie Check Script for Flashit Milkshake Pub

/**
 * Check if cookies are enabled in the browser
 */
function cookiesEnabled() {
    try {
        document.cookie = 'cookietest=1';
        const cookiesEnabled = document.cookie.indexOf('cookietest=') !== -1;
        document.cookie = 'cookietest=1; expires=Thu, 01-Jan-1970 00:00:01 GMT';
        return cookiesEnabled;
    } catch (e) {
        return false;
    }
}

/**
 * Display a warning if cookies are disabled
 */
function checkCookieSupport() {
    if (!cookiesEnabled()) {
        const warning = document.createElement('div');
        warning.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #ff9800;
            color: white;
            padding: 1rem;
            text-align: center;
            z-index: 9999;
            font-family: system-ui, -apple-system, sans-serif;
        `;
        warning.innerHTML = `
            <strong>⚠️ Cookies Disabled</strong><br>
            This application requires cookies to function properly. Please enable cookies in your browser settings.
        `;
        document.body.insertBefore(warning, document.body.firstChild);
    }
}

// Run check when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkCookieSupport);
} else {
    checkCookieSupport();
}
