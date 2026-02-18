# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AutoForum is a WordPress plugin (PHP 8.1+, WooCommerce 8.0+, WordPress 6.3+) providing a high-performance automotive forum with license management and HWID (Hardware ID) binding. It uses no Composer or NPM — all PHP and JS are written from scratch.

## Development Setup

There are no build steps. Deploy by placing the plugin directory in `wp-content/plugins/` and activating via the WordPress admin. The SPL autoloader in `autoforum-core.php` handles class loading automatically.

**Testing endpoints:** Use the WordPress admin AJAX handler at `wp-admin/admin-ajax.php` with `action=af_*` parameters and valid nonces. **Every** endpoint — reads and writes alike — requires a `nonce` POST param (see Nonce Infrastructure below).

**Database changes:** Increment `AF_DB_VERSION` in `autoforum-core.php` and add migration logic in `DB_Installer::maybe_update_schema()`. Use `dbDelta()` for safe schema changes — never write raw `CREATE TABLE` without it.

## Architecture

### PHP Backend

**Entry point:** `autoforum-core.php` — defines constants (`AF_VERSION`, `AF_DB_VERSION`, `AF_PLUGIN_DIR`), registers the SPL autoloader (maps `AutoForum\ClassName` → `includes/class-class-name.php`), and hooks `autoforum()` singleton factory to `plugins_loaded`.

**Class responsibilities:**
- `class-plugin.php` — Singleton orchestrator; instantiates and wires all subsystems in `__construct()`, calls `init_hooks()` on each
- `class-db-installer.php` — Schema definition for 7 custom tables (`wp_af_categories`, `wp_af_topics`, `wp_af_posts`, `wp_af_licenses`, `wp_af_thanks`, `wp_af_attachments`, `wp_af_reports`); handles migrations
- `class-forum-api.php` — All AJAX endpoints (20+), caching logic, content gating
- `class-auth-handler.php` — Login/register/logout/profile with rate limiting via transients
- `class-license-manager.php` — WooCommerce order lifecycle hooks, license key generation, HWID binding/resets
- `class-admin-panel.php` — WordPress admin menu and all admin AJAX handlers
- `class-shortcode.php` — `[auto_forum]` shortcode; renders the SPA container and auth modal markup
- `class-assets.php` — Conditional CSS/JS enqueuing; injects `AF_DATA` (nonce, AJAX URL, user state) as inline JS
- `class-utils.php` — Static helpers: IP detection, rate-limit checks (`Utils::check_rate_limit()`), BBCode→HTML parsing, time formatting

**Naming convention:** Class names are PascalCase (e.g., `License_Manager`), resolved by autoloader to `includes/class-license-manager.php`.

### Frontend SPA

A vanilla JS Single-Page Application rendered inside `<div id="af-app">`. No framework or bundler — modules communicate via global objects.

**Module responsibilities:**
- `app.js` — Entry point: initializes State, renders Header, boots Router, sets up 5-min heartbeat
- `api.js` — All backend communication; wraps `fetch()` to `admin-ajax.php`; handles nonces automatically
- `router.js` — Hash-based routing (`#home`, `#thread-list`, `#thread-view`, `#dashboard`, `#create-topic`, `#user-profile`); mounts view components, enforces auth-required routes
- `state.js` — LocalStorage-backed state (current user, view state)
- `config.js` — Constants: pagination limits, thread prefixes, sort options; reads `window.AF_DATA` injected by PHP

### Data Flow

Frontend → `api.js` → `admin-ajax.php?action=af_*` → `Forum_API` AJAX handler → `$wpdb` → MySQL

All AJAX handlers — including read-only ones — validate nonces with `check_ajax_referer()`, check capabilities with `current_user_can()`, use `$wpdb->prepare()` for queries, sanitize input with `sanitize_*()`, and escape output with `esc_*()` / `wp_kses()`.

### Content Gating

Topics with `is_premium = 1` require an active, non-expired license to view full post content. Users without a license can unlock posts via the "thank" mechanic (inserts a row in `wp_af_thanks`). The gate logic lives in `Forum_API::ajax_get_posts()`.

### Caching

Transient-based caching in `Forum_API`:
- Categories: 10-minute TTL (`af_categories_cache`)
- Topic lists: 2-minute TTL, invalidated on topic creation/deletion
- Column existence checks: 5-minute TTL (for safe DB migration guards)

### WooCommerce Integration

`License_Manager` hooks into:
- `woocommerce_order_status_completed` → generate license key + expiry
- `woocommerce_order_status_refunded` → revoke license
- WooCommerce Subscriptions actions → extend/suspend/revoke on renewal events

The WooCommerce product IDs that trigger license issuance are configured in `af_settings['woo_product_ids']`.

### User Metadata (wp_usermeta)

| Key | Purpose |
|-----|---------|
| `af_post_count` | Incremented on each post created |
| `af_reputation` | Incremented when user's post receives a "thank" |
| `af_location` | User-editable location field |
| `af_signature` | User-editable signature (shown under posts) |
| `_af_last_active` | Unix timestamp; used for online status |

### Plugin Settings

Stored in `wp_options` as `af_settings`. Key options: `forum_page_id`, `posts_per_page` (20), `threads_per_page` (25), `primary_color`, `hwid_reset_cooldown` (7 days), `max_hwid_resets` (3 lifetime cap), `license_duration` (365 days), `woo_product_ids` (array), `enable_rest_api`, `show_demo_data`.

## Nonce Infrastructure

Every AJAX endpoint has a corresponding nonce. The full lifecycle for any `af_*` action:

1. **PHP constant** — `private const NONCE_FOO = 'af_foo';` in the relevant handler class.
2. **Generation** — `'foo' => wp_create_nonce( 'af_foo' )` added to the `nonces` array in `class-assets.php` `enqueue_frontend()`.
3. **Consumption (JS)** — `nonce: _nonce( 'foo' )` passed in the `api.js` method's params object.
4. **Verification (PHP)** — `check_ajax_referer( self::NONCE_FOO, 'nonce' )` as the first line of the AJAX handler.

When adding a new endpoint, all four steps are required. Read-only endpoints are not exempt.

**Current nonce keys in `AF_DATA.nonces`:**
`login`, `register`, `getUserData`, `getCategories`, `getTopics`, `getPosts`, `viewTopic`, `getHomeStats`, `getUserProfile`, `createTopic`, `createPost`, `thankPost`, `search`, `deleteTopic`, `deletePost`, `editPost`, `uploadAttachment`, `heartbeat`

User-session nonces (`logout`, `profile`, `hwid_reset`) are generated per-request and returned inside the `user` object from `safe_user_data()` / `ajax_get_user_data()`, not baked into `AF_DATA.nonces` at page load.

## JS Module Separation Rules

These patterns are enforced across the codebase and must not be violated:

### No business logic in state.js
`state.js` holds only UI state (`currentUser`, `currentView`, `viewData`) and localStorage persistence. It must not contain server-side business logic. Specifically forbidden in state.js:
- HWID reset tracking (`canResetHWID`, `performHWIDReset`, `getRemainingResets`)
- Content-unlock tracking (`unlockedContent`, `unlockContent`, `isContentUnlocked`)
- Any computation that mirrors server rules

### No raw fetch() in views
All network calls go through `api.js`. Views never call `fetch()` directly.

### Logout flow
Always call `API.logout()` before clearing local state:
```js
try { await API.logout(); } catch (e) { /* clear state regardless */ }
State.setUser(null);
Header.render();
Router.navigateTo('home');
```

### HWID reset flow
Call `API.resetHwid(licenseId, nonce)` directly. The license `id` (not key) comes from the user object returned by `safe_user_data()` — the nonce comes from `user.nonces.hwid_reset`. Never replicate cooldown/cap logic client-side. The server enforces: per-user rate limit (5 attempts/hour), per-license cooldown (`hwid_reset_cooldown` days), and lifetime cap (`max_hwid_resets`).

### Heartbeat guards
The `DEMO_MODE` and auth guards for `API.pingActive()` live in `app.js`, not in `api.js`. `api.js` stays decoupled from `CONFIG` and `State`.

### No emojis in Toast messages
`Toast.success()`, `Toast.error()`, `Toast.info()`, `Toast.warning()` must not contain emoji characters.

### Modal tab navigation
Use `Modal.showAuthTab('register')` instead of calling `Modal.show()` followed by a `document.querySelector('[data-tab="register"]')?.click()` chain.

### Server-authoritative content gating
Never persist unlock state in `state.js`. After a successful unlock API call, re-render the view — the server response determines what is visible.
