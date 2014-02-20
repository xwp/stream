/* globals stream_notifications, ajaxurl, triggers */
jQuery(function($){
	'use strict';

	_.templateSettings.variable = 'vars';

	$.datepicker.setDefaults({
		dateFormat: 'yy/mm/dd',
		minDate: 0
	});

	var types = stream_notifications.types,
		i,

		divTriggers = $('#triggers'), // Trigger Playground
		divAlerts   = $('#alerts .inside'), // Alerts Playground

		iGroup      = 0,

		btns = {
			add_trigger: '.add-trigger',
			add_alert: '.add-alert',
			add_group: '.add-trigger-group',
			del: '#delete-trigger'
		},

		tmpl               = _.template( $('script#trigger-template-row').html() ),
		tmpl_options       = _.template( $('script#trigger-template-options').html() ),
		tmpl_group         = _.template( $('script#trigger-template-group').html() ),
		tmpl_alert         = _.template( $('script#alert-template-row').html() ),
		tmpl_alert_options = _.template( $('script#alert-template-options').html() ),

		select2_format = function( item ) {
			var text = item.text;
			if ( typeof item.avatar !== 'undefined' ) {
				text = item.avatar + item.text;
			}
			return text;
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
				var item = $('.select2-container-active.trigger-value');
				if ( ! item.size() ) {
					return 0;
				}

				var operator = item.parents('.form-row').first().find('select.trigger-operator').val();
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
				$(this).datepicker();
			});
			$( '#ui-datepicker-div' ).addClass( 'stream-datepicker' );
		},

		selectify = function( elements, args ) {
			args = args || {};
			$.extend( args, select2_args );

			$(elements).filter(':not(.select2-offscreen)').each( function() {
				var $this = $(this),
					elementArgs = jQuery.extend( {}, args ),
					tORa = $this.closest('#alerts, #triggers').attr('id')
				;
				elementArgs.width = parseInt( $this.css('width'), 10 ) + 30;
				if ( $this.hasClass('ajax') ) {
					var type = '';
					if ( ! ( type = $this.data( 'ajax-key' ) ) ) {
						if ( tORa === 'triggers' ) {
							type = $this.parents('.form-row').first().find('select.trigger-type').val();
						} else {
							type = $this.parents('.form-row').eq(1).find('select.alert-type').val();
						}
					}
					elementArgs.minimumInputLength = 3;
					elementArgs.ajax = {
						url: ajaxurl,
						type: 'post',
						dataType: 'json',
						data: function (term) {
							return {
								action: 'stream_notification_endpoint',
								type: type,
								q: term,
								args: $(this).attr( 'data-args' )
							};
						},
						results: function (data) {
							var r = data.data || [];
							return {results: r};
						}
					};
					elementArgs.initSelection = function(element, callback) {
						var id = $(element).val();
						if ( id !== '' ) {
							$.ajax({
								url: ajaxurl,
								type: 'post',
								data: {
									action: 'stream_notification_endpoint',
									q     : id,
									single: 1,
									type  : type,
									args: $(this).attr( 'data-args' )
								},
								dataType: 'json'
							}).done( function( data ) { callback( data.data ); } );
						}
					};
				}

				$this.select2( elementArgs );
				$this.on( 'select2_populate', function( e, val ) {
					var $this = $(this);
					if ( ! val ) {
						return;
					}
					if ( $this.hasClass('ajax') ) {
						$.ajax({
			            	url: ajaxurl,
			            	type: 'post',
			                data: {
			                	action: 'stream_notification_endpoint',
			                    q : val,
			                    single: 1,
			                    type  : type,
								args: $(this).attr("data-args")
			                },
			                dataType: "json",
			                success: function(j){
			                	$this.select2( 'data', j.data );
			                }
		            	})
					} else if ( $this.hasClass('tags') ) {
						$this.select2( 'data', [{ id: val, text: val }] );
					} else {
						$this.select2( 'val', val );
					}
				} );
			});
		},

		add_trigger = function (group_index) {
			var index    = 0,
				lastItem = null,
				group    = divTriggers.find('.group[rel=' + group_index + ']' );

			if ( ( lastItem = divTriggers.find('.trigger').last() ) && lastItem.size() ) {
				index = parseInt( lastItem.attr('rel') ) + 1;
			}

			group.append( tmpl( $.extend(
				{ index: index, group: group_index },
				stream_notifications
			) ) );
			group.find('.trigger').first().addClass('first');
			selectify( group.find('select') );
		};

	divTriggers
		// Add new rule
		.on( 'click.sn', btns.add_trigger, function(e) {
			e.preventDefault();

			add_trigger($(this).data('group'));
		})

		// Add new group
		.on( 'click.sn', btns.add_group, function(e, groupIndex) {
			e.preventDefault();
			var $this = $(this),
				lastItem = null,
				parentGroupIndex = $this.data('group'),
				group = divTriggers.find('.group[rel=' + $this.data('group') + ']' );

			if ( ! groupIndex ) {
				groupIndex = ++iGroup;
			}

			group.append( tmpl_group({ index: groupIndex, parent: parentGroupIndex }) );
			selectify( group.find('.field.relation select') );
		})

		// Delete a trigger
		.on( 'click.sn', '.delete-trigger', function(e) {
			e.preventDefault();
			var $this = $(this);

			$this.parents('.trigger').first().remove();
		})

		// Delete a group
		.on( 'click.sn', '.delete-group', function(e) {
			e.preventDefault();
			var $this = $(this);

			$this.parents('.group').first().remove();
		})

		// Reveal rule options after choosing rule type
		.on( 'change.sn', '.trigger-type', function() {
			var $this   = $(this),
				options = types[ $this.val() ],
				index   = $this.parents('.trigger').first().attr('rel');
			$this.next('.trigger-options').remove();

			if ( ! options ) { return; }

			$this.after( tmpl_options( $.extend( options, { index: index } ) ) );
			selectify( $this.parent().find('select') );
			selectify( $this.parent().find('input.tags, input.ajax'), { tags: [] } );
			datify( $this.parent().find('.type-date') );
		})
	;

	divAlerts
		// Add new alert
		.on( 'click.sn', btns.add_alert, function(e) {
			e.preventDefault();
			var $this = $(this),
				index = divAlerts.find('.alert').size();

			divAlerts.append( tmpl_alert( $.extend(
				{ index: index },
				stream_notifications
			) ) );
			selectify( divAlerts.find('.alert select') );
		})

		// Reveal rule options after choosing rule type
		.on( 'change.sn', '.alert-type', function() {
			var $this   = $(this),
				options = stream_notifications.adapters[ $this.val() ],
				index   = $this.parents('.alert').first().attr('rel');
			$this.parent().next('.alert-options').remove();

			if ( ! options ) { return; }

			$this.parent().after( tmpl_alert_options( $.extend( options, { index: index  } ) ) );
			selectify( $this.parent().next().find('select') );
			selectify( $this.parent().next().find('input.tags, input.ajax'), { tags: [] } );
		})

		// Delete an alert
		.on( 'click.sn', '.delete-alert', function(e) {
			e.preventDefault();
			var $this = $(this);

			$this.parents('.alert').first().remove();

			$( '.alert .circle' ).each(function (index) {
				$(this).text(index + 1);
			});
		})
	;

	// Populate form values if it exists
	if ( typeof notification_rule !== 'undefined'  ) {

		// Triggers
		jQuery.each( notification_rule.triggers, function(i, trigger) {
			var groupDiv = divTriggers.find('.group').filter('[rel='+trigger.group+']'),
				row,
				valueField;

			// create the group if it doesn't exist
			if ( ! groupDiv.size() ) {
				var group = notification_rule.groups[trigger.group];
				$( btns.add_group ).filter('[data-group='+group.group+']').trigger('click', trigger.group);
				groupDiv = divTriggers.find('.group').filter('[rel='+trigger.group+']');
				groupDiv.find('select.group-relation').select2( 'val', group.relation );
			}

			// create the new row, by clicking the add-trigger button in the appropriate group
			divTriggers.find( btns.add_trigger ).filter('[data-group='+trigger.group+']').trigger( 'click' );
			// debugger; # DEBUG

			// populate values
			row = groupDiv.find('.trigger:last');
			row.find('select.trigger-relation').select2( 'val', trigger.relation ).trigger('change');
			row.find('select.trigger-type').select2( 'val', trigger.type ).trigger('change');
			row.find('select.trigger-operator').select2( 'val', trigger.operator ).trigger('change');

			// populate the trigger value, according to the trigger type
			if ( trigger.value ) {
				valueField = row.find('.trigger-value:not(.select2-container)').eq(0);
				if ( valueField.is('select') || valueField.is('.ajax') ) {
					valueField.trigger( 'select2_populate', trigger.value );
					// valueField.select2( 'val', trigger.value ).trigger('change');
				} else {
					valueField.val( trigger.value ).trigger('change');
				}
			}
		} );

		// Alerts
		jQuery.each( notification_rule.alerts, function(i, alert) {
			var row,
				optionFields,
				valueField;

			// create the new row, by clicking the add-alert button
			divAlerts.find( btns.add_alert ).trigger( 'click' );

			// populate values
			row = divAlerts.find('.alert:last');
			row.find('select.alert-type').select2( 'val', alert.type ).trigger('change');
			optionFields = row.find('.alert-options');
			optionFields.find(':input[name]').each(function(i, el){
				var $this = $(this),
					name,
					val;
				name = $this.attr('name').match('\\[([a-z_\-]+)\\]$')[1];
				if ( typeof alert[name] != 'undefined' ) {
					val = alert[name];
					if ( $this.hasClass( 'select2-offscreen' ) ) {
						$this.trigger( 'select2_populate', val )
						// $this.select2( 'val', val ).trigger( 'change' );
					} else {
						$this.val( val ).trigger('change');
					}
				}
			});
		});
	}

	// Do not submit if no triggers exist
	$('#rule-form').submit(function(e){
		if ( divTriggers.find('.trigger').size() < 1 ) {
			$('body,html').scrollTop(0)
			$('.wrap > h2')
				.after('<div class="updated error fade" style="display:none"><p>'+stream_notifications.i18n.empty_triggers+'</p></div>')
				.next('.updated')
				.slideDown('fast')
				.delay(3000)
				.slideUp('slow');
			return false;
		}
	});

	divAlerts
		.on( 'click', '.toggler', function(e) {
			e.preventDefault();
			var rel = this.rel,
				toggled = $(rel),
				toggler = $(this)
				;

			if ( ! toggled.is(':visible') ) {
				toggled.slideDown('fast');
				toggler.data( 'text', toggler.text() );
				toggler.text( toggler.data('text-toggle') );
			} else {
				toggled.slideUp('fast');
				toggler.text( toggler.data('text') );
			}
		} );

	// Autofocus for earlier browsers
	$('[autofocus]').focus();

	// Data tags accordion
	$('#data-tag-glossary').attr('data-theme', 'none').attr('data-role', 'none').accordion({
		header: 'header',
		collapsible: true,
		heightStyle: 'content',
		active: false,
		icons: { "header": "", "activeHeader": "" }
	});

	// Reset occurrences link
	$('a.reset-occ').click(function(e){
		e.preventDefault();

		if ( ! confirm( stream_notifications.i18n.confirm_reset ) ) {
			return;
		}

		$.getJSON( this.href, {}, function(j) {
			var div = $('.submitbox .occurrences span');
			if ( j.success ) {
				div.html( div.html().replace(/\d+/, 0) );
			} else {
				alert( stream_notifications.i18n.ajax_error );
			}
		} )
	});

	// Add empty trigger if no triggers are visible
	if ( $( '.trigger' ).length === 0 ) {
		add_trigger( 0 );
	}
});
