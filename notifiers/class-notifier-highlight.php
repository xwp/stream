<?php
namespace WP_Stream;

class Notifier_Highlight extends Notifier {
	/**
	 * Notifier name
	 *
	 * @var string
	 */
	public $name = 'Highlight Notifier';

	/**
	 * Notifier slug
	 *
	 * @var string
	 */
	public $slug = 'highlight';

	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		if ( is_admin() ) {
				add_filter( 'wp_stream_record_classes', array( $this, 'post_class' ), 10, 2 );
		}
	}

	public function notify( $record_id, $recordarr, $options ) {
		$options = wp_parse_args( $options, array(
			'color' => 'yellow',
		) );
		$this->plugin->db->insert_meta( $record_id, 'highlight', $options['color'] );
	}

	public function display_settings_form( $alert, $post ) {
		$options = wp_parse_args( $alert->alert_meta, array(
			'color' => 'yellow',
		) );

		$form = new Form_Generator;
		$form->add_field( 'select', array(
			'name'    => 'wp_stream_highlight_color',
			'title'   => esc_attr( __( 'Highlight Color', 'stream' ) ),
			'options' => $this->get_highlight_options(),
			'value'   => $options['color'],
		) );

		echo $form->render_all(); // xss ok
	}

	public function get_highlight_options() {
		return array(
			'yellow' => __( 'Yellow', 'stream' ),
			'red'    => __( 'Red', 'stream' ),
		);
	}

	public function process_settings_form( $alert, $post ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );

		$input_color = $_POST['wp_stream_highlight_color'];
		if ( ! array_key_exists( $input_color , $this->get_highlight_options() ) ) {
			$alert->alert_meta['color'] = 'yellow';
		} else {
			$alert->alert_meta['color'] = $input_color;
		}

	}

	public function post_class( $classes, $recordarr ) {
		if ( ! empty( $recordarr->meta['highlight'] ) ) {
				$classes[] = 'highlight-notification-' . esc_attr( $recordarr->meta['highlight'] );
		}
		return $classes;
	}
}
