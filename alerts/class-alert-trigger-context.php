<?php
namespace WP_Stream;

class Alert_Trigger_Context extends Alert_Trigger {

	/**
	 * Unique identifier
	 *
	 * @var string
	 */
	public $slug = 'context';

	/**
	 * Field key used in database
	 *
	 * @var string
	 */
	public $field_key = 'wp_stream_trigger_context';

	/**
	 * Checks if a record matches the criteria from the trigger.
	 *
	 * @see Alert_Trigger::check_record().
	 * @param bool  $success Status of previous checks.
	 * @param int   $record_id Record ID.
	 * @param array $recordarr Record data.
	 * @param Alert $alert The Alert being worked on.
	 * @return bool False on failure, otherwise should return original value of $success.
	 */
	public function check_record( $success, $record_id, $recordarr, $alert ) {
		if ( ! empty( $alert->alert_meta['trigger_context'] ) && $recordarr['context'] !== $alert->alert_meta['trigger_context'] ) {
			return false;
		}
		return $success;
	}

	/**
	 * Adds fields to the trigger form.
	 *
	 * @see Alert_Trigger::add_fields().
	 * @param Form_Generator $form The Form Object to add to.
	 * @param Alert          $alert The Alert being worked on.
	 * @return void
	 */
	public function add_fields( $form, $alert ) {
		$value = '';
		if ( ! empty( $alert->alert_meta['trigger_context'] ) ) {
			$value = $alert->alert_meta['trigger_context'];
		}

		$args = array(
			'name'        => esc_attr( $this->field_key ),
			'value'       => esc_attr( $value ),
			'options'     => $this->get_values( $alert ),
			'placeholder' => __( 'Show all contexts', 'stream' ),
			'classes'     => 'wp_stream_ajax_forward',
		);
		$form->add_field( 'select2', $args );
	}

	/**
	 * Validate and save Alert object
	 *
	 * @see Alert_Trigger::save_fields().
	 * @param Alert $alert The Alert being worked on.
	 * @return void
	 */
	public function save_fields( $alert ) {
		$alert->alert_meta['trigger_context'] = wp_stream_filter_input( INPUT_POST, $this->field_key );
	}

	/**
	 * Generate array of possible action values
	 *
	 * @return array
	 */
	public function get_values() {
		$context_values = array();
		foreach ( $this->get_terms_labels( 'context' ) as $context_id => $context_data ) {
			if ( is_array( $context_data ) ) {
				$child_values = array();
				if ( isset( $context_data['children'] ) ) {
					$child_values = array();
					foreach ( $context_data['children'] as $child_id => $child_value ) {
						$child_values[] = array( 'id' => $child_id, 'text' => $child_value, 'parent' => $context_id );
					}
				}
				if ( isset( $context_data['label'] ) ) {
					$context_values[] = array( 'id' => 'group-' . $context_id, 'text' => $context_data['label'], 'children' => $child_values, 'group' => true );
				}
			} else {
				$context_values[] = array( 'id' => $context_id, 'text' => $context_data );
			}
		}
		return $context_values;
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

	/**
	 * Returns the trigger's value for the given alert.
	 *
	 * @see Alert_Trigger::get_display_value().
	 * @param string $context The location this data will be displayed in.
	 * @param Alert  $alert Alert being processed.
	 * @return string
	 */
	function get_display_value( $context = 'normal', $alert ) {
		$context = ( ! empty( $alert->alert_meta['trigger_context'] ) ) ? $alert->alert_meta['trigger_context'] : null;
		if ( empty( $context ) ) {
			$context = __( 'Any Context', 'stream' );
		} elseif ( strpos( $context, 'group-' ) === 0 ) {
			$context = substr( $context, strlen( 'group-' ) );
		}
		return ucfirst( $context );
	}

	/**
	 * Alters the preview table query to show records matching this query.
	 *
	 * @see Alert_Trigger::filter_preview_query().
	 * @param array $query_args The database query arguments for the table.
	 * @param Alert $alert The Alert being worked on.
	 * @return array The new query arguments.
	 */
	public function filter_preview_query( $query_args, $alert ) {
		if ( ! empty( $alert->alert_meta['trigger_context'] ) ) {
			if ( 0 === strpos( $alert->alert_meta['trigger_context'], 'group-' ) ) {
				$query_args['connector'] = str_replace( 'group-', '', $alert->alert_meta['trigger_context'] );
				$query_args['context']   = '';
			} else {
					$query_args['context'] = $alert->alert_meta['trigger_context'];
			}
		}
		return $query_args;
	}
}
