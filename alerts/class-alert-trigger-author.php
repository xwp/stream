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
	 *
	 * @var string
	 */
	public $slug = 'author';

	/**
	 * Field key used in database
	 *
	 * @var string
	 */
	public $field_key = 'wp_stream_trigger_author';

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
		if ( ! empty( $alert->alert_meta['trigger_author'] ) && intval( $alert->alert_meta['trigger_author'] ) !== intval( $recordarr['user_id'] ) ) {
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

		$args = array(
			'name'    => esc_attr( $this->field_key ),
			'value'   => esc_attr( $value ),
			'options' => $this->get_values(),
			'classes' => 'wp_stream_ajax_forward',
			'data'    => array(
				'placeholder' => __( 'Any Author', 'stream' ),
			),
		);
		$form->add_field( 'select2', $args );
	}

	/**
	 * Generate array of possible action values
	 *
	 * @return array
	 */
	public function get_values() {
		$all_records = array();

		$user_count  = count_users();
		$total_users = $user_count['total_users'];

		if ( $total_users > $this->plugin->admin->preload_users_max ) {
			return array();
		}

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
				'text'  => $user->get_display_name(),
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
		if ( array_key_exists( $input, $this->get_values( $alert ) ) ) {
			$alert->alert_meta['trigger_author'] = $input;
		} else {
			$alert->alert_meta['trigger_author'] = '';
		}
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
		$author = ( ! empty( $alert->alert_meta['trigger_author'] ) ) ? $alert->alert_meta['trigger_author'] : null;
		if ( empty( $author ) ) {
			$author = __( 'Any User', 'stream' );
		} elseif ( is_numeric( $author ) ) {
			$author_data = get_userdata( $author );
			if ( $author_data ) {
				$author = $author_data->display_name;
			} else {
				$author = __( 'Unknown User', 'stream' );
			}
		}

		return ucfirst( $author );
	}
}
