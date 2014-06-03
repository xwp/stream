<?php

class WP_Stream_Connector_Installer extends WP_Stream_Connector {

	/**
	 * Context slug
	 *
	 * @var string
	 */
	public static $name = 'installer';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'upgrader_process_complete', // plugins::installed | themes::installed
		'activate_plugin', // plugins::activated
		'deactivate_plugin', // plugins::deactivated
		'switch_theme', // themes::activated
		'delete_site_transient_update_themes', // themes::deleted
		'pre_option_uninstall_plugins', // plugins::deleted
		'pre_set_site_transient_update_plugins',
		'wp_redirect',
		'_core_updated_successfully',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'Installer', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'installed'   => __( 'Installed', 'stream' ),
			'activated'   => __( 'Activated', 'stream' ),
			'deactivated' => __( 'Deactivated', 'stream' ),
			'deleted'     => __( 'Deleted', 'stream' ),
			'edited'      => __( 'Edited', 'stream' ),
			'updated'     => __( 'Updated', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'plugins'   => __( 'Plugins', 'default' ),
			'themes'    => __( 'Themes', 'default' ),
			'wordpress' => __( 'WordPress', 'default' ),
		);
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
		if ( 'wordpress' === $record->context && 'updated' === $record->action ) {
			global $wp_version;
			$version = wp_stream_get_meta( $record->ID, 'new_version', true );
			if ( $version === $wp_version ) {
				$links[ __( 'About', 'stream' ) ] = admin_url( 'about.php?updated' );
			}
			$links[ __( 'View Release Notes', 'stream' ) ] = esc_url( sprintf( 'http://codex.wordpress.org/Version_%s', $version ) );
		}
		return $links;
	}

	/**
	 * Log plugin installations
	 *
	 * @action transition_post_status
	 */
	public static function callback_upgrader_process_complete( $upgrader, $extra ) {
		$logs    = array();
		$success = ! is_wp_error( $upgrader->skin->result );
		$error   = null;

		if ( ! $success ) {
			$errors = $upgrader->skin->result->errors;
			list( $error ) = reset( $errors );
		}

		// This would have failed down the road anyway
		if ( ! isset( $extra['type'] ) ) {
			return false;
		}

		$type   = $extra['type'];
		$action = $extra['action'];

		if ( ! in_array( $type, array( 'plugin', 'theme' ) ) ) {
			return;
		}

		if ( 'install' === $action ) {
			if ( 'plugin' === $type ) {
				$path = $upgrader->plugin_info();
				if ( ! $path ) {
					return;
				}
				$data    = get_plugin_data( $upgrader->skin->result['local_destination'] . '/' . $path );
				$slug    = $upgrader->result['destination_name'];
				$name    = $data['Name'];
				$version = $data['Version'];
			} else { // theme
				$slug = $upgrader->theme_info();
				if ( ! $slug ) {
					return;
				}
				wp_clean_themes_cache();
				$theme   = wp_get_theme( $slug );
				$name    = $theme->name;
				$version = $theme->version;
			}
			$action  = 'installed';
			$message = _x(
				'Installed %1$s: %2$s %3$s',
				'Plugin/theme installation. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'stream'
			);
			$logs[]  = compact( 'slug', 'name', 'version', 'message', 'action' );
		} elseif ( 'update' === $action ) {
			$action  = 'updated';
			$message = _x(
				'Updated %1$s: %2$s %3$s',
				'Plugin/theme update. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'stream'
			);
			if ( 'plugin' === $type ) {
				if ( isset( $extra['bulk'] ) && true == $extra['bulk'] ) {
					$slugs = $extra['plugins'];
				} else {
					$slugs = array( $upgrader->skin->plugin );
				}
				$plugins = get_plugins();
				foreach ( $slugs as $slug ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug );
					$name        = $plugin_data['Name'];
					$version     = $plugin_data['Version'];
					$old_version = $plugins[ $slug ]['Version'];

					$logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );
				}
			} else { // theme
				if ( isset( $extra['bulk'] ) && true == $extra['bulk'] ) {
					$slugs = $extra['themes'];
				} else {
					$slugs = array( $upgrader->skin->theme );
				}
				foreach ( $slugs as $slug ) {
					$theme       = wp_get_theme( $slug );
					$stylesheet  = $theme['Stylesheet Dir'] . '/style.css';
					$theme_data  = get_file_data( $stylesheet, array( 'Version' => 'Version' ) );
					$name        = $theme['Name'];
					$old_version = $theme['Version'];
					$version     = $theme_data['Version'];

					$logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );
				}
			}
		} else {
			return false;
		}

		$context = $type . 's';

		foreach ( $logs as $log ) {
			$name        = isset( $log['name'] ) ? $log['name'] : null;
			$version     = isset( $log['version'] ) ? $log['version'] : null;
			$slug        = isset( $log['slug'] ) ? $log['slug'] : null;
			$old_version = isset( $log['old_version'] ) ? $log['old_version'] : null;
			$message     = isset( $log['message'] ) ? $log['message'] : null;
			$action      = isset( $log['action'] ) ? $log['action'] : null;
			self::log(
				$message,
				compact( 'type', 'name', 'version', 'slug', 'success', 'error', 'old_version' ),
				null,
				array( $context => $action )
			);
		}
	}

	public static function callback_activate_plugin( $slug, $network_wide ) {
		$plugins      = get_plugins();
		$name         = $plugins[ $slug ]['Name'];
		$network_wide = $network_wide ? __( 'network wide', 'stream' ) : null;

		self::log(
			_x(
				'"%1$s" plugin activated %2$s',
				'1: Plugin name, 2: Single site or network wide',
				'stream'
			),
			compact( 'name', 'network_wide' ),
			null,
			array( 'plugins' => 'activated' )
		);
	}

	public static function callback_deactivate_plugin( $slug, $network_wide ) {
		$plugins      = get_plugins();
		$name         = $plugins[ $slug ]['Name'];
		$network_wide = $network_wide ? __( 'network wide', 'stream' ) : null;

		self::log(
			_x(
				'"%1$s" plugin deactivated %2$s',
				'1: Plugin name, 2: Single site or network wide',
				'stream'
			),
			compact( 'name', 'network_wide' ),
			null,
			array( 'plugins' => 'deactivated' )
		);
	}

	public static function callback_switch_theme( $name, $theme ) {
		$stylesheet = $theme->get_stylesheet();

		self::log(
			__( '"%s" theme activated', 'stream' ),
			compact( 'name' ),
			null,
			array( 'themes' => 'activated' )
		);
	}

	/**
	 * @todo Core needs a delete_theme hook
	 */
	public static function callback_delete_site_transient_update_themes() {

		$backtrace = debug_backtrace();
		$delete_theme_call = null;
		foreach ( $backtrace as $call ) {
			if ( isset( $call['function'] ) && 'delete_theme' === $call['function'] ) {
				$delete_theme_call = $call;
				break;
			}
		}

		if ( empty( $delete_theme_call ) ) {
			return;
		}

		$name = $delete_theme_call['args'][0];
		// @todo Can we get the name of the theme? Or has it already been eliminated

		self::log(
			__( '"%s" theme deleted', 'stream' ),
			compact( 'name' ),
			null,
			array( 'themes' => 'deleted' )
		);
	}

	/**
	 * @todo Core needs an uninstall_plugin hook
	 * @todo This does not work in WP-CLI
	 */
	public static function callback_pre_option_uninstall_plugins() {
		global $plugins;

		if ( 'delete-selected' !== wp_stream_filter_input( INPUT_GET, 'action' ) && 'delete-selected' !== wp_stream_filter_input( INPUT_POST, 'action2' ) ) {
			return false;
		}

		$_plugins = get_plugins();

		foreach ( $plugins as $plugin ) {
			$plugins_to_delete[ $plugin ] = $_plugins[ $plugin ];
		}

		update_option( 'wp_stream_plugins_to_delete', $plugins_to_delete );

		return false;
	}

	/**
	 * @todo Core needs a delete_plugin hook
	 * @todo This does not work in WP-CLI
	 */
	public static function callback_pre_set_site_transient_update_plugins( $value ) {
		if ( ! wp_stream_filter_input( INPUT_POST, 'verify-delete' ) || ! ( $plugins_to_delete = get_option( 'wp_stream_plugins_to_delete' ) ) ) {
			return $value;
		}

		foreach ( $plugins_to_delete as $plugin => $data ) {
			$name         = $data['Name'];
			$network_wide = $data['Network'] ? __( 'network wide', 'stream' ) : '';

			self::log(
				__( '"%s" plugin deleted', 'stream' ),
				compact( 'name', 'plugin', 'network_wide' ),
				null,
				array( 'plugins' => 'deleted' )
			);
		}

		delete_option( 'wp_stream_plugins_to_delete' );

		return $value;
	}

	public static function callback_wp_redirect( $location ) {
		if ( ! preg_match( '#(plugin)-editor.php#', $location, $match ) ) {
			return $location;
		}

		$type = $match[1];

		list( $url, $query ) = explode( '?', $location );

		$query = wp_parse_args( $query );
		$file  = $query['file'];

		if ( empty( $query['file'] ) ) {
			return $location;
		}

		if ( 'theme' === $type ) {
			if ( empty( $query['updated'] ) ) {
				return $location;
			}
			$theme = wp_get_theme( $query['theme'] );
			$name  = $theme['Name'];
		}
		elseif ( 'plugin' === $type ) {
			global $plugin, $plugins;
			$plugin_base = current( explode( '/', $plugin ) );
			foreach ( $plugins as $key => $plugin_data ) {
				if ( $plugin_base === current( explode( '/', $key ) ) ) {
					$name = $plugin_data['Name'];
					break;
				}
			}
		}

		self::log(
			_x(
				'Edited %1$s: %2$s',
				'Plugin/theme editing. 1: Type (plugin/theme), 2: Plugin/theme name',
				'stream'
			),
			compact( 'type', 'name', 'file' ),
			null,
			array( $type . 's' => 'edited' )
		);

		return $location;
	}

	public static function callback__core_updated_successfully( $new_version ) {
		global $pagenow, $wp_version;

		$old_version  = $wp_version;
		$auto_updated = ( 'update-core.php' !== $pagenow );

		if ( $auto_updated ) {
			$message = __( 'WordPress auto-updated to %s', 'stream' );
		} else {
			$message = __( 'WordPress updated to %s', 'stream' );
		}

		self::log(
			$message,
			compact( 'new_version', 'old_version', 'auto_updated' ),
			null,
			array( 'wordpress' => 'updated' )
		);
	}

}
