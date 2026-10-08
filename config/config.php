<?php
/**
 * PeaceWatch - general settings
 */
define('APP_NAME', 'PeaceWatch');
define('APP_TIMEZONE', 'Africa/Lagos');

// 'auto' works for most setups (XAMPP, shared hosting, virtual host).
// If links are broken, set it by hand, e.g. '/peacewatch/public' or ''.
define('BASE_URL', 'auto');

define('ADMIN_REFRESH_SECONDS', 5);      // how often the admin dashboard checks for new reports
define('REPORTER_REFRESH_SECONDS', 10);  // how often the reporter's tracker refreshes
define('OVERDUE_MINUTES', 5);            // critical report not acknowledged after this long = OVERDUE
define('REPORT_COOLDOWN_SECONDS', 20);   // stops accidental double-taps creating duplicate reports
define('SESSION_DAYS', 365);             // reporters stay logged in this long

// Where the admin map opens when there are no reports yet (default: Enugu)
define('MAP_DEFAULT_LAT', 6.4584);
define('MAP_DEFAULT_LNG', 7.5464);
define('MAP_DEFAULT_ZOOM', 8);

define('DEBUG', false);                  // true shows PHP errors on screen (development only)
