<?php
/**
 * Settings class
 *
 * @author X-Team <x-team.com>
 * @author Shady Sharaf <shady@x-team.com>
 */
class WP_Stream_Settings {

	/**
	 * Settings key/identifier
	 */
	const KEY = 'wp_stream';

	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	public static $options = array();

	/**
	 * Public constructor
	 *
	 * @return \WP_Stream_Settings
	 */
	public static function load() {

		// Parse field information gathering default values
		$defaults = self::get_defaults();

		// Get options
		self::$options = apply_filters(
			'wp_stream_options',
			wp_parse_args(
				(array) get_option( self::KEY, array() ),
				$defaults
			)
		);

		// Register settings, and fields
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Check if we need to flush rewrites rules
		add_action( 'update_option_' . self::KEY  , array( __CLASS__, 'updated_option_trigger_flush_rules' ), 10, 2 );
	}

	/**
	 * Return settings fields
	 *
	 * @return array Multidimensional array of fields
	 */
	public static function get_fields() {
		$fields = array(
			'general' => array(
				'title'  => __( 'General', 'stream' ),
				'fields' => array(
					array(
						'name'        => 'log_activity_for',
						'title'       => __( 'Log Activity for', 'stream' ),
						'type'        => 'multi_checkbox',
						'desc'        => __( 'Only the selected roles above will have their activity logged.', 'stream' ),
						'choices'     => self::get_roles(),
						'default'     => array_keys( self::get_roles() ),
					),
					array(
						'name'        => 'role_access',
						'title'       => __( 'Role Access', 'stream' ),
						'type'        => 'multi_checkbox',
						'desc'        => __( 'Users from the selected roles above will have permission to view Stream Records. However, only site Administrators can access Stream Settings.', 'stream' ),
						'choices'     => self::get_roles(),
						'default'     => array( 'administrator' ),
					),
					array(
						'name'        => 'private_feeds',
						'title'       => __( 'Private Feeds', 'stream' ),
						'type'        => 'checkbox',
						'desc'        => sprintf(
							__( 'Users from the selected roles above will be given a private key found in their %suser profile%s to access feeds of Stream Records securely.', 'stream' ),
							sprintf(
								'<a href="%s" title="%s">',
								admin_url( 'profile.php' ),
								esc_attr__( 'View Profile', 'stream' )
							),
							'</a>'
						),
						'after_field' => __( 'Enabled' ),
						'default'     => 0,
					),
					array(
						'name'        => 'records_ttl',
						'title'       => __( 'Keep Records for', 'stream' ),
						'type'        => 'number',
						'class'       => 'small-text',
						'desc'        => __( 'Maximum number of days to keep activity records. Leave blank to keep records forever.', 'stream' ),
						'default'     => 90,
						'after_field' => __( 'days', 'stream' ),
					),
					array(
						'name'        => 'delete_all_records',
						'title'       => __( 'Delete All Records', 'stream' ),
						'type'        => 'link',
						'href'        => add_query_arg(
							array(
								'action'          => 'wp_stream_reset',
								'wp_stream_nonce' => wp_create_nonce( 'stream_nonce' ),
							),
							admin_url( 'admin-ajax.php' )
						),
						'desc'        => __( 'Warning: Clicking this will delete all activity records from the database.', 'stream' ),
						'default'     => 0,
					),
				),
			),
		);

		return apply_filters( 'wp_stream_options_fields', $fields );
	}

	/**
	 * Iterate through registered fields and extract default values
	 *
	 * @return array Default option values
	 */
	public static function get_defaults() {
		$fields   = self::get_fields();
		$defaults = array();
		foreach ( $fields as $section_name => $section ) {
			foreach ( $section['fields'] as $field ) {
				$defaults[$section_name.'_'.$field['name']] = isset( $field['default'] )
					? $field['default']
					: null;
			}
		}
		return $defaults;
	}

	/**
	 * Registers settings fields and sections
	 *
	 * @return void
	 */
	public static function register_settings() {

		$sections = self::get_fields();

		register_setting( self::KEY, self::KEY );

		foreach ( $sections as $section_name => $section ) {
			add_settings_section(
				$section_name,
				null,
				'__return_false',
				self::KEY
			);

			foreach ( $section['fields'] as $field_idx => $field ) {
				if ( ! isset( $field['type'] ) ) { // No field type associated, skip, no GUI
					continue;
				}
				add_settings_field(
					$field['name'],
					$field['title'],
					( isset( $field['callback'] ) ? $field['callback'] : array( __CLASS__, 'output_field' ) ),
					self::KEY,
					$section_name,
					$field + array(
						'section'   => $section_name,
						'label_for' => sprintf( '%s_%s_%s', self::KEY, $section_name, $field['name'] ), // xss ok
					)
				);
			}
		}
	}

	/**
	 * Check if we have updated a settings that requires rewrite rules to be flushed
	 *
	 * @param array $old_value
	 * @param array $new_value
	 *
	 * @internal param $option
	 * @internal param string $option
	 * @action   updated_option
	 * @return void
	 */
	public static function updated_option_trigger_flush_rules( $old_value, $new_value ) {
		if ( is_array( $new_value ) && is_array( $old_value ) ) {
			$new_value = ( array_key_exists( 'general_private_feeds', $new_value ) ) ? $new_value['general_private_feeds'] : 0;
			$old_value = ( array_key_exists( 'general_private_feeds', $old_value ) ) ? $old_value['general_private_feeds'] : 0;
			if ( $new_value !== $old_value ) {
				delete_option( 'rewrite_rules' );
			}
		}
	}

	/**
	 * Compile HTML needed for displaying the field
	 *
	 * @param  array  $field  Field settings
	 * @return string         HTML to be displayed
	 */
	public static function render_field( $field ) {

		$output = null;

		$type        = isset( $field['type'] ) ? $field['type'] : null;
		$section     = isset( $field['section'] ) ? $field['section'] : null;
		$name        = isset( $field['name'] ) ? $field['name'] : null;
		$class       = isset( $field['class'] ) ? $field['class'] : null;
		$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : null;
		$description = isset( $field['desc'] ) ? $field['desc'] : null;
		$href        = isset( $field['href'] ) ? $field['href'] : null;
		$after_field = isset( $field['after_field'] ) ? $field['after_field'] : null;

		if ( ! $type || ! $section || ! $name ) {
			return;
		}

		if ( 'multi_checkbox' === $type
			&& ( empty( $field['choices'] ) || ! is_array( $field['choices'] ) )
		) {
			return;
		}

		switch ( $type ) {
			case 'text':
			case 'number':
				$output = sprintf(
					'<input type="%1$s" name="%2$s[%3$s_%4$s]" id="%2$s_%3$s_%4$s" class="%5$s" placeholder="%6$s" value="%7$s" /> %8$s',
					esc_attr( $type ),
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $placeholder ),
					esc_attr( self::$options[$section . '_' . $name] ),
					esc_html( $after_field )
				);
				break;
			case 'checkbox':
				$output = sprintf(
					'<label><input type="checkbox" name="%1$s[%2$s_%3$s]" id="%1$s[%2$s_%3$s]" value="1" %4$s /> %5$s</label>',
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name ),
					checked( self::$options[$section . '_' . $name], 1, false ),
					esc_html( $after_field )
				);
				break;
			case 'multi_checkbox':
				$current_value = (array) self::$options[$section . '_' . $name];

				$output = sprintf(
					'<div id="%1$s[%2$s_%3$s]"><fieldset>',
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				foreach ( $field['choices'] as $value => $label ) {
					$output .= sprintf(
						'<label>%1$s <span>%2$s</span></label><br />',
						sprintf(
							'<input type="checkbox" name="%1$s[%2$s_%3$s][]" value="%4$s" %5$s />',
							esc_attr( self::KEY ),
							esc_attr( $section ),
							esc_attr( $name ),
							esc_attr( $value ),
							checked( in_array( $value, $current_value ), true, false )
						),
						esc_html( $label )
					);
				}
				$output .= '</fieldset></div>';
				break;
			case 'link':
				$output = sprintf(
					'<a id="%1$s_%2$s_%3$s" class="%4$s" href="%5$s">%6$s</a>',
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $href ),
					__( 'Reset Stream Database', 'stream' )
				);
				break;
		}

		$output .= ! empty( $description ) ? sprintf( '<p class="description">%s</p>', $description /* xss ok */ ) : null;

		return $output;
	}

	/**
	 * Render Callback for post_types field
	 *
	 * @param array $field
	 *
	 * @internal param $args
	 * @return void
	 */
	public static function output_field( $field ) {
		$method = 'output_' . $field['name'];
		if ( method_exists( __CLASS__, $method ) ) {
			return call_user_func( array( __CLASS__, $method ), $field );
		}

		$output = self::render_field( $field );
		echo $output; // xss okay
	}

	/**
	 * Get an array of user roles
	 *
	 * @return array
	 */
	public static function get_roles() {
		$wp_roles = new WP_Roles();
		$roles    = array();

		foreach ( $wp_roles->get_names() as $role => $label ) {
			$roles[ $role ] = translate_user_role( $label );
		}

		return $roles;
	}
}
