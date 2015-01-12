/* globals stream_notifications, ajaxurl, _, alert, confirm */
jQuery( function( $ ) {
	'use strict';

	_.templateSettings.variable = 'vars';

	$( '#toplevel_page_wp_stream' )
		.add( '#toplevel_page_wp_stream > a.toplevel_page_wp_stream' )
		.removeClass( 'wp-not-current-submenu' )
		.addClass( 'wp-has-current-submenu wp-menu-open' );

	$.datepicker.setDefaults({
		dateFormat: 'yy/mm/dd',
		minDate: 0
	});

	var types = stream_notifications.types,

		divTriggers = $( '#stream-notifications-triggers' ), // Trigger Playground
		divAlerts   = $( '#stream-notifications-alerts .inside' ), // Alerts Playground

		iGroup = 0,

		btns = {
			add_trigger: '.add-trigger',
			add_alert: '.add-alert',
			add_group: '.add-trigger-group',
			del: '#delete-trigger'
		},

		tmpl               = _.template( $( 'script#trigger-template-row' ).html() ),
		tmpl_options       = _.template( $( 'script#trigger-template-options' ).html() ),
		tmpl_group         = _.template( $( 'script#trigger-template-group' ).html() ),
		tmpl_alert         = _.template( $( 'script#alert-template-row' ).html() ),
		tmpl_alert_options = _.template( $( 'script#alert-template-options' ).html() ),

		select2_format = function( item ) {
			return item.text;
		},

		select2_args = {
			allowClear: true,
			minimumResultsForSearch: 8,
			width: '160px',
			format: select2_format,
			formatSelection: select2_format,
			formatResult: select2_format,

			// Only allow multi items if we have a proper operator
			maximumSelectionSize: function() {
				var item = $( '.select2-container-active.trigger-value' );
				if ( ! item.size() ) {
					return 0;
				}

				var operator = item.parents( '.form-row' ).first().find( 'select.trigger-operator' ).val();
				if ( ! operator ) {
					return 0;
				}

				if ( operator.match(/in$/) ) {
					return 0;
				} else {
					return 1;
				}
			}

		},

		datify = function( elements ) {
			$( elements ).each( function() {
				$( this ).datepicker();
			});

			$( '#ui-datepicker-div' ).addClass( 'stream-datepicker' );
		},

		selectify = function( elements, args ) {
			args = args || {};
			$.extend( args, select2_args );

			$( elements ).filter( ':not(.select2-offscreen)' ).each( function() {
				var $this = $( this ),
					elementArgs = jQuery.extend( {}, args ),
					tORa = $this.closest( '#stream-notifications-alerts, #stream-notifications-triggers' ).attr( 'id' ).replace( 'stream-notifications-', '' );

				elementArgs.width = parseInt( $this.css( 'width' ), 10 ) + 30;
				if ( $this.hasClass( 'ip' ) ) {
					elementArgs.ajax = {
						type: 'POST',
						url: ajaxurl,
						dataType: 'json',
						quietMillis: 500,
						data: function( term ) {
							return {
								find:   term,
								limit:  10,
								action: 'stream_get_ips'
							};
						},
						results: function( response ) {
							var answer = {
								results: []
							};

							if ( true !== response.success || undefined === response.data ) {
								return answer;
							}

							$.each(response.data, function( key, ip ) {
								answer.results.push({
									id:   ip,
									text: ip
								});
							});

							return answer;
						}
					},
					elementArgs.initSelection = function( item, callback ) {
						callback( item.data( 'selected' ) );
					},
					elementArgs.formatNoMatches = function() {
						return '';
					},
					elementArgs.createSearchChoice = function( term ) {
						var ip_chunks = [];

						ip_chunks = term.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);

						if ( null === ip_chunks ) {
							return;
						}

						// remove whole match
						ip_chunks.shift();

						ip_chunks = $.grep(
							ip_chunks,
							function( chunk ) {
								var numeric = parseInt(chunk, 10);

								return numeric <= 255 && numeric.toString() === chunk;
							}
						);

						if (ip_chunks.length < 4) {
							return;
						}

						return {
							id:   term,
							text: term
						};
					};
				} else if ( $this.hasClass( 'ajax' ) ) {
					var type = '';
					if ( ! ( type = $this.data( 'ajax-key' ) ) ) {
						if ( 'triggers' === tORa ) {
							type = $this.parents( '.form-row' ).first().find( 'select.trigger-type' ).val();
						} else {
							type = $this.parents( '.form-row' ).eq(1).find( 'select.alert-type' ).val();
						}
					}
					elementArgs.minimumInputLength = 3;
					elementArgs.ajax = {
						url: ajaxurl,
						type: 'post',
						dataType: 'json',
						data: function( term ) {
							return {
								action: 'stream_notification_endpoint',
								type: type,
								q: term,
								args: $( this ).attr( 'data-args' )
							};
						},
						results: function( data ) {
							var r = data.data || [];
							return {results: r};
						}
					};
					elementArgs.initSelection = function( element, callback ) {
						var id = $(element).val();
						if ( '' !== id ) {
							$.ajax({
								url: ajaxurl,
								type: 'post',
								data: {
									action: 'stream_notification_endpoint',
									q: id,
									single: 1,
									type: type,
									args: $( this ).attr( 'data-args' )
								},
								dataType: 'json'
							}).done( function( data ) { callback( data.data ); } );
						}
					};
					elementArgs.formatResult = function( object ) {
						var result = object.text;

						if ( object.hasOwnProperty( 'avatar' ) ) {
							result = object.avatar + result;
						}

						return result;
					};
					elementArgs.formatSelection = function( object ) {
						var result = object.text;

						if ( object.hasOwnProperty( 'avatar' ) ) {
							result += '<i class="icon16 icon-users"></i>';
						}

						return result;
					};
				}

				$this.select2( elementArgs );
				$this.on( 'select2_populate', function( e, val ) {
					var $this = $( this );
					if ( ! val ) {
						return;
					}
					if ( $this.hasClass( 'ajax' ) ) {
						$.ajax({
							url: ajaxurl,
							type: 'post',
							data: {
								action: 'stream_notification_endpoint',
								q: val,
								single: 1,
								type: type,
								args: $( this ).attr( 'data-args' )
							},
							dataType: 'json',
							success: function( j ) {
								$this.select2( 'data', j.data );
							}
						});
					} else if ( $this.hasClass( 'tags' ) ) {
						$this.select2( 'data', $.map(
							val.split( ',' ),
							function( ip ) {
								return { id: ip, text: ip };
							}
						) );
					} else {
						$this.select2( 'val', val.split( ',' ) );
					}
				} );
			});
		},

		add_trigger = function( group_index ) {
			var index    = 0,
				lastItem   = null,
				group    = divTriggers.find( '.group[rel=' + group_index + ']' ),
				i          = null,
				type       = null,
				types      = {},
				connectors = {}
			;

			if ( ( lastItem = divTriggers.find( '.trigger' ).last() ) && lastItem.size() ) {
				index = parseInt( lastItem.attr( 'rel' ), 10 ) + 1;
			}

			// Get adjacent trigger[type=connector] to filter special trigger types
			connectors = group.find( 'select.trigger-type option:selected[value=connector]' );
			connectors = connectors.map( function() {
				return $( this ).parents( '.trigger' ).first().find( ':input.trigger-value' ).val();
			}).toArray();
			if ( connectors.length ) {
				for ( i in stream_notifications.special_types ) {
					type = stream_notifications.special_types[i];
					if ( -1 !== connectors.indexOf( type.connector ) ) {
						types[i] = type;
					}
				}
			}

			group.append( tmpl( $.extend(
				{ index: index, group: group_index },
				stream_notifications,
				{ types: $.extend( {}, stream_notifications.types, types ) }
			) ) );
			group.find( '.trigger' ).first().addClass( 'first' );
			selectify( group.find( 'select' ) );
		},

		add_alert = function() {
			var index = divAlerts.find( '.alert' ).size();

			divAlerts.append( tmpl_alert( $.extend(
				{ index: index },
				stream_notifications
			) ) );
			selectify( divAlerts.find( '.alert select' ) );
		},

		display_error = function( key ) {
			if ( $( '.error' ).filter( function() { return $( this ).attr( 'data-key' ) === key; } ).length === 0 ) {
				$( 'body,html' ).scrollTop(0);
				$( '.wrap > h2' )
					.after(
						$( '<div></div>' )
							.addClass( 'updated error fade' )
							.attr( 'data-key', key )
							.hide()
							.append(
								$( '<p></p>' ).text(stream_notifications.i18n[key])
							)
					)
					.next( '.updated' )
					.fadeIn( 'normal' )
					.delay( 3000 )
					.fadeOut( 'normal', function() { $( this ).remove(); } );
			}
		};

	divTriggers
		// Add new rule
		.on( 'click.sn', btns.add_trigger, function( e ) {
			e.preventDefault();

			add_trigger( $( this ).data( 'group' ) );
		})

		// Add new group
		.on( 'click.sn', btns.add_group, function( e, groupIndex ) {
			e.preventDefault();
			var $this = $( this ),
				parentGroupIndex = $this.data( 'group' ),
				group = divTriggers.find( '.group[rel=' + $this.data( 'group' ) + ']' );

			if ( ! groupIndex ) {
				groupIndex = ++iGroup;
			}

			group.append( tmpl_group( { index: groupIndex, parent: parentGroupIndex } ) );
			selectify( group.find( '.field.relation select' ) );
		})

		// Delete a trigger
		.on( 'click.sn', '.delete-trigger', function( e ) {
			e.preventDefault();
			var $this  = $( this );

			$this.closest( '.trigger' ).remove();

			// add `first` class in case the first trigger was removed
			$this.closest( '.group' ).find( '.trigger' ).first().addClass( 'first' );
		})

		// Delete a group
		.on( 'click.sn', '.delete-group', function( e ) {
			e.preventDefault();
			var $this = $( this );

			$this.parents( '.group' ).first().remove();
		})

		// Reveal rule options after choosing rule type
		.on( 'change.sn', '.trigger-type', function() {
			var $this   = $( this ),
				options = null,
				index   = $this.parents( '.trigger' ).first().attr( 'rel' );

			if ( ( 'undefined' !== typeof types[ $this.val() ] ) ) {
				options = types[ $this.val() ];
			} else {
				options = stream_notifications.special_types[ $this.val() ];
			}

			$this.next( '.trigger-options' ).remove();

			if ( ! options ) { return; }

			$this.after( tmpl_options( $.extend( options, { index: index } ) ) );
			selectify( $this.parent().find( 'select' ) );
			selectify( $this.parent().find( 'input.tags, input.ajax' ), { tags: [] } );
			datify( $this.parent().find( '.type-date' ) );
		})
	;

	divAlerts
		// Add new alert
		.on( 'click.sn', btns.add_alert, function( e ) {
			e.preventDefault();
			add_alert();
			$( 'html, body' ).animate({
				scrollTop: divAlerts.find( '.alert' ).last().offset().top
			}, 400);
		})

		// Reveal alert options after choosing alert type
		.on( 'change.sn', '.alert-type', function() {
			var $this    = $( this ),
				$wrapper = $this.closest( '.alert' ),
				$alert   = {},
				$copy    = {},
				options  = stream_notifications.adapters[ $this.val() ],
				type     = $this.val(),
				index    = $wrapper.attr( 'rel' );

			$wrapper.find( '.alert-options' ).hide();

			if ( ! options ) { return; }

			$copy = $wrapper
				.find( '.alert-options' )
				.filter( function() {
					return $( this ).attr( 'data-type' ) === type;
				});
				$wrapper.find( '.alert-options' ).hide();

			if( 0 === $copy.length ) { // render new alert template
				$alert = $( tmpl_alert_options( $.extend( options, { type: type, index: index  } ) ) );
				$alert.appendTo( $wrapper );
				selectify( $alert.find( 'select' ) );
				selectify( $alert.find( 'input.tags, input.ajax' ), { tags: [] } );
			} else { // copy found, just show it
				$copy.show();
			}
		})

		// Delete an alert
		.on( 'click.sn', '.delete-alert', function( e ) {
			e.preventDefault();
			var $this = $( this );

			$this.parents( '.alert' ).first().remove();

			$( '.alert .circle' ).each( function( index ) {
				$( this ).text(index + 1);
			});
		})
	;

	// Populate form values if it exists
	if ( 'undefined' !== typeof stream_notifications.meta  ) {

		// Triggers
		jQuery.each( stream_notifications.meta.triggers, function( i, trigger ) {
			var groupDiv = divTriggers.find( '.group' ).filter( '[rel='+trigger.group+']' ),
				row,
				valueField;

			// create the group if it doesn't exist
			if ( ! groupDiv.size() ) {
				var group = stream_notifications.meta.groups[trigger.group];
				$( btns.add_group ).filter( '[data-group='+group.group+']' ).trigger( 'click', trigger.group);
				groupDiv = divTriggers.find( '.group' ).filter( '[rel='+trigger.group+']' );
				groupDiv.find( 'select.group-relation' ).select2( 'val', group.relation );
			}

			// create the new row, by clicking the add-trigger button in the appropriate group
			divTriggers.find( btns.add_trigger ).filter( '[data-group='+trigger.group+']' ).trigger( 'click' );
			// debugger; # DEBUG

			// populate values
			row = groupDiv.find( '.trigger:last' );
			row.find( 'select.trigger-relation' ).select2( 'val', trigger.relation ).trigger( 'change' );
			row.find( 'select.trigger-type' ).select2( 'val', trigger.type ).trigger( 'change' );
			row.find( 'select.trigger-operator' ).select2( 'val', trigger.operator ).trigger( 'change' );

			// populate the trigger value, according to the trigger type
			if ( trigger.value ) {
				valueField = row.find( '.trigger-value:not(.select2-container)' ).eq(0);
				if ( valueField.is( 'select' ) || valueField.is( '.ajax, .ip' ) ) {
					valueField.trigger( 'select2_populate', trigger.value );
					// valueField.select2( 'val', trigger.value ).trigger( 'change' );
				} else {
					valueField.val( trigger.value ).trigger( 'change' );
				}
			}
		} );

		// Alerts
		jQuery.each( stream_notifications.meta.alerts, function( i, alert ) {
			var row,
				optionFields;

			// create the new row, by clicking the add-alert button
			add_alert();

			// populate values
			row = divAlerts.find( '.alert:last' );
			row.find( 'select.alert-type' ).select2( 'val', alert.type ).trigger( 'change' );
			optionFields = row.find( '.alert-options' );
			optionFields.find( ':input[name]' ).each( function( i, el ) {
				var $this = $(el),
					name,
					val;
				name = $this.attr( 'name' ).match(/\[([a-z_\-]+)\]$/)[1];
				if ( 'undefined' !== typeof alert[name] ) {
					val = alert[name];
					if ( $this.hasClass( 'select2-offscreen' ) ) {
						$this.trigger( 'select2_populate', val );
						// $this.select2( 'val', val ).trigger( 'change' );
					} else {
						$this.val( val ).trigger( 'change' );
					}
				}
			});
		});
	}

	$( '#rule-form' ).submit( function() {
		// Do not submit if no triggers exist
		if ( divTriggers.find( '.trigger' ).size() < 1 ) {
			display_error( 'empty_triggers' );
			return false;
		}

		// Do not submit if no working triggers exist
		if ( null === $( '.trigger-type:first' ).select2( 'data' ) ) {
			display_error( 'invalid_first_trigger' );
			return false;
		}

		$( '.alert-options:hidden' ).remove();
	});

	divAlerts
		.on( 'click', '.toggler', function( e ) {
			e.preventDefault();
			var rel = this.rel,
				toggled = $( rel ),
				toggler = $( this )
				;

			if ( ! toggled.is( ':visible' ) ) {
				toggled.slideDown( 'fast' );
				toggler.data( 'text', toggler.text() );
				toggler.text( toggler.data( 'text-toggle' ) );
			} else {
				toggled.slideUp( 'fast' );
				toggler.text( toggler.data( 'text' ) );
			}
		} );

	// Autofocus for earlier browsers
	$( '[autofocus]' ).focus();

	// Reset occurrences link
	$( 'a.reset-occ' ).click( function( e ) {
		e.preventDefault();

		if ( ! confirm( stream_notifications.i18n.confirm_reset ) ) {
			return;
		}

		$.getJSON( this.href, {}, function( j ) {
			var div = $( '.submitbox .occurrences strong' );
			if ( j.success ) {
				div.html( div.html().replace(/\d+/, 0) );
			} else {
				alert( stream_notifications.i18n.ajax_error );
			}
		} );
	});

	// Add empty trigger if no triggers are visible
	if ( 0 ===  $( '.trigger' ).length ) {
		add_trigger( 0 );
	}

});
