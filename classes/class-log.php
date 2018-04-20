<?php

namespace WP_Stream;

class Log {

	/**
	 * Hold Plugin class
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Hold Current visitors IP Address.
	 *
	 * @var string
	 */
	private $ip_address;


	/**
	 * Previous Stream record ID, used for chaining same-session records
	 *
	 * @var int
	 */
	private $prev_record;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Support proxy mode by checking the `X-Forwarded-For` header first.
		$ip_address = wp_stream_filter_input( INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP );
		$ip_address = $ip_address ? $ip_address : wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );

		$this->ip_address = $ip_address;

		// Ensure function used in various methods is pre-loaded.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Log handler
	 *
	 * @param Connector $connector Connector responsible for logging the event.
	 * @param string    $message sprintf-ready error message string.
	 * @param array     $args sprintf (and extra) arguments to use.
	 * @param int       $object_id Target object id.
	 * @param string    $context Context of the event.
	 * @param string    $action Action of the event.
	 * @param int       $user_id User responsible for the event.
	 *
	 * @return mixed True if updated, otherwise false|WP_Error
	 */
	public function log( $connector, $message, $args, $object_id, $context, $action, $user_id = null ) {
		global $wp_roles;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( is_null( $object_id ) ) {
			$object_id = 0;
		}

		$wp_cron_tracking = isset( $this->plugin->settings->options['advanced_wp_cron_tracking'] ) ? $this->plugin->settings->options['advanced_wp_cron_tracking'] : false;
		$author           = new Author( $user_id );
		$agent            = $author->get_current_agent();

		// WP Cron tracking requires opt-in and WP Cron to be enabled.
		if ( ! $wp_cron_tracking && 'wp_cron' === $agent ) {
			return false;
		}

		$user = new \WP_User( $user_id );

		if ( $this->is_record_excluded( $connector, $context, $action, $user ) ) {
			return false;
		}

		$user_meta = array(
			'user_email'      => (string) ! empty( $user->user_email ) ? $user->user_email : '',
			'display_name'    => (string) $author->get_display_name(),
			'user_login'      => (string) ! empty( $user->user_login ) ? $user->user_login : '',
			'user_role_label' => (string) $author->get_role(),
			'agent'           => (string) $agent,
		);

		if ( 'wp_cli' === $agent && function_exists( 'posix_getuid' ) ) {
			$uid       = posix_getuid();
			$user_info = posix_getpwuid( $uid );

			$user_meta['system_user_id']   = (int) $uid;
			$user_meta['system_user_name'] = (string) $user_info['name'];
		}

		// Prevent any meta with null values from being logged.
		$stream_meta = array_filter(
			$args,
			function ( $var ) {
				return ! is_null( $var );
			}
		);

		// Add user meta to Stream meta.
		$stream_meta['user_meta'] = $user_meta;

		// Get the current time in milliseconds.
		$iso_8601_extended_date = wp_stream_get_iso_8601_extended_date();

		if ( ! empty( $user->roles ) ) {
			$roles = array_values( $user->roles );
			$role  = $roles[0];
		} elseif ( is_multisite() && is_super_admin() && $wp_roles->is_role( 'administrator' ) ) {
			$role = 'administrator';
		} else {
			$role = '';
		}

		$recordarr = array(
			'object_id' => (int) $object_id,
			'site_id'   => (int) is_multisite() ? get_current_site()->id : 1,
			'blog_id'   => (int) apply_filters( 'wp_stream_blog_id_logged', get_current_blog_id() ),
			'user_id'   => (int) $user_id,
			'user_role' => (string) $role,
			'created'   => (string) $iso_8601_extended_date,
			'summary'   => (string) vsprintf( $message, $args ),
			'connector' => (string) $connector,
			'context'   => (string) $context,
			'action'    => (string) $action,
			'ip'        => (string) $this->ip_address,
			'meta'      => (array) $stream_meta,
		);

		if ( 0 === $recordarr['object_id'] ) {
			unset( $recordarr['object_id'] );
		}

		$result = $this->plugin->db->insert( $recordarr );

		// This is helpful in development environments:
		// error_log( $this->debug_backtrace( $recordarr ) );

		return $result;
	}

	/**
	 * This function is use to check whether or not a record should be excluded from the log.
	 *
	 * @param string   $connector Name of the connector being logged.
	 * @param string   $context Name of the context being logged.
	 * @param string   $action Name of the action being logged.
	 * @param \WP_User $user The user being logged.
	 * @param string   $ip IP address being logged.
	 *
	 * @return bool
	 */
	public function is_record_excluded( $connector, $context, $action, $user = null, $ip = null ) {
		if ( is_null( $user ) ) {
			$user = wp_get_current_user();
		}

		if ( is_null( $ip ) ) {
			$ip = $this->ip_address;
		} else {
			$ip = wp_stream_filter_var( $ip, FILTER_VALIDATE_IP );
		}

		if ( ! empty( $user->roles ) ) {
			$roles = array_values( $user->roles );
			$role  = $roles[0];
		} else {
			$role = '';
		}
		$record = array(
			'connector'  => $connector,
			'context'    => $context,
			'action'     => $action,
			'author'     => $user->ID,
			'role'       => $role,
			'ip_address' => $ip,
		);

		$exclude_settings = isset( $this->plugin->settings->options['exclude_rules'] ) ? $this->plugin->settings->options['exclude_rules'] : array();

		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) && ! is_network_admin() ) {
			$multisite_options          = (array) get_site_option( 'wp_stream_network', array() );
			$multisite_exclude_settings = isset( $multisite_options['exclude_rules'] ) ? $multisite_options['exclude_rules'] : array();

			if ( ! empty( $multisite_exclude_settings ) ) {
				foreach ( $multisite_exclude_settings['exclude_row'] as $key => $rule ) {
					$exclude_settings['exclude_row'][]    = $multisite_exclude_settings['exclude_row'][ $key ];
					$exclude_settings['author_or_role'][] = $multisite_exclude_settings['author_or_role'][ $key ];
					$exclude_settings['connector'][]      = $multisite_exclude_settings['connector'][ $key ];
					$exclude_settings['context'][]        = $multisite_exclude_settings['context'][ $key ];
					$exclude_settings['action'][]         = $multisite_exclude_settings['action'][ $key ];
					$exclude_settings['ip_address'][]     = $multisite_exclude_settings['ip_address'][ $key ];
				}
			}
		}

		$exclude_record = false;

		if ( isset( $exclude_settings['exclude_row'] ) && ! empty( $exclude_settings['exclude_row'] ) ) {
			foreach ( $exclude_settings['exclude_row'] as $key => $value ) {
				// Prepare values.
				$author_or_role = isset( $exclude_settings['author_or_role'][ $key ] ) ? $exclude_settings['author_or_role'][ $key ] : '';
				$connector      = isset( $exclude_settings['connector'][ $key ] ) ? $exclude_settings['connector'][ $key ] : '';
				$context        = isset( $exclude_settings['context'][ $key ] ) ? $exclude_settings['context'][ $key ] : '';
				$action         = isset( $exclude_settings['action'][ $key ] ) ? $exclude_settings['action'][ $key ] : '';
				$ip_address     = isset( $exclude_settings['ip_address'][ $key ] ) ? $exclude_settings['ip_address'][ $key ] : '';

				$exclude = array(
					'connector'  => ! empty( $connector ) ? $connector : null,
					'context'    => ! empty( $context ) ? $context : null,
					'action'     => ! empty( $action ) ? $action : null,
					'ip_address' => ! empty( $ip_address ) ? $ip_address : null,
					'author'     => is_numeric( $author_or_role ) ? absint( $author_or_role ) : null,
					'role'       => ( ! empty( $author_or_role ) && ! is_numeric( $author_or_role ) ) ? $author_or_role : null,
				);

				$exclude_rules = array_filter( $exclude, 'strlen' );

				if ( ! empty( $exclude_rules ) ) {
					$matches_exclusion_rule = true;

					foreach ( $exclude_rules as $exclude_key => $exclude_value ) {
						if ( 'ip_address' === $exclude_key ) {
							$ip_addresses = explode( ',', $exclude_value );
							if ( ! in_array( $record['ip_address'], $ip_addresses, true ) ) {
								$matches_exclusion_rule = false;
								break;
							}
						} elseif ( $record[ $exclude_key ] !== $exclude_value ) {
							$matches_exclusion_rule = false;
							break;
						}
					}

					if ( $matches_exclusion_rule ) {
						$exclude_record = true;
						break;
					}
				}
			}
		}

		/**
		 * Filters whether or not a record should be excluded from the log.
		 *
		 * If true, the record is not logged.
		 *
		 * @param array $exclude_record Whether the record should excluded.
		 * @param array $recordarr The record to log.
		 *
		 * @return bool
		 */
		return apply_filters( 'wp_stream_is_record_excluded', $exclude_record, $record );
	}

	/**
	 * Helper function to send a full backtrace of calls to the PHP error log for debugging
	 *
	 * @param array $recordarr Record argument array.
	 *
	 * @return string
	 */
	public function debug_backtrace( $recordarr ) {
		if ( version_compare( PHP_VERSION, '5.3.6', '<' ) ) {
			return __( 'Debug backtrace requires at least PHP 5.3.6', 'wp_stream' );
		}

		// Record details.
		$summary   = isset( $recordarr['summary'] ) ? $recordarr['summary'] : null;
		$author    = isset( $recordarr['author'] ) ? $recordarr['author'] : null;
		$connector = isset( $recordarr['connector'] ) ? $recordarr['connector'] : null;
		$context   = isset( $recordarr['context'] ) ? $recordarr['context'] : null;
		$action    = isset( $recordarr['action'] ) ? $recordarr['action'] : null;

		// Stream meta.
		$stream_meta = isset( $recordarr['meta'] ) ? $recordarr['meta'] : null;

		unset( $stream_meta['user_meta'] );

		if ( $stream_meta ) {
			array_walk(
				$stream_meta, function ( &$value, $key ) {
					$value = sprintf( '%s: %s', $key, ( '' === $value ) ? 'null' : $value );
				}
			);
			$stream_meta = implode( ', ', $stream_meta );
		}

		// User meta.
		$user_meta = isset( $recordarr['meta']['user_meta'] ) ? $recordarr['meta']['user_meta'] : null;

		if ( $user_meta ) {
			array_walk(
				$user_meta, function ( &$value, $key ) {
					$value = sprintf( '%s: %s', $key, ( '' === $value ) ? 'null' : $value );
				}
			);

			$user_meta = implode( ', ', $user_meta );
		}

		// Debug backtrace.
		ob_start();

		// @codingStandardsIgnoreStart
		debug_print_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ); // Option to ignore args requires PHP 5.3.6
		// @codingStandardsIgnoreEnd

		$backtrace = ob_get_clean();
		$backtrace = array_values( array_filter( explode( "\n", $backtrace ) ) );

		$output = sprintf(
			"WP Stream Debug Backtrace\n\n    Summary | %s\n     Author | %s\n  Connector | %s\n    Context | %s\n     Action | %s\nStream Meta | %s\nAuthor Meta | %s\n\n%s\n",
			$summary,
			$author,
			$connector,
			$context,
			$action,
			$stream_meta,
			$user_meta,
			implode( "\n", $backtrace )
		);

		return $output;
	}
}
