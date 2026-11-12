/* eslint-disable camelcase */
/**
 * External dependencies
 */
import $ from 'jquery';

/**
 * Internal dependencies
 */
import wp_stream_regenerate_alt_rows from './utils/wp-stream-regenerate-alt-rows';
import initUserComboboxes from './utils/user-combobox';

const $excludeRows = $( '.stream-exclude-list tbody tr:not(.hidden)' );
const $placeholderRow = $( '.stream-exclude-list tr.helper' );

/**
 * Bind native exclude-rule controls for one or more rows.
 *
 * @param {Object} $rows jQuery collection of table rows.
 */
function initExcludeRows( $rows ) {
	$( 'select.connector_or_context', $rows ).on(
		'change', function() {
			const row = $( this ).closest( 'tr' );
			let connector = $( this ).val();
			if ( connector && 0 < connector.indexOf( '-' ) ) {
				const connector_split = connector.split( '-' );
				connector = connector_split[ 0 ];
			}
			getActions( row, connector );
		},
	);

	initUserComboboxes( $( '.stream-user-combobox', $rows ), window[ 'wp-stream-admin-exclude' ] );

	$( '.exclude_rules_remove_rule_row', $rows ).on(
		'click', function( e ) {
			const $thisRow = $( this ).closest( 'tr' );

			$thisRow.remove();

			recalculate_rules_found();
			recalculate_rules_selected();

			e.preventDefault();
		},
	);
}

initExcludeRows( $excludeRows );

$( 'select.connector_or_context', $excludeRows ).each(
	function() {
		const parts = [
			$( this ).siblings( '.connector' ).val(),
			$( this ).siblings( '.context' ).val(),
		];
		if ( parts[ 1 ] === '' ) {
			parts.splice( 1, 1 );
		}
		$( this ).val( parts.join( '-' ) ).trigger( 'change' );
	},
);

$( '#exclude_rules_new_rule' ).on(
	'click', function() {
		const $newRow = $placeholderRow.clone();

		$newRow.removeAttr( 'class' );
		$newRow.insertBefore( $placeholderRow );

		initExcludeRows( $newRow );
		recalculate_rules_found();
		recalculate_rules_selected();
	},
);

$( '#exclude_rules_remove_rules' ).on(
	'click', function() {
		const $excludeList = $( 'table.stream-exclude-list' ),
			selectedRows = $( 'tbody input.cb-select:checked', $excludeList ).closest( 'tr' );

		if ( ( $( 'tbody tr', $excludeList ).length - selectedRows.length ) >= 2 ) {
			selectedRows.remove();
		} else {
			$( ':input', selectedRows ).val( '' );
			$( selectedRows ).not( ':first' ).remove();
		}

		$excludeList.find( 'input.cb-select' ).prop( 'checked', false );

		recalculate_rules_found();
		recalculate_rules_selected();
	},
);

$( '.stream-exclude-list' ).closest( 'form' ).submit(
	function() {
		$( '.stream-exclude-list tbody tr.hidden', this ).each(
			function() {
				$( this ).find( ':input' ).removeAttr( 'name' );
			},
		);
		$( '.stream-exclude-list tbody tr:not(.hidden) select.connector_or_context', this ).each(
			function() {
				const parts = $( this ).val().split( '-' );
				$( this ).siblings( '.connector' ).val( parts[ 0 ] );
				$( this ).siblings( '.context' ).val( parts[ 1 ] );
				$( this ).removeAttr( 'name' );
			},
		);
	},
);

$( '.stream-exclude-list' ).closest( 'td' ).prev( 'th' ).hide();

$( 'table.stream-exclude-list' ).on(
	'click', 'input.cb-select', function() {
		recalculate_rules_selected();
	},
);

function getActions( row, connector ) {
	const trigger_action = $( 'select.action', row ),
		action_value = trigger_action.val();

	trigger_action.empty();
	trigger_action.prop( 'disabled', true );

	const placeholder = $( '<option/>', { value: '', text: trigger_action.data( 'placeholder' ) || '' } );
	trigger_action.append( placeholder );

	const data = {
		action: 'get_actions',
		connector,
		nonce: window[ 'wp-stream-admin-exclude' ].getActionsNonce,
	};

	$.post(
		window.ajaxurl, data, function( response ) {
			const success = response.success,
				actions = response.data;
			if ( ! success ) {
				return;
			}
			for ( const key in actions ) {
				if ( actions.hasOwnProperty( key ) ) {
					const value = actions[ key ];
					const option = $( '<option/>', { value: key, text: value } );
					trigger_action.append( option );
				}
			}
			trigger_action.val( action_value );
			trigger_action.prop( 'disabled', false );
			$( document ).trigger( 'alert-actions-updated' );
		},
	);
}

function recalculate_rules_selected() {
	const $selectedRows = $( 'table.stream-exclude-list tbody tr:not( .hidden ) input.cb-select:checked' ),
		$deleteButton = $( '#exclude_rules_remove_rules' );

	if ( 0 === $selectedRows.length ) {
		$deleteButton.prop( 'disabled', true );
	} else {
		$deleteButton.prop( 'disabled', false );
	}
}

function recalculate_rules_found() {
	const $allRows = $( 'table.stream-exclude-list tbody tr:not( .hidden )' ),
		$noRulesFound = $( 'table.stream-exclude-list tbody tr.no-items' ),
		$selectAll = $( '.check-column.manage-column input.cb-select' ),
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

$( document ).ready(
	function() {
		recalculate_rules_found();
		recalculate_rules_selected();
	},
);
