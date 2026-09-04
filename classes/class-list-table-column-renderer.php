<?php
/**
 * Renders Stream list-table cell HTML for a record column.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - List_Table_Column_Renderer
 */
class List_Table_Column_Renderer {

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Return HTML for a list-table cell.
	 *
	 * Callers (List_Table) apply `wp_kses` and echo the result.
	 *
	 * @param object|array $item        Record data.
	 * @param string       $column_name Column name.
	 * @return string
	 */
	public function render( $item, $column_name ) {
		$out    = '';
		$record = new Record( $item );

		switch ( $column_name ) {
			case 'date':
				$created     = gmdate( 'Y-m-d H:i:s', strtotime( $record->created ) );
				$date_string = sprintf(
					'<time datetime="%s" class="relative-time record-created">%s</time>',
					wp_stream_get_iso_8601_extended_date( strtotime( $record->created ) ),
					get_date_from_gmt( $created, 'Y/m/d' )
				);
				$out         = $this->column_link( $date_string, 'date', get_date_from_gmt( $created, 'Y/m/d' ) );
				$out        .= '<br />';
				$out        .= get_date_from_gmt( $created, 'h:i:s A T' );
				break;

			case 'summary':
				$out          = $record->summary;
				$object_title = $record->get_object_title();
				/* translators: %s: the title of any object, like a Post (e.g. "Hello World") */
				$view_all_text = $object_title ? sprintf( esc_html__( 'View all activity for "%s"', 'stream' ), esc_attr( $object_title ) ) : esc_html__( 'View all activity for this object', 'stream' );

				if ( $record->object_id ) {
					$out .= $this->column_link(
						'<span class="dashicons dashicons-search stream-filter-object-id"></span>',
						array(
							'object_id' => $record->object_id,
							'context'   => $record->context,
						),
						null,
						esc_attr( $view_all_text )
					);
				}
				$out .= $this->get_action_links( $record );
				break;

			case 'user_id':
				$user = new Author( (int) $record->user_id, (array) $record->user_meta );

				$filtered_records_url = add_query_arg(
					array(
						'page'    => $this->plugin->admin->records_page_slug,
						'user_id' => absint( $user->id ),
					),
					self_admin_url( $this->plugin->admin->admin_parent_page )
				);

				$out = sprintf(
					'<a href="%s">%s <span>%s</span></a>%s%s%s',
					$filtered_records_url,
					$user->get_avatar_img( 80 ),
					$user->get_display_name(),
					$user->is_deleted() ? sprintf( '<br /><small class="deleted">%s</small>', esc_html__( 'Deleted User', 'stream' ) ) : '',
					sprintf( '<br /><small>%s</small>', $user->get_role() ),
					sprintf( '<br /><small>%s</small>', $user->get_agent_label( $user->get_agent() ) )
				);
				break;

			case 'context':
				$connector_title = $this->get_term_title( $record->{'connector'}, 'connector' );
				$context_title   = $this->get_term_title( $record->{'context'}, 'context' );

				$out  = $this->column_link( $connector_title, 'connector', $item->{'connector'} );
				$out .= '<br />&#8627;&nbsp;';
				$out .= $this->column_link(
					$context_title,
					array(
						'connector' => $record->{'connector'},
						'context'   => $record->{'context'},
					)
				);
				break;

			case 'action':
				$out = $this->column_link( $this->get_term_title( $record->{$column_name}, $column_name ), $column_name, $record->{$column_name} );
				break;

			case 'blog_id':
				$blog = ( $record->blog_id && is_multisite() ) ? get_blog_details( $record->blog_id ) : $this->plugin->admin->network->get_network_blog();
				$out  = $this->column_link( $blog->blogname, 'blog_id', $blog->blog_id );
				break;

			case 'ip':
				$out = $this->column_link( $record->{$column_name}, 'ip', $record->{$column_name} );
				break;

			default:
				/**
				 * Registers new Columns to be inserted into the table. The cell contents of this column is set
				 * below with 'wp_stream_insert_column_default_'
				 *
				 * @since      3.5.1
				 * @deprecated 4.0.1 Use the {@see 'wp_stream_list_table_columns'} filter instead.
				 *
				 * @param array $new_columns Columns injected in the table.
				 *
				 * @return array
				 */
				apply_filters_deprecated(
					'wp_stream_register_column_defaults',
					array( array() ),
					/* translators: %s is the Stream version number. It is part of a filter deprecation notice and is preceded by: "{hook_name} is deprecated since version %s of Stream". */
					sprintf( __( '%s of Stream', 'stream' ), '4.0.1' ),
					'wp_stream_list_table_columns',
					__( 'This filter is being deprecated as it is redundant. You can define custom column names and titles using the `wp_stream_list_table_columns` filter then provide the value for the custom columns using the `wp_stream_insert_column_default_{$column_name}` filter.', 'stream' )
				);

				/**
				 * Allows for the addition of content under a specified column.
				 *
				 * @param string $out         Column content.
				 * @param object $record      Record with row content.
				 * @param string $column_name Column name.
				 *
				 * @return string
				 */
				$out = (string) apply_filters( "wp_stream_insert_column_default_{$column_name}", $out, $record, $column_name );
				break;
		}

		return $out;
	}

	/**
	 * Returns the actions links for the provided record. (Eg. Edit, View)
	 *
	 * @param Record $record Record.
	 * @return string
	 */
	public function get_action_links( $record ) {
		$out = '';

		/**
		 * Filter allows modification of action links for a specific connector
		 *
		 * @param array
		 * @param Record
		 *
		 * @return array Action links for this connector
		 */
		$action_links = apply_filters( 'wp_stream_action_links_' . $record->connector, array(), $record );

		/**
		 * Filter allows addition of custom links for a specific connector
		 *
		 * @param array
		 * @param Record
		 *
		 * @return array Custom links for this connector
		 */
		$custom_links = apply_filters( 'wp_stream_custom_action_links_' . $record->connector, array(), $record );

		if ( $action_links || $custom_links ) {
			$out .= '<div class="row-actions">';
		}

		$links = array();
		if ( $action_links && is_array( $action_links ) ) {
			foreach ( $action_links as $al_title => $al_href ) {
				$links[] = sprintf(
					'<span><a href="%s" class="action-link">%s</a></span>',
					$al_href,
					$al_title
				);
			}
		}

		if ( $custom_links && is_array( $custom_links ) ) {
			foreach ( $custom_links as $key => $link ) {
				$links[] = $link;
			}
		}

		$out .= implode( ' | ', $links );

		if ( $action_links || $custom_links ) {
			$out .= '</div>';
		}

		return $out;
	}

	/**
	 * Returns a link to be displayed in the table.
	 *
	 * @param string       $display Text to be displayed in link.
	 * @param string|array $key     Query string variable(s).
	 * @param string       $value   Single query string value.
	 * @param string       $title   Tooltip value.
	 * @return string
	 */
	public function column_link( $display, $key, $value = null, $title = null ) {
		$url = add_query_arg(
			array(
				'page' => $this->plugin->admin->records_page_slug,
			),
			self_admin_url( $this->plugin->admin->admin_parent_page )
		);

		$args = ! is_array( $key ) ? array(
			$key => $value,
		) : $key;

		foreach ( $args as $k => $v ) {
			$url = add_query_arg( $k, $v, $url );
		}

		return sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $title ),
			$display
		);
	}

	/**
	 * Returns the label for a connector term.
	 *
	 * @param string $term Connector label type.
	 * @param string $type Connector term.
	 * @return string
	 */
	public function get_term_title( $term, $type ) {
		if ( ! isset( $this->plugin->connectors->term_labels[ 'stream_' . $type ][ $term ] ) ) {
			return $term;
		}

		return $this->plugin->connectors->term_labels[ 'stream_' . $type ][ $term ];
	}
}
