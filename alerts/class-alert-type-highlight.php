<?php
/**
 * Highlight Alert type.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type_Highlight
 *
 * @package WP_Stream
 */
class Alert_Type_Highlight extends Alert_Type {
	/**
	 * Alert type name
	 *
	 * @var string
	 */
	public $name = 'Highlight Record';

	/**
	 * Alert type slug
	 *
	 * @var string
	 */
	public $slug = 'highlight';

	/**
	 * The Plugin
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Class Constructor
	 *
	 * @param Plugin $plugin Plugin object.
	 * @return void
	 */
	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		$this->plugin = $plugin;
		if ( is_admin() ) {
			add_filter( 'wp_stream_record_classes', array( $this, 'post_class' ), 10, 2 );
		}
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
	}

	/**
	 * Displays a settings form for the alert type
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function display_fields( $alert ) {
		$options = wp_parse_args( $alert->alert_meta, array(
			'color' => 'yellow',
		) );

		$form = new Form_Generator;

		echo '<p>' . esc_html__( 'Color', 'stream' ) . ':</p>';
		echo $form->render_field( 'select', array( // Xss ok.
			'name'    => 'wp_stream_highlight_color',
			'title'   => esc_attr( __( 'Highlight Color', 'stream' ) ),
			'options' => $this->get_highlight_options(),
			'value'   => $options['color'],
		) );
	}

	/**
	 * Lists available color options for alerts.
	 *
	 * @return array List of highlight color options.
	 */
	public function get_highlight_options() {
		return array(
			'yellow' => __( 'Yellow', 'stream' ),
			'red'    => __( 'Red', 'stream' ),
		);
	}

	/**
	 * Validates and saves form settings for later use.
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function save_fields( $alert ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );

		$input_color = sanitize_text_field( wp_unslash( $_POST['wp_stream_highlight_color'] ) );
		if ( ! array_key_exists( $input_color , $this->get_highlight_options() ) ) {
			$alert->alert_meta['color'] = 'yellow';
		} else {
			$alert->alert_meta['color'] = $input_color;
		}

	}

	/**
	 * Apply highlight to records
	 *
	 * @param array  $classes List of classes being applied to the post.
	 * @param object $record Record data.
	 * @return array New list of classes.
	 */
	public function post_class( $classes, $record ) {
		$alert_item = new \stdClass();
		$alert = new Alert( $alert_item, $this->plugin );
		$color = $alert->get_triggered_alert_setting_value( $record, $this->slug, 'color', 'yellow' );

		if ( empty( $color ) || ! is_string( $color ) ) {
			return $classes;
		}

		$new_class = 'highlight-notification-' . esc_attr( $color );
		if ( ! in_array( $new_class, $classes, true ) ) {
			$classes[] = 'alert-highlight highlight-notification-' . esc_attr( $color );
		}

		return $classes;
	}
}
