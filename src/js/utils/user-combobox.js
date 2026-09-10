/*
 * Accessible Ajax user combobox used by Stream user pickers.
 *
 * Rendered server-side by Form_Generator::render_user_combobox() as:
 *   .stream-user-combobox
 *   ├── .stream-user-combobox__input             <input role="combobox">
 *   ├── .stream-user-combobox__value             <input type="hidden"> (submitted)
 *   └── .stream-user-combobox__listbox           <ul role="listbox" hidden>
 *
 * Pickers that also accept roles (settings exclude rules) pass them via a
 * data-role-options JSON attribute; they render as a "Roles" group inside the
 * same listbox (filtered client-side alongside Ajax user results), mirroring
 * the previous Select2 dropdown's Roles/Users grouping in one control. The
 * hidden value stores whichever was picked: a role slug or a user id.
 *
 * Behaviour contract:
 * - Results come from the `wp_stream_filters` endpoint (`filter=user_id`,
 *   `q=<term>`), nonce supplied via Admin_Assets::user_combobox_l10n().
 * - The hidden input holds the only submitted value. The visible input is a
 *   search box, never a value source, so a half-typed query cannot win a
 *   race against Filter / Save: blur and form-submit both restore the last
 *   committed selection (see restoreCommitted()).
 * - Roles (when provided) are always selectable from the listbox; picking
 *   either a role or a user replaces the previous selection.
 * - Keyboard: ArrowDown/ArrowUp move (or reopen the cached list), Enter picks
 *   the active option, Escape closes (and, when closed, clears). wp.a11y
 *   announces result counts, empty results, and errors.
 */

/**
 * External dependencies
 */
import $ from 'jquery';

/**
 * Internal dependencies
 */
import '../../css/user-combobox.scss';

const USER_SEARCH_MIN_LENGTH = 2;
const USER_SEARCH_DELAY_MS = 500;

let comboboxSerial = 0;

/**
 * Write a committed selection into a combobox root (hidden value, visible label, role reset).
 *
 * Shared by the module and by callers that seed a cloned form (alerts inline edit).
 *
 * @param {Object} $root jQuery `.stream-user-combobox` root.
 * @param {string} id    User id ('' clears).
 * @param {string} label Display name ('' clears).
 */
export function setUserComboboxSelection( $root, id, label ) {
	const value = id || '';
	const text = label || '';

	$root.find( '.stream-user-combobox__value' ).val( value ).data( 'committedValue', value );
	$root.find( '.stream-user-combobox__input' ).val( text );
	$root.data( 'selectedLabel', text );
}

/**
 * Normalize wp_stream_filters JSON (array or object map) into a list.
 *
 * @param {*} data Ajax payload.
 * @return {Array} User result objects.
 */
function normalizeUserFilterResults( data ) {
	if ( Array.isArray( data ) ) {
		return data;
	}
	if ( data && 'object' === typeof data ) {
		return Object.keys( data ).map( function( key ) {
			return data[ key ];
		} );
	}
	return [];
}

/**
 * Announce a message to assistive technology.
 *
 * @param {string} message Text to speak.
 */
function speakMessage( message ) {
	if ( ! message ) {
		return;
	}
	if ( window.wp && window.wp.a11y && 'function' === typeof window.wp.a11y.speak ) {
		window.wp.a11y.speak( message, 'polite' );
	}
}

/**
 * Parse the data-role-options JSON attribute into role entries.
 *
 * @param {string} attr Raw attribute value.
 * @return {Array<{value: string, label: string}>} Role options.
 */
function parseRoleOptions( attr ) {
	try {
		const parsed = JSON.parse( attr || '[]' );
		return Array.isArray( parsed ) ? parsed.filter( ( role ) => role && role.value ) : [];
	} catch {
		return [];
	}
}

/**
 * Roles whose label contains the needle (case-insensitive).
 *
 * @param {Array<{value: string, label: string}>} roles  Role options.
 * @param {string}                                needle Search term.
 * @return {Array<{value: string, label: string}>} Matching roles.
 */
function filterRoles( roles, needle ) {
	if ( ! needle ) {
		return roles;
	}
	const lower = needle.toLowerCase();
	return roles.filter( ( role ) => ( role.label || '' ).toLowerCase().includes( lower ) );
}

/**
 * Whether a picker row has a usable avatar URL.
 *
 * @param {*} icon Candidate from Ajax (`icon` may be false or empty).
 * @return {boolean} True when `icon` is a non-empty string.
 */
function hasIconUrl( icon ) {
	return 'string' === typeof icon && '' !== icon;
}

/**
 * Apply a `%d` count into a PHP-translated template.
 *
 * @param {string} template Localized string with a `%d` placeholder.
 * @param {number} count    Matching-user count.
 * @return {string} Filled string.
 */
function formatCount( template, count ) {
	return String( template || '' ).replace( '%d', String( count ) );
}

/**
 * Map a result item to id/label/icon.
 *
 * @param {Object} item Ajax user row.
 * @return {{id: string, label: string, icon: string}} Normalized option.
 */
function toOption( item ) {
	const id = undefined !== item.id ? String( item.id ) : '';
	const label = item.label || item.text || '';
	const icon = hasIconUrl( item.icon ) ? item.icon : '';
	return { id, label, icon };
}

/**
 * Bind one combobox root.
 *
 * @param {Object} $root jQuery root.
 * @param {string} nonce `wp_stream_filters` nonce.
 * @param {Object} i18n  PHP-translated strings from `user_combobox_l10n()`.
 */
function bindCombobox( $root, nonce, i18n ) {
	if ( $root.data( 'streamUserComboboxReady' ) ) {
		return;
	}
	$root.data( 'streamUserComboboxReady', true );

	comboboxSerial += 1;
	const uid = 'stream-user-cb-' + String( comboboxSerial );
	const $input = $root.find( '.stream-user-combobox__input' );
	const $hidden = $root.find( '.stream-user-combobox__value' );
	const $list = $root.find( '.stream-user-combobox__listbox' );

	// Roles render inside the listbox (single control, like the previous
	// Select2 dropdown); pickers without roles simply have none.
	const roleOptions = parseRoleOptions( $root.attr( 'data-role-options' ) );

	$input.attr( 'id', uid + '-input' );
	$input.attr( 'aria-controls', uid + '-listbox' );
	$list.attr( 'id', uid + '-listbox' );

	// Server-rendered selection: a user id or role slug, plus its label.
	const selectedLabel = $root.attr( 'data-selected-label' ) || '';
	if ( selectedLabel && $hidden.val() ) {
		setUserComboboxSelection( $root, $hidden.val(), selectedLabel );
	} else {
		$hidden.data( 'committedValue', $hidden.val() );
	}

	/**
	 * Close the listbox.
	 */
	function closeList() {
		// A selection or escape inside the debounce window must not reopen the list later.
		window.clearTimeout( $root.data( 'userSearchTimer' ) );
		const pending = $root.data( 'userSearchXhr' );
		if ( pending && pending.abort ) {
			pending.abort();
		}
		$list.attr( 'hidden', 'hidden' ).empty();
		$input.attr( 'aria-expanded', 'false' ).removeAttr( 'aria-activedescendant' );
	}

	/**
	 * Active option node.
	 *
	 * @return {Object} jQuery option.
	 */
	function getActiveOption() {
		return $list.find( '.stream-user-combobox__option.is-active' );
	}

	/**
	 * Highlight an option by index.
	 *
	 * @param {number} index Option index.
	 */
	function setActiveIndex( index ) {
		const $options = $list.find( '.stream-user-combobox__option' );
		if ( ! $options.length ) {
			return;
		}
		const next = Math.max( 0, Math.min( index, $options.length - 1 ) );
		$options.removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
		const $active = $options.eq( next );
		$active.addClass( 'is-active' ).attr( 'aria-selected', 'true' );
		$input.attr( 'aria-activedescendant', $active.attr( 'id' ) );
		const node = $active.get( 0 );
		if ( node && node.scrollIntoView ) {
			node.scrollIntoView( { block: 'nearest' } );
		}
	}

	/**
	 * Apply a chosen user.
	 *
	 * @param {string} id    User id.
	 * @param {string} label Display name.
	 */
	function selectUser( id, label ) {
		setUserComboboxSelection( $root, id, label );
		closeList();
	}

	/**
	 * Clear the committed user (and role, if any).
	 */
	function clearSelection() {
		setUserComboboxSelection( $root, '', '' );
		closeList();
	}

	/**
	 * Put the last committed user or role back into the hidden field.
	 *
	 * Used on blur and on form submit so a keystroke that cleared the live
	 * value cannot win the race against Filter / Save.
	 */
	function restoreCommitted() {
		const committed = $hidden.data( 'committedValue' );
		const label = $root.data( 'selectedLabel' );
		if ( ! $hidden.val() && committed && label ) {
			$hidden.val( committed );
			$input.val( label );
		}
	}

	/**
	 * Reopen the listbox from keyboard (cached rows, or a new search).
	 */
	function openListFromKeyboard() {
		const cached = $root.data( 'userSearchOptions' );
		if ( Array.isArray( cached ) ) {
			renderOptions( filterRoles( roleOptions, '' ), cached );
			return;
		}

		const needle = String( $input.val() || '' ).trim();
		if ( needle.length >= USER_SEARCH_MIN_LENGTH ) {
			requestUsers( needle );
			return;
		}

		// Nothing typed yet: offer the roles straight away (users need a query).
		if ( roleOptions.length ) {
			renderOptions( roleOptions, [] );
		}
	}

	/**
	 * Clicking into the control opens the roles view, like the previous
	 * Select2 dropdown did (users still need a search query).
	 */
	$input.on( 'focus', function() {
		if ( ! $list.is( '[hidden]' ) ) {
			return;
		}
		if ( String( $input.val() || '' ).trim() || ! roleOptions.length ) {
			return;
		}
		renderOptions( roleOptions, [] );
	} );

	/**
	 * Render role and user entries in the listbox as grouped options.
	 *
	 * @param {Array<{value: string, label: string}>} roles Matching roles.
	 * @param {Array}                                 users Ajax user results.
	 */
	function renderOptions( roles, users ) {
		$list.empty();
		$root.data( 'userSearchOptions', users );

		const optionCount = roles.length + users.length;

		if ( 0 === optionCount ) {
			const none = i18n.noUsers || '';
			$list.append(
				$( '<li></li>' )
					.addClass( 'stream-user-combobox__empty' )
					.attr( 'role', 'presentation' )
					.text( none ),
			);
			$list.removeAttr( 'hidden' );
			$input.attr( 'aria-expanded', 'true' ).removeAttr( 'aria-activedescendant' );
			speakMessage( none );
			return;
		}

		let index = 0;

		if ( roles.length ) {
			$list.append( groupHeader( i18n.rolesHeader || 'Roles' ) );
			roles.forEach( function( role ) {
				$list.append( buildOption( { id: role.value, label: role.label, icon: '' }, index++ ) );
			} );
		}

		if ( users.length ) {
			$list.append( groupHeader( i18n.usersHeader || 'Users' ) );
			users.forEach( function( item ) {
				const option = toOption( item );
				if ( option.id ) {
					$list.append( buildOption( option, index++ ) );
				}
			} );
		}

		// Users need a query; when only roles are shown say so.
		if ( roles.length && ! users.length && ! String( $input.val() || '' ).trim() ) {
			$list.append(
				$( '<li></li>' )
					.addClass( 'stream-user-combobox__hint' )
					.attr( 'role', 'presentation' )
					.text( i18n.searchUsersHint || '' ),
			);
		}

		$list.removeAttr( 'hidden' );
		$input.attr( 'aria-expanded', 'true' );
		setActiveIndex( 0 );
		speakMessage(
			formatCount(
				1 === optionCount ? i18n.foundSingular : i18n.foundPlural,
				optionCount,
			),
		);
	}

	/**
	 * Non-interactive group header row for the listbox.
	 *
	 * @param {string} label Header text.
	 * @return {Object} jQuery <li>.
	 */
	function groupHeader( label ) {
		return $( '<li></li>' )
			.addClass( 'stream-user-combobox__group' )
			.attr( 'role', 'presentation' )
			.text( label );
	}

	/**
	 * Build one selectable listbox option node.
	 *
	 * @param {{id: string, label: string, icon: string}} option Option data.
	 * @param {number}                                    index  Stable index for ids.
	 * @return {Object} jQuery <li role="option">.
	 */
	function buildOption( option, index ) {
		const $option = $( '<li></li>' )
			.addClass( 'stream-user-combobox__option' )
			.attr( {
				id: `${ uid }-opt-${ index }`,
				role: 'option',
				'aria-selected': 'false',
				'data-id': option.id,
				'data-label': option.label,
			} );

		if ( option.icon ) {
			$option.append(
				$( '<img>' )
					.addClass( 'stream-user-combobox__avatar' )
					.attr( {
						src: option.icon,
						alt: '',
						width: 32,
						height: 32,
					} ),
			);
		}

		$option.append(
			$( '<span></span>' )
				.addClass( 'stream-user-combobox__name' )
				.text( option.label ),
		);

		return $option;
	}

	/**
	 * Request remote users.
	 *
	 * @param {string} query Search needle.
	 */
	function requestUsers( query ) {
		const pending = $root.data( 'userSearchXhr' );
		if ( pending && pending.abort ) {
			pending.abort();
		}

		$input.attr( 'aria-busy', 'true' );

		const xhr = $.ajax(
			{
				type: 'GET',
				url: window.ajaxurl,
				dataType: 'json',
				data: {
					action: 'wp_stream_filters',
					nonce,
					filter: 'user_id',
					q: query,
				},
				success( data ) {
					renderOptions(
						filterRoles( roleOptions, String( $input.val() || '' ).trim() ),
						normalizeUserFilterResults( data ),
					);
				},
				error( jqXHR, textStatus ) {
					if ( 'abort' === textStatus ) {
						return;
					}
					speakMessage( i18n.searchError || '' );
				},
				complete() {
					$input.removeAttr( 'aria-busy' );
				},
			},
		);

		$root.data( 'userSearchXhr', xhr );
	}

	$input.on( 'input search', function( event ) {
		window.clearTimeout( $root.data( 'userSearchTimer' ) );

		const query = String( $input.val() || '' ).trim();
		if ( '' === query ) {
			clearSelection();
			return;
		}

		if ( 'search' === event.type ) {
			return;
		}

		// Searching invalidates the live value; blur/submit restores the
		// committed selection (user or role).
		$hidden.val( '' );

		const timer = window.setTimeout(
			function() {
				const needle = String( $input.val() || '' ).trim();
				if ( needle.length < USER_SEARCH_MIN_LENGTH ) {
					closeList();
					if ( needle.length > 0 ) {
						speakMessage( i18n.minChars || '' );
					}
					return;
				}
				requestUsers( needle );
			},
			USER_SEARCH_DELAY_MS,
		);

		$root.data( 'userSearchTimer', timer );
	} );

	$input.on( 'keydown', function( event ) {
		const isOpen = ! $list.is( '[hidden]' );

		if ( 'ArrowDown' === event.key ) {
			if ( ! isOpen ) {
				event.preventDefault();
				openListFromKeyboard();
				return;
			}
			const $options = $list.find( '.stream-user-combobox__option' );
			if ( ! $options.length ) {
				return;
			}
			event.preventDefault();
			setActiveIndex( $options.index( getActiveOption() ) + 1 );
			return;
		}

		if ( 'ArrowUp' === event.key ) {
			if ( ! isOpen ) {
				event.preventDefault();
				openListFromKeyboard();
				return;
			}
			const $options = $list.find( '.stream-user-combobox__option' );
			if ( ! $options.length ) {
				return;
			}
			event.preventDefault();
			setActiveIndex( $options.index( getActiveOption() ) - 1 );
			return;
		}

		if ( 'Enter' === event.key ) {
			const $active = getActiveOption();
			if ( isOpen && $active.length ) {
				event.preventDefault();
				selectUser( $active.attr( 'data-id' ), $active.attr( 'data-label' ) );
				return;
			}
			restoreCommitted();
			return;
		}

		if ( 'Escape' === event.key ) {
			if ( isOpen ) {
				closeList();
				return;
			}
			if ( $hidden.data( 'committedValue' ) ) {
				event.preventDefault();
				clearSelection();
			}
		}
	} );

	$list.on( 'mousedown', '.stream-user-combobox__option', function( event ) {
		event.preventDefault();
		selectUser(
			this.getAttribute( 'data-id' ),
			this.getAttribute( 'data-label' ),
		);
	} );

	$input.on( 'blur', function() {
		window.setTimeout(
			function() {
				closeList();
				restoreCommitted();
			},
			150,
		);
	} );

	$input.closest( 'form' ).on( 'submit', restoreCommitted );
}

/**
 * Initialize user comboboxes in a jQuery collection.
 *
 * @param {Object} $nodes    jQuery collection of `.stream-user-combobox` roots.
 * @param {Object} localized Script data from Admin_Assets user_combobox_l10n() (`userSearchNonce`, `userSearchI18n`).
 */
export default function initUserComboboxes( $nodes, localized ) {
	if ( ! $nodes || ! $nodes.length ) {
		return;
	}
	const nonce = ( localized && localized.userSearchNonce ) || '';
	const i18n = ( localized && localized.userSearchI18n ) || {};
	$nodes.each(
		function() {
			bindCombobox( $( this ), nonce, i18n );
		},
	);
}
