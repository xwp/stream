<?php
namespace WP_Stream;

class Export {
  
  /**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

  /**
	 * Hold Admin class
	 * @var Admin
	 */
	public $admin;

  public function __construct( $plugin ) {
    $this->plugin = $plugin;
    $this->admin = $plugin->admin;

    $output = wp_stream_filter_input( INPUT_GET, 'output' );
    $page = wp_stream_filter_input( INPUT_GET, 'page' );
    if (  'csv' === $output && 'wp_stream' === $page ) {
			add_action( 'admin_init', array( $this, 'render_csv_page' ) );
		}
  }

  public function render_csv_page() {

		$this->admin->register_list_table();
    $list_table = $this->admin->list_table;
		$list_table->prepare_items();
		add_filter( 'stream_records_per_page', array( $this, 'render_csv_disable_paginate' ) );
		add_filter( 'wp_stream_list_table_columns', array( $this, 'render_csv_expand_columns' ), 10, 1 );

		$records = $list_table->get_records();
		$columns = $list_table->get_columns();
		$csv_output = array( array_values( $columns ) );
		foreach ( $records as $item ) {
			$record = new Record( $item );
			$row_out = array();
			foreach ( array_keys( $columns ) as $column_name ) {
				switch ( $column_name ) {
					case 'date' :
						$created   = date( 'Y-m-d H:i:s', strtotime( $record->created ) );
						$row_out[] = get_date_from_gmt( $created, 'Y/m/d h:i:s A' );
						break;
					case 'summary' :
						$row_out[] = $record->summary;
						break;

					case 'user_id' :
						$user      = new Author( (int) $record->user_id, (array) maybe_unserialize( $record->user_meta ) );
						$row_out[] = $user->get_display_name();
						break;

					case 'connector':
						$row_out[] = $record->{'connector'};
						break;

					case 'context':
						$row_out[] = $record->{'context'};
						break;

					case 'action':
						$row_out[] = $record->{$column_name};
						break;

					case 'blog_id':
						$row_out[] = $record->blog_id;
						break;
					case 'ip' :
						$row_out[] = $record->{$column_name};
						break;
				}
			}
			$csv_output[] = $row_out;
		}

		header( 'Content-type: text/csv' );
		header( 'Content-Disposition: attachment; filename="output.csv"' );
		$output = '';
		foreach ( $csv_output as $row_data ) {
			$output .= join( ',', $row_data ) . "\n";
		}
		die( $output ); // @codingStandardsIgnoreLine text-only output
	}

	/**
	 * Increase pagination limit for CSV Output
	 */
	public function render_csv_disable_paginate( $records_per_page ) {
		return 10000;
	}

	/**
	 * Expand columns for CSV Output
	 */
	public function render_csv_expand_columns( $columns ) {
		return array(
			'date'      => $columns['date'],
			'summary'   => $columns['summary'],
			'user_id'   => $columns['user_id'],
			'connector' => __( 'Connector', 'stream' ),
			'context'   => $columns['context'],
			'action'    => $columns['action'],
			'blog_id'   => __( 'Blog ID', 'stream' ),
			'ip'        => $columns['ip'],
		);
	}

}
