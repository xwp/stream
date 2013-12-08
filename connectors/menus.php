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
			'created' => __( 'Created', 'stream' ),
			'updated' => __( 'Updated', 'stream' ),
			'deleted' => __( 'Deleted', 'stream' ),
			'assigned' => __( 'Assigned', 'stream' ),
			'unassigned' => __( 'Unassigned', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'menus' => __( 'Menus', 'stream' ),
		);
	}

	public static function register() {
		parent::register();
		add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), array( __CLASS__, 'callback_update_option_theme_mods' ), 10, 2 );
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_posts
	 * @param  array $links      Previous links registered
	 * @param  int   $stream_id  Stream drop id
	 * @param  int   $object_id  Object ( post ) id
	 * @return array             Action links
	 */
	public static function action_links( $links, $stream_id, $object_id ) {

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
			array( 'menus' => 'created' )
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
			array( 'menus' => 'updated' )
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
			array( 'menus' => 'deleted' )
			);
	}

	/**
	 * Track assignment to menu locations
	 *
	 * @action update_option_theme_mods_{$stylesheet}
	 */
	public static function callback_update_option_theme_mods( $old, $new )
	{
		$key = 'nav_menu_locations';
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
					$menu_id = $old[$key][$location_id];
					$message = __( '"%s" has been unassigned from "%s"', 'stream' );
				} else {
					$action  = 'assigned';
					$menu_id = $new[$key][$location_id];
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
					array( 'menus' => $action )
					);
			}
		}

	}

}