<?php
/**
 * Trigger on an Action.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Trigger_Action
 *
 * @package WP_Stream
 */
class Alert_Trigger_Action extends Alert_Trigger {

	/**
	 * Unique identifier
	 *
	 * @var string
	 */
	public $slug = 'action';

	/**
	 * Meta key used in forms.
	 *
	 * @var string
	 */
	public $meta_key = 'trigger_action';

	/**
	 * Field key used in database
	 *
	 * @var string
	 */
	public $field_key = 'wp_stream_trigger_action';

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
		if ( ! empty( $alert->alert_meta['trigger_action'] ) && $recordarr['action'] !== $alert->alert_meta['trigger_action'] ) {
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
		if ( is_object( $alert ) && ! empty( $alert->alert_meta['trigger_action'] ) ) {
			$value = $alert->alert_meta['trigger_action'];
		}

		$args = array(
			'name'    => esc_attr( $this->field_key ),
			'value'   => esc_attr( $value ),
			'options' => $this->get_values(),
			'classes' => 'wp_stream_ajax_forward',
			'data'    => array(
				'placeholder' => __( 'Any Action', 'stream' ),
			),
		);
		$form->add_field( 'select2', $args );
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
		if ( array_key_exists( $input, $this->get_values( true ) ) ) {
			$alert->alert_meta['trigger_action'] = $input;
		} else {
			$alert->alert_meta['trigger_action'] = '';
		}
	}

	/**
	 * Generate array of possible action values
	 *
	 * @param bool $flat If the array should be multidimensional.
	 *
	 * @return array
	 */
	public function get_values( $flat = false ) {
		$action_values = array();
		foreach ( $this->get_terms_labels( 'action' ) as $action_id => $action_data ) {
			if ( ! $flat ) {
				$action_values[] = array(
					'id'   => $action_id,
					'text' => $action_data,
				);
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
	 *
	 * @param string $column string Name of the column.
	 *
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
	 *
	 * @param string $context The location this data will be displayed in.
	 * @param Alert  $alert Alert being processed.
	 *
	 * @return string
	 */
	public function get_display_value( $context = 'normal', $alert ) {
		$action = ( ! empty( $alert->alert_meta['trigger_action'] ) ) ? $alert->alert_meta['trigger_action'] : null;

		if ( 'post_title' === $context ) {
			if ( empty( $action ) ) {
				$action = __( 'performed any action on', 'stream' );
			}
		} else {
			if ( empty( $action ) ) {
				$action = __( 'Any Action', 'stream' );
			} else {
				$actions = $this->plugin->connectors->term_labels['stream_action'];
				if ( ! empty( $actions[ $action ] ) ) {
					$action = $actions[ $action ];
				}
				$action = ucfirst( $action );
			}
		}

		return ucfirst( $action );
	}
}
