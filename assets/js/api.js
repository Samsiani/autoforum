/**
 * api.js — AutoForum API Service
 *
 * Single module that handles ALL communication with the WordPress backend.
 * Every other JS file should import data through this module, never through
 * raw fetch() calls scattered across views.
 *
 * In WordPress context:  AF_DATA is injected by wp_localize_script().
 * In standalone demo:    Falls back to CONFIG values so the SPA still works.
 *
 * Conventions:
 *  - All methods return Promises that resolve with parsed response data.
 *  - On API error the Promise rejects with { message, code }.
 *  - AJAX actions go to admin-ajax.php (POST).
 *  - REST endpoints go to /wp-json/af/v1/ (GET/POST).
 */

const API = ( () => {
    'use strict';

    // ── Configuration (from wp_localize_script or CONFIG fallback) ─────────────

    function _cfg() {
        return typeof AF_DATA !== 'undefined' ? AF_DATA : null;
    }

    function _ajaxUrl() {
        return _cfg()?.ajaxUrl ?? '/wp-admin/admin-ajax.php';
    }

    function _restUrl() {
        return _cfg()?.restUrl ?? '/wp-json/af/v1/';
    }

    function _nonce( key ) {
        return _cfg()?.nonces?.[ key ] ?? '';
    }

    function _restNonce() {
        return _cfg()?.restNonce ?? '';
    }

    // ── Low-level fetch helpers ────────────────────────────────────────────────

    /**
     * POST to admin-ajax.php.
     * @param {string} action  WP AJAX action name.
     * @param {Object} data    Extra POST fields.
     * @returns {Promise<any>} Resolves with res.data on success, rejects on error.
     */
    async function _ajax( action, data = {} ) {
        const body = new URLSearchParams( { action, ...data } );
        const res  = await fetch( _ajaxUrl(), {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        body.toString(),
        } );

        if ( ! res.ok ) {
            // Try to parse structured error from body (e.g. premium_required 403).
            const errJson = await res.json().catch( () => null );
            if ( errJson && ! errJson.success && errJson.data ) {
                throw errJson.data;
            }
            throw { message: `HTTP ${ res.status }`, code: 'http_error' };
        }

        const json = await res.json();
        if ( ! json.success ) {
            throw json.data ?? { message: 'Unknown error', code: 'unknown' };
        }
        return json.data;
    }

    /**
     * GET request to the WP REST API.
     * @param {string} endpoint  Path relative to /wp-json/af/v1/.
     * @param {Object} params    Query string parameters.
     */
    async function _rest_get( endpoint, params = {} ) {
        const url = new URL( _restUrl() + endpoint );
        Object.entries( params ).forEach( ( [ k, v ] ) => url.searchParams.set( k, v ) );

        const res = await fetch( url.toString(), {
            method:      'GET',
            credentials: 'same-origin',
            headers:     {
                'X-WP-Nonce': _restNonce(),
                'Accept':     'application/json',
            },
        } );

        if ( ! res.ok ) {
            const err = await res.json().catch( () => ( {} ) );
            throw { message: err.message ?? `HTTP ${ res.status }`, code: err.code ?? 'http_error' };
        }
        return res.json();
    }

    /**
     * POST request to the WP REST API.
     */
    async function _rest_post( endpoint, body = {} ) {
        const res = await fetch( _restUrl() + endpoint, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     {
                'Content-Type': 'application/json',
                'X-WP-Nonce':  _restNonce(),
            },
            body: JSON.stringify( body ),
        } );

        if ( ! res.ok ) {
            const err = await res.json().catch( () => ( {} ) );
            throw { message: err.message ?? `HTTP ${ res.status }`, code: err.code ?? 'http_error' };
        }
        return res.json();
    }

    // ── Auth ───────────────────────────────────────────────────────────────────

    async function login( username, password, remember = false ) {
        return _ajax( 'af_login', {
            nonce:    _nonce( 'login' ),
            username,
            password,
            remember: remember ? '1' : '',
        } );
    }

    async function register( username, email, password ) {
        return _ajax( 'af_register', {
            nonce:    _nonce( 'register' ),
            username,
            email,
            password,
        } );
    }

    async function logout() {
        // Logout nonce lives on the user object (generated per-session server-side)
        const nonce = _cfg()?.currentUser?.nonces?.logout ?? _nonce( 'logout' ) ?? '';
        return _ajax( 'af_logout', { nonce } );
    }

    async function getUserData() {
        return _ajax( 'af_get_user_data', { nonce: _nonce( 'getUserData' ) } );
    }

    async function updateProfile( fields ) {
        return _ajax( 'af_update_profile', {
            nonce: _nonce( 'profile' ),
            ...fields,
        } );
    }

    // ── Forum: Read ────────────────────────────────────────────────────────────

    async function getCategories() {
        return _ajax( 'af_get_categories' );
    }

    async function getTopics( { categoryId = 0, page = 1, sort = 'latest' } = {} ) {
        return _ajax( 'af_get_topics', {
            category_id: categoryId,
            page,
            sort,
        } );
    }

    async function getPosts( { topicId, page = 1 } ) {
        return _ajax( 'af_get_posts', { topic_id: topicId, page } );
    }

    async function search( query ) {
        return _ajax( 'af_search', { nonce: _nonce( 'search' ), q: query } );
    }

    // ── Forum: Write ───────────────────────────────────────────────────────────

    async function viewTopic( topicId ) {
        // Fire-and-forget — we don't await this in views.
        return _ajax( 'af_view_topic', { topic_id: topicId } );
    }

    async function createTopic( { categoryId, title, prefix, content, locked = false, lockContent = false } ) {
        return _ajax( 'af_create_topic', {
            nonce:        _nonce( 'createTopic' ),
            category_id:  categoryId,
            title,
            prefix,
            content,
            locked:       locked ? '1' : '',
            lock_content: lockContent ? '1' : '',
        } );
    }

    async function createPost( { topicId, content } ) {
        return _ajax( 'af_create_post', {
            nonce:    _nonce( 'createPost' ),
            topic_id: topicId,
            content,
        } );
    }

    async function thankPost( postId ) {
        return _ajax( 'af_thank_post', {
            nonce:   _nonce( 'thankPost' ),
            post_id: postId,
        } );
    }

    async function deleteTopic( topicId ) {
        return _ajax( 'af_delete_topic', {
            nonce:    _nonce( 'deleteTopic' ),
            topic_id: topicId,
        } );
    }

    async function deletePost( postId ) {
        return _ajax( 'af_delete_post', {
            nonce:   _nonce( 'deletePost' ),
            post_id: postId,
        } );
    }

    async function editPost( postId, content ) {
        return _ajax( 'af_edit_post', {
            nonce:   _nonce( 'editPost' ),
            post_id: postId,
            content,
        } );
    }

    async function reportPost( postId ) {
        return _ajax( 'af_report_post', {
            nonce:   _nonce( 'reportPost' ),
            post_id: postId,
        } );
    }

    // ── Licenses (REST API) ────────────────────────────────────────────────────

    async function validateLicense( { key, hwid } ) {
        return _rest_post( 'licenses/validate', { key, hwid } );
    }

    async function getLicenseInfo() {
        return _rest_get( 'license-info' );
    }

    async function resetHwid( licenseId, nonce ) {
        return _ajax( 'af_user_reset_hwid', {
            nonce:      nonce,
            license_id: licenseId,
        } );
    }

    async function uploadAttachment( file, postId = 0 ) {
        const formData = new FormData();
        formData.append( 'action', 'af_upload_attachment' );
        formData.append( 'nonce',  _nonce( 'uploadAttachment' ) );
        formData.append( 'file',   file );
        formData.append( 'post_id', postId );

        const res = await fetch( _ajaxUrl(), {
            method:      'POST',
            credentials: 'same-origin',
            body:        formData, // Do NOT set Content-Type — browser sets it with the boundary.
        } );

        if ( ! res.ok ) {
            throw { message: `HTTP ${ res.status }`, code: 'http_error' };
        }
        const json = await res.json();
        if ( ! json.success ) {
            throw json.data ?? { message: 'Upload failed', code: 'upload_error' };
        }
        return json.data;
    }

    async function getHomeStats() {
        return _ajax( 'af_get_home_stats', {} );
    }

    function pingActive() {
        _ajax( 'af_heartbeat', { nonce: _nonce( 'heartbeat' ) } ).catch( () => {} );
    }

    async function getUserProfile( userId ) {
        return _ajax( 'af_get_user_profile', { user_id: userId } );
    }

    // ── Public API ─────────────────────────────────────────────────────────

    return {
        // Auth
        login,
        register,
        logout,
        getUserData,
        updateProfile,
        // Forum reads
        getCategories,
        getTopics,
        getPosts,
        search,
        viewTopic,
        getHomeStats,
        pingActive,
        getUserProfile,
        // Forum writes
        createTopic,
        createPost,
        thankPost,
        deleteTopic,
        deletePost,
        editPost,
        reportPost,
        // Licenses
        validateLicense,
        getLicenseInfo,
        resetHwid,
        // Attachments
        uploadAttachment,
    };
} )();
