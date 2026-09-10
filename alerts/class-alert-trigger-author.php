<?php
/**
 * Trigger for an Author.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Trigger_Author
 *
 * @package WP_Stream
 */
class Alert_Trigger_Author extends Alert_Trigger {

	/**
	 * Unique identifier
	 */
	public string $slug = 'author';

	/**
	 * Field key used in database
	 */
	public string $field_key = 'wp_stream_trigger_author';

	/**
	 * Checks if a record matches the criteria from the trigger.
	 *
	 * @see Alert_Trigger::check_record().
	 *
	 * @param bool  $success Status of previous checks.
	 * @param int   $record_id Record ID.
	 * @param array $recordarr Record data.
	 * @param Alert $alert The Alert being worked on.
	 *
	 * @return bool False on failure, otherwise should return original value of $success.
	 */
	public function check_record( $success, $record_id, $recordarr, $alert ) {
		// The stored value may be '0' (WP-CLI), which empty() would drop and
		// turn the trigger into "any author".
		$trigger_author = isset( $alert->alert_meta['trigger_author'] ) ? (string) $alert->alert_meta['trigger_author'] : '';
		if ( '' !== $trigger_author && (int) $trigger_author !== (int) $recordarr['user_id'] ) {
			return false;
		}

		return $success;
	}

	/**
	 * Adds fields to the trigger form.
	 *
	 * @see Alert_Trigger::add_fields().
	 *
	 * @param Form_Generator $form The Form Object to add to.
	 * @param Alert          $alert The Alert being worked on.
	 *
	 * @return void
	 */
	public function add_fields( $form, $alert = array() ) {
		$value = '';
		if ( is_object( $alert ) && ! empty( $alert->alert_meta['trigger_author'] ) ) {
			$value = $alert->alert_meta['trigger_author'];
		}

		$picker = $this->plugin->user_picker->get( $this->plugin->admin->get_preload_users_max() );

		// Over the preload cap: Ajax user combobox instead of a preloaded select.
		if ( $picker['ajax'] ) {
			$form->add_field(
				'user_combobox',
				array(
					'name'           => esc_attr( $this->field_key ),
					'value'          => esc_attr( $value ),
					'selected_label' => $this->plugin->user_picker->label_for_value( $value ),
					'data'           => array(
						'placeholder' => __( 'Any Author', 'stream' ),
					),
				)
			);
			return;
		}

		$form->add_field(
			'grouped_select',
			array(
				'name'    => esc_attr( $this->field_key ),
				'value'   => esc_attr( $value ),
				'options' => $this->append_stored_value_option( $this->get_values(), $value ),
				'data'    => array(
					'placeholder' => __( 'Any Author', 'stream' ),
				),
			)
		);
	}

	/**
	 * Generate array of possible action values
	 *
	 * @return array
	 */
	public function get_values() {
		$all_records = array();

		$users = array_map(
			function ( $user_id ) {
				return new Author( $user_id );
			},
			get_users(
				array(
					'fields' => 'ID',
				)
			)
		);

		if ( is_multisite() && is_super_admin() ) {
			$super_admins = array_map(
				function ( $login ) {
					$user = get_user_by( 'login', $login );

					return new Author( $user->ID );
				},
				get_super_admins()
			);
			$users        = array_unique( array_merge( $users, $super_admins ) );
		}

		$user_meta = array(
			'is_wp_cli' => true,
		);
		$users[]   = new Author( 0, $user_meta );

		foreach ( $users as $user ) {
			$all_records[] = array(
				'id'    => $user->id,
				'value' => $user->id,
				'text'  => $this->plugin->user_picker->label( $user->id ),
			);
		}

		return $all_records;
	}

	/**
	 * Validate and save Alert object
	 *
	 * @see Alert_Trigger::save_fields().
	 *
	 * @param Alert $alert The Alert being worked on.
	 *
	 * @return void
	 */
	public function save_fields( $alert ) {
		$input = wp_stream_filter_input( INPUT_POST, $this->field_key );
		$input = is_scalar( $input ) ? (string) $input : '';

		// Only a user ID (or 0 for WP-CLI) is stored; anything else clears the
		// trigger. Membership checks are impossible in combobox (Ajax) mode,
		// where the option list is not rendered server-side.
		$alert->alert_meta['trigger_author'] = ctype_digit( $input ) ? $input : '';
	}

	/**
	 * Append the stored author to the option list when missing (e.g. deleted user).
	 *
	 * @param array  $options Picker options.
	 * @param string $current Stored author id.
	 * @return array
	 */
	private function append_stored_value_option( array $options, $current ) {
		if ( ! ctype_digit( (string) $current ) ) {
			return $options;
		}

		$values = array_map( 'strval', array_column( $options, 'value' ) );
		if ( in_array( (string) $current, $values, true ) ) {
			return $options;
		}

		$options[] = array(
			'value' => (string) $current,
			'text'  => $this->plugin->user_picker->label( (int) $current ),
		);

		return $options;
	}

	/**
	 * Returns the trigger's value for the given alert.
	 *
	 * @see Alert_Trigger::get_display_value().
	 *
	 * @param string     $context The location this data will be displayed in.
	 * @param Alert|null $alert Alert being processed.
	 *
	 * @return string
	 */
	public function get_display_value( $context = 'normal', $alert = null ) {
		// Note: the stored value may be '0' (WP-CLI), which is falsy — use isset().
		$trigger_author = $alert?->alert_meta['trigger_author'] ?? '';

		if ( '' === $trigger_author ) {
			return __( 'Any User', 'stream' );
		}

		if ( ctype_digit( $trigger_author ) ) {
			return $this->plugin->user_picker->label( (int) $trigger_author );
		}

		return ucfirst( $trigger_author );
	}
}
