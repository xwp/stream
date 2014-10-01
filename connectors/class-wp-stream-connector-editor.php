<?php

class WP_Stream_Connector_Editor extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'editor';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array();

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	private static $edited_file = array();

	/**
	 * Register all context hooks
	 *
	 * @return void
	 */
	public static function register() {
		parent::register();
		add_action( 'load-theme-editor.php', array( __CLASS__, 'get_edition_data' ) );
		add_action( 'load-plugin-editor.php', array( __CLASS__, 'get_edition_data' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'log_changes' ) );
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'Editor', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'updated' => __( 'Updated', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		/**
		 * Filter available context labels for the Editor connector
		 *
		 * @return array Array of context slugs and their translated labels
		 */
		return apply_filters(
			'wp_stream_editor_context_labels',
			array(
				'themes'  => __( 'Themes', 'stream' ),
				'plugins' => __( 'Plugins', 'stream' ),
			)
		);
	}

	/**
	 * Get the context based on wp_redirect location
	 *
	 * @param  string $location The URL of the redirect
	 * @return string           Context slug
	 */
	public static function get_context( $location ) {
		$context = null;

		if ( false !== strpos( $location, 'theme-editor.php' ) ) {
			$context = 'themes';
		}

		if ( false !== strpos( $location, 'plugin-editor.php' ) ) {
			$context = 'plugins';
		}

		/**
		 * Filter available contexts for the Editor connector
		 *
		 * @param  string  $context  Context slug
		 * @param  string  $location The URL of the redirect
		 * @return string            Context slug
		 */
		return apply_filters( 'wp_stream_editor_context', $context, $location );
	}

	/**
	 * Get the message format for file updates
	 *
	 * @return string Translated string
	 */
	public static function get_message() {
		return _x(
			'"%1$s" in "%2$s" updated',
			'1: File name, 2: Theme/plugin name',
			'stream'
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links     Previous links registered
	 * @param  object $record    Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( current_user_can( 'edit_theme_options' ) ) {
			$file_name = wp_stream_get_meta( $record, 'file', true );
			$file_path = wp_stream_get_meta( $record, 'file_path', true );

			if ( ! empty( $file_name ) && ! empty( $file_path ) ) {
				$theme_slug    = wp_stream_get_meta( $record, 'theme_slug', true );
				$plugin_slug   = wp_stream_get_meta( $record, 'plugin_slug', true );
				$theme_exists  = ( ! empty( $theme_slug ) && file_exists( $file_path ) );
				$plugin_exists = ( ! empty( $plugin_slug ) && file_exists( $file_path ) );

				if ( $theme_exists ) {
					$links[ __( 'Edit File', 'stream' ) ] = add_query_arg(
						array(
							'theme' => urlencode( $theme_slug ),
							'file'  => urlencode( $file_name ),
						),
						self_admin_url( 'theme-editor.php' )
					);

					$links[ __( 'Theme Details', 'stream' ) ] = add_query_arg(
						array(
							'theme' => urlencode( $theme_slug ),
						),
						self_admin_url( 'themes.php' )
					);
				}

				if ( $plugin_exists ) {
					$links[ __( 'Edit File', 'stream' ) ] = add_query_arg(
						array(
							'plugin' => urlencode( $plugin_slug ),
							'file'   => urlencode( str_ireplace( trailingslashit( WP_PLUGIN_DIR ), '', $file_path ) ),
						),
						self_admin_url( 'plugin-editor.php' )
					);
				}
			}
		}

		return $links;
	}

	/**
	 * Retrieves data submitted on the screen, and prepares it for the appropriate context type
	 *
	 * @action load-theme-editor.php
	 * @action load-plugin-editor.php
	 * @return void
	 */
	public static function get_edition_data() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || 'update' !== wp_stream_filter_input( INPUT_POST, 'action' ) ) {
			return;
		}

		if ( $slug = wp_stream_filter_input( INPUT_POST, 'theme' ) ) {
			self::$edited_file = self::get_theme_data( $slug );
		}

		if ( $slug = wp_stream_filter_input( INPUT_POST, 'plugin' ) ) {
			self::$edited_file = self::get_plugin_data( $slug );
		}
	}

	/**
	 * Retrieve theme data needed for the log message
	 *
	 * @param  string $slug   The theme slug (e.g. twentyfourteen)
	 * @return mixed  $output Compacted variables
	 */
	public static function get_theme_data( $slug ) {
		$theme = wp_get_theme( $slug );

		if ( ! $theme->exists() || ( $theme->errors() && 'theme_no_stylesheet' === $theme->errors()->get_error_code() ) ) {
			return;
		}

		$allowed_files              = $theme->get_files( 'php', 1 );
		$style_files                = $theme->get_files( 'css' );
		$allowed_files['style.css'] = $style_files['style.css'];
		$file                       = wp_stream_filter_input( INPUT_POST, 'file' );

		if ( empty( $file ) ) {
			$file_name = 'style.css';
			$file_path = $allowed_files['style.css'];
		} else {
			$file_name = $file;
			$file_path = sprintf( '%s/%s', $theme->get_stylesheet_directory(), $file_name );
		}

		$file_contents_before = file_get_contents( $file_path );

		$name = $theme->get( 'Name' );

		$output = compact(
			'file_name',
			'file_path',
			'file_contents_before',
			'slug',
			'name'
		);

		return $output;
	}

	/**
	 * Retrieve plugin data needed for the log message
	 *
	 * @param  string $slug   The plugin file base name (e.g. akismet/akismet.php)
	 * @return mixed  $output Compacted variables
	 */
	public static function get_plugin_data( $slug ) {
		$base                 = null;
		$name                 = null;
		$slug                 = current( explode( '/', $slug ) );
		$file_name            = wp_stream_filter_input( INPUT_POST, 'file' );
		$file_path            = WP_PLUGIN_DIR . '/' . $file_name;
		$file_contents_before = file_get_contents( $file_path );

		$plugins = get_plugins();

		foreach ( $plugins as $key => $plugin_data ) {
			if ( 0 === strpos( $key, $slug ) ) {
				$base = $key;
				$name = $plugin_data['Name'];
				break;
			}
		}

		$file_name = str_ireplace( trailingslashit( $slug ), '', $file_name );
		$slug      = ! empty( $base ) ? $base : $slug;

		$output = compact(
			'file_name',
			'file_path',
			'file_contents_before',
			'slug',
			'name'
		);

		return $output;
	}

	/**
	 * @filter wp_redirect
	 */
	public static function log_changes( $location ) {
		if ( ! empty( self::$edited_file ) ) {
			if ( file_get_contents( self::$edited_file['file_path'] ) !== self::$edited_file['file_contents_before'] ) {
				$context = self::get_context( $location );

				switch ( $context ) {
					case 'themes':
						$name_key = 'theme_name';
						$slug_key = 'theme_slug';
						break;
					case 'plugins':
						$name_key = 'plugin_name';
						$slug_key = 'plugin_slug';
						break;
					default:
						$name_key = 'name';
						$slug_key = 'slug';
				}

				self::log(
					self::get_message(),
					array(
						'file'      => (string) self::$edited_file['file_name'],
						$name_key   => (string) self::$edited_file['name'],
						$slug_key   => (string) self::$edited_file['slug'],
						'file_path' => (string) self::$edited_file['file_path'],
					),
					null,
					$context,
					'updated'
				);
			}
		}

		return $location;
	}

}
