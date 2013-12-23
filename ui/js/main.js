/* globals console, stream_notifications, ajaxurl */
jQuery(function($){
	'use strict';
	
	_.templateSettings.variable = 'vars';

	var types = stream_notifications.types,
		divTriggers = $('#triggers'), // Trigger Playground
		btns = {
			add_trigger: '.add-trigger',
			add_group: '.add-trigger-group',
			del: '#delete-trigger'
		},
		tmpl = _.template( $('script#trigger-template-row').html() ),
		tmpl_group = _.template( $('script#trigger-template-group').html() ),
		tmpl_options = _.template( $('script#trigger-template-options').html() ),
		select2_args = {
			allowClear: true,
			width: '160px'
		},
		selectify = function( elements, args ) {
			args = args || {};
			$.extend( args, select2_args );

			$(elements).filter(':not(.select2-offscreen)').each( function() {
				var $this = $(this),
					elementArgs = args;
				elementArgs.width = parseInt( $this.css('width'), 10 ) + 30;

				if ( $this.hasClass('ajax') ) {
					elementArgs.minimumInputLength = 3;
					elementArgs.ajax = {
						url: ajaxurl,
						type: 'post',
						dataType: 'json',
						data: function (term) {
							return {
								action: 'stream_notification_endpoint',
								type: $this.parents('.form-row').first().find('select.rule_type').val(),
								q: term
							};
						},
						results: function (data, page) {
							var r = data.data || [];
							return {results: r};
						},
						formatSelection: function(item) {
							debugger;
							return item.title;
						}
					};
				}
				$this.select2( args );
			});
		};

	// Add new rule
	divTriggers.on( 'click.sn', btns.add_trigger, function(e) {
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
	});
	
	// Add new group
	divTriggers.on( 'click.sn', btns.add_group, function(e) {
		e.preventDefault();
		var $this = $(this),
			parentGroupIndex = $this.data('group'),
			group = divTriggers.find('.group').eq(parentGroupIndex),
			groupIndex = divTriggers.find('.group').size();

		group.append( tmpl_group({ index: groupIndex, parent: parentGroupIndex }) );
		selectify( group.find('.field.relation select') );
	});

	// Delete a trigger
	divTriggers.on( 'click.sn', '.delete-trigger', function(e) {
		e.preventDefault();
		var $this = $(this);

		$this.parents('.trigger').first().remove();
	});

	// Delete a group
	divTriggers.on( 'click.sn', '.delete-group', function(e) {
		e.preventDefault();
		var $this = $(this);

		$this.parents('.group').first().remove();
	});

	// Reveal rule option after choosing rule type
	divTriggers.on( 'change.sn', '.rule_type', function() {
		var $this = $(this),
			options = types[ $this.val() ],
			index = $this.parents('.trigger').first().attr('rel');
		$this.next('.rule_options').remove();
		
		if ( ! options ) { return; }
		
		$this.after( tmpl_options( $.extend( options, { index: index } ) ) );
		selectify( $this.parent().find('select') );
		selectify( $this.parent().find('input.tags, input.ajax'), { tags: [] } );
	});

});