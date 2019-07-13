/* globals jQuery, wp_stream */
jQuery(
	function( $ ) {
		var network_affix;
		if ( 'wp_stream_network' === $( 'input[name="option_page"]' ).val() ) {
			network_affix = '_network_affix';
		} else {
			network_affix = '';
		}
		var keepRecordsIndefinitely = $( '#wp_stream' + network_affix + '\\[general_keep_records_indefinitely\\]' ),
			keepRecordsFor = $( '#wp_stream' + network_affix + '_general_records_ttl' ),
			keepRecordsForRow = keepRecordsFor.closest( 'tr' );

		function toggleKeepRecordsFor() {
			if ( keepRecordsIndefinitely.is( ':checked' ) ) {
				keepRecordsForRow.addClass( 'hidden' );
				keepRecordsFor.addClass( 'hidden' );
			} else {
				keepRecordsForRow.removeClass( 'hidden' );
				keepRecordsFor.removeClass( 'hidden' );
			}
		}

		keepRecordsIndefinitely.on(
			'change', function() {
				toggleKeepRecordsFor();
			}
		);

		toggleKeepRecordsFor();

		// Confirmation on some important actions
		$( '#wp_stream_general_reset_site_settings' ).click(
			function( e ) {
				if ( ! confirm( wp_stream.i18n.confirm_defaults ) ) {
					e.preventDefault();
				}
			}
		);

		// Settings page tabs
		var $tabs = $( '.nav-tab-wrapper' ),
			$panels = $( '.nav-tab-content table.form-table' ),
			$activeTab = $tabs.find( '.nav-tab-active' ),
			defaultIndex = $activeTab.length > 0 ? $tabs.find( 'a' ).index( $activeTab ) : 0,
			hashIndexStart = window.location.hash.match( /^#(\d+)$/ ),
			currentHash = ( null !== hashIndexStart ? hashIndexStart[ 1 ] : defaultIndex ),
			syncFormAction = function( index ) {
				var $optionsForm = $( 'input[name="option_page"][value^="wp_stream"]' ).closest( 'form' );
				if ( $optionsForm.length === 0 ) {
					return;
				}
				var currentAction = $optionsForm.attr( 'action' );

				$optionsForm.prop( 'action', currentAction.replace( /(^[^#]*).*$/, '$1#' + index ) );
			};

		$tabs.on(
			'click', 'a', function() {
				var index = $tabs.find( 'a' ).index( $( this ) ),
					hashIndex = window.location.hash.match( /^#(\d+)$/ );

				$panels.hide().eq( index ).show();

				$tabs
					.find( 'a' )
					.removeClass( 'nav-tab-active' )
					.filter( $( this ) )
					.addClass( 'nav-tab-active' );

				if ( '' === window.location.hash || null !== hashIndex ) {
					window.location.hash = index;
				}

				syncFormAction( index );

				return false;
			}
		);

		$tabs.children().eq( currentHash ).trigger( 'click' );
	}
);
