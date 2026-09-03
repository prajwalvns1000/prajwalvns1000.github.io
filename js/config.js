/**
 * Prajwal N Portfolio - Headless WordPress CMS Configuration
 * 
 * Automatically resolves to the local WordPress instance on port 8000 matching
 * the current host (localhost or 127.0.0.1), or can be overridden with a custom URL:
 * e.g., "http://portfolio.local/wp-json/wp/v2" for LocalWP or "http://localhost/wordpress/wp-json/wp/v2" for XAMPP.
 */
const DEFAULT_WP_HOST = (typeof window !== 'undefined' && window.location.hostname) ? window.location.hostname : 'localhost';

const CONFIG = {
    // Configurable WordPress REST API root endpoint
    WORDPRESS_API_URL: `http://${DEFAULT_WP_HOST}:8000/wp-json/wp/v2`,

    // REST API Sub-Endpoints
    PROJECTS_ENDPOINT: "/projects",
    EXPERIENCE_ENDPOINT: "/experience",

    // Network timeout in milliseconds
    REQUEST_TIMEOUT_MS: 5000
};
