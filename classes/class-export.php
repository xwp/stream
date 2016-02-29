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

	public $exporters = array();

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->admin = $plugin->admin;

		if ( 'wp_stream' === wp_stream_filter_input( INPUT_GET, 'page' ) ) {
			add_action( 'admin_init', array( $this, 'render_download' ) );
			add_filter( 'stream_exporters', array( $this, 'register_default_exporters' ) );
		}

	}

	public function render_download() {

		$this->exporters = apply_filters( 'stream_exporters', array() );
		$output_type = wp_stream_filter_input( INPUT_GET, 'output' );
		if ( ! array_key_exists( $output_type, $this->exporters ) ) {
			return;
		}

		$this->admin->register_list_table();
		$list_table = $this->admin->list_table;
		$list_table->prepare_items();
		add_filter( 'stream_records_per_page', array( $this, 'render_csv_disable_paginate' ) );
		add_filter( 'wp_stream_list_table_columns', array( $this, 'render_csv_expand_columns' ), 10, 1 );

		$records = $list_table->get_records();
		$columns = $list_table->get_columns();
		$output = array( array_values( $columns ) );
		foreach ( $records as $item ) {
			$output[] = $this->build_record( $item, $columns );
		}

		$exporter = $this->exporters[ $output_type ];
		$exporter->output_file( $output );
		die;
	}

	protected function build_record ( $item, $columns ) {

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

		return $row_out;
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
		$new_columns = array(
			'date'      => $columns['date'],
			'summary'   => $columns['summary'],
			'user_id'   => $columns['user_id'],
			'connector' => __( 'Connector', 'stream' ),
			'context'   => $columns['context'],
			'action'    => $columns['action'],
			'ip'        => $columns['ip'],
		);

		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			$new_columns['blog_id'] = __( 'Blog ID', 'stream' );
		}

		return $new_columns;
	}

	public function register_default_exporters ( $exporters ) {
		$exporters['csv'] = new Export_CSV;
		$exporters['json'] = new Export_JSON;

		return $exporters;
	}

}
