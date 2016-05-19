<?php
namespace WP_Stream;

class Alert_Trigger_Action extends Alert_Trigger {

	public $slug = 'action';
	public $meta_key = 'trigger_action';
	public $field_key = 'wp_stream_trigger_action';

	public function check_record( $success, $record_id, $recordarr, $alert ) {
		if ( ! empty( $alert->alert_meta['trigger_action'] ) && $recordarr['action'] !== $alert->alert_meta['trigger_action'][0] ) {
			return false;
		}
		return $success;
	}

	public function add_fields( $form, $alert ) {
		$value = '';
		if ( ! empty( $alert->alert_meta['trigger_action'] ) ) {
			$value = $alert->alert_meta['trigger_action'];
		}

		$args = array(
			'name'        => esc_attr( $this->field_key ),
			'value'       => esc_attr( $value ),
			'options'     => $this->get_values( $alert ),
			'placeholder' => __( 'Show all actions', 'stream' ),
		);
		$form->add_field( 'select2', $args );
	}

	public function save_fields( $alert ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );

		$input = $_POST[ $this->field_key ];
		if ( array_key_exists( $input, $this->get_values( $alert, true ) ) ) {
			$alert->alert_meta[ $this->meta_key ] = $input;
		} else {
			$alert->alert_meta[ $this->meta_key ] = '';
		}
	}

	public function get_values( $alert, $flat = false ) {
		$action_values = array();
		foreach ( $this->get_terms_labels( 'action' ) as $action_id => $action_data ) {
			if ( ! $flat ) {
				$action_values[] = array( 'id' => $action_id, 'text' => $action_data );
			} else {
				$action_values[ $action_id ] = $action_data;
			}
		}
		return $action_values;
	}

	/**
	 * Function will return all terms labels of given column
	 *
	 * @todo refactor Settings::get_terms_labels into general utility
	 * @param string $column string Name of the column.
	 * @return array
	 */
	public function get_terms_labels( $column ) {
		$return_labels = array();

		if ( isset( $this->plugin->connectors->term_labels[ 'stream_' . $column ] ) ) {
			if ( 'context' === $column && isset( $this->plugin->connectors->term_labels['stream_connector'] ) ) {
				$connectors = $this->plugin->connectors->term_labels['stream_connector'];
				$contexts   = $this->plugin->connectors->term_labels['stream_context'];

				foreach ( $connectors as $connector => $connector_label ) {
					$return_labels[ $connector ]['label'] = $connector_label;
					foreach ( $contexts as $context => $context_label ) {
						if ( isset( $this->plugin->connectors->contexts[ $connector ] ) && array_key_exists( $context, $this->plugin->connectors->contexts[ $connector ] ) ) {
							$return_labels[ $connector ]['children'][ $context ] = $context_label;
						}
					}
				}
			} else {
				$return_labels = $this->plugin->connectors->term_labels[ 'stream_' . $column ];
			}

			ksort( $return_labels );
		}
		return $return_labels;
	}

	function get_display_value( $context = 'normal', $alert ) {
		$action = ( ! empty( $alerts->alert_meta['trigger_action'] ) ) ? $alert->alert_meta['trigger_action'] : null;
		if ( empty( $action ) ) {
			if ( 'post_title' === $context ) {
				$action = __( 'preformed any action on', 'stream' );
			} else {
				$action = __( 'Any Action', 'stream' );
			}
		} else {
			$action = ucfirst( $action );
		}

		return $action;
	}

	public function filter_preview_query( $query_args, $alert ) {
		if ( ! empty( $alert->alert_meta['trigger_action'] ) ) {
			$query_args['action'] = $alert->alert_meta['trigger_action'];
		}
		return $query_args;
	}
}
