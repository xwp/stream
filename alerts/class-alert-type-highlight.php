<?php
namespace WP_Stream;

class Alert_Type_Highlight extends Alert_Type {
	/**
	 * Alert type name
	 *
	 * @var string
	 */
	public $name = 'Highlight';

	/**
	 * Alert type slug
	 *
	 * @var string
	 */
	public $slug = 'highlight';

	/**
	 * Class Constructor
	 *
	 * @param Plugin $plugin Plugin object.
	 * @return void
	 */
	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		if ( is_admin() ) {
				add_filter( 'wp_stream_record_classes', array( $this, 'post_class' ), 10, 2 );
		}
	}

	/**
	 * Adds a highlight to the record that triggered the alert.
	 *
	 * @param int   $record_id Record that triggered alert.
	 * @param array $recordarr Record details.
	 * @param array $options Alert options.
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $options ) {
		$options = wp_parse_args( $options, array(
			'color' => 'yellow',
		) );
		$this->plugin->db->insert_meta( $record_id, 'highlight', $options['color'] );
	}

	/**
	 * Displays a settings form for the alert type
	 *
	 * @param Alert   $alert Alert object for the currently displayed alert.
	 * @param WP_Post $post Post object representing the current alert.
	 * @return void
	 */
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
	 * @param Alert   $alert Alert object for the currently displayed alert.
	 * @param WP_Post $post Post object representing the current alert.
	 * @return void
	 */
	public function process_settings_form( $alert, $post ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );

		$input_color = $_POST['wp_stream_highlight_color'];
		if ( ! array_key_exists( $input_color , $this->get_highlight_options() ) ) {
			$alert->alert_meta['color'] = 'yellow';
		} else {
			$alert->alert_meta['color'] = $input_color;
		}

	}

	/**
	 * Apply highlight to records
	 *
	 * @param array $classes List of classes being applied to the post.
	 * @param array $recordarr Record data.
	 * @return array New list of classes.
	 */
	public function post_class( $classes, $recordarr ) {
		if ( ! empty( $recordarr->meta['highlight'] ) ) {
				$classes[] = 'highlight-notification-' . esc_attr( $recordarr->meta['highlight'] );
		}
		return $classes;
	}
}
