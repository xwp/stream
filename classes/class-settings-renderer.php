<?php
/**
 * Renders Stream settings field HTML.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use WP_User;

/**
 * Class - Settings_Renderer
 */
class Settings_Renderer {

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Compile HTML needed for displaying the field.
	 *
	 * @param array  $field      Field settings.
	 * @param array  $options    Current option values keyed by `{section}_{name}`.
	 * @param string $option_key Settings option key.
	 * @return string HTML to be displayed
	 */
	public function render_field( $field, $options, $option_key ) {
		$output      = null;
		$type        = isset( $field['type'] ) ? $field['type'] : null;
		$section     = isset( $field['section'] ) ? $field['section'] : null;
		$name        = isset( $field['name'] ) ? $field['name'] : null;
		$class       = isset( $field['class'] ) ? $field['class'] : null;
		$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : null;
		$description = isset( $field['desc'] ) ? $field['desc'] : null;
		$href        = isset( $field['href'] ) ? $field['href'] : null;
		$rows        = isset( $field['rows'] ) ? $field['rows'] : 10;
		$cols        = isset( $field['cols'] ) ? $field['cols'] : 50;
		$after_field = isset( $field['after_field'] ) ? $field['after_field'] : null;
		$default     = isset( $field['default'] ) ? $field['default'] : null;
		$min         = isset( $field['min'] ) ? $field['min'] : 0;
		$max         = isset( $field['max'] ) ? $field['max'] : 999;
		$step        = isset( $field['step'] ) ? $field['step'] : 1;
		$title       = isset( $field['title'] ) ? $field['title'] : null;
		$nonce       = isset( $field['nonce'] ) ? $field['nonce'] : null;

		if ( isset( $field['value'] ) ) {
			$current_value = $field['value'];
		} elseif ( isset( $options[ $section . '_' . $name ] ) ) {
			$current_value = $options[ $section . '_' . $name ];
		} else {
			$current_value = null;
		}

		unset( $nonce );

		if ( is_callable( $current_value ) ) {
			$current_value = call_user_func( $current_value );
		}

		if ( ! $type || ! $section || ! $name ) {
			return '';
		}

		if ( 'multi_checkbox' === $type && ( empty( $field['choices'] ) || ! is_array( $field['choices'] ) ) ) {
			return '';
		}

		switch ( $type ) {
			case 'text':
			case 'number':
				$output = sprintf(
					'<input type="%1$s" name="%2$s[%3$s_%4$s]" id="%2$s_%3$s_%4$s" class="%5$s" placeholder="%6$s" min="%7$d" max="%8$d" step="%9$d" value="%10$s" /> %11$s',
					esc_attr( $type ),
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $placeholder ),
					esc_attr( $min ),
					esc_attr( $max ),
					esc_attr( $step ),
					esc_attr( $current_value ),
					wp_kses_post( $after_field )
				);
				break;
			case 'textarea':
				$output = sprintf(
					'<textarea name="%1$s[%2$s_%3$s]" id="%1$s_%2$s_%3$s" class="%4$s" placeholder="%5$s" rows="%6$d" cols="%7$d">%8$s</textarea> %9$s',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $placeholder ),
					absint( $rows ),
					absint( $cols ),
					esc_textarea( $current_value ),
					wp_kses_post( $after_field )
				);
				break;
			case 'checkbox':
				if ( isset( $current_value ) ) {
					$value = $current_value;
				} elseif ( isset( $default ) ) {
					$value = $default;
				} else {
					$value = 0;
				}

				$output = sprintf(
					'<label><input type="checkbox" name="%1$s[%2$s_%3$s]" id="%1$s[%2$s_%3$s]" value="1" %4$s /> %5$s</label>',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					checked( $value, 1, false ),
					wp_kses_post( $after_field )
				);
				break;
			case 'multi_checkbox':
				$output = sprintf(
					'<div id="%1$s[%2$s_%3$s]"><fieldset>',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				// Fallback if nothing is selected.
				$output       .= sprintf(
					'<input type="hidden" name="%1$s[%2$s_%3$s][]" value="__placeholder__" />',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$current_value = (array) $current_value;
				$choices       = $field['choices'];
				if ( is_callable( $choices ) ) {
					$choices = call_user_func( $choices );
				}
				foreach ( $choices as $value => $label ) {
					$output .= sprintf(
						'<label>%1$s <span>%2$s</span></label><br />',
						sprintf(
							'<input type="checkbox" name="%1$s[%2$s_%3$s][]" value="%4$s" %5$s />',
							esc_attr( $option_key ),
							esc_attr( $section ),
							esc_attr( $name ),
							esc_attr( $value ),
							checked( in_array( $value, $current_value, true ), true, false )
						),
						esc_html( $label )
					);
				}
				$output .= '</fieldset></div>';
				break;
			case 'select':
				$current_value = $options[ $section . '_' . $name ];
				$default_value = isset( $default['value'] ) ? $default['value'] : '-1';
				$default_name  = isset( $default['name'] ) ? $default['name'] : 'Choose Setting';

				$output  = sprintf(
					'<select name="%1$s[%2$s_%3$s]" class="%1$s_%2$s_%3$s">',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$output .= sprintf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $default_value ),
					checked( $default_value === $current_value, true, false ),
					esc_html( $default_name )
				);
				foreach ( $field['choices'] as $value => $label ) {
					$output .= sprintf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $value ),
						checked( $value === $current_value, true, false ),
						esc_html( $label )
					);
				}
				$output .= '</select>';
				break;
			case 'file':
				$output = sprintf(
					'<input type="file" name="%1$s[%2$s_%3$s]" class="%4$s">',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class )
				);
				break;
			case 'link':
				$output = sprintf(
					'<a id="%1$s_%2$s_%3$s" class="%4$s" href="%5$s">%6$s</a>',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $href ),
					esc_attr( $title )
				);
				break;
			case 'none':
				// Intentional no-op: callers set 'none' to hide a control's value
				// column while still letting the row label + description render
				// (e.g. Reset Stream Database while a deletion is running, or
				// Clean Orphaned Meta while the auto-purge chain is active).
				// The description string carries the running-state message.
				$output = '';
				break;
			case 'select2':
				if ( ! isset( $current_value ) ) {
					$current_value = '';
				}

				$data_values = array();

				if ( isset( $field['choices'] ) ) {
					$choices = $field['choices'];
					if ( is_callable( $choices ) ) {
						$param   = ( isset( $field['param'] ) ) ? $field['param'] : null;
						$choices = call_user_func( $choices, $param );
					}
					foreach ( $choices as $key => $value ) {
						if ( is_array( $value ) ) {
							$child_values = array();
							if ( isset( $value['children'] ) ) {
								$child_values = array();
								foreach ( $value['children'] as $child_key => $child_value ) {
									$child_values[] = array(
										'id'   => $child_key,
										'text' => $child_value,
									);
								}
							}
							if ( isset( $value['label'] ) ) {
								$data_values[] = array(
									'id'       => $key,
									'text'     => $value['label'],
									'children' => $child_values,
								);
							}
						} else {
							$data_values[] = array(
								'id'   => $key,
								'text' => $value,
							);
						}
					}
					$class .= ' with-source';
				}

				$input_html = sprintf(
					'<input type="hidden" name="%1$s[%2$s_%3$s]" data-values=\'%4$s\' value="%5$s" class="select2-select %6$s" data-placeholder="%7$s" />',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( wp_json_encode( $data_values ) ),
					esc_attr( $current_value ),
					esc_attr( $class ),
					/* translators: %s: the title of the dropdown menu (e.g. "users") */
					sprintf( esc_html__( 'Any %s', 'stream' ), $title )
				);

				$output = sprintf(
					'<div class="%1$s_%2$s_%3$s">%4$s</div>',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					$input_html
				);

				break;
			case 'rule_list':
				$output      = $this->render_rule_list( $field, $current_value, $option_key, $section, $name, $description );
				$description = null;
				break;
		}
		$output .= ! empty( $description ) ? wp_kses_post( sprintf( '<p class="description">%s</p>', $description ) ) : null;

		return $output;
	}

	/**
	 * Render the exclude-rules table control.
	 *
	 * @param array       $field         Field settings.
	 * @param mixed       $current_value Stored rule list value.
	 * @param string      $option_key    Settings option key.
	 * @param string      $section       Field section slug.
	 * @param string      $name          Field name.
	 * @param string|null $description   Field description (consumed here; caller must not reprint).
	 * @return string
	 */
	private function render_rule_list( $field, $current_value, $option_key, $section, $name, $description ) {
		unset( $field );

		$users  = count_users();
		$form   = new Form_Generator();
		$output = '<p class="description">' . esc_html( $description ) . '</p>';

		$actions_top    = sprintf( '<input type="button" class="button" id="%1$s_new_rule" value="&#43; %2$s" />', esc_attr( $section . '_' . $name ), esc_html__( 'Add New Rule', 'stream' ) );
		$actions_bottom = sprintf( '<input type="button" class="button" id="%1$s_remove_rules" value="%2$s" />', esc_attr( $section . '_' . $name ), esc_html__( 'Delete Selected Rules', 'stream' ) );

		$output .= sprintf( '<div class="tablenav top">%1$s</div>', $actions_top );
		$output .= '<table class="wp-list-table widefat fixed stream-exclude-list">';

		$description = null;

		$heading_row = sprintf(
			'<tr>
						<td scope="col" class="manage-column column-cb check-column">%1$s</td>
						<th scope="col" class="manage-column">%2$s</th>
						<th scope="col" class="manage-column">%3$s</th>
						<th scope="col" class="manage-column">%4$s</th>
						<th scope="col" class="manage-column">%5$s</th>
						<th scope="col" class="actions-column manage-column"><span class="hidden">%6$s</span></th>
					</tr>',
			'<input class="cb-select" type="checkbox" />',
			esc_html__( 'Author or Role', 'stream' ),
			esc_html__( 'Context', 'stream' ),
			esc_html__( 'Action', 'stream' ),
			esc_html__( 'IP Address', 'stream' ),
			esc_html__( 'Filters', 'stream' )
		);

		$exclude_rows = array();

		// Account for when no rules have been added yet.
		if ( ! is_array( $current_value ) ) {
			$current_value = array();
		}

		// Prepend an empty row.
		$current_value['exclude_row'] = ( isset( $current_value['exclude_row'] ) ? $current_value['exclude_row'] : array() ) + array( 'helper' => '' );

		foreach ( $current_value['exclude_row'] as $key => $value ) {
			$exclude_rows[] = $this->render_rule_list_row(
				$form,
				$users,
				$current_value,
				$key,
				$option_key,
				$section,
				$name
			);
		}

		$no_rules_found_row = sprintf(
			'<tr class="no-items hidden"><td class="colspanchange" colspan="5">%1$s</td></tr>',
			esc_html__( 'No rules found.', 'stream' )
		);

		$output .= '<thead>' . $heading_row . '</thead>';
		$output .= '<tfoot>' . $heading_row . '</tfoot>';
		$output .= '<tbody>' . $no_rules_found_row . implode( '', $exclude_rows ) . '</tbody>';

		$output .= '</table>';

		$output .= sprintf( '<div class="tablenav bottom">%1$s</div>', $actions_bottom );

		return $output;
	}

	/**
	 * Render a single exclude-rule table row.
	 *
	 * @param Form_Generator $form          Form helper.
	 * @param array          $users         count_users() payload.
	 * @param array          $current_value Stored rule list value.
	 * @param string|int     $key           Row key.
	 * @param string         $option_key    Settings option key.
	 * @param string         $section       Field section slug.
	 * @param string         $name          Field name.
	 * @return string
	 */
	private function render_rule_list_row( $form, $users, $current_value, $key, $option_key, $section, $name ) {
		$author_or_role = isset( $current_value['author_or_role'][ $key ] ) ? $current_value['author_or_role'][ $key ] : '';
		$connector      = isset( $current_value['connector'][ $key ] ) ? $current_value['connector'][ $key ] : '';
		$context        = isset( $current_value['context'][ $key ] ) ? $current_value['context'][ $key ] : '';
		$action         = isset( $current_value['action'][ $key ] ) ? $current_value['action'][ $key ] : '';
		$ip_address     = isset( $current_value['ip_address'][ $key ] ) ? $current_value['ip_address'][ $key ] : '';

		$author_or_role_values   = array();
		$author_or_role_selected = array();

		foreach ( Settings_Registry::get_roles() as $role_id => $role ) {
			$args  = array(
				'value' => $role_id,
				'text'  => $role,
			);
			$count = isset( $users['avail_roles'][ $role_id ] ) ? $users['avail_roles'][ $role_id ] : 0;

			if ( ! empty( $count ) ) {
				/* translators: %d: a number of users (e.g. "42") */
				$args['user_count'] = sprintf( _n( '%d user', '%d users', absint( $count ), 'stream' ), absint( $count ) );
			}

			if ( $role_id === $author_or_role ) {
				$author_or_role_selected['value'] = $role_id;
				$author_or_role_selected['text']  = $role;
			}

			$author_or_role_values[] = $args;
		}

		if ( empty( $author_or_role_selected ) && is_numeric( $author_or_role ) ) {
			$user                    = new WP_User( $author_or_role );
			$display_name            = ( 0 === $user->ID ) ? esc_html__( 'N/A', 'stream' ) : $user->display_name;
			$author_or_role_selected = array(
				'value' => $user->ID,
				'text'  => $display_name,
			);
			$author_or_role_values[] = $author_or_role_selected;
		}

		$author_or_role_input = $form->render_field(
			'select2',
			array(
				'name'    => esc_attr( sprintf( '%1$s[%2$s_%3$s][%4$s][]', $option_key, $section, $name, 'author_or_role' ) ),
				'options' => $author_or_role_values,
				'classes' => 'author_or_role',
				// Data attributes are escaped in Form_Generator::prepare_data_attributes_string().
				'data'    => array(
					'placeholder'   => __( 'Any Author or Role', 'stream' ),
					'nonce'         => wp_create_nonce( 'stream_get_users' ),
					'selected-id'   => isset( $author_or_role_selected['value'] ) ? $author_or_role_selected['value'] : '',
					'selected-text' => isset( $author_or_role_selected['text'] ) ? $author_or_role_selected['text'] : '',
				),
			),
			false
		);

		$context_values = array();

		foreach ( $this->get_terms_labels( 'context' ) as $context_id => $context_data ) {
			if ( is_array( $context_data ) ) {
				$child_values = array();
				if ( isset( $context_data['children'] ) ) {
					$child_values = array();
					foreach ( $context_data['children'] as $child_id => $child_value ) {
						$child_values[] = array(
							'value'  => $context_id . '-' . $child_id,
							'text'   => $child_value,
							'parent' => $context_id,
						);
					}
				}
				if ( isset( $context_data['label'] ) ) {
					$context_values[] = array(
						'value'    => $context_id,
						'text'     => $context_data['label'],
						'children' => $child_values,
					);
				}
			} else {
				$context_values[] = array(
					'value' => $context_id,
					'text'  => $context_data,
				);
			}
		}

		$connector_or_context_input = $form->render_field(
			'select2',
			array(
				'name'    => esc_attr( sprintf( '%1$s[%2$s_%3$s][%4$s][]', $option_key, $section, $name, 'connector_or_context' ) ),
				'options' => $context_values,
				'classes' => 'connector_or_context',
				// Data attributes are escaped in Form_Generator::prepare_data_attributes_string().
				'data'    => array(
					'group'       => 'connector',
					'placeholder' => __( 'Any Context', 'stream' ),
				),
			),
			false
		);

		$connector_input = $form->render_field(
			'hidden',
			array(
				'name'    => esc_attr( sprintf( '%1$s[%2$s_%3$s][%4$s][]', $option_key, $section, $name, 'connector' ) ),
				'value'   => $connector,
				'classes' => 'connector',
			),
			false
		);

		$context_input = $form->render_field(
			'hidden',
			array(
				'name'    => esc_attr( sprintf( '%1$s[%2$s_%3$s][%4$s][]', $option_key, $section, $name, 'context' ) ),
				'value'   => $context,
				'classes' => 'context',
			),
			false
		);

		$action_values = array();

		foreach ( $this->get_terms_labels( 'action' ) as $action_id => $action_data ) {
			$action_values[] = array(
				'value' => $action_id,
				'text'  => $action_data,
			);
		}

		$action_input = $form->render_field(
			'select2',
			array(
				'name'    => esc_attr( sprintf( '%1$s[%2$s_%3$s][%4$s][]', $option_key, $section, $name, 'action' ) ),
				'value'   => $action,
				'options' => $action_values,
				'classes' => 'action',
				// Data attributes are escaped in Form_Generator::prepare_data_attributes_string().
				'data'    => array(
					'placeholder' => __( 'Any Action', 'stream' ),
				),
			),
			false
		);

		$ip_address_input = $form->render_field(
			'select2',
			array(
				'name'     => esc_attr( sprintf( '%1$s[%2$s_%3$s][%4$s][]', $option_key, $section, $name, 'ip_address' ) ),
				'value'    => $ip_address,
				'classes'  => 'ip_address',
				// Data attributes are escaped in Form_Generator::prepare_data_attributes_string().
				'data'     => array(
					'placeholder' => __( 'Any IP Address', 'stream' ),
					'nonce'       => wp_create_nonce( 'stream_get_ips' ),
				),
				'multiple' => true,
			),
			false
		);

		$helper_input = sprintf(
			'<input type="hidden" name="%1$s[%2$s_%3$s][%4$s][]" value="" />',
			esc_attr( $option_key ),
			esc_attr( $section ),
			esc_attr( $name ),
			'exclude_row'
		);

		return sprintf(
			'<tr class="%1$s %2$s">
							<th scope="row" class="check-column">%3$s %4$s</th>
							<td>%5$s</td>
							<td>%6$s %7$s %8$s</td>
							<td>%9$s</td>
							<td>%10$s</td>
							<th scope="row" class="actions-column">
								<a href="#" class="exclude_rules_remove_rule_row">%11$s</a>
							</th>
						</tr>',
			( 0 !== (int) $key % 2 ) ? 'alternate' : '',
			( 'helper' === (string) $key ) ? 'hidden helper' : '',
			'<input class="cb-select" type="checkbox" />',
			$helper_input,
			$author_or_role_input,
			$connector_or_context_input,
			$connector_input,
			$context_input,
			$action_input,
			$ip_address_input,
			esc_html__( 'Delete', 'stream' )
		);
	}

	/**
	 * Function will return all terms labels of given column.
	 *
	 * @param string $column Name of the column.
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
	 * Settings API field callback: render and echo field HTML.
	 *
	 * @param array $field Field to be rendered.
	 * @return void
	 */
	public function output_field( $field ) {
		$settings = $this->plugin->settings;
		$method   = 'output_' . $field['name'];

		if ( method_exists( $settings, $method ) ) {
			call_user_func( array( $settings, $method ), $field );
			return;
		}

		echo $this->render_field( $field, $settings->options, $settings->option_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
