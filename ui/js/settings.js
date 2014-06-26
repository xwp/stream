/* globals confirm, wp_stream, ajaxurl */
jQuery(function( $ ) {

	var initSettingsSelect2 = function() {
		$( '#tab-content-settings input[type=hidden].select2-select.with-source' ).each(function( k, el ) {
			var $input = $( el ),
				$connector = $( this ).prevAll( ':input.connector' );

			$input.select2({
				data: $input.data( 'values' ),
				allowClear: true,
				placeholder: $input.data( 'placeholder' )
			});

			if ( '' === $input.val() && '' !== $connector.val() ) {
				$input.select2( 'val', $connector.val() );
				$input.val( '' );
			}
		});

		var $input_user;
		$( '#tab-content-settings input[type=hidden].select2-select.author_or_role' ).each(function( k, el ) {
			$input_user = $( el );

			$input_user.select2({
				ajax: {
					type: 'POST',
					url: ajaxurl,
					dataType: 'json',
					quietMillis: 500,
					data: function( term, page ) {
						return {
							find: term,
							limit: 10,
							pager: page,
							action: 'stream_get_users',
							nonce: $input_user.data('nonce')
						};
					},
					results: function( response ) {
						var roles  = [],
							answer = [];

						roles = $.grep(
							$input_user.data( 'values' ),
							function( role ) {
								var roleVal = $input_user.data( 'select2' )
									.search
									.val()
									.toLowerCase();
								var rolePos = role
									.text
									.toLowerCase()
									.indexOf( roleVal );
								return rolePos >= 0;
							}
						);

						answer = {
							results: [
								{
									text: 'Roles',
									children: roles
								},
								{
									text: 'Users',
									children: []
								}
							]
						};

						if ( true !== response.success || undefined === response.data || true !== response.data.status ) {
							return answer;
						}
						$.each( response.data.users, function( k, user ) {
							if ( $.contains( roles, user.id ) ) {
								user.disabled = true;
							}
						});
						answer.results[ 1 ].children = response.data.users;
						// notice we return the value of more so Select2 knows if more results can be loaded
						return answer;
					}
				},
				initSelection: function( item, callback ) {
					callback( { id: item.data( 'selected-id' ), text: item.data( 'selected-text' ) } );
				},
				formatResult: function( object, container ) {
					var result = object.text;

					if ( 'undefined' !== typeof object.icon && object.icon ) {
						result = '<img src="' + object.icon + '" class="wp-stream-select2-icon">' + result;
						// Add more info to the container
						container.attr( 'title', object.tooltip );
					}
					// Add more info to the container
					if ( 'undefined' !== typeof object.tooltip ) {
						container.attr( 'title', object.tooltip );
					} else if ( 'undefined' !== typeof object.user_count ) {
						container.attr( 'title', object.user_count );
					}
					return result;
				},
				formatSelection: function( object ){
					if ( $.isNumeric( object.id ) && object.text.indexOf( 'icon-users' ) < 0 ) {
						object.text += '<i class="icon16 icon-users"></i>';
					}

					return object.text;
				},
				allowClear: true,
				placeholder: $input_user.data( 'placeholder' )
			}).on( 'change', function() {
				var value = $( this ).select2( 'data' );
				$( this ).data( 'selected-id', value.id );
				$( this ).data( 'selected-text', value.text );
			});
		});

		$( '#tab-content-settings input[type=hidden].select2-select.context' ).on( 'change', function( val ) {
			var $connector = $( this ).prevAll( ':input.connector' );

			if ( undefined !== val.added.parent ) {
				$connector.val( val.added.parent );
			} else {
				$connector.val( $( this ).val() );
				$( this ).val( '' );
			}
		});

		$( '#tab-content-settings input.ip_address' ).on( 'change', function() {
			var ipv4Expression = /^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/,
				ipv6Expression = /^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$/,
				ip = $( this ).val();
			if ( ! ipv4Expression.test( ip ) && ! ipv6Expression.test( ip ) && '' !== ip ) {
				$( this ).addClass( 'invalid' );
			} else {
				$( this ).removeClass( 'invalid' );
			}
		}).trigger( 'change' );

	};
	initSettingsSelect2();

	$( '#exclude_rules_new_rule' ).on( 'click', function() {
		var $excludeList = $( 'table.stream-exclude-list' );

		$( '.select2-select', $excludeList ).each( function(){
			$( this ).select2( 'destroy' );
		});

		var $lastRow = $( 'tr', $excludeList ).last(),
			$newRow  = $lastRow.clone();

		$newRow.toggleClass( 'alternate' ).removeAttr( 'class' );
		$( ':input', $newRow ).off().val( '' );

		$lastRow.after( $newRow );
		initSettingsSelect2();

		recalculate_rules_found();
	});

	$( '#exclude_rules_remove_rules' ).on( 'click', function() {
		var $excludeList = $( 'table.stream-exclude-list' ),
			selectedRows = $( 'tbody input.cb-select:checked', $excludeList ).closest( 'tr' );

		if ( ( $( 'tbody tr', $excludeList ).length - selectedRows.length ) >= 2 ) {
			selectedRows.remove();
			$( 'tbody tr', $excludeList ).removeClass( 'alternate' );
			$( 'tbody tr:even', $excludeList ).addClass( 'alternate' );
		} else {
			$( ':input', selectedRows ).val( '' );
			$( selectedRows ).not( ':first' ).remove();
			$( '.select2-select', selectedRows ).select2( 'val', '' );
		}

		$excludeList.find( 'input.cb-select' ).prop( 'checked', false );

		recalculate_rules_found();
	});

	$( '.exclude_rules_remove_rule_row' ).on( 'click', function() {
		var $excludeList = $( 'table.stream-exclude-list' ),
			$thisRow     = $( this ).closest( 'tr' );

		$thisRow.remove();

		$( 'tbody tr', $excludeList ).removeClass( 'alternate' );
		$( 'tbody tr:even', $excludeList ).addClass( 'alternate' );

		recalculate_rules_found();
	});

	$( '.stream-exclude-list' ).closest( 'form' ).submit( function() {
		if ( $( ':input.invalid', this ).length > 0 ) {
			$( ':input.invalid', this ).first().focus();
			return false;
		}
		$( '.stream-exclude-list tbody tr', this ).each( function() {
			if ( 0 === $( this ).find( ':input[value][value!=""]' ).length ) {
				// Don't send inputs in this row
				$( this ).find( ':input[value]' ).removeAttr( 'name' );
			}
		});
	});
	function recalculate_rules_found() {
		var $allRows     = $( 'table.stream-exclude-list tbody tr:not( .hidden )' ),
			$noRulesFound = $( 'table.stream-exclude-list tbody tr.no-items' );

		if ( 0 === $allRows.length ) {
			$noRulesFound.show();
		} else {
			$noRulesFound.hide();
		}
	}

	$( window ).load(function() {
		recalculate_rules_found();
	});

	// Confirmation on some important actions
	$( '#wp_stream_general_delete_all_records, #wp_stream_network_general_delete_all_records' ).click(function( e ) {
		if ( ! confirm( wp_stream.i18n.confirm_purge ) ) {
			e.preventDefault();
		}
	});

	$( '#wp_stream_general_reset_site_settings, #wp_stream_network_general_reset_site_settings' ).click(function( e ) {
		if ( ! confirm( wp_stream.i18n.confirm_defaults ) ) {
			e.preventDefault();
		}
	});

	$( '#wp_stream_uninstall' ).click(function( e ) {
		if ( ! confirm( wp_stream.i18n.confirm_uninstall ) ) {
			e.preventDefault();
		}
	});

	// Settings page tabs
	var $tabs          = $( '.nav-tab-wrapper' ),
		$panels        = $( '.nav-tab-content table.form-table' ),
		$activeTab     = $tabs.find( '.nav-tab-active' ),
		defaultIndex   = $activeTab.length > 0 ? $tabs.find( 'a' ).index( $activeTab ) : 0,
		hashIndex      = window.location.hash.match( /^#(\d+)$/ ),
		currentHash    = ( null !== hashIndex ? hashIndex[ 1 ] : defaultIndex ),
		syncFormAction = function( index ) {
			var $optionsForm  = $( 'input[name="option_page"][value^="wp_stream"]' ).closest( 'form' );
			var currentAction = $optionsForm.attr( 'action' );

			$optionsForm.prop( 'action', currentAction.replace( /(^[^#]*).*$/, '$1#' + index ) );
		};

	$tabs.on( 'click', 'a', function() {
		var index     = $tabs.find( 'a' ).index( $( this ) ),
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
	});
	$tabs.children().eq( currentHash ).trigger( 'click' );

});
