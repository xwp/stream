<?php
namespace WP_Stream;

class Export {

	/**
	 * Hold Plugin class
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Hold Admin class
	 *
	 * @var Admin
	 */
	public $admin;

	/**
	 * Hold registered exporters
	 *
	 * @var array
	 */
	public $exporters = array();

	/**
	 * Class constructor
	 *
	 * @param Plugin $plugin The plugin object.
	 * @return void
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->admin = $plugin->admin;

		if ( 'wp_stream' === wp_stream_filter_input( INPUT_GET, 'page' ) ) {
			add_action( 'admin_init', array( $this, 'render_download' ) );
			add_action( 'register_stream_exporters', array( $this, 'register_default_exporters' ), 10, 1 );
		}

	}

	/**
	 * Outputs download file to user based on selected exporter
	 *
	 * @return void
	 */
	public function render_download() {
		$this->get_exporters();
		$output_type = wp_stream_filter_input( INPUT_GET, 'output' );
		if ( ! array_key_exists( $output_type, $this->exporters ) ) {
			return;
		}

		$this->admin->register_list_table();
		$list_table = $this->admin->list_table;
		$list_table->prepare_items();
		add_filter( 'stream_records_per_page', array( $this, 'disable_paginate' ) );
		add_filter( 'wp_stream_list_table_columns', array( $this, 'expand_columns' ), 10, 1 );

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

	/**
	 * Extracts data from Records
	 *
	 * @param array $item Post to extract data from.
	 * @param array $columns Columns being extracted.
	 * @return array Numerically-indexed array with extracted data.
	 */
	function build_record( $item, $columns ) {
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
	 *
	 * @param int $records_per_page Old limit for records_per_page.
	 */
	public function disable_paginate( $records_per_page ) {
		return 10000;
	}

	/**
	 * Expand columns for CSV Output
	 *
	 * @param array $columns Columns currently registered to the list table being exported.
	 * @return array New columns for exporting.
	 */
	public function expand_columns( $columns ) {
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

	/**
	 * Registers an exporter for later use
	 *
	 * @param Exporter $exporter The exporter to register for use.
	 * @return void
	 */
	public function register_exporter( $exporter ) {
		if ( ! is_a( $exporter, 'WP_Stream\Exporter' ) ) {
			trigger_error( __( 'Registered exporters must extend WP_Stream\Exporter.', 'stream' ) ); // @codingStandardsIgnoreLine text-only output
		}

		$this->exporters[ $exporter->name ] = $exporter;
	}

	/**
	 * Returns an array with all available exporters
	 *
	 * @return array
	 */
	public function get_exporters() {
		do_action( 'register_stream_exporters', $this );
		return $this->exporters;
	}

	/**
	 * Register default exporters
	 *
	 * @param Export $export Instance of Export to register to.
	 */
	public function register_default_exporters( $export ) {
		$export->register_exporter( new Exporter_CSV );
		$export->register_exporter( new Exporter_JSON );
	}
}
