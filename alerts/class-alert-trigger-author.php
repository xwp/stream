<?php
namespace WP_Stream;

class Alert_Trigger_Author extends Alert_Trigger {

	public $slug = 'author';
	public $field_key = 'wp_stream_trigger_author';

	public function check_record( $success, $record_id, $recordarr, $alert ) {
		if ( ! empty( $alert->alert_meta['trigger_author'] ) && intval( $alert->alert_meta['trigger_author'] ) !== $recordarr['user_id'] ) {
			return false;
		}
		return $success;
	}

	public function add_fields( $form, $alert ) {
		$value = '';
		if ( ! empty( $alert->alert_meta['trigger_author'] ) ) {
			$value = $alert->alert_meta['trigger_author'];
		}

		$args = array(
			'name'        => esc_attr( $this->field_key ),
			'value'       => esc_attr( $value ),
			'options'     => $this->get_values( $alert ),
			'placeholder' => __( 'Show all users', 'stream' ),
		);
		$form->add_field( 'select2', $args );
	}

	public function get_values( $alert ) {
		$all_records = array();

		// If the number of users exceeds the max users constant value then return an empty array and use AJAX instead
		$user_count  = count_users();
		$total_users = $user_count['total_users'];

		if ( $total_users > $this->plugin->admin->preload_users_max ) {
			return array();
		}

		$users = array_map(
			function( $user_id ) {
				return new Author( $user_id );
			},
			get_users( array( 'fields' => 'ID' ) )
		);

		if ( is_multisite() && is_super_admin() ) {
			$super_admins = array_map(
				function( $login ) {
					$user = get_user_by( 'login', $login );
					return new Author( $user->ID );
				},
				get_super_admins()
			);
			$users = array_unique( array_merge( $users, $super_admins ) );
		}

		$users[] = new Author( 0, array( 'is_wp_cli' => true ) );

		foreach ( $users as $user ) {
			$all_records[] = array(
				'id'   => $user->id,
				'text' => $user->get_display_name(),
			);
		}
		return $all_records;
	}

	public function save_fields( $alert ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );
		$alert->alert_meta['trigger_author'] = wp_stream_filter_input( INPUT_POST, $this->field_key );
	}

	function get_display_value( $context = 'normal', $alert ) {
		$author = ( ! empty( $alert->alert_meta['trigger_author'] ) ) ? $alert->alert_meta['trigger_author'] : null;
		if ( empty( $author ) ) {
			$author = __( 'Any Author', 'stream' );
		} else if ( is_numeric( $author ) ) {
			$author_data = get_userdata( $author );
			if ( $author_data ) {
				$author = $author_data->display_name;
			} else {
				$author = __( 'Unknown User', 'stream' );
			}
		}
		return ucfirst( $author );
	}

	public function filter_preview_query( $query_args, $alert ) {
		if ( ! empty( $alert->alert_meta['trigger_author'] ) ) {
			$query_args['user_id'] = $alert->alert_meta['trigger_author'];
		}
		return $query_args;
	}
}
