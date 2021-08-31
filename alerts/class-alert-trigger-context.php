<?php
/**
 * Trigger on Context.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Trigger_Context
 *
 * @package WP_Stream
 */
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
	 *
	 * @param bool  $success Status of previous checks.
	 * @param int   $record_id Record ID.
	 * @param array $recordarr Record data.
	 * @param Alert $alert The Alert being worked on.
	 *
	 * @return bool False on failure, otherwise should return original value of $success.
	 */
	public function check_record( $success, $record_id, $recordarr, $alert ) {
		if ( ! empty( $alert->alert_meta['trigger_connector'] ) && $recordarr['connector'] !== $alert->alert_meta['trigger_connector'] ) {
			return false;
		}
		if ( ! empty( $alert->alert_meta['trigger_context'] ) && $recordarr['context'] !== $alert->alert_meta['trigger_context'] ) {
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
		$connector = '';
		if ( is_object( $alert ) && ! empty( $alert->alert_meta['trigger_connector'] ) ) {
			$connector = $alert->alert_meta['trigger_connector'];
		}

		$context = '';
		if ( is_object( $alert ) && ! empty( $alert->alert_meta['trigger_context'] ) ) {
			$context = $alert->alert_meta['trigger_context'];
		}

		// Context dropdown menu.
		$context_values = array();

		$form->add_field(
			'select2',
			array(
				'name'    => 'wp_stream_trigger_connector_or_context',
				'options' => $this->get_values(),
				'classes' => 'wp_stream_ajax_forward connector_or_context',
				'data'    => array(
					'placeholder' => __( 'Any Context', 'stream' ),
				),
			)
		);

		$form->add_field(
			'hidden',
			array(
				'name'    => 'wp_stream_trigger_connector',
				'value'   => $connector,
				'classes' => 'connector wp_stream_ajax_forward',
			)
		);

		$form->add_field(
			'hidden',
			array(
				'name'    => 'wp_stream_trigger_context',
				'value'   => $context,
				'classes' => 'context wp_stream_ajax_forward',
			)
		);
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
		$alert->alert_meta['trigger_connector'] = wp_stream_filter_input( INPUT_POST, 'wp_stream_trigger_connector' );
		$alert->alert_meta['trigger_context']   = wp_stream_filter_input( INPUT_POST, 'wp_stream_trigger_context' );
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
						$child_values[] = array(
							'value'  => $context_id . '-' . $child_id,
							'id'     => $context_id . '-' . $child_id,
							'text'   => $child_value,
							'parent' => $context_id,
						);
					}
				}
				if ( isset( $context_data['label'] ) ) {
					$context_values[] = array(
						'value'    => $context_id,
						'id'       => $context_id,
						'text'     => $context_data['label'],
						'children' => $child_values,
					);
				}
			} else {
				$context_values[] = array(
					'value' => $context_id,
					'id'    => $context_id,
					'text'  => $context_data,
				);
			}
		}

		return $context_values;
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
	 * @param string     $context The location this data will be displayed in.
	 * @param Alert|null $alert Alert being processed.
	 *
	 * @return string
	 */
	public function get_display_value( $context = 'normal', $alert = null ) {
		$context   = ( ! empty( $alert->alert_meta['trigger_context'] ) ) ? $alert->alert_meta['trigger_context'] : null;
		$connector = ( ! empty( $alert->alert_meta['trigger_connector'] ) ) ? $alert->alert_meta['trigger_connector'] : null;
		if ( empty( $context ) && empty( $connector ) ) {
			$context = __( 'Any Context', 'stream' );
		} else {
			$term_labels = $this->get_terms_labels( 'context' );
			if ( ! empty( $term_labels[ $connector ]['children'][ $context ] ) ) {
				$context = $term_labels[ $connector ]['children'][ $context ];
			} else {
				if ( ! empty( $term_labels[ $connector ]['label'] ) ) {
					$context = $term_labels[ $connector ]['label'];
				}
			}
		}

		return ucfirst( $context );
	}
}
