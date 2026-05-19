<?php
/**
 * Trait: shared permission_callback() for read-only Stream abilities.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Grants access to read abilities via Stream's `view_stream` capability so
 * editors and other roles allowed by the "Role Access" setting can call them,
 * matching the admin UI's record-viewing permissions.
 */
trait Trait_View_Stream_Permission {

	/**
	 * {@inheritDoc}
	 *
	 * @param array $input Input that will be passed to execute().
	 */
	public function permission_callback( $input = array() ) {
		unset( $input );
		return current_user_can( 'view_stream' );
	}
}
