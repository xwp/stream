<?php

class WP_Stream_Notification_Adapter_SMS extends WP_Stream_Notifications_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'SMS', 'stream-notifications' ) );
	}

	public static function fields() {
		return array(
			'mobile_number' => array(
				'title'    => __( 'Send to Mobile Number', 'stream-notifications' ),
				'type'     => 'text',
				'multiple' => false,
				'tags'     => true,
				'hint'     => __( 'Enter mobile numbers without dashes (ex: 8885550000)', 'stream-notifications' ),
			),
			'carrier' => array(
				'title'   => __( 'Carrier', 'stream-notifications' ),
				'type'    => 'select',
				'hint'    => __( 'Select your mobile service provider.', 'stream-notifications' ),
				'ajax'    => false,
				'options' => array(
					'@sms.3rivers.net'          => esc_html__( '3 River Wireless', 'stream-notifications' ),
					'@paging.acswireless.com'   => esc_html__( 'ACS Wireless', 'stream-notifications' ),
					'@message.alltel.com'       => esc_html__( 'Alltel', 'stream-notifications' ),
					'@txt.att.net'              => esc_html__( 'AT&T, Cingular, Net10 or Tracfone', 'stream-notifications' ),
					'@bellmobility.ca'          => esc_html__( 'Bell Canada', 'stream-notifications' ),
					'@txt.bell.ca'              => esc_html__( 'Bell Mobility (Canada), Presidents Choice or Solo Mobile', 'stream-notifications' ),
					'@txt.bellmobility.ca'      => esc_html__( 'Bell Mobility', 'stream-notifications' ),
					'@blueskyfrog.com'          => esc_html__( 'Blue Sky Frog', 'stream-notifications' ),
					'@sms.bluecell.com'         => esc_html__( 'Bluegrass Cellular', 'stream-notifications' ),
					'@myboostmobile.com'        => esc_html__( 'Boost Mobile', 'stream-notifications' ),
					'@bplmobile.com'            => esc_html__( 'BPL Mobile', 'stream-notifications' ),
					'@cwwsms.com'               => esc_html__( 'Carolina West Wireless', 'stream-notifications' ),
					'@mobile.celloneusa.com'    => esc_html__( 'Cellular One', 'stream-notifications' ),
					'@csouth1.com'              => esc_html__( 'Cellular South', 'stream-notifications' ),
					'@messaging.centurytel.net' => esc_html__( 'CenturyTel', 'stream-notifications' ),
					'@msg.clearnet.com'         => esc_html__( 'Clearnet', 'stream-notifications' ),
					'@comcastpcs.textmsg.com'   => esc_html__( 'Comcast', 'stream-notifications' ),
					'@corrwireless.net'         => esc_html__( 'Corr Wireless Communications', 'stream-notifications' ),
					'@sms.mycricket.com'        => esc_html__( 'Cricket', 'stream-notifications' ),
					'@mobile.dobson.net'        => esc_html__( 'Dobson', 'stream-notifications' ),
					'@sms.edgewireless.com'     => esc_html__( 'Edge Wireless', 'stream-notifications' ),
					'@fido.ca'                  => esc_html__( 'Fido', 'stream-notifications' ),
					'@sms.goldentele.com'       => esc_html__( 'Golden Telecom', 'stream-notifications' ),
					'@text.houstoncellular.net' => esc_html__( 'Houston Cellular', 'stream-notifications' ),
					'@ideacellular.net'         => esc_html__( 'Idea Cellular', 'stream-notifications' ),
					'@ivctext.com'              => esc_html__( 'Illinois Valley Cellular', 'stream-notifications' ),
					'@inlandlink.com'           => esc_html__( 'Inland Cellular Telephone', 'stream-notifications' ),
					'@pagemci.com'              => esc_html__( 'MCI', 'stream-notifications' ),
					'@page.metrocall.com'       => esc_html__( 'Metrocall', 'stream-notifications' ),
					'@my2way.com'               => esc_html__( 'Metrocall 2-way', 'stream-notifications' ),
					'@mymetropcs.com'           => esc_html__( 'Metro PCS', 'stream-notifications' ),
					'@clearlydigital.com'       => esc_html__( 'Midwest Wireless', 'stream-notifications' ),
					'@mobilecomm.net'           => esc_html__( 'Mobilcomm', 'stream-notifications' ),
					'@text.mtsmobility.com'     => esc_html__( 'MTS', 'stream-notifications' ),
					'@messaging.nextel.com'     => esc_html__( 'Nextel', 'stream-notifications' ),
					'@onlinebeep.net'           => esc_html__( 'OnlineBeep', 'stream-notifications' ),
					'@pcsone.net'               => esc_html__( 'PCS One', 'stream-notifications' ),
					'@sms.pscel.com'            => esc_html__( 'Public Service Cellular', 'stream-notifications' ),
					'@qwestmp.com'              => esc_html__( 'Qwest', 'stream-notifications' ),
					'@pcs.rogers.com'           => esc_html__( 'Rogers AT&T Wireless and Rogers Canada', 'stream-notifications' ),
					'@satellink.net'            => esc_html__( 'Satellink', 'stream-notifications' ),
					'@messaging.sprintpcs.com'  => esc_html__( 'Sprint or Helio', 'stream-notifications' ),
					'@tms.suncom.com'           => esc_html__( 'Suncom and Triton', 'stream-notifications' ),
					'@mobile.surewest.com'      => esc_html__( 'Surewest Communications', 'stream-notifications' ),
					'@tmomail.net'              => esc_html__( 'T-Mobile', 'stream-notifications' ),
					'@msg.telus.com'            => esc_html__( 'Telus', 'stream-notifications' ),
					'@utext.com'                => esc_html__( 'Unicel', 'stream-notifications' ),
					'@email.uscc.net'           => esc_html__( 'US Cellular', 'stream-notifications' ),
					'@uswestdatamail.com'       => esc_html__( 'US West', 'stream-notifications' ),
					'@vtext.com'                => esc_html__( 'Verizon or Straight Talk', 'stream-notifications' ),
					'@vmobl.com'                => esc_html__( 'Virgin Mobile', 'stream-notifications' ),
					'@vmobile.ca'               => esc_html__( 'Virgin Mobile Canada', 'stream-notifications' ),
					'@sms.wcc.net'              => esc_html__( 'West Central Wireless', 'stream-notifications' ),
					'@cellularonewest.com'      => esc_html__( 'Western Wireless', 'stream-notifications' ),
				),
			),
			'message' => array(
				'title' => __( 'Message', 'stream-notifications' ),
				'type'  => 'textarea',
				'hint'  => __( 'Data tags are allowed. HTML is not allowed.', 'stream-notifications' ),
			),
		);
	}

	public function send( $log ) {
		$number  = preg_replace( '/\D/', '', $this->params['mobile_number'] ); // Removes all non-numeric characters
		$to      = sanitize_email( $number . $this->params['carrier'] );
		$message = $this->replace( strip_tags( $this->params['message'] ), $log );

		wp_mail( $to, null, $message );
	}

}

WP_Stream_Notification_Adapter_SMS::register();
