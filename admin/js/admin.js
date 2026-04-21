/* global pdmiucAdmin, wp */
( function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Media library uploader
	// -------------------------------------------------------------------------

	function bindMediaButtons() {
		$( document ).on( 'click', '.pdmiuc-media-button', function ( event ) {
			event.preventDefault();

			var $button = $( this );
			var targetId = $button.data( 'target' );
			var $target = $( '#' + targetId );

			if ( ! targetId || ! $target.length ) {
				return;
			}

			var frame = $button.data( 'frameInstance' );

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: $button.data( 'title' ) || 'Selecteer afbeelding',
				button: {
					text: $button.data( 'buttonText' ) || 'Gebruik afbeelding',
				},
				library: {
					type: 'image',
				},
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();

				if ( attachment && attachment.url ) {
					$target.val( attachment.url ).trigger( 'change' );
				}
			} );

			$button.data( 'frameInstance', frame );
			frame.open();
		} );
	}

	// -------------------------------------------------------------------------
	// IPv4 detection
	// Localised data is passed from PHP via wp_localize_script as pdmiucAdmin.
	// -------------------------------------------------------------------------

	function initIpv4Detection() {
		var $display = $( '#pdmiuc-ipv4-display' );
		var $btn     = $( '#pdmiuc-add-ipv4' );
		var $textarea = $( '#pdmiuc_allowed_ips' );

		if ( ! $display.length ) {
			return;
		}

		var i18n = pdmiucAdmin.i18n;

		$display.text( i18n.fetching );

		// If the server already sees an IPv4 address there is no need to fetch.
		if ( pdmiucAdmin.currentIpv4 === pdmiucAdmin.currentIpv6 && isIpv4( pdmiucAdmin.currentIpv4 ) ) {
			$display.text( pdmiucAdmin.currentIpv4 );
			showAddButton( $btn, $textarea, pdmiucAdmin.currentIpv4, i18n );
			return;
		}

		// Fetch IPv4 via an IPv4-only endpoint.
		fetch( pdmiucAdmin.ipv4ApiUrl )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Network response was not ok' );
				}
				return response.text();
			} )
			.then( function ( ip ) {
				ip = ip.trim();
				if ( ! isIpv4( ip ) ) {
					throw new Error( 'Not a valid IPv4' );
				}
				$display.text( ip );
				showAddButton( $btn, $textarea, ip, i18n );
			} )
			.catch( function () {
				$display.text( i18n.unavailable );
			} );
	}

	function isIpv4( ip ) {
		return /^(\d{1,3}\.){3}\d{1,3}$/.test( ip );
	}

	function showAddButton( $btn, $textarea, ip, i18n ) {
		$btn.text( i18n.addToWhitelist ).show();

		$btn.on( 'click', function () {
			var current = $textarea.val().trim();

			if ( -1 === current.indexOf( ip ) ) {
				$textarea.val( current ? current + ', ' + ip : ip );
			}

			$btn.prop( 'disabled', true ).text( i18n.added );
		} );
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	$( function () {
		bindMediaButtons();
		initIpv4Detection();
	} );
}( jQuery ) );
