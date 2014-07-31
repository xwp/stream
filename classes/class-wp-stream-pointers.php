<?php
/**
 * Stream pointers. Based on WP core internal pointers.
 *
 * @since 1.4.4
 */
class WP_Stream_Pointers {

	public static $pointers = array();
	public static $caps     = array();

	public static function load() {
		add_action( 'admin_enqueue_scripts', array( 'WP_Stream_Pointers', 'enqueue_scripts' ) );
		add_action( 'user_register', array( 'WP_Stream_Pointers', 'dismiss_pointers_for_new_users' ) );
	}

	public static function init_core_pointers() {
		self::$pointers = array(
			'WP_Stream_Pointers' => array(
				'index.php'                                           => 'wpstream143_extensions',
				'toplevel_page_' . WP_Stream_Admin::RECORDS_PAGE_SLUG => 'wpstream143_extensions',
				'stream_page_' . WP_Stream_Admin::SETTINGS_PAGE_SLUG  => 'wpstream143_extensions',
			)
		);

		self::$caps = array(
			'WP_Stream_Pointers' => array(
				'wpstream143_extensions' => array( 'install_plugins' ),
			)
		);
	}

	/**
	 * Initializes the pointers.
	 *
	 * @since 1.4.4
	 *
	 * All pointers can be disabled using the following:
	 *     remove_action( 'admin_enqueue_scripts', array( 'WP_Stream_Pointers', 'enqueue_scripts' ) );
	 *
	 * Individual pointers (e.g. wpstream143_extensions) can be disabled using the following:
	 *     remove_action( 'admin_print_footer_scripts', array( 'WP_Stream_Pointers', 'pointer_wpstream143_extensions' ) );
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		/*
		 * Register feature pointers
		 * Format: array( hook_suffix => pointer_id )
		 */
		self::init_core_pointers();

		$get_pointers = array_merge( self::$pointers, apply_filters( 'wp_stream_pointers', array() ) );
		$caps         = array_merge( self::$caps, apply_filters( 'wp_stream_pointer_caps', array() ) );

		foreach ( $get_pointers as $context => $registered_pointers ) {

			// Check if screen related pointer is registered
			if ( empty( $registered_pointers[ $hook_suffix ] ) ) {
				return;
			}

			$pointers = (array) $registered_pointers[ $hook_suffix ];

			$caps_required = $caps[ $context ];

			// Get dismissed pointers
			$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );

			$got_pointers = false;
			foreach ( array_diff( $pointers, $dismissed ) as $pointer ) {
				if ( isset( $caps_required[ $pointer ] ) ) {
					foreach ( $caps_required[ $pointer ] as $cap ) {
						if ( ! current_user_can( $cap ) ) {
							continue 2;
						}
					}
				}

				// Bind pointer print function
				add_action( 'admin_print_footer_scripts', array( $context, 'pointer_' . $pointer ) );

				$got_pointers = true;
			}
		}

		if ( ! $got_pointers ) {
			return;
		}

		// Add pointers script and style to queue
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
	}

	/**
	 * Print the pointer javascript data.
	 *
	 * @since 1.4.4
	 *
	 * @param string $pointer_id The pointer ID.
	 * @param string $selector The HTML elements, on which the pointer should be attached.
	 * @param array  $args Arguments to be passed to the pointer JS (see wp-pointer.js).
	 */
	public static function print_js( $pointer_id, $selector, $args ) {
		if ( empty( $pointer_id ) || empty( $selector ) || empty( $args ) || empty( $args['content'] ) ) {
			return;
		}

		?>
		<script type="text/javascript">
		//<![CDATA[
		(function($){
			var options = <?php echo json_encode( $args ) ?>, setup;

			if ( ! options ) {
				return;
			}

			options = $.extend( options, {
				close: function() {
					$.post( ajaxurl, {
						pointer: <?php echo json_encode( $pointer_id ) ?>,
						action: 'dismiss-wp-pointer'
					});
				}
			});

			setup = function() {
				$(<?php echo json_encode( $selector ) ?>).first().pointer( options ).pointer('open');
			};

			if ( options.position && options.position.defer_loading ) {
				$(window).bind( 'load.wp-pointers', setup );
			} else {
				$(document).ready( setup );
			}

		})( jQuery );
		//]]>
		</script>
		<?php
	}

	public static function pointer_wpstream143_extensions() {
		$content  = '<h3>' . esc_html__( 'Stream Extensions', 'stream' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'Extension plugins are now available for Stream!', 'stream' ) . '</p>';

		if ( 'dashboard' === get_current_screen()->id ) {
			$selector = sprintf( '#toplevel_page_%s', WP_Stream_Admin::RECORDS_PAGE_SLUG );
			$position = array( 'edge' => is_rtl() ? 'right' : 'left', 'align' => 'center' );
		} else {
			$selector = sprintf( 'a[href="%s?page=%s"]', WP_Stream_Admin::ADMIN_PARENT_PAGE, WP_Stream_Admin::ADMIN_PARENT_PAGE );
			$position = array( 'edge' => is_rtl() ? 'right' : 'left', 'align' => 'center' );
		}

		self::print_js(
			'wpstream143_extensions',
			$selector,
			array(
				'content'  => $content,
				'position' => $position,
			)
		);
	}

	/**
	 * Prevents new users from seeing existing 'new feature' pointers.
	 *
	 * @since 1.4.4
	 */
	public static function dismiss_pointers_for_new_users( $user_id ) {
		add_user_meta( $user_id, 'dismissed_wp_pointers', '' );
	}

}
