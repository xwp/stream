<?php

function get_stream_config() {
	return array(
			'storage' => array(
					'driver' => '\WP_Stream\DB_Driver_Mysql',

				/* sumo example
					'driver' => '\WP_Stream\DB_Driver_Sumo',
					'receiver_endpoint' => '',
					'api_endpoint' => '',
					'api_access_id' => '',
					'api_access_key' => '',
				*/
			)
	);
};