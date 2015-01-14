/* globals confirm, wp_stream, ajaxurl, wp_stream_regenerate_alt_rows */
jQuery( function( $ ) {

	var initSettingsSelect2 = function() {
		$( '.stream-exclude-list tr:not(.hidden) input[type=hidden].select2-select.with-source' ).each( function( k, el ) {
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

		var $input_user, $input_ip;

		$( '.stream-exclude-list tr:not(.hidden) input[type=hidden].select2-select.ip_address' ).each( function( k, el ) {
			$input_ip = $( el );

			$input_ip.select2({
				ajax: {
					type: 'POST',
					url: ajaxurl,
					dataType: 'json',
					quietMillis: 500,
					data: function( term ) {
						return {
							find: term,
							limit: 10,
							action: 'stream_get_ips',
							nonce: $input_ip.data( 'nonce' )
						};
					},
					results: function( response ) {
						var answer = { results: [] };

						if ( true !== response.success || undefined === response.data ) {
							return answer;
						}

						$.each( response.data, function( key, ip ) {
							answer.results.push({
								id: ip,
								text: ip
							});
						});

						return answer;
					}
				},
				initSelection: function( item, callback ) {
					var data = [];

					data.push( { id: item.val(), text: item.val() } );

					callback( data );
				},
				createSearchChoice: function( term ) {
					var ip_chunks = [];

					ip_chunks = term.match( /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/ );

					if ( null === ip_chunks ) {
						return;
					}

					// remove whole match
					ip_chunks.shift();

					ip_chunks = $.grep(
						ip_chunks,
						function( chunk ) {
							var numeric = parseInt( chunk, 10 );
							return numeric <= 255 && numeric.toString() === chunk;
						}
					);

					if ( ip_chunks.length < 4 ) {
						return;
					}

					return {
						id: term,
						text: term
					};
				},
				allowClear: true,
				multiple: true,
				maximumSelectionSize: 1,
				placeholder: $input_ip.data( 'placeholder' )
			});
		}).on( 'change', function() {
			$( this ).prev( '.select2-container' ).find( 'input.select2-input' ).blur();
		});

		$( '.stream-exclude-list tr:not(.hidden) input[type=hidden].select2-select.author_or_role' ).each( function( k, el ) {
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
							nonce: $input_user.data( 'nonce' )
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

						// Notice we return the value of more so Select2 knows if more results can be loaded
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
				formatSelection: function( object ) {
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

		$( 'ul.select2-choices, ul.select2-choices li, input.select2-input', '.stream-exclude-list tr:not(.hidden) .ip_address' ).on( 'mousedown click focus', function() {
			var $container = $( this ).closest( '.select2-container' ),
				$input     = $container.find( 'input.select2-input' ),
				value      = $container.select2( 'data' );

			if ( value.length >= 1 ) {
				$input.blur();
				return false;
			}
		});

		$( '.stream-exclude-list tr:not(.hidden) input[type=hidden].select2-select.context' ).on( 'change', function( val ) {
			var $connector = $( this ).prevAll( ':input.connector' );

			if ( undefined !== val.added && undefined !== val.added.parent ) {
				$connector.val( val.added.parent );
			} else {
				$connector.val( $( this ).val() );
				$( this ).val( '' );
			}
		});

		$( '.stream-exclude-list tr:not(.hidden) .exclude_rules_remove_rule_row' ).on( 'click', function() {
			var $thisRow = $( this ).closest( 'tr' );

			$thisRow.remove();

			recalculate_rules_found();
			recalculate_rules_selected();
		});

	};

	initSettingsSelect2();

	$( '#exclude_rules_new_rule' ).on( 'click', function() {
		var $excludeList = $( 'table.stream-exclude-list' );

		$( '.select2-select', $excludeList ).each( function() {
			$( this ).select2( 'destroy' );
		});

		var $lastRow = $( 'tr', $excludeList ).last(),
			$newRow  = $lastRow.clone();

		$newRow.removeAttr( 'class' );
		$( '.stream-exclude-list tbody :input' ).off();
		$( ':input', $newRow ).off().val( '' );

		$lastRow.after( $newRow );

		initSettingsSelect2();

		recalculate_rules_found();
		recalculate_rules_selected();
	});

	$( '#exclude_rules_remove_rules' ).on( 'click', function() {
		var $excludeList = $( 'table.stream-exclude-list' ),
			selectedRows = $( 'tbody input.cb-select:checked', $excludeList ).closest( 'tr' );

		if ( ( $( 'tbody tr', $excludeList ).length - selectedRows.length ) >= 2 ) {
			selectedRows.remove();
		} else {
			$( ':input', selectedRows ).val( '' );
			$( selectedRows ).not( ':first' ).remove();
			$( '.select2-select', selectedRows ).select2( 'val', '' );
		}

		$excludeList.find( 'input.cb-select' ).prop( 'checked', false );

		recalculate_rules_found();
		recalculate_rules_selected();
	});

	$( '.stream-exclude-list' ).closest( 'form' ).submit( function() {
		$( '.stream-exclude-list tbody tr', this ).each( function() {
			if ( 0 === $( this ).find( ':input[value][value!=""]' ).length ) {
				// Don't send inputs in this row
				$( this ).find( ':input[value]' ).removeAttr( 'name' );
			}
		});
	});

	$( '.stream-exclude-list' ).closest( 'td' ).prev( 'th' ).hide();

	$( 'table.stream-exclude-list' ).on( 'click', 'input.cb-select', function() {
		recalculate_rules_selected();
	});

	function recalculate_rules_selected() {
		var $selectedRows = $( 'table.stream-exclude-list tbody tr:not( .hidden ) input.cb-select:checked' ),
			$deleteButton = $( '#exclude_rules_remove_rules' );

		if ( 0 === $selectedRows.length ) {
			$deleteButton.prop( 'disabled', true );
		} else {
			$deleteButton.prop( 'disabled', false );
		}
	}

	function recalculate_rules_found() {
		var $allRows      = $( 'table.stream-exclude-list tbody tr:not( .hidden )' ),
			$noRulesFound = $( 'table.stream-exclude-list tbody tr.no-items' ),
			$selectAll    = $( '.check-column.manage-column input.cb-select' ),
			$deleteButton = $( '#exclude_rules_remove_rules' );

		if ( 0 === $allRows.length ) {
			$noRulesFound.show();
			$selectAll.prop( 'disabled', true );
			$deleteButton.prop( 'disabled', true );
		} else {
			$noRulesFound.hide();
			$selectAll.prop( 'disabled', false );
		}

		wp_stream_regenerate_alt_rows( $allRows );
	}

	$( document ).ready( function() {
		recalculate_rules_found();
		recalculate_rules_selected();
	});

	// Confirmation on some important actions
	$( '#wp_stream_general_reset_site_settings' ).click( function( e ) {
		if ( ! confirm( wp_stream.i18n.confirm_defaults ) ) {
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
