<?php
/**
 * Server-side backend for every "pick a user" control.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class User_Picker
 *
 * One shared decision for the records filter, settings exclude rules, and the
 * alert author trigger: under the preload cap (Admin::get_preload_users_max(),
 * filterable) every user is preloaded into a native <select>; over the cap the
 * control becomes the Ajax combobox (src/js/utils/user-combobox.js) whose
 * search hits Admin_Ajax::ajax_filters() -> User_Picker::search().
 *
 * The option source is wp_users (+ network super-admins on multisite, plus the
 * WP-CLI pseudo-user id 0) — the same set the previous Select2 Ajax dropdowns
 * offered. Labels come from Author; 0 renders as "WP-CLI" and deleted users as
 * "Unknown user {id}" so stored values always display.
 *
 * Stateless: no Plugin dependency, safe to instantiate anywhere.
 *
 * @package WP_Stream
 */
class User_Picker {

	/**
	 * Picker state for the current preload cap.
	 *
	 * Under the cap every user (plus network super-admins on multisite and the
	 * WP-CLI pseudo-user) is preloaded into the picker, sorted by display name.
	 * Over the cap the picker switches to Ajax search and no items are sent.
	 *
	 * @param int $cap Preload cap (see Admin::get_preload_users_max()).
	 * @return array{ajax: bool, items: array<int, array{id: int, text: string, label: string, icon: string|false}>}
	 */
	public function get( int $cap ): array {
		if ( $cap < 1 ) {
			return array(
				'ajax'  => true,
				'items' => array(),
			);
		}

		$user_count  = count_users();
		$total_users = $user_count['total_users'];

		if ( $total_users > $cap ) {
			return array(
				'ajax'  => true,
				'items' => array(),
			);
		}

		return array(
			'ajax'  => false,
			'items' => $this->label_items( $this->user_ids() ),
		);
	}

	/**
	 * Ajax search results for a user picker.
	 *
	 * Matches logins, names, emails, and URLs (so network super-admins surface
	 * when their own fields match), and offers the WP-CLI pseudo-user for
	 * system-ish searches.
	 *
	 * @param string $term  Search term.
	 * @param int    $limit Maximum rows to return.
	 * @return array<int, array{id: int, text: string, label: string, icon: string|false}>
	 */
	public function search( string $term, int $limit ): array {
		$term  = trim( $term );
		$limit = max( 1, $limit );

		if ( '' === $term ) {
			return array();
		}

		$query = new \WP_User_Query(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array(
					'user_login',
					'user_nicename',
					'user_email',
					'user_url',
					'display_name',
				),
				'orderby'        => 'display_name',
				'number'         => $limit,
				'fields'         => 'ID',
				'count_total'    => false,
			)
		);

		$ids = array_map( 'intval', $query->get_results() );

		// Note: super-admins are NOT appended unconditionally (the previous
		// exclude-screen endpoint did that); the search itself covers logins,
		// emails, URLs, and display names, so they surface when relevant.

		if ( preg_match( '/wp|cli|system|unknown/i', $term ) ) {
			$ids[] = 0;
		}

		return array_slice( $this->label_items( array_values( array_unique( $ids ) ) ), 0, $limit );
	}

	/**
	 * Display label for a picker user.
	 *
	 * @param int $user_id User ID. Zero is WP-CLI.
	 * @return string
	 */
	public function label( int $user_id ): string {
		if ( 0 === $user_id ) {
			return 'WP-CLI';
		}

		$author = new Author( $user_id );

		if ( $author->is_deleted() ) {
			return sprintf(
				/* translators: %d: user ID */
				__( 'Unknown user %d', 'stream' ),
				$user_id
			);
		}

		return $author->get_display_name();
	}

	/**
	 * Display label for a stored picker value (form input, alert meta, query arg).
	 *
	 * @param mixed $value Raw stored value.
	 * @return string Label, or empty string when the value is not a user ID.
	 */
	public function label_for_value( $value ): string {
		$value = (string) $value;

		return ctype_digit( $value ) ? $this->label( (int) $value ) : '';
	}

	/**
	 * Avatar URL for a picker user.
	 *
	 * @param int $user_id User ID. Zero is WP-CLI.
	 * @return string|false Image URL, or false when avatars are disabled or missing.
	 */
	public function icon( int $user_id ) {
		return ( new Author( $user_id ) )->get_avatar_src( 32 );
	}

	/**
	 * All user IDs offered by a picker: site users, network super-admins, WP-CLI.
	 *
	 * @return int[]
	 */
	private function user_ids(): array {
		$ids = array_map( 'intval', get_users( array( 'fields' => 'ID' ) ) );

		if ( is_multisite() && is_super_admin() ) {
			foreach ( get_super_admins() as $login ) {
				$super = get_user_by( 'login', $login );
				if ( $super ) {
					$ids[] = (int) $super->ID;
				}
			}
		}

		$ids[] = 0; // WP-CLI pseudo-user.

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Attach labels, icons, and natural name order to picker user IDs.
	 *
	 * @param int[] $ids User IDs (0 is WP-CLI).
	 * @return array<int, array{id: int, text: string, label: string, icon: string|false}>
	 */
	private function label_items( array $ids ): array {
		$lookup = array();
		foreach ( $ids as $user_id ) {
			if ( $user_id > 0 ) {
				$lookup[] = $user_id;
			}
		}

		if ( array() !== $lookup && function_exists( 'cache_users' ) ) {
			cache_users( $lookup );
		}

		$items = array();
		foreach ( $ids as $user_id ) {
			$label   = $this->label( $user_id );
			$icon    = $this->icon( $user_id );
			$items[] = array(
				'id'    => $user_id,
				'text'  => $label,
				'label' => $label,
				'icon'  => $icon,
			);
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['label'], $b['label'] );
			}
		);

		return $items;
	}
}
