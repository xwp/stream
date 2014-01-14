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
					elementArgs.minimumInputLength = 3;
					elementArgs.ajax = {
						url: ajaxurl,
						type: 'post',
						dataType: 'json',
						data: function (term) {
							var type = '';
							if ( ! ( type = $this.data( 'ajax-key' ) ) ) {
								if ( tORa == 'triggers' ) {
									type = $this.parents('.form-row').first().find('select.trigger_type').val();
								} else {
									type = $this.parents('.form-row').eq(1).find('select.alert_type').val();
								}
							}
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
				}
				$this.select2( args );
			});
		};

	divTriggers
		// Add new rule
		.on( 'click.sn', btns.add_trigger, function(e) {
			e.preventDefault();
			var $this = $(this),
				index = divTriggers.find('.trigger').size(),
				group = divTriggers.find('.group').eq( $this.data('group') );

			group.append( tmpl( $.extend(
				{ index: index, group: $this.data('group') },
				stream_notifications
				) ) );
			group.find('.trigger').first().addClass('first');
			selectify( group.find('select') );
		})
	
		// Add new group
		.on( 'click.sn', btns.add_group, function(e) {
			e.preventDefault();
			var $this = $(this),
				parentGroupIndex = $this.data('group'),
				group = divTriggers.find('.group').eq(parentGroupIndex),
				groupIndex = divTriggers.find('.group').size();

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

	// Populate form values if it exists
	if ( triggers ) {
		
		for ( i = 0; i < triggers.length; i++ ) {
			var trigger = triggers[i],
				groupDiv = divTriggers.find('.group').filter('[rel='+trigger.group+']'),
				row,
				valueField;

			// create the group if it doesn't exist
			if ( ! groupDiv.size() ) {
				var group = groups[trigger.group];
				$( btns.add_group ).filter('[data-group='+group.group+']').trigger('click');
				groupDiv = divTriggers.find('.group').filter('[rel='+trigger.group+']');
				groupDiv.find('select.group_relation').select2( 'val', group.relation );
			}

			// create the new row, by clicking the add-trigger button in the appropriate group
			divTriggers.find( btns.add_trigger ).filter('[data-group='+trigger.group+']').trigger( 'click' );

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
			
		}
	}

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


});