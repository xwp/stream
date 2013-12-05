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
		$name    = $deleted_term;
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

		if ( $changed = array_diff( $old[$key], $new[$key] ) ) {
			foreach ( $changed as $location_id => $menu_id ) {
				$location = $locations[$location_id];
				$name     = get_term( $menu_id, 'nav_menu' )->name;
				$action   = 'unassigned';
				$message  = __( '"%s" has been unassigned from "%s"', 'stream' );

				self::log(
					$message,
					compact( 'name', 'location', 'location_id', 'menu_id' ),
					$menu_id,
					array( 'menus' => 'unassigned' )
					);
			}
		}

		if ( $changed = array_diff( $new[$key], $old[$key] ) ) {
			foreach ( $changed as $location_id => $menu_id ) {
				$location = $locations[$location_id];
				$name     = get_term( $menu_id, 'nav_menu' )->name;
				$action   = 'assigned';
				$message  = __( '"%s" has been assigned to "%s"', 'stream' );

				self::log(
					$message,
					compact( 'name', 'location', 'location_id', 'menu_id' ),
					$menu_id,
					array( 'menus' => 'assigned' )
					);
			}
		}

	}

}