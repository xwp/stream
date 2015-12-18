<?php
namespace WP_Stream;

class DB_Driver_Sumo implements DB_Driver_Interface {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Hold congig
	 * @var Config
	 */
	protected $config;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->config = $plugin->config['storage'];
	}

	/**
	 * Insert a record
	 *
	 * @param array $record
	 *
	 * @return int
	 */
	public function insert_record( $record ) {

		$response = wp_remote_post( $this->config['receiver_endpoint'], array(
			'method'      => 'POST',
			'timeout'     => 45,
			'httpversion' => '1.1',
			'blocking'    => false,
			'body'        => wp_json_encode( $record, JSON_UNESCAPED_SLASHES ),
			)
		);

		return is_wp_error( $response ) ? 0 : 1;
	}

	/**
	 * Retrieve records
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function get_records( $args ) {

		$headers = array(
				'Authorization' => 'Basic ' . base64_encode( $this->config['api_access_id'] . ':' . $this->config['api_access_key'] ),
		);

		$params = array(
			'q' => '_source=spp-test-stream',
		);

		$url = $this->config['api_endpoint'] . '?' . build_query( $params );

		$response = vip_safe_wp_remote_get( $url, array(
			'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$result = json_decode( $response['body'] );

		return $result;
	}

	/**
	 * Returns array of existing values for requested column.
	 *
	 * @param string $column
	 *
	 * @return array
	 */
	public function get_column_values( $column ) {
		// TODO: Implement method
		return array();
	}

	/**
	 * Purge storage
	 */
	public function purge_storage() {
		// TODO: Implement method
	}

	/**
	 * Init storage
	 */
	public function setup_storage() {
		// TODO: Implement method
	}
}
