# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AutoForum is a WordPress plugin (PHP 8.1+, WooCommerce 8.0+, WordPress 6.3+) providing a high-performance automotive forum with license management and HWID (Hardware ID) binding. It uses no Composer or NPM — all PHP and JS are written from scratch.

## Development Setup

There are no build steps. Deploy by placing the plugin directory in `wp-content/plugins/` and activating via the WordPress admin. The SPL autoloader in `autoforum-core.php` handles class loading automatically.

**Testing endpoints:** Use the WordPress admin AJAX handler at `wp-admin/admin-ajax.php` with `action=af_*` parameters and valid nonces. All mutations require `X-WP-Nonce` (or `_wpnonce` POST param).

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

All AJAX handlers validate nonces with `check_ajax_referer()`, check capabilities with `current_user_can()`, use `$wpdb->prepare()` for queries, sanitize input with `sanitize_*()`, and escape output with `esc_*()` / `wp_kses()`.

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

Stored in `wp_options` as `af_settings`. Key options: `forum_page_id`, `posts_per_page` (20), `threads_per_page` (25), `primary_color`, `hwid_reset_cooldown` (7 days), `license_duration` (365 days), `woo_product_ids` (array), `enable_rest_api`, `show_demo_data`.
