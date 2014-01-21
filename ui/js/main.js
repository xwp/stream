/* globals stream_notifications, ajaxurl, triggers */
jQuery(function($){
	'use strict';
	
	_.templateSettings.variable = 'vars';

	var types = stream_notifications.types,
		i,
		
		divTriggers = $('#triggers'), // Trigger Playground
		divAlerts = $('#alerts'), // Alerts Playground
		
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

		select2_args = {
			allowClear: true,
			width: '160px'
		},
		
		selectify = function( elements, args ) {
			args = args || {};
			$.extend( args, select2_args );

			$(elements).filter(':not(.select2-offscreen)').each( function() {
				var $this = $(this),
					elementArgs = args,
					tORa = $this.closest('#alerts, #triggers').attr('id');
				;
				elementArgs.width = parseInt( $this.css('width'), 10 ) + 30;

				if ( $this.hasClass('ajax') ) {
					var type = '';
					if ( ! ( type = $this.data( 'ajax-key' ) ) ) {
						if ( tORa == 'triggers' ) {
							type = $this.parents('.form-row').first().find('select.trigger_type').val();
						} else {
							type = $this.parents('.form-row').eq(1).find('select.alert_type').val();
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
								q: term
							};
						},
						results: function (data) {
							var r = data.data || [];
							return {results: r};
						},
						formatSelection: function(item) {
							return item.title;
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
				                    q : id,
				                    single: 1,
				                    type  : type,
				                },
				                dataType: "json"
			            	}).done( function( data ) { callback( data.data ); } );
				        }
					};
				}

				$this.select2( elementArgs );
			});
		};

	divTriggers
		// Add new rule
		.on( 'click.sn', btns.add_trigger, function(e) {
			e.preventDefault();
			var $this = $(this),
				index = 0,
				lastItem  = null,
				group = divTriggers.find('.group').filter( '[rel=' + $this.data('group') + ']' );

			if ( ( lastItem = divTriggers.find('.trigger').last() ) && lastItem.size() ) {
				index = parseInt( lastItem.attr('rel') ) + 1;
			}

			group.append( tmpl( $.extend(
				{ index: index, group: $this.data('group') },
				stream_notifications
				) ) );
			group.find('.trigger').first().addClass('first');
			selectify( group.find('select') );
		})
	
		// Add new group
		.on( 'click.sn', btns.add_group, function(e, groupIndex) {
			e.preventDefault();
			var $this = $(this),
				lastItem = null,
				parentGroupIndex = $this.data('group'),
				group = divTriggers.find('.group').eq(parentGroupIndex);
			if ( ! groupIndex ) {
				if ( ( lastItem = divTriggers.find('.group').last() ) && lastItem.size() ) {
					groupIndex = parseInt( lastItem.attr('rel') ) + 1;
				}
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
		.on( 'change.sn', '.trigger_type', function() {
			var $this   = $(this),
				options = types[ $this.val() ],
				index   = $this.parents('.trigger').first().attr('rel');
			$this.next('.trigger_options').remove();
			
			if ( ! options ) { return; }
			
			$this.after( tmpl_options( $.extend( options, { index: index } ) ) );
			selectify( $this.parent().find('select') );
			selectify( $this.parent().find('input.tags, input.ajax'), { tags: [] } );
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
		.on( 'change.sn', '.alert_type', function() {
			var $this   = $(this),
				options = stream_notifications.adapters[ $this.val() ],
				index   = $this.parents('.alert').first().attr('rel');
			$this.next('.alert_options').remove();

			if ( ! options ) { return; }

			$this.after( tmpl_alert_options( $.extend( options, { index: index } ) ) );
			selectify( $this.parent().find('select') );
			selectify( $this.parent().find('input.tags, input.ajax'), { tags: [] } );
		})
	;

	// Populate form values if it exists
	if ( typeof notification_rule != 'undefined'  ) {

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
				groupDiv.find('select.group_relation').select2( 'val', group.relation );
			}

			// create the new row, by clicking the add-trigger button in the appropriate group
			divTriggers.find( btns.add_trigger ).filter('[data-group='+trigger.group+']').trigger( 'click' );
			// debugger; # DEBUG

			// populate values
			row = groupDiv.find('.trigger:last');
			row.find('select.trigger_relation').select2( 'val', trigger.relation ).trigger('change');
			row.find('select.trigger_type').select2( 'val', trigger.type ).trigger('change');
			row.find('select.trigger_operator').select2( 'val', trigger.operator ).trigger('change');

			// populate the trigger value, according to the trigger type
			if ( trigger.value ) {
				valueField = row.find('.trigger_value:not(.select2-container)').eq(0);
				if ( valueField.is('select') || valueField.is('.ajax') ) {
					valueField.select2( 'val', trigger.value ).trigger('change');
				} else {
					valueField.val( trigger.value ).trigger('change');
				}
			}
		} );

		// Alerts
		for ( i = 0; i < notification_rule.alerts.length; i++ ) {
			var alert = notification_rule.alerts[i],
				row,
				optionFields,
				valueField;

			// create the new row, by clicking the add-alert button
			divAlerts.find( btns.add_alert ).trigger( 'click' );

			// populate values
			row = divAlerts.find('.alert:last');
			row.find('select.alert_type').select2( 'val', alert.type ).trigger('change');
			optionFields = row.find('.alert_options');
			optionFields.find(':input[name]').each(function(i, el){
				var $this = $(this),
					name,
					val;
				name = $this.attr('name').match('\\[([a-z_\-]+)\\]$')[1];
				if ( typeof alert[name] != 'undefined' ) {
					val = alert[name];
					$this.val( val ).trigger('change');
				}
			});
		}
	}

});