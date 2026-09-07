<?php
/**
 * Renders and manages the plugin Settings page.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use WP_User_Query;

/**
 * Class - Settings
 */
class Settings {

	/**
	 * Settings key/identifier
	 *
	 * @var string
	 */
	public $option_key = 'wp_stream';

	/**
	 * Network settings key/identifier
	 *
	 * @var string
	 */
	public string $network_options_key = 'wp_stream_network';

	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Field definition registry.
	 *
	 * Public so in-plugin callers can query schema without a Settings façade.
	 *
	 * @var Settings_Registry
	 */
	public Settings_Registry $registry;

	/**
	 * Field HTML renderer.
	 *
	 * Public so callers can render a field without a Settings façade.
	 *
	 * @var Settings_Renderer
	 */
	public Settings_Renderer $renderer;

	/**
	 * Posted-value sanitizer.
	 *
	 * Public so callers can sanitize without a Settings façade.
	 *
	 * @var Settings_Sanitizer
	 */
	public Settings_Sanitizer $sanitizer;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( public $plugin ) {
		$this->registry  = new Settings_Registry( $this->plugin );
		$this->renderer  = new Settings_Renderer( $this->plugin );
		$this->sanitizer = new Settings_Sanitizer( $this->plugin );

		$this->option_key = $this->get_option_key();
		$this->options    = $this->get_options();

		// Register settings, and fields.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Remove records when records TTL is shortened.
		add_action(
			'update_option_' . $this->option_key,
			array(
				$this,
				'updated_option_ttl_remove_records',
			),
			10,
			2
		);

		// Apply label translations for settings.
		add_filter(
			'wp_stream_serialized_labels',
			array(
				$this->registry,
				'filter_serialized_labels',
			)
		);

		// Ajax callback function to search users.
		add_action( 'wp_ajax_stream_get_users', array( $this, 'get_users' ) );

		// Ajax callback function to search IPs.
		add_action( 'wp_ajax_stream_get_ips', array( $this, 'get_ips' ) );
	}

	/**
	 * Ajax callback function to search users, used on exclude setting page
	 *
	 * @uses \WP_User_Query
	 */
	public function get_users() {
		if ( ! defined( 'DOING_AJAX' ) || ! current_user_can( $this->plugin->admin->settings_cap ) ) {
			return;
		}

		check_ajax_referer( 'stream_get_users', 'nonce' );

		$response = (object) array(
			'status'  => false,
			'message' => esc_html__( 'There was an error in the request', 'stream' ),
		);

		$search = '';
		$input  = wp_stream_filter_input( INPUT_POST, 'find' );

		if ( isset( $input['term'] ) ) {
			$search = wp_unslash( trim( $input['term'] ) );
		}

		$request = (object) array(
			'find' => $search,
		);

		add_filter(
			'user_search_columns',
			array(
				$this,
				'add_display_name_search_columns',
			),
			10,
			3
		);

		$users = new WP_User_Query(
			array(
				'search'         => "*{$request->find}*",
				'search_columns' => array(
					'user_login',
					'user_nicename',
					'user_email',
					'user_url',
				),
				'orderby'        => 'display_name',
				'number'         => $this->plugin->admin->preload_users_max,
			)
		);

		remove_filter(
			'user_search_columns',
			array(
				$this,
				'add_display_name_search_columns',
			),
			10
		);

		if ( 0 === $users->get_total() ) {
			wp_send_json_error( $response );
		}
		$users_array = $users->results;

		if ( is_multisite() && is_super_admin() ) {
			$super_admins = get_super_admins();
			foreach ( $super_admins as $admin ) {
				$user          = get_user_by( 'login', $admin );
				$users_array[] = $user;
			}
		}

		$response->status        = true;
		$response->message       = '';
		$response->roles         = $this->registry->get_roles();
		$response->users         = array();
		$users_added_to_response = array();

		foreach ( $users_array as $key => $user ) {
			// exclude duplications.
			if ( array_key_exists( $user->ID, $users_added_to_response ) ) {
				continue;
			} else {
				$users_added_to_response[ $user->ID ] = true;
			}

			$author = new Author( $user->ID );

			$args = array(
				'id'   => $author->ID,
				'text' => $author->display_name,
			);

			$args['tooltip'] = esc_attr(
				sprintf(
					/* translators: %1$d: user ID, %2$s: username, %3$s: email, %4$s: user role (e.g. "42", "administrator", "foo@bar.com", "subscriber") */
					__( 'ID: %1$d\nUser: %2$s\nEmail: %3$s\nRole: %4$s', 'stream' ),
					$author->id,
					$author->user_login,
					$author->user_email,
					ucwords( $author->get_role() )
				)
			);

			$args['icon'] = $author->get_avatar_src( 32 );

			$response->users[] = $args;
		}

		usort(
			$response->users,
			function ( $a, $b ) {
				return strcmp( $a['text'], $b['text'] );
			}
		);

		if ( empty( $search ) || preg_match( '/wp|cli|system|unknown/i', $search ) ) {
			$author            = new Author( 0 );
			$response->users[] = array(
				'id'      => '0',
				'text'    => $author->get_display_name(),
				'icon'    => $author->get_avatar_src( 32 ),
				'tooltip' => esc_html__( 'Actions performed by the system when a user is not logged in (e.g. auto site upgrader, or invoking WP-CLI without --user)', 'stream' ),
			);
		}

		wp_send_json_success( $response );
	}

	/**
	 * Ajax callback function to search IP addresses, used on exclude setting page
	 */
	public function get_ips() {
		if ( ! defined( 'DOING_AJAX' ) || ! current_user_can( $this->plugin->admin->settings_cap ) ) {
			return;
		}

		check_ajax_referer( 'stream_get_ips', 'nonce' );

		$ips  = $this->plugin->db->existing_records( 'ip' );
		$find = wp_stream_filter_input( INPUT_POST, 'find' );

		if ( isset( $find['term'] ) && '' !== $find['term'] ) {
			$ips = array_filter(
				$ips,
				function ( $ip ) use ( $find ) {
					return 0 === strpos( $ip, $find['term'] );
				}
			);
		}

		if ( $ips ) {
			wp_send_json_success( $ips );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Filter the columns to search in a WP_User_Query search.
	 *
	 * @param array          $search_columns Array of column names to be searched.
	 * @param string         $search Text being searched.
	 * @param \WP_User_Query $query current WP_User_Query instance.
	 *
	 * @return array
	 */
	public function add_display_name_search_columns( $search_columns, $search, $query ) {
		unset( $search );
		unset( $query );

		$search_columns[] = 'display_name';

		return $search_columns;
	}

	/**
	 * Returns the option key
	 *
	 * @return string
	 */
	public function get_option_key() {
		$option_key = $this->option_key;

		$current_page = wp_stream_filter_input( INPUT_GET, 'page' );

		if ( ! $current_page ) {
			$current_page = wp_stream_filter_input( INPUT_GET, 'action' );
		}

		if ( 'wp_stream_network_settings' === $current_page ) {
			$option_key = $this->network_options_key;
		}

		$filtered_key = apply_filters( 'wp_stream_settings_option_key', $option_key );

		// Guard against filters returning a non-string: the result is assigned
		// to the string-typed Settings::$option_key property, where anything but
		// a string would throw a TypeError (XWPENG-47).
		return is_string( $filtered_key ) ? $filtered_key : $option_key;
	}

	/**
	 * Returns a single setting value, reading the network-level option when
	 * Stream is network-activated on multisite.
	 *
	 * Settings::get_options() only loads from get_site_option() inside
	 * is_network_admin() screens. In REST and frontend contexts on a
	 * network-activated install, $this->options reflects the (typically empty)
	 * per-site option, which would silently mask a network-admin-controlled
	 * setting. This accessor handles that case so callers don't have to
	 * duplicate the multisite branching.
	 *
	 * @param string $key           Fully-qualified setting key (e.g. "advanced_enable_abilities_api").
	 * @param mixed  $default_value Value returned when the setting is not present.
	 *
	 * @return mixed
	 */
	public function get_setting_value( $key, $default_value = null ) {
		if (
			is_multisite()
			&& isset( $this->plugin )
			&& $this->plugin->is_network_activated()
		) {
			$options = (array) get_site_option( $this->network_options_key, array() );
		} else {
			$options = (array) $this->options;
		}

		return isset( $options[ $key ] ) ? $options[ $key ] : $default_value;
	}

	/**
	 * Returns the full options array, reading the network-level option when
	 * Stream is network-activated on multisite. Mirrors get_setting_value()
	 * but returns the entire array.
	 *
	 * @return array
	 */
	public function get_all_setting_values() {
		if (
			is_multisite()
			&& isset( $this->plugin )
			&& $this->plugin->is_network_activated()
		) {
			return (array) get_site_option( $this->network_options_key, array() );
		}

		return (array) $this->options;
	}

	/**
	 * Persists the options array, writing to the network-level option when
	 * Stream is network-activated on multisite. Used by REST/ability writers
	 * which run outside is_network_admin() but must respect the authoritative
	 * store. Refreshes $this->options afterwards so in-request reads see the
	 * new values.
	 *
	 * @param array $options Full options array to persist (caller is responsible
	 *                       for merging over existing values when desired).
	 *
	 * @return bool True on a successful write, false on no-op or failure.
	 */
	public function update_all_setting_values( array $options ) {
		$is_network = (
			is_multisite()
			&& isset( $this->plugin )
			&& $this->plugin->is_network_activated()
		);

		if ( $is_network ) {
			$result = update_site_option( $this->network_options_key, $options );
		} else {
			$result = update_option( $this->option_key, $options );
		}

		// Refresh the in-memory copy so subsequent reads in the same request
		// see the updated values. On network-activated installs we re-read
		// from the network option directly because Settings::get_options()
		// gates on is_network_admin() and would return the (now-stale)
		// per-site option in REST contexts. Merge defaults on top so callers
		// reading $plugin->settings->options keep seeing a fully-populated
		// array (matches get_options()'s historical contract).
		if ( $is_network ) {
			$defaults      = $this->registry->get_defaults();
			$this->options = wp_parse_args(
				(array) get_site_option( $this->network_options_key, array() ),
				$defaults
			);
		} else {
			$this->options = $this->get_options();
		}

		return (bool) $result;
	}

	/**
	 * Returns a list of options based on the current screen.
	 *
	 * @return array
	 */
	public function get_options() {
		$option_key = $this->option_key;
		$defaults   = $this->registry->get_defaults();

		$options = wp_parse_args(
			is_network_admin() ? (array) get_site_option( $option_key, array() ) : (array) get_option( $option_key, array() ),
			$defaults
		);

		/**
		 * Filter allows for modification of options
		 *
		 * @param array  $options    Options.
		 * @param string $option_key Option key.
		 *
		 * @return array Updated array of options
		 */
		$filtered = apply_filters( 'wp_stream_settings_options', $options, $option_key );

		// Guard against filters returning a non-array: the result is assigned
		// to the array-typed Settings::$options property, where anything but an
		// array would throw a TypeError (XWPENG-47).
		return is_array( $filtered ) ? $filtered : $options;
	}

	/**
	 * Registers settings fields and sections
	 *
	 * @return void
	 */
	public function register_settings() {
		$sections = $this->registry->get_fields();

		register_setting(
			$this->option_key,
			$this->option_key,
			array(
				$this->sanitizer,
				'sanitize_settings_for_save',
			)
		);

		foreach ( $sections as $section_name => $section ) {
			add_settings_section(
				$section_name,
				null,
				'__return_false',
				$this->option_key
			);

			foreach ( $section['fields'] as $field_idx => $field ) {
				// No field type associated, skip, no GUI.
				if ( ! isset( $field['type'] ) ) {
					continue;
				}

				add_settings_field(
					$field['name'],
					$field['title'],
					( isset( $field['callback'] ) ? $field['callback'] : array(
						$this->renderer,
						'output_field',
					) ),
					$this->option_key,
					$section_name,
					$field + array(
						'section'   => $section_name,
						'label_for' => sprintf( '%s_%s_%s', $this->option_key, $section_name, $field['name'] ),
					)
				);
			}
		}
	}

	/**
	 * Remove records when records TTL is shortened
	 *
	 * @action update_option_wp_stream
	 *
	 * @param array $old_value  Old value.
	 * @param array $new_value  New value.
	 */
	public function updated_option_ttl_remove_records( $old_value, $new_value ) {
		$ttl_before = isset( $old_value['general_records_ttl'] ) ? (int) $old_value['general_records_ttl'] : - 1;
		$ttl_after  = isset( $new_value['general_records_ttl'] ) ? (int) $new_value['general_records_ttl'] : - 1;

		if ( $ttl_after < $ttl_before ) {
			/**
			 * Fires when the records TTL is shortened.
			 *
			 * Preserved for backward compatibility with third-party code that
			 * hooked this action in Stream <= 4.1.x. The auto-purge itself
			 * no longer listens to this hook (it was migrated to Action
			 * Scheduler), so trigger the purge directly below.
			 */
			do_action( 'wp_stream_auto_purge' );

			// Trigger an immediate auto-purge cycle so the shortened TTL
			// takes effect now instead of at the next 12h recurring tick.
			//
			// Enqueue the recurring action as a one-shot async action so the
			// work serializes through the scheduler. Calling
			// purge_scheduled_action() inline here would bypass the overlap
			// guard's view of "in-flight" work (the current request is not a
			// scheduled action) and could stack a parallel chain when a
			// real chain is already running. Falls back to inline if no
			// scheduler is available (defensive — Plugin::__construct() sets it).
			if ( ! empty( $this->plugin->scheduler ) ) {
				// Prefer the purge collaborator when Admin is loaded (is_admin /
				// WP-CLI / cron). Without it, skip the overlap probe and still
				// enqueue — the recurring callback's own guard covers stacking.
				$is_running = isset( $this->plugin->admin->purge )
					&& $this->plugin->admin->purge->is_running_auto_purge();
				if ( ! $is_running ) {
					$this->plugin->scheduler->enqueue_async(
						\WP_Stream\Admin::AUTO_PURGE_ACTION,
						array(),
						\WP_Stream\Admin::AUTO_PURGE_GROUP
					);
				}
			} elseif ( isset( $this->plugin->admin->purge ) ) {
				$this->plugin->admin->purge->purge_scheduled_action();
			}
		}
	}
}
