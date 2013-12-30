<?php

class WP_Stream_Connector_Menus extends WP_Stream_Connector {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'menus';

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'wp_create_nav_menu',
		'wp_update_nav_menu',
		'delete_nav_menu',
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Menus', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'    => __( 'Created', 'stream' ),
			'updated'    => __( 'Updated', 'stream' ),
			'deleted'    => __( 'Deleted', 'stream' ),
			'assigned'   => __( 'Assigned', 'stream' ),
			'unassigned' => __( 'Unassigned', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		$labels = array();
		$menus  = get_terms( 'nav_menu', array( 'hide_empty' => false ) );

		foreach ( $menus as $menu ) {
			$slug          = sanitize_title( $menu->name );
			$labels[$slug] = $menu->name;
		}

		return $labels;
	}

	public static function register() {
		parent::register();
		add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), array( __CLASS__, 'callback_update_option_theme_mods' ), 10, 2 );
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 * @param  array $links      Previous links registered
	 * @param  int   $record     Stream record
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( $record->object_id ) {
			$menus    = wp_get_nav_menus();
			$menu_ids = wp_list_pluck( $menus, 'term_id' );
			if ( in_array( $record->object_id, $menu_ids ) ) {
				$links[ __( 'Edit Menu', 'stream' ) ] = admin_url( 'nav-menus.php?action=edit&menu=' . $record->object_id );
			}
		}
		return $links;
	}

	/**
	 * Tracks creation of menus
	 *
	 * @action wp_create_nav_menu
	 */
	public static function callback_wp_create_nav_menu( $menu_id, $menu_data ) {
		$name = $menu_data['menu-name'];
		self::log(
			__( 'Created new menu "%s"', 'stream' ),
			compact( 'name', 'menu_id' ),
			$menu_id,
			array( sanitize_title( $name ) => 'created' )
		);
	}

	/**
	 * Tracks menu updates
	 *
	 * @action wp_update_nav_menu
	 */
	public static function callback_wp_update_nav_menu( $menu_id, $menu_data = array() ) {
		if ( empty( $menu_data ) ) {
			return;
		}
		$name = $menu_data['menu-name'];
		self::log(
			__( 'Updated menu "%s"', 'stream' ),
			compact( 'name', 'menu_id', 'menu_data' ),
			$menu_id,
			array( sanitize_title( $name ) => 'updated' )
		);
	}

	/**
	 * Tracks menu deletion
	 *
	 * @action delete_nav_menu
	 */
	public static function callback_delete_nav_menu( $term, $tt_id, $deleted_term ) {
		$name    = $deleted_term->name;
		$menu_id = $term;
		self::log(
			__( 'Deleted "%s"', 'stream' ),
			compact( 'name', 'menu_id' ),
			$menu_id,
			array( sanitize_title( $name ) => 'deleted' )
		);
	}

	/**
	 * Track assignment to menu locations
	 *
	 * @action update_option_theme_mods_{$stylesheet}
	 */
	public static function callback_update_option_theme_mods( $old, $new )
	{
		// Disable if we're switching themes
		if ( did_action( 'after_switch_theme' ) ) return;

		$key = 'nav_menu_locations';
		if ( ! isset( $new[$key] ) ) {
			return; // Switching themes ?
		}

		if ( $old[$key] === $new[$key] ) {
			return;
		}

		$locations = get_registered_nav_menus();
		$changed   = array_diff_assoc( $old[$key], $new[$key] ) + array_diff_assoc( $new[$key], $old[$key] );

		if ( $changed ) {
			foreach ( $changed as $location_id => $menu_id ) {
				$location = $locations[$location_id];
				if ( empty( $new[$key][$location_id] ) ) {
					$action  = 'unassigned';
					$menu_id = isset( $old[$key][$location_id] ) ? $old[$key][$location_id] : 0;
					$message = __( '"%s" has been unassigned from "%s"', 'stream' );
				} else {
					$action  = 'assigned';
					$menu_id = isset( $new[$key][$location_id] ) ? $new[$key][$location_id] : 0;
					$message = __( '"%s" has been assigned to "%s"', 'stream' );
				}
				$menu = get_term( $menu_id, 'nav_menu' );

				if ( ! $menu || is_wp_error( $menu ) ) {
					continue; // This is a deleted menu
				}

				$name = $menu->name;

				self::log(
					$message,
					compact( 'name', 'location', 'location_id', 'menu_id' ),
					$menu_id,
					array( sanitize_title( $name ) => $action )
				);
			}
		}

	}

}
