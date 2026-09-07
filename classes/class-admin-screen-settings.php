<?php
/**
 * Renders the Stream Settings admin screen.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Admin_Screen_Settings
 */
class Admin_Screen_Settings {

	/**
	 * Class constructor.
	 *
	 * @param Admin $admin Admin façade.
	 */
	public function __construct( private Admin $admin ) {
		$this->register_hooks();
	}

	/**
	 * Register WordPress actions and filters for the settings screen.
	 */
	private function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'display_feature_request_notice' ) );
	}

	/**
	 * Display a feature request notice.
	 *
	 * @action admin_notices
	 *
	 * @return void
	 */
	public function display_feature_request_notice() {
		$screen = get_current_screen();

		// Display the notice only on the Stream settings page.
		if ( empty( $this->admin->menu->screen_id['settings'] ) || $this->admin->menu->screen_id['settings'] !== $screen->id ) {
			return;
		}

		printf(
			'<div class="notice notice-info notice-stream-feature-request"><p>%1$s <a href="https://github.com/xwp/stream/issues/new/choose" target="_blank">%2$s <span class="dashicons dashicons-external"></span></a></p></div>',
			esc_html__( 'Have suggestions or found a bug?', 'stream' ),
			esc_html__( 'Click here to let us know!', 'stream' )
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		$option_key  = $this->admin->plugin->settings->option_key;
		$form_action = apply_filters( 'wp_stream_settings_form_action', admin_url( 'options.php' ) );

		$page_description = apply_filters( 'wp_stream_settings_form_description', '' );

		$sections   = $this->admin->plugin->settings->registry->get_fields();
		$active_tab = wp_stream_filter_input( INPUT_GET, 'tab' );

		$this->admin->plugin->enqueue_asset(
			'settings',
			array(),
			array(
				'i18n' => array(
					'confirm_purge' => __( 'Are you sure you want to delete all Stream activity records from the database? This cannot be undone.', 'stream' ),
				),
			)
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( ! empty( $page_description ) ) : ?>
				<p><?php echo esc_html( $page_description ); ?></p>
			<?php endif; ?>

			<?php settings_errors(); ?>

			<?php if ( count( $sections ) > 1 ) : ?>
				<h2 class="nav-tab-wrapper">
					<?php $i = 0; ?>
					<?php foreach ( $sections as $section => $data ) : ?>
						<?php ++$i; ?>
						<?php $is_active = ( ( 1 === $i && ! $active_tab ) || $active_tab === $section ); ?>
						<a href="<?php echo esc_url( add_query_arg( 'tab', $section ) ); ?>" class="nav-tab <?php echo $is_active ? esc_attr( ' nav-tab-active' ) : ''; ?>">
							<?php echo esc_html( $data['title'] ); ?>
						</a>
					<?php endforeach; ?>
				</h2>
			<?php endif; ?>

			<div class="nav-tab-content" id="tab-content-settings">
				<form method="post" action="<?php echo esc_attr( $form_action ); ?>" enctype="multipart/form-data">
					<div class="settings-sections">
						<?php
						$i = 0;
						foreach ( $sections as $section => $data ) {
							++$i;

							$is_active = ( ( 1 === $i && ! $active_tab ) || $active_tab === $section );

							if ( $is_active ) {
								settings_fields( $option_key );
								do_settings_sections( $option_key );
							}
						}
						?>
					</div>
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
