<?php

class WP_Stream_Unread {

	const UNREAD_COUNT_OPTION_KEY = 'stream_unread_count';
	const LAST_READ_OPTION_KEY    = 'stream_last_read';

	public static function load() {
		if ( WP_Stream::is_vip() ) {
			return;
		}

		// Filter the main admin menu title
		add_filter( 'wp_stream_admin_menu_title', array( __CLASS__, 'admin_menu_title' ) );

		// Catch list table results
		add_filter( 'wp_stream_query_results', array( __CLASS__, 'mark_as_read' ), 10, 3 );

		// Add user option for enabling unread counts
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_option' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_option' ) );

		// Save unread counts user option
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_option' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_option' ) );

		// Delete user-specific transient when user is deleted
		add_action( 'delete_user', array( __CLASS__, 'delete_transient' ) );
	}

	/**
	 * Check whether or not the current user should see the unread counter.
	 *
	 * Defaults to TRUE if user option does not exist.
	 *
	 * @param int $user_id (optional)
	 *
	 * @return bool
	 */
	public static function unread_enabled_for_user( $user_id = 0 ) {
		$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;
		$enabled = wp_stream_get_user_meta( $user_id, self::UNREAD_COUNT_OPTION_KEY );
		$enabled = ( ! WP_Stream::is_vip() || 'off' !== $enabled );

		/**
		 * Enable or disable the unread count functionality
		 *
		 * @since 2.0.4
		 *
		 * @param int $user_id
		 *
		 * @return bool
		 */
		return (bool) apply_filters( 'wp_stream_unread_enabled', $enabled, $user_id );
	}

	/**
	 * Get the unread count for the current user.
	 *
	 * Results are cached in transient with a 5 min TTL.
	 *
	 * @return int
	 */
	public static function get_unread_count() {
		if ( ! self::unread_enabled_for_user() ) {
			return false;
		}

		$user_id   = get_current_user_id();
		$cache_key = sprintf( '%s_%d', self::UNREAD_COUNT_OPTION_KEY, $user_id );

		if ( false === ( $count = get_transient( $cache_key ) ) ) {
			$count     = 0;
			$last_read = wp_stream_get_user_meta( $user_id, self::LAST_READ_OPTION_KEY );

			if ( ! empty( $last_read ) ) {
				$args = array(
					'records_per_page' => 101,
					'author__not_in'   => array( $user_id ), // Ignore changes authored by the current user
					'date_after'       => date( 'c', strtotime( $last_read . ' + 1 second' ) ), // Bump time to bypass gte issue
					'fields'           => array( 'created' ), // We don't need the entire record
				);

				$unread_records = wp_stream_query( $args );

				$count = empty( $unread_records ) ? 0 : count( $unread_records );
			}

			set_transient( $cache_key, $count, 5 * 60 ); // TTL 5 min
		}

		return absint( $count );
	}

	/**
	 * Add unread count badge to the main admin menu title
	 *
	 * @filter wp_stream_admin_menu_title
	 *
	 * @param string $menu_title
	 *
	 * @return string
	 */
	public static function admin_menu_title( $menu_title ) {
		$unread_count = self::get_unread_count();

		if ( self::unread_enabled_for_user() && ! empty( $unread_count ) ) {
			$formatted_count = ( $unread_count > 99 ) ? esc_html__( '99 +', 'stream' ) : absint( $unread_count );
			$menu_title      = sprintf( '%s <span class="update-plugins count-%d"><span class="plugin-count">%s</span></span>', esc_html( $menu_title ), absint( $unread_count ), esc_html( $formatted_count ) );
		}

		return $menu_title;
	}

	/**
	 * Mark records as read based on Records screen results
	 *
	 * @filter wp_stream_query_results
	 *
	 * @param array $results
	 * @param array $query
	 * @param array $fields
	 *
	 * @return array
	 */
	public static function mark_as_read( $results, $query, $fields ) {
		if ( ! is_admin() ) {
			return $results;
		}

		$screen        = get_current_screen();
		$is_list_table = ( isset( $screen->id ) && sprintf( 'toplevel_page_%s', WP_Stream_Admin::RECORDS_PAGE_SLUG ) === $screen->id );
		$is_first_page = empty( $query['from'] );
		$is_date_desc  = ( isset( $query['sort'][0]['created']['order'] ) && 'desc' === $query['sort'][0]['created']['order'] );

		if ( $is_list_table && $is_first_page && $is_date_desc ) {
			$user_id   = get_current_user_id();
			$cache_key = sprintf( '%s_%d', self::UNREAD_COUNT_OPTION_KEY, $user_id );

			if ( self::unread_enabled_for_user() && isset( $results[0]->created ) ) {
				wp_stream_update_user_meta( $user_id, self::LAST_READ_OPTION_KEY, date( 'c', strtotime( $results[0]->created ) ) );
			}

			set_transient( $cache_key, 0 ); // No expiration
		}

		return $results;
	}

	/**
	 * Output for Stream Unread Count field in user profiles.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 *
	 * @param WP_User $user
	 *
	 * @return void
	 */
	public static function render_user_option( $user ) {
		if ( ! array_intersect( $user->roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
			return;
		}

		$unread_enabled = self::unread_enabled_for_user();
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( self::UNREAD_COUNT_OPTION_KEY ) ?>">
						<?php esc_html_e( 'Stream Unread Count', 'stream' ) ?>
					</label>
				</th>
				<td>
					<label for="<?php echo esc_attr( self::UNREAD_COUNT_OPTION_KEY ) ?>">
						<input type="checkbox" name="<?php echo esc_attr( self::UNREAD_COUNT_OPTION_KEY ) ?>" id="<?php echo esc_attr( self::UNREAD_COUNT_OPTION_KEY ) ?>" value="1" <?php checked( $unread_enabled ) ?>>
						<?php esc_html_e( 'Enabled', 'stream' ) ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves unread count user meta option in profiles.
	 *
	 * @action personal_options_update
	 * @action edit_user_profile_update
	 *
	 * @param $user_id
	 *
	 * @return void
	 */
	public static function save_user_option( $user_id ) {
		$enabled = wp_stream_filter_input( INPUT_POST, self::UNREAD_COUNT_OPTION_KEY );
		$enabled = ( '1' === $enabled ) ? 'on' : 'off';

		wp_stream_update_user_meta( $user_id, self::UNREAD_COUNT_OPTION_KEY, $enabled );
	}

	/**
	 * Delete user-specific transient when a user is deleted
	 *
	 * @action delete_user
	 *
	 * @param int $user_id
	 *
	 * @return void
	 */
	public static function delete_transient( $user_id ) {
		$cache_key = sprintf( '%s_%d', self::UNREAD_COUNT_OPTION_KEY, $user_id );

		delete_transient( $cache_key );
	}

}
