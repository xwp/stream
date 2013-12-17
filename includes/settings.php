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
	}

	/**
	 * Return settings fields
	 *
	 * @return array Multidimensional array of fields
	 */
	public static function get_fields() {
		return array(
			'general' => array(
				'title'  => __( 'General', 'stream' ),
				'fields' => array(
					array(
						'name'        => 'role_access',
						'title'       => __( 'Role Access', 'stream' ),
						'type'        => 'multi_checkbox',
						'desc'        => __( 'Users from the selected roles above will have permission to view Stream Records. However, only site Administrators can access Stream Settings.', 'stream' ),
						'choices'     => self::get_roles(),
						'default'     => array( 'administrator' ),
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
						'href'        => admin_url( 'admin-ajax.php?action=wp_stream_reset' ),
						'desc'        => __( 'Warning: Clicking this will delete all activity records from the database.', 'stream' ),
						'default'     => 0,
					),
					array(
						'name'        => 'private_feeds',
						'title'       => __( 'Private Feeds', 'stream' ),
						'type'        => 'checkbox',
						'desc'        => __( 'Users from the selected roles above will be given a private Feed URL in their User Profile.  Please flush rewrite rules on your site after changing this setting.', 'stream' ),
						'after_field' => __( 'Enabled' ),
						'default'     => 0,
					),
				),
			),
		);
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
					__( 'Reset Stream Database', 'streams' )
				);
				break;
		}

		$output .= ! empty( $description ) ? sprintf( '<p class="description">%s</p>', $description /* xss ok */ ) : null;

		return $output;
	}

	/**
	 * Render Callback for post_types field
	 *
	 * @param $args
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
	 * Get translated user roles
	 *
	 * @return array
	 */
	public static function get_roles() {
		global $wp_roles;

		// If a plugin has previously registered roles but that plugin has been deactivated WordPress
		// will throw a non-object notice here even though those roles will still be returned.
		// So we'll suppress any notices here just to avoid unnecessary confusion.
		$role_names = @$wp_roles->role_names;

		$roles = array();

		foreach ( (array) $role_names as $role_name => $role_label ) {
			$roles[ $role_name ] = translate_user_role( $role_label );
		}

		return $roles;
	}
}
