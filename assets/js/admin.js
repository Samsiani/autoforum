/**
 * AutoForum — Admin JavaScript
 *
 * Handles:
 *  - WP Color Picker init for the primary color field.
 *  - AJAX "Reset HWID" action (table button).
 *  - AJAX "Revoke License" action (table button).
 *  - Live search for the license table (client-side debounce).
 *
 * All sensitive actions use nonces generated server-side (inline in the
 * admin table rows) to prevent CSRF.  AF_ADMIN object is localized by
 * Assets::enqueue_admin().
 */

/* global AF_ADMIN, jQuery */

( function ( $, afAdmin ) {
    'use strict';

    const ajaxUrl = afAdmin.ajaxUrl;
    const i18n    = afAdmin.i18n;

    $( function () {   // ← DOM ready

    // ── Color picker ───────────────────────────────────────────────────────────────────

    if ( typeof $.fn.wpColorPicker === 'function' ) {
        $( '.af-color-picker' ).wpColorPicker( {
            change: function ( _event, ui ) {
                $( '.af-color-preview' ).css( 'background', ui.color.toString() );
            }
        } );
    }

    // ── Helper: show spinner on a button ─────────────────────────────────────

    function showSpinner( $btn ) {
        $btn.prop( 'disabled', true ).append( '<span class="af-spinner"></span>' );
    }

    function hideSpinner( $btn ) {
        $btn.prop( 'disabled', false ).find( '.af-spinner' ).remove();
    }

    // ── Helper: inline notice ─────────────────────────────────────────────────

    function showNotice( $row, type, msg ) {
        const $notice = $row.find( '.af-notice-inline' );
        $notice
            .removeClass( 'success error' )
            .addClass( type )
            .text( msg );
        setTimeout( function () {
            $notice.removeClass( 'success error' );
        }, 4000 );
    }

    // ── Force HWID Reset ──────────────────────────────────────────────────────

    $( document ).on( 'click', '.af-force-reset', function ( e ) {
        e.preventDefault();

        if ( ! window.confirm( i18n.confirmHwidReset ) ) {
            return;
        }

        const $btn    = $( this );
        const $row    = $btn.closest( 'tr' );
        const licId   = $btn.data( 'license-id' );
        const nonce   = $btn.data( 'nonce' );

        showSpinner( $btn );

        $.post( ajaxUrl, {
            action:     'af_force_hwid_reset',
            license_id: licId,
            nonce:      nonce
        } )
        .done( function ( res ) {
            if ( res.success ) {
                // Clear the HWID cell and reset count.
                $row.find( '.af-hwid-cell' ).html( '<em>—</em>' );
                $row.find( '.af-reset-count' ).text( '0' );
                showNotice( $row, 'success', i18n.success );
            } else {
                showNotice( $row, 'error', res.data.message || i18n.error );
            }
        } )
        .fail( function () {
            showNotice( $row, 'error', i18n.error );
        } )
        .always( function () {
            hideSpinner( $btn );
        } );
    } );

    // ── Revoke License ────────────────────────────────────────────────────────

    $( document ).on( 'click', '.af-revoke-btn', function ( e ) {
        e.preventDefault();

        if ( ! window.confirm( i18n.confirmRevoke ) ) {
            return;
        }

        const $btn  = $( this );
        const $row  = $btn.closest( 'tr' );
        const licId = $btn.data( 'license-id' );
        const nonce = $btn.data( 'nonce' );

        showSpinner( $btn );

        $.post( ajaxUrl, {
            action:     'af_revoke_license',
            license_id: licId,
            nonce:      nonce
        } )
        .done( function ( res ) {
            if ( res.success ) {
                const $badge = $row.find( '.af-badge' );
                $badge
                    .removeClass( 'af-badge-active af-badge-warn af-badge-muted' )
                    .addClass( 'af-badge-danger' )
                    .text( 'Revoked' );
                $btn.remove();
                showNotice( $row, 'success', i18n.success );
            } else {
                showNotice( $row, 'error', res.data.message || i18n.error );
            }
        } )
        .fail( function () {
            showNotice( $row, 'error', i18n.error );
        } )
        .always( function () {
            hideSpinner( $btn );
        } );
    } );

    // ── Client-side table search (debounced) ──────────────────────────────────

    let searchTimer = null;

    $( '#af-table-search' ).on( 'input', function () {
        clearTimeout( searchTimer );
        const q = $( this ).val().toLowerCase().trim();

        searchTimer = setTimeout( function () {
            $( '.af-license-table tbody tr' ).each( function () {
                const text = $( this ).text().toLowerCase();
                $( this ).toggle( ! q || text.includes( q ) );
            } );
        }, 200 );
    } );

    // ── Topics: Lock / Unlock ─────────────────────────────────────────────────

    $( document ).on( 'click', '.af-btn-lock-topic', function ( e ) {
        e.preventDefault();
        const $btn   = $( this );
        const id     = $btn.data( 'id' );
        const nonce  = $btn.data( 'nonce' );
        showSpinner( $btn );
        $.post( ajaxUrl, { action: 'af_lock_topic', id: id, nonce: nonce } )
        .done( function ( res ) {
            if ( res.success ) {
                const locked = res.data.locked;
                $btn.data( 'locked', locked ).text( locked ? 'Unlock' : 'Lock' );
            } else {
                alert( ( res.data && res.data.message ) || 'Error.' );
            }
        } )
        .fail( function () { alert( 'Request failed.' ); } )
        .always( function () { hideSpinner( $btn ); } );
    } );

    // ── Topics: Pin / Unpin ───────────────────────────────────────────────────

    $( document ).on( 'click', '.af-btn-pin-topic', function ( e ) {
        e.preventDefault();
        const $btn   = $( this );
        const id     = $btn.data( 'id' );
        const nonce  = $btn.data( 'nonce' );
        showSpinner( $btn );
        $.post( ajaxUrl, { action: 'af_pin_topic', id: id, nonce: nonce } )
        .done( function ( res ) {
            if ( res.success ) {
                const sticky = res.data.sticky;
                $btn.data( 'sticky', sticky ).text( sticky ? 'Unpin' : 'Pin' );
            } else {
                alert( ( res.data && res.data.message ) || 'Error.' );
            }
        } )
        .fail( function () { alert( 'Request failed.' ); } )
        .always( function () { hideSpinner( $btn ); } );
    } );

    // ── Topics: Delete ────────────────────────────────────────────────────────

    $( document ).on( 'click', '.af-btn-delete-topic', function ( e ) {
        e.preventDefault();
        if ( ! window.confirm( 'Delete this topic and all its replies? This cannot be undone.' ) ) return;
        const $btn  = $( this );
        const $row  = $btn.closest( 'tr' );
        const id    = $btn.data( 'id' );
        const nonce = $btn.data( 'nonce' );
        showSpinner( $btn );
        $.post( ajaxUrl, { action: 'af_delete_topic', id: id, nonce: nonce } )
        .done( function ( res ) {
            if ( res.success ) {
                $row.fadeOut( 300, function () { $row.remove(); } );
            } else {
                alert( ( res.data && res.data.message ) || 'Error.' );
                hideSpinner( $btn );
            }
        } )
        .fail( function () { alert( 'Request failed.' ); hideSpinner( $btn ); } );
    } );

    // ── Icon Picker (inline panel) ────────────────────────────────────────────

    const icons   = ( typeof AF_ADMIN !== 'undefined' && AF_ADMIN.icons ) ? AF_ADMIN.icons : [];
    const $btn    = $( '#af-icon-picker-btn' );
    const $panel  = $( '#af-icon-panel' );
    const $grid   = $( '#af-icon-grid' );
    const $hidden = $( '#af_cat_icon' );
    const $preview= $( '#af-icon-preview' );
    const $label  = $( '#af-icon-label' );
    const $clear  = $( '#af-icon-clear' );
    const $chevron= $( '#af-icon-chevron' );

    if ( $btn.length && icons.length ) {

        function renderGrid( filter ) {
            filter = ( filter || '' ).toLowerCase().trim();
            const list = filter
                ? icons.filter( function (ic) { return ic.replace( /^fa-/, '' ).includes( filter ); } )
                : icons;
            const current = $hidden.val();

            if ( ! list.length ) {
                $grid.html( '<div class="af-icon-empty">No icons match \u201c' + filter + '\u201d.</div>' );
                return;
            }

            $grid.html( list.map( function ( ic ) {
                var sel  = ic === current ? ' selected' : '';
                var name = ic.replace( /^fa-/, '' );
                return '<div class="af-icon-item' + sel + '" data-icon="' + ic + '" title="' + ic + '">' +
                       '<i class="fa-solid ' + ic + '"></i>' +
                       '<span>' + name + '</span>' +
                       '</div>';
            } ).join( '' ) );

            $grid.find( '.af-icon-item' ).on( 'click', function () {
                var chosen = $( this ).data( 'icon' );
                $hidden.val( chosen );
                $preview.html( '<i class="fa-solid ' + chosen + '"></i>' );
                $label.text( chosen );
                $clear.show();
                closePanel();
            } );
        }

        function openPanel() {
            $panel.slideDown( 180 );
            $btn.addClass( 'open' );
            $chevron.css( 'transform', 'rotate(180deg)' );
            renderGrid( '' );
            $( '#af-icon-search' ).val( '' ).trigger( 'focus' );
        }

        function closePanel() {
            $panel.slideUp( 150 );
            $btn.removeClass( 'open' );
            $chevron.css( 'transform', '' );
        }

        $btn.on( 'click', function ( e ) {
            e.preventDefault();
            e.stopPropagation();
            if ( $panel.is( ':visible' ) ) {
                closePanel();
            } else {
                openPanel();
            }
        } );

        $( '#af-icon-search' ).on( 'input', function () {
            renderGrid( $( this ).val() );
        } );

        $clear.on( 'click', function ( e ) {
            e.preventDefault();
            $hidden.val( '' );
            $preview.html( '<i class="fa-solid fa-icons"></i>' );
            $label.text( 'Choose icon\u2026' );
            $clear.hide();
            closePanel();
        } );

        $( document ).on( 'click', function ( e ) {
            if ( $panel.is( ':visible' ) &&
                 ! $( e.target ).closest( '#af-icon-panel, #af-icon-picker-btn, #af-icon-clear' ).length ) {
                closePanel();
            }
        } );

        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) closePanel();
        } );
    }

    } ); // end DOM ready

} )( jQuery, AF_ADMIN );
