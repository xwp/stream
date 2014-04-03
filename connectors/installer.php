<?php

class WP_Stream_Connector_Installer extends WP_Stream_Connector {

	/**
	 * Context name
	 *
	 * @var string
	 */
	public static $name = 'installer';

	/**
	 * Actions registered for this context
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
	 * Return translated context label
	 *
	 * @return string Translated context label
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
			'plugins'   => __( 'Plugins', 'stream' ),
			'themes'    => __( 'Themes', 'stream' ),
			'wordpress' => __( 'WordPress', 'stream' ),
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
			$version = get_stream_meta( $record->ID, 'new_version', true );
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
		$logs = array(); // If doing a bulk update, store log info in an array

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
			$from = $upgrader->skin->options['type'];
			if ( 'upload' === $from ) {
				if ( 'plugin' === $type ) {
					$cached_plugins = wp_cache_get( 'plugins', 'plugins' );
					$plugin_data    = $cached_plugins['/'.$upgrader->result['destination_name']];
					if ( $plugin_data ) {
						$plugin_data = reset( $plugin_data );
					} else { // Probably a failed installation
						return;
					}
					$slug    = $upgrader->result['destination_name'];
					$name    = $plugin_data['Name'];
					$version = $plugin_data['Version'];
				} elseif ( 'theme' === $type ) {
					$slug = $upgrader->result['destination_name'];
					$theme_data = wp_get_theme( $slug );
					if ( empty( $theme_data ) ) {
						return;
					}
					$name    = $theme_data->get( 'Name' );
					$version = $theme_data->get( 'Version' );
				}
			} else {
				$slug    = $upgrader->skin->api->slug;
				$name    = $upgrader->skin->api->name;
				$version = $upgrader->skin->api->version;
			}
			$action  = 'installed';
			$message = _x(
				'Installed %1$s: %2$s %3$s',
				'Plugin/theme installation. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'stream'
			);
		} elseif ( 'update' === $action ) {
			if ( 'plugin' === $type ) {
				if ( isset( $extra['bulk'] ) && true == $extra['bulk'] ) {
					$slugs = $extra['plugins'];
				} else {
					$slugs = array( $upgrader->skin->plugin );
				}
				$logs    = array();
				$plugins = get_plugins();
				foreach ( $slugs as $slug ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug );
					$logs[] = array(
						'name'        => $plugin_data['Name'],
						'version'     => $plugin_data['Version'],
						'old_version' => $plugins[ $slug ]['Version'],
					);
				}
			}
			elseif ( 'theme' === $type ) {
				if ( isset( $extra['bulk'] ) && true == $extra['bulk'] ) {
					$slugs = $extra['themes'];
				} else {
					$slugs = array( $upgrader->skin->theme );
				}
				$logs = array();
				foreach ( $slugs as $slug ) {
					$theme      = wp_get_theme( $slug );
					$stylesheet = $theme['Stylesheet Dir'] . '/style.css';
					$theme_data = get_file_data( $stylesheet, array( 'Version' => 'Version' ) );
					$logs[] = array(
						'name'        => $theme['Name'],
						'old_version' => $theme['Version'],
						'version'     => $theme_data['Version'],
					);
				}
			}
			$action  = 'updated';
			$message = _x(
				'Updated %1$s: %2$s %3$s',
				'Plugin/theme update. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'stream'
			);
		} else {
			return false;
		}

		$context = $type . 's';

		// If not doing bulk, simulate one to trigger a log operation
		if ( ! $logs ) $logs[] = array();

		foreach ( $logs as $log ) {
			extract( $log );
			self::log(
				$message,
				compact( 'type', 'name', 'version', 'slug', 'success', 'error', 'from' , 'old_version' ),
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

	public static function callback_delete_site_transient_update_themes() {
		$stylesheet = wp_stream_filter_input( INPUT_GET, 'stylesheet' );

		if ( 'delete' !== wp_stream_filter_input( INPUT_GET, 'action' ) || ! $stylesheet ) {
			return;
		}

		$theme = $GLOBALS['theme'];
		$name  = $theme['Name'];

		self::log(
			__( '"%s" theme deleted', 'stream' ),
			compact( 'name' ),
			null,
			array( 'themes' => 'deleted' )
		);
	}

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
			$data = $plugins[ $plugin ];
			$name = $data['Name'];
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
