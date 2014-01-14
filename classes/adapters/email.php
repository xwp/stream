<?php

class WP_Stream_Notification_Adapter_Email extends WP_Stream_Notification_Adapter {

	function __construct() {
		parent::__construct(
			__( 'Email', 'stream_notification' ),
			array(
				'to' => array(
					'title'   => __( 'To', 'stream_notification' ),
					'type'    => 'text',
					'is_tags' => true,
					),
				'subject' => array(
					'title' => __( 'Subject', 'stream_notification' ),
					'type'  => 'text',
					'hint'  => __( 'ex: "%%summary%%" or "[%%created%% - %%author%%] %%summary%%", consult FAQ for documentaion.', 'stream_notification' ),
					),
			)
		);
	}

	function fields() {

	}

}
