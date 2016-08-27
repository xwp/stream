<?php
/**
 * If This Then That (IFTT) Alert type.
 *
 * A user must create a IFTT Maker account,
 * and get their Maker Key, found here:
 *
 * @link https://ifttt.com/maker
 * This will be used in the Stream Alert form.
 *
 * Next, they create a new recipe, using the
 * "Maker" > "Receive a web request" trigger.
 * @link https://ifttt.com/myrecipes/personal/new
 *
 * Here, they will enter a label for the event name.
 * This will be also need to be entered into the Stream Alert form.
 *
 * Finally, the Maker Key and Event Name need to be entered into
 * the Alert metabox.
 *
 * This class uses the above data to send at POST request to IFTT,
 * which then hooks into whichever service the user has determined
 * in their Recipe.
 *
 * Notes:
 *
 * In their IFTT recipe, the Task Content field "quicktags" are
 * defined as such in the unfiltered state of this Alert Type:
 *
 * {{EventName}} - The name the user assigned to this event.
 * {{Value1}} - The Record Summary
 * {{Value2}} - The User that triggered the alert
 * {{Value3}} - The date/time on which the alert was triggered.
 *
 * There are several filters in self::notify_iftt() that
 * allow for complete customization of these values.
 *
 * More info:
 * @link https://ifttt.com/channels/maker/triggers/1636368624-receive-a-web-request
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type_IFTT
 *
 * @package WP_Stream
 */
class Alert_Type_IFTT extends Alert_Type {
	/**
	 * Alert type name
	 *
	 * @var string
	 */
	public $name = 'IFTT';

	/**
	 * Alert type slug
	 *
	 * @var string
	 */
	public $slug = 'iftt';

	/**
	 * Class Constructor
	 *
	 * @param Plugin $plugin Plugin object.
	 * @return void
	 */
	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		$this->plugin = $plugin;
		if ( ! is_admin() ) {
			return;
		}
		add_filter( 'wp_stream_alerts_save_meta', array( $this, 'add_alert_meta' ), 10, 2 );
	}
		/**
	 * Record that the Alert was triggered by a Record.
	 *
	 * In self::post_class() this value is checked so we can determine
	 * if the class should be added to the Record's display.
	 *
	 * @param int|string $record_id Record that triggered alert.
	 * @param array      $recordarr Record details.
	 * @param object     $alert Alert options.
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $alert ) {
		$recordarr['ID'] = $record_id;
		Alert::update_record_triggered_alerts( (object) $recordarr, $this->slug, $alert->ID );
		$this->notify_iftt( $alert, $recordarr );
	}

	/**
	 * Displays a settings form for the alert type
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function display_fields( $alert ) {
		$options = wp_parse_args( $alert->alert_meta, array(
			'maker_key' => '',
			'event_name' => '',
		) );

		$form = new Form_Generator;

		echo '<p>' . esc_html__( 'IFTT Maker Key', 'stream' ) . ':</p>';
		echo $form->render_field( 'text', array( // Xss ok.
			'name'    => 'wp_stream_iftt_maker_key',
			'title'   => esc_attr( __( 'Maker Key', 'stream' ) ),
			'value'   => $options['maker_key'],
		) );
		echo '<p>' . esc_html__( 'IFTT Event Name', 'stream' ) . ':</p>';
		echo $form->render_field( 'text', array( // Xss ok.
			'name'    => 'wp_stream_iftt_event_name',
			'title'   => esc_attr( __( 'Event Name', 'stream' ) ),
			'value'   => $options['event_name'],
		) );
	}
	/**
	 * Displays a settings form for the alert type
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function display_new_fields() {
		$form = new Form_Generator;

		echo '<p>' . esc_html__( 'IFTT Maker Key', 'stream' ) . ':</p>';
		echo $form->render_field( 'text', array( // Xss ok.
			'name'    => 'wp_stream_iftt_maker_key',
			'title'   => esc_attr( __( 'Maker Key', 'stream' ) ),
			'value'   => '',
		) );
		echo '<p>' . esc_html__( 'IFTT Event Name', 'stream' ) . ':</p>';
		echo $form->render_field( 'text', array( // Xss ok.
			'name'    => 'wp_stream_iftt_event_name',
			'title'   => esc_attr( __( 'Event Name', 'stream' ) ),
			'value'   => '',
		) );
	}
	/**
	 * Validates and saves form settings for later use.
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function save_fields( $alert ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );
		$alert->alert_meta['maker_key'] = '';

		if ( ! empty( $_POST['wp_stream_iftt_maker_key'] ) ) {
			$alert->alert_meta['maker_key'] = sanitize_text_field( wp_unslash( $_POST['wp_stream_iftt_maker_key'] ) );
		}
		if ( ! empty( $_POST['wp_stream_iftt_event_name'] ) ) {
			$alert->alert_meta['event_name'] = sanitize_text_field( wp_unslash( $_POST['wp_stream_iftt_event_name'] ) );
		}
	}

	/**
	 * Send a POST request to IFTT.
	 *
	 * This method sends Record data to IFTT for processing.
	 *
	 * There are several filters here (documented below) that
	 * allow for customization of the data sent.
	 *
	 * Note that IFTT only allows for three specifically-named
	 * array keys of data.  (also documented below)
	 *
	 * @param object $alert The Alert object.
	 * @param array  $recordarr  Array of Record data.
	 *
	 * @return bool
	 */
	public function notify_iftt( $alert, $recordarr ) {
		if ( empty( $alert->alert_meta['maker_key'] ) || empty( $alert->alert_meta['event_name'] ) || empty( $recordarr ) ) {
			return false;
		}

		$record_data = wp_parse_args( $recordarr, array(
			'summary' => sprintf( __( 'The event %s was triggered' ), $alert->alert_meta['event_name'] ),
			'user_id' => get_current_user_id(),
			'created' => current_time( 'Y-m-d H:i:s' ),  // Blog's local time.
		) );

		$user_id = $recordarr['user_id'];
		$user = get_user_by( 'id', $user_id );

		/**
		 * Filter User data field
		 *
		 * Defaults to 'user_login'.
		 *
		 * @param object $alert The Alert.
		 * @param array  $recordarray The Record's data.
		 * @return string
		 */
		$user_field = apply_filters( 'wp_stream_alert_iftt_user_data_value', 'user_login', $alert, $recordarr );
		$user_value = ! empty( $user->$user_field ) ? $user->$user_field : $user->user_login;

		$created = $recordarr['created'];
		/**
		 * Filter the date format string
		 *
		 * Defaults to 'Y-m-d H:i:s'.
		 *
		 * @param object $alert The Alert.
		 * @param array  $recordarray The Record's data.
		 * @return string
		 */
		$date_format = apply_filters( 'wp_stream_alert_iftt_date_format', 'Y-m-d H:i:s', $alert, $recordarr );
		$date = date( $date_format, strtotime( $created ) );

		$url = 'https://maker.ifttt.com/trigger/' . $alert->alert_meta['event_name'] . '/with/key/' . $alert->alert_meta['maker_key'];

		/*
		 * The array key labels for the request body are required by
		 * IFTT; their names cannot be changed.
		 *
		 * The filter defaults are set up to send:
		 * value1 = Record Summary
		 *
		 * value2 = User login name
		 * (see the wp_stream_alert_iftt_user_data_value filter above)
		 *
		 * value3 = Record Date
		 * (see the wp_stream_alert_iftt_date_format filter above)
         *
		 * The filters below allow complete customization of these data values.
		 */
		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode(
				array(
					/**
					 * Filter the first IFTTT alert value
					 *
					 * @param string $summary The Record's summary.
					 * @param object $alert  The Alert.
					 * @param array  $recordarr Array of Record data.
					 * @return mixed
					 */
					'value1' => apply_filters( 'wp_stream_alert_iftt_value_one', $record_data['summary'], $alert, $recordarr ),

					/**
					 * Filter the second IFTTT alert value
					 *
					 * @param string $user_value The user meta value requested above.
					 * @param int    $user_id  The user ID who fired the Alert.
					 * @param object $alert The Alert.
					 * @param array  $recordarr Array of Record data.
					 * @return mixed
					 */
					'value2' => apply_filters( 'wp_stream_alert_iftt_value_two', $user_value, $user_id, $alert, $recordarr ),

					/**
					 * Filter the third IFTTT alert value
					 *
					 * @param string $date The Record's date.
					 * @param object $alert  The Alert.
					 * @param array  $recordarr Array of Record data.
					 * @return mixed
					 */
					'value3' => apply_filters( 'wp_stream_alert_iftt_value_three', $date, $alert, $recordarr ),
				)
			),
		);
		$response = wp_remote_post( $url, $args );
		if ( ! is_array( $response ) ) {
			return false;
		}
		return true;
	}
	/**
	 * Add alert meta if this is a highlight alert
	 *
	 * @param array  $alert_meta The metadata to be inserted for this alert.
	 * @param string $alert_type The type of alert being added or updated.
	 *
	 * @return mixed
	 */
	public function add_alert_meta( $alert_meta, $alert_type ) {
		if ( $this->slug === $alert_type ) {
			$maker_key = wp_stream_filter_input( INPUT_POST, 'wp_stream_iftt_maker_key' );
			if ( ! empty( $maker_key ) ) {
				$alert_meta['maker_key'] = $maker_key;
			}
			$event_name = wp_stream_filter_input( INPUT_POST, 'wp_stream_iftt_event_name' );
			if ( ! empty( $event_name ) ) {
				$alert_meta['event_name'] = $event_name;
			}
		}
		return $alert_meta;
	}
}
