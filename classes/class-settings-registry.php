<?php
/**
 * Builds and queries Stream settings field definitions.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use WP_Roles;

/**
 * Class - Settings_Registry
 */
class Settings_Registry {

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Return settings fields.
	 *
	 * Applies `wp_stream_settings_option_fields` and sticky title sort.
	 *
	 * @return array
	 */
	public function get_fields() {
		$fields = array(
			'general'  => array(
				'title'  => esc_html__( 'General', 'stream' ),
				'fields' => array(
					array(
						'name'    => 'role_access',
						'title'   => esc_html__( 'Role Access', 'stream' ),
						'type'    => 'multi_checkbox',
						'desc'    => esc_html__( 'Users from the selected roles above will have permission to view Stream Records. However, only site Administrators can access Stream Settings.', 'stream' ),
						'choices' => self::get_roles(),
						'default' => array( 'administrator' ),
					),
					array(
						'name'        => 'records_ttl',
						'title'       => esc_html__( 'Keep Records for', 'stream' ),
						'type'        => 'number',
						'class'       => 'small-text',
						'desc'        => esc_html__( 'Maximum number of days to keep activity records.', 'stream' ),
						'default'     => 30,
						'min'         => 1,
						'max'         => 999,
						'step'        => 1,
						'after_field' => esc_html__( 'days', 'stream' ),
					),
					array(
						'name'        => 'keep_records_indefinitely',
						'title'       => esc_html__( 'Keep Records Indefinitely', 'stream' ),
						'type'        => 'checkbox',
						'desc'        => sprintf( '<strong>%s</strong> %s', esc_html__( 'Not recommended.', 'stream' ), esc_html__( 'Purging old records helps to keep your WordPress installation running optimally.', 'stream' ) ),
						'after_field' => esc_html__( 'Enabled', 'stream' ),
						'default'     => 0,
					),
				),
			),
			'exclude'  => array(
				'title'  => esc_html__( 'Exclude', 'stream' ),
				'fields' => array(
					array(
						'name'    => 'rules',
						'title'   => esc_html__( 'Exclude Rules', 'stream' ),
						'type'    => 'rule_list',
						'desc'    => esc_html__( 'Create rules to exclude certain kinds of activity from being recorded by Stream.', 'stream' ),
						'default' => array(),
						'nonce'   => 'stream_get_ips',
					),
				),
			),
			'advanced' => array(
				'title'  => esc_html__( 'Advanced', 'stream' ),
				'fields' => array(
					array(
						'name'        => 'comment_flood_tracking',
						'title'       => esc_html__( 'Comment Flood Tracking', 'stream' ),
						'type'        => 'checkbox',
						'desc'        => esc_html__( 'WordPress will automatically prevent duplicate comments from flooding the database. By default, Stream does not track these attempts unless you opt-in here. Enabling this is not necessary or recommended for most sites.', 'stream' ),
						'after_field' => esc_html__( 'Enabled', 'stream' ),
						'default'     => 0,
					),
					$this->build_delete_all_records_field(),
					$this->build_clean_orphan_meta_field(),
				),
			),
		);

		// If Akismet is active, allow Admins to opt-in to Akismet tracking.
		if ( class_exists( 'Akismet' ) ) {
			$akismet_tracking = array(
				'name'        => 'akismet_tracking',
				'title'       => esc_html__( 'Akismet Tracking', 'stream' ),
				'type'        => 'checkbox',
				'desc'        => esc_html__( 'Akismet already keeps statistics for comment attempts that it blocks as SPAM. By default, Stream does not track these attempts unless you opt-in here. Enabling this is not necessary or recommended for most sites.', 'stream' ),
				'after_field' => esc_html__( 'Enabled', 'stream' ),
				'default'     => 0,
			);

			array_push( $fields['advanced']['fields'], $akismet_tracking );
		}

		$wp_cron_tracking = array(
			'name'        => 'wp_cron_tracking',
			'title'       => esc_html__( 'WP Cron Tracking', 'stream' ),
			'type'        => 'checkbox',
			'desc'        => esc_html__( 'By default, Stream does not track activity performed by WordPress cron events unless you opt-in here. Enabling this is not necessary or recommended for most sites.', 'stream' ),
			'after_field' => esc_html__( 'Enabled', 'stream' ),
			'default'     => 0,
		);

		array_push( $fields['advanced']['fields'], $wp_cron_tracking );

		// Abilities API toggle is only meaningful on WordPress 6.9+. On
		// network-activated multisite, Abilities::is_enabled() reads the
		// network option (wp_stream_network), so a per-site checkbox on the
		// site's own settings screen would be a no-op and misleading. Hide
		// the field from per-site settings pages, but keep it available in
		// network admin and in REST/CLI contexts where update_all_setting_values()
		// routes writes to the network option correctly.
		$hide_per_site = $this->plugin->is_network_activated() && is_admin() && ! is_network_admin();

		if (
			class_exists( '\WP_Ability' )
			&& ! $hide_per_site
		) {
			$enable_abilities_api = array(
				'name'        => 'enable_abilities_api',
				'title'       => esc_html__( 'Enable Abilities API and MCP', 'stream' ),
				'type'        => 'checkbox',
				'desc'        => esc_html__( 'Expose Stream operations to AI agents via the WordPress Abilities API (and MCP when the MCP Adapter plugin is installed). Requires WordPress 6.9.', 'stream' ),
				'after_field' => esc_html__( 'Enabled', 'stream' ),
				'default'     => 0,
			);

			array_push( $fields['advanced']['fields'], $enable_abilities_api );
		}

		/**
		 * Filter allows for modification of options fields
		 *
		 * @param array $fields Option fields.
		 *
		 * @return array Array of option fields
		 */
		$filtered_fields = apply_filters( 'wp_stream_settings_option_fields', $fields );

		// Guard against filters returning a non-array (XWPENG-47).
		$fields = is_array( $filtered_fields ) ? $filtered_fields : $fields;

		// Sort option fields in each tab by title ASC.
		foreach ( $fields as $tab => $options ) {
			$titles = array();

			foreach ( $options['fields'] as $field ) {
				$prefix = null;

				if ( ! empty( $field['sticky'] ) ) {
					$prefix = ( 'bottom' === $field['sticky'] ) ? 'ZZZ' : 'AAA';
				}

				$titles[] = $prefix . $field['title'];
			}

			array_multisort( $titles, SORT_ASC, $fields[ $tab ]['fields'] );
		}

		return $fields;
	}

	/**
	 * Return a single field definition by option key.
	 *
	 * @param string $key Option key in `{section}_{name}` form (e.g. `general_records_ttl`).
	 * @return array|null Field definition, or null when unknown.
	 */
	public function get_field( $key ) {
		foreach ( $this->get_fields() as $section_name => $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				if ( empty( $field['name'] ) ) {
					continue;
				}

				if ( $section_name . '_' . $field['name'] === $key ) {
					return $field;
				}
			}
		}

		return null;
	}

	/**
	 * Whether a field exists for the given option key.
	 *
	 * @param string $key Option key in `{section}_{name}` form (e.g. `general_records_ttl`).
	 * @return bool
	 */
	public function has_field( $key ) {
		return null !== $this->get_field( $key );
	}

	/**
	 * Build the "Reset Stream Database" settings field definition.
	 *
	 * Extracted so the async-deletion running-state check
	 * ({@see Admin_Purge::is_running_async_deletion()}) is evaluated once per render
	 * instead of once per field property, and only in admin context.
	 *
	 * `Settings::__construct` populates `$this->options = $this->get_options()`
	 * on the `init` hook for every pageload, which walks `get_fields()`. The
	 * field is only ever rendered in admin, so outside admin the dynamic state
	 * is irrelevant and the Action Scheduler query is skipped entirely.
	 *
	 * @return array
	 */
	private function build_delete_all_records_field() {
		$is_running_deletion = is_admin() ? $this->plugin->admin->purge->is_running_async_deletion() : false;

		return array(
			'name'    => 'delete_all_records',
			'title'   => esc_html__( 'Reset Stream Database', 'stream' ),
			'type'    => $is_running_deletion ? 'none' : 'link',
			'href'    => add_query_arg(
				array(
					'action'                => 'wp_stream_reset',
					'wp_stream_nonce_reset' => wp_create_nonce( 'stream_nonce_reset' ),
				),
				admin_url( 'admin-ajax.php' )
			),
			'class'   => 'warning',
			'desc'    => esc_html( $this->get_deletion_warning( $is_running_deletion ) ),
			'default' => 0,
			'sticky'  => 'bottom',
		);
	}

	/**
	 * Build the "Clean Orphaned Meta" settings field definition.
	 *
	 * Extracted so the auto-purge running-state check
	 * ({@see Admin_Purge::is_running_auto_purge()}) is evaluated once per render
	 * instead of once per field property, and only in admin context — the
	 * field is never rendered outside admin, so the Action Scheduler query
	 * is skipped on front-end pageloads.
	 *
	 * @return array
	 */
	private function build_clean_orphan_meta_field() {
		$is_running = is_admin() ? $this->plugin->admin->purge->is_running_auto_purge() : false;

		return array(
			'name'    => 'clean_orphan_meta',
			'title'   => esc_html__( 'Clean Orphaned Meta', 'stream' ),
			'type'    => $is_running ? 'none' : 'link',
			'href'    => add_query_arg(
				array(
					'action'                            => 'wp_stream_clean_orphan_meta',
					'wp_stream_nonce_clean_orphan_meta' => wp_create_nonce( 'stream_nonce_clean_orphan_meta' ),
				),
				admin_url( 'admin-ajax.php' )
			),
			'desc'    => $is_running
				? esc_html__( 'Auto-purge is currently running. The orphan reaper will execute as part of that cycle; the manual cleanup link is hidden to avoid duplicating the work.', 'stream' )
				: esc_html__( 'Schedules an immediate background cleanup of stream_meta rows whose parent record is missing. Safe to run while Stream is in use; runs once via Action Scheduler.', 'stream' ),
			'default' => 0,
			'sticky'  => 'bottom',
		);
	}

	/**
	 * Iterate through registered fields and extract default values.
	 *
	 * @return array
	 */
	public function get_defaults() {
		$fields   = $this->get_fields();
		$defaults = array();

		foreach ( $fields as $section_name => $section ) {
			foreach ( $section['fields'] as $field ) {
				$defaults[ $section_name . '_' . $field['name'] ] = isset( $field['default'] ) ? $field['default'] : null;
			}
		}

		return (array) $defaults;
	}

	/**
	 * Retrieves the deletion warning message based on the site type
	 * and whether or not there is currently a process running to delete the tables.
	 *
	 * @param bool|null $is_running_deletion Optional pre-computed deletion state.
	 *                                       Pass to avoid a duplicate Action Scheduler
	 *                                       query when the caller has already checked.
	 *                                       Defaults to checking only in admin context.
	 *                                       Untyped parameter to remain compatible with
	 *                                       phpcs.xml.dist testVersion=7.0- (nullable
	 *                                       type declarations require PHP 7.1+).
	 * @return string The deletion warning message.
	 */
	public function get_deletion_warning( $is_running_deletion = null ): string {
		if ( null === $is_running_deletion ) {
			$is_running_deletion = is_admin() ? $this->plugin->admin->purge->is_running_async_deletion() : false;
		}

		if ( $is_running_deletion ) {
			$warning = __( 'Currently deleting records. Please be patient, this can take a while.', 'stream' );
		} elseif ( $this->plugin->is_multisite_network_activated() ) {
			$warning = __( 'Warning: This will delete all activity records from the database for all sites.', 'stream' );
		} elseif ( $this->plugin->is_multisite_not_network_activated() ) {
			$warning = __( 'Warning: This will delete all activity records from the database for this site.', 'stream' );
		} else {
			$warning = __( 'Warning: This will delete all activity records from the database.', 'stream' );
		}

		return $warning;
	}

	/**
	 * Get an array of user roles.
	 *
	 * Static so the renderer can share this list without a second WP_Roles loop.
	 *
	 * @return array
	 */
	public static function get_roles() {
		$wp_roles = \wp_roles();

		if ( ! $wp_roles instanceof WP_Roles ) {
			return array();
		}

		$roles = array();

		foreach ( $wp_roles->get_names() as $role => $label ) {
			$roles[ $role ] = translate_user_role( $label );
		}

		return $roles;
	}

	/**
	 * Filter callback for site-level settings labels.
	 *
	 * @filter wp_stream_serialized_labels
	 *
	 * @param array $labels Setting labels.
	 * @return array Multidimensional array of fields
	 */
	public function filter_serialized_labels( $labels ) {
		return $this->get_settings_translations( $labels, $this->plugin->settings->option_key );
	}

	/**
	 * Get translations of serialized Stream settings.
	 *
	 * @param array  $labels     Setting labels.
	 * @param string $option_key Settings option key.
	 * @return array Multidimensional array of fields
	 */
	public function get_settings_translations( $labels, $option_key ) {
		if ( ! isset( $labels[ $option_key ] ) ) {
			$labels[ $option_key ] = array();
		}

		foreach ( $this->get_fields() as $section_slug => $section ) {
			foreach ( $section['fields'] as $field ) {
				$labels[ $option_key ][ sprintf( '%s_%s', $section_slug, $field['name'] ) ] = $field['title'];
			}
		}

		return $labels;
	}
}
