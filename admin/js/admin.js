( function ( $ ) {
	'use strict';

	/**
	 * Binds media uploader buttons.
	 */
	function bindMediaButtons() {
		$( document ).on( 'click', '.pdmiuc-media-button', function ( event ) {
			event.preventDefault();

			const $button = $( this );
			const targetId = $button.data( 'target' );
			const $target = $( '#' + targetId );

			if ( ! targetId || ! $target.length ) {
				return;
			}

			let frame = $button.data( 'frameInstance' );

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
				const attachment = frame.state().get( 'selection' ).first().toJSON();

				if ( attachment && attachment.url ) {
					$target.val( attachment.url ).trigger( 'change' );
				}
			} );

			$button.data( 'frameInstance', frame );
			frame.open();
		} );
	}

	$( bindMediaButtons );
}( jQuery ) );

