<?php

class WP_Stream_Notification_Adapter_SMS extends WP_Stream_Notifications_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'SMS', 'stream' ) );
	}

	public static function fields() {
		return array(
			'mobile_number' => array(
				'title'    => __( 'Send to Mobile Number', 'stream' ),
				'type'     => 'text',
				'multiple' => false,
				'tags'     => true,
				'hint'     => __( 'Enter mobile numbers without dashes (ex: 8885550000)', 'stream' ),
			),
			'carrier' => array(
				'title'   => __( 'Carrier', 'stream' ),
				'type'    => 'select',
				'hint'    => __( 'Select your mobile service provider.', 'stream' ),
				'ajax'    => false,
				'options' => array(
					'@sms.3rivers.net'          => esc_html__( '3 River Wireless', 'stream' ),
					'@paging.acswireless.com'   => esc_html__( 'ACS Wireless', 'stream' ),
					'@message.alltel.com'       => esc_html__( 'Alltel', 'stream' ),
					'@txt.att.net'              => esc_html__( 'AT&T, Cingular, Net10 or Tracfone', 'stream' ),
					'@bellmobility.ca'          => esc_html__( 'Bell Canada', 'stream' ),
					'@txt.bell.ca'              => esc_html__( 'Bell Mobility (Canada), Presidents Choice or Solo Mobile', 'stream' ),
					'@txt.bellmobility.ca'      => esc_html__( 'Bell Mobility', 'stream' ),
					'@blueskyfrog.com'          => esc_html__( 'Blue Sky Frog', 'stream' ),
					'@sms.bluecell.com'         => esc_html__( 'Bluegrass Cellular', 'stream' ),
					'@myboostmobile.com'        => esc_html__( 'Boost Mobile', 'stream' ),
					'@bplmobile.com'            => esc_html__( 'BPL Mobile', 'stream' ),
					'@cwwsms.com'               => esc_html__( 'Carolina West Wireless', 'stream' ),
					'@mobile.celloneusa.com'    => esc_html__( 'Cellular One', 'stream' ),
					'@csouth1.com'              => esc_html__( 'Cellular South', 'stream' ),
					'@messaging.centurytel.net' => esc_html__( 'CenturyTel', 'stream' ),
					'@msg.clearnet.com'         => esc_html__( 'Clearnet', 'stream' ),
					'@comcastpcs.textmsg.com'   => esc_html__( 'Comcast', 'stream' ),
					'@corrwireless.net'         => esc_html__( 'Corr Wireless Communications', 'stream' ),
					'@sms.mycricket.com'        => esc_html__( 'Cricket', 'stream' ),
					'@mobile.dobson.net'        => esc_html__( 'Dobson', 'stream' ),
					'@sms.edgewireless.com'     => esc_html__( 'Edge Wireless', 'stream' ),
					'@fido.ca'                  => esc_html__( 'Fido', 'stream' ),
					'@sms.goldentele.com'       => esc_html__( 'Golden Telecom', 'stream' ),
					'@text.houstoncellular.net' => esc_html__( 'Houston Cellular', 'stream' ),
					'@ideacellular.net'         => esc_html__( 'Idea Cellular', 'stream' ),
					'@ivctext.com'              => esc_html__( 'Illinois Valley Cellular', 'stream' ),
					'@inlandlink.com'           => esc_html__( 'Inland Cellular Telephone', 'stream' ),
					'@pagemci.com'              => esc_html__( 'MCI', 'stream' ),
					'@page.metrocall.com'       => esc_html__( 'Metrocall', 'stream' ),
					'@my2way.com'               => esc_html__( 'Metrocall 2-way', 'stream' ),
					'@mymetropcs.com'           => esc_html__( 'Metro PCS', 'stream' ),
					'@clearlydigital.com'       => esc_html__( 'Midwest Wireless', 'stream' ),
					'@mobilecomm.net'           => esc_html__( 'Mobilcomm', 'stream' ),
					'@text.mtsmobility.com'     => esc_html__( 'MTS', 'stream' ),
					'@messaging.nextel.com'     => esc_html__( 'Nextel', 'stream' ),
					'@onlinebeep.net'           => esc_html__( 'OnlineBeep', 'stream' ),
					'@pcsone.net'               => esc_html__( 'PCS One', 'stream' ),
					'@sms.pscel.com'            => esc_html__( 'Public Service Cellular', 'stream' ),
					'@qwestmp.com'              => esc_html__( 'Qwest', 'stream' ),
					'@pcs.rogers.com'           => esc_html__( 'Rogers AT&T Wireless and Rogers Canada', 'stream' ),
					'@satellink.net'            => esc_html__( 'Satellink', 'stream' ),
					'@messaging.sprintpcs.com'  => esc_html__( 'Sprint or Helio', 'stream' ),
					'@tms.suncom.com'           => esc_html__( 'Suncom and Triton', 'stream' ),
					'@mobile.surewest.com'      => esc_html__( 'Surewest Communications', 'stream' ),
					'@tmomail.net'              => esc_html__( 'T-Mobile', 'stream' ),
					'@msg.telus.com'            => esc_html__( 'Telus', 'stream' ),
					'@utext.com'                => esc_html__( 'Unicel', 'stream' ),
					'@email.uscc.net'           => esc_html__( 'US Cellular', 'stream' ),
					'@uswestdatamail.com'       => esc_html__( 'US West', 'stream' ),
					'@vtext.com'                => esc_html__( 'Verizon or Straight Talk', 'stream' ),
					'@vmobl.com'                => esc_html__( 'Virgin Mobile', 'stream' ),
					'@vmobile.ca'               => esc_html__( 'Virgin Mobile Canada', 'stream' ),
					'@sms.wcc.net'              => esc_html__( 'West Central Wireless', 'stream' ),
					'@cellularonewest.com'      => esc_html__( 'Western Wireless', 'stream' ),
				),
			),
			'message' => array(
				'title' => __( 'Message', 'stream' ),
				'type'  => 'textarea',
				'hint'  => __( 'Data tags are allowed. HTML is not allowed.', 'stream' ),
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
