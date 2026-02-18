<?php
/**
 * AutoForum — Main Forum Template
 *
 * Loaded by the [auto_forum] shortcode via ob_start() / ob_get_clean().
 * This file must NOT output a full <html> document — WordPress provides
 * the theme wrapper. It outputs only the forum container that the SPA
 * mounts into, plus the auth modal markup.
 *
 * All dynamic PHP values are escaped before output.
 * JavaScript payload is injected via wp_localize_script() in Assets::enqueue_frontend(),
 * so no raw PHP data is embedded here.
 *
 * @package AutoForum
 */

defined( 'ABSPATH' ) || exit;

$settings    = get_option( 'af_settings', [] );
$primary     = esc_attr( sanitize_hex_color( $settings['primary_color'] ?? '#3b82f6' ) );
?>

<!-- AutoForum SPA Root ───────────────────────────────────────────────── -->
<div id="af-app" class="af-forum-root" style="--primary:<?php echo $primary; ?>">

    <!-- News Ticker — hydrated by ticker.js; PHP below is a no-JS fallback only -->
    <div class="af-ticker" id="af-ticker" role="marquee" aria-label="<?php esc_attr_e( 'News ticker', 'autoforum' ); ?>" aria-live="off">
        <div class="af-ticker-label" id="af-ticker-label">
            <i class="fa-solid fa-bolt"></i>
            <span><?php esc_html_e( 'NEWS', 'autoforum' ); ?></span>
        </div>
        <div class="af-ticker-separator"></div>
        <div class="af-ticker-viewport">
            <div class="af-ticker-track" id="af-ticker-track">
                <!-- Items injected by ticker.js -->
                <span class="af-ticker-item">
                    <i class="fa-solid fa-fire"></i>
                    <?php esc_html_e( 'Welcome to the forum — your automotive community.', 'autoforum' ); ?>
                </span>
            </div>
        </div>
        <div class="af-ticker-controls">
            <button class="af-ticker-btn" id="af-ticker-pause" type="button" aria-label="<?php esc_attr_e( 'Pause ticker', 'autoforum' ); ?>">
                <i class="fa-solid fa-pause"></i>
            </button>
        </div>
    </div>

    <!-- App header (rendered + hydrated by header.js) -->
    <header id="main-header" aria-label="<?php esc_attr_e( 'Site header', 'autoforum' ); ?>"></header>

    <!-- SPA page mount point (router.js swaps content here) -->
    <main id="main-container" role="main" aria-live="polite"></main>

    <!-- Auth Modal ──────────────────────────────────────────────────────── -->
    <div
        class="af-modal-overlay"
        id="auth-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-modal-title"
    >
        <div class="af-modal-box">

            <!-- Close — div to avoid Woodmart button style bleed -->
            <div class="af-modal-close" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Close', 'autoforum' ); ?>">
                <svg viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 1L9 9M9 1L1 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </div>

            <!-- Brand mark -->
            <div class="af-modal-brand">
                <i class="fa-solid fa-gauge-high"></i>
                <span>EsyTuner</span>
            </div>

            <!-- Tab switcher — divs to avoid Woodmart button style bleed -->
            <div class="af-modal-tabs" role="tablist">
                <div class="af-modal-tab active" data-tab="login" role="tab" tabindex="0" aria-selected="true" aria-controls="tab-login">
                    <?php esc_html_e( 'Sign In', 'autoforum' ); ?>
                </div>
                <div class="af-modal-tab" data-tab="register" role="tab" tabindex="0" aria-selected="false" aria-controls="tab-register">
                    <?php esc_html_e( 'Register', 'autoforum' ); ?>
                </div>
                <div class="af-modal-tab-indicator" aria-hidden="true"></div>
            </div>

            <!-- Login tab -->
            <div class="af-modal-pane active" id="tab-login" role="tabpanel">
                <p class="af-modal-sub"><?php esc_html_e( 'Welcome back — sign in to continue.', 'autoforum' ); ?></p>

                <form id="login-form" novalidate>
                    <?php wp_nonce_field( 'af_login', '_af_login_nonce' ); ?>

                    <div class="af-field">
                        <label class="af-label" for="login-username"><?php esc_html_e( 'Username', 'autoforum' ); ?></label>
                        <div class="af-input-wrap">
                            <i class="fa-solid fa-user af-input-icon"></i>
                            <input type="text" id="login-username" name="username" class="af-input" autocomplete="username" placeholder="your_username" required>
                        </div>
                    </div>

                    <div class="af-field">
                        <label class="af-label" for="login-password"><?php esc_html_e( 'Password', 'autoforum' ); ?></label>
                        <div class="af-input-wrap af-input-wrap--pw">
                            <i class="fa-solid fa-lock af-input-icon"></i>
                            <input type="password" id="login-password" name="password" class="af-input" autocomplete="current-password" placeholder="••••••••" required>
                            <div class="af-eye-btn" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'autoforum' ); ?>">
                                <i class="fa-solid fa-eye"></i>
                            </div>
                        </div>
                    </div>

                    <div class="af-field af-field-row">
                        <label class="af-check-label">
                            <input type="checkbox" name="remember" value="1" class="af-check">
                            <span class="af-check-box"></span>
                            <?php esc_html_e( 'Keep me signed in', 'autoforum' ); ?>
                        </label>
                        <a class="af-forgot" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
                            <?php esc_html_e( 'Forgot password?', 'autoforum' ); ?>
                        </a>
                    </div>

                    <div class="af-form-error" id="login-error" role="alert" aria-live="assertive"></div>

                    <button type="submit" class="af-btn-submit" id="login-submit">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <?php esc_html_e( 'Sign In', 'autoforum' ); ?>
                    </button>
                </form>
            </div><!-- /tab-login -->

            <!-- Register tab -->
            <div class="af-modal-pane" id="tab-register" role="tabpanel">
                <p class="af-modal-sub"><?php esc_html_e( 'Create a free account to join the discussion.', 'autoforum' ); ?></p>

                <form id="register-form" novalidate>
                    <?php wp_nonce_field( 'af_register', '_af_register_nonce' ); ?>

                    <div class="af-field">
                        <label class="af-label" for="reg-username"><?php esc_html_e( 'Username', 'autoforum' ); ?></label>
                        <div class="af-input-wrap">
                            <i class="fa-solid fa-user af-input-icon"></i>
                            <input type="text" id="reg-username" name="username" class="af-input" autocomplete="username" placeholder="choose_a_username" required>
                        </div>
                    </div>

                    <div class="af-field">
                        <label class="af-label" for="reg-email"><?php esc_html_e( 'Email', 'autoforum' ); ?></label>
                        <div class="af-input-wrap">
                            <i class="fa-solid fa-envelope af-input-icon"></i>
                            <input type="email" id="reg-email" name="email" class="af-input" autocomplete="email" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="af-field">
                        <label class="af-label" for="reg-password"><?php esc_html_e( 'Password', 'autoforum' ); ?></label>
                        <div class="af-input-wrap af-input-wrap--pw">
                            <i class="fa-solid fa-lock af-input-icon"></i>
                            <input type="password" id="reg-password" name="password" class="af-input" autocomplete="new-password" placeholder="Min. 8 characters" minlength="8" required>
                            <div class="af-eye-btn" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'autoforum' ); ?>">
                                <i class="fa-solid fa-eye"></i>
                            </div>
                        </div>
                        <!-- Password strength bar -->
                        <div class="af-strength-bar" id="reg-strength-bar">
                            <div class="af-strength-fill" id="reg-strength-fill"></div>
                        </div>
                        <span class="af-strength-label" id="reg-strength-label"></span>
                    </div>

                    <div class="af-form-error" id="register-error" role="alert" aria-live="assertive"></div>

                    <button type="submit" class="af-btn-submit" id="register-submit">
                        <i class="fa-solid fa-user-plus"></i>
                        <?php esc_html_e( 'Create Account', 'autoforum' ); ?>
                    </button>
                </form>
            </div><!-- /tab-register -->

        </div><!-- /.af-modal-box -->
    </div><!-- /.af-modal-overlay #auth-modal -->

    <!-- Toast container (managed by toast.js) -->
    <div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="true"></div>

</div><!-- /#af-app -->
