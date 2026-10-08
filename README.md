# PeaceWatch

Early-warning system for farm and community clashes. Reporters press one button on their phone and their GPS
location goes straight to the admin's live map. Built with plain PHP, MySQL, HTML and CSS. **There is no API:**
every page reads MySQL directly and every button is a normal form.

## Requirements
- PHP 7.4 or newer (PDO MySQL enabled), MySQL 5.7+ or MariaDB 10.3+
- Apache (XAMPP / WAMP / shared hosting all work)
- Internet access in the browser for the OpenStreetMap map tiles and the Leaflet library (see "Leaflet offline" below)

## Setup (about 5 minutes)
1. Copy the `peacewatch` folder into your web server (for XAMPP: `C:\xampp\htdocs\peacewatch`).
2. In phpMyAdmin create an empty database named `peacewatch` (collation `utf8mb4_unicode_ci`).
3. Select that database and **import `database/schema.sql`**, then **import `database/seed.sql`**.
4. Open `config/database.php` and set the host, database name, user and password.
5. Open `config/config.php` and check the timezone and map start position.
6. Visit `http://localhost/peacewatch/public/`
   (best practice on a real server: point the domain's document root at the `public` folder).

### First admin login
- Email: `admin@peacewatch.local`
- Password: `ChangeMe123!`
Log in and change the password straight away (**My account** in the top bar). The admin is sent to the
control room automatically; reporters register themselves at `register.php`.

## Important: GPS needs HTTPS
Browsers only give a website the phone's location over **HTTPS** (or on `localhost`). To test on a real phone you
need an SSL certificate (free from Let's Encrypt, or a tunnel such as ngrok while testing). Without HTTPS the
report is still sent, flagged "Location unavailable".

## What is where
```
config/        settings and database credentials
includes/      bootstrap, database connection, login checks, CSRF, helpers, page header/footer
src/           the database functions (reports, messages, notifications, users, audit)
public/        the only folder the web should expose
  dashboard.php        reporter home: REPORT button + live tracker + add details + post update
  history.php, notifications.php, profile.php, login.php, register.php
  admin/dashboard.php  live Leaflet map, live feed, alerts, filters
  admin/report.php     one case: status, severity, resolve, false alarm, messages, internal notes
  admin/users.php, user.php   reporter list and trust record, suspend / reinstate
  admin/quick_replies.php, audit_log.php
  assets/              css and js
database/      schema.sql, seed.sql
storage/       error log and long-lived login sessions
```

## How the live updating works (still no API)
`admin/dashboard.php` re-reads its own page every 5 seconds and swaps in the fresh feed and map markers,
so the map position and the sound permission are never lost. The reporter's tracker does the same every 10 seconds.
These intervals are in `config/config.php`.

## Alerts on the admin dashboard
- New report: sound, pop-up, flashing marker (critical reports get a stronger alarm)
- New update posted by a reporter (including when they add details or raise severity): sound and pop-up
- OVERDUE: a critical report still "Received" after 5 minutes shows a red banner and an alarm
- Browsers block sound until the page has been clicked once, so press **Turn on sound and desktop alerts**
  when you open the dashboard. Desktop notifications work while the tab is open, even in the background.

## Decisions I made where the plan was open
- **Suspended reporter:** can still log in and read their history; cannot send reports or updates.
- **Outcome note:** the reporter can read it on a resolved report. To hide it, remove the "Outcome" blocks in
  `public/dashboard.php` and `public/history.php`.
- **Resolved reports** stay on the reporter's home screen for 24 hours so they see the final result.
- **Double-tap guard:** a second report within 20 seconds is not created; the person is shown the first one.
- **Web push** (alerts with the browser closed) is not included, because it needs a server endpoint, which
  conflicts with the no-API rule. The in-page alerts above replace it for the prototype.

## Leaflet offline (optional)
By default the map library loads from the unpkg CDN. To host it yourself, download Leaflet 1.9.4 and copy
`leaflet.js`, `leaflet.css` and its `images` folder into `public/assets/vendor/leaflet/`. The app uses the local
copy automatically when it finds it. Map tiles still need internet.

## Security built in
Passwords hashed (bcrypt), prepared statements for every query, CSRF token on every form, output escaped,
admin pages locked to the admin role, config/includes/src/database/storage blocked from the web by `.htaccess`,
session cookies HttpOnly with SameSite.
