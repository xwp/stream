<?php
/**
 * Extensions Class
 *
 * @author Chris Olbekson <chris@x-team.com
 */

class WP_Stream_Extensions {

	const EXTENSIONS_KEY = 'wp_stream_extensions_';
	const MEMBER_KEY     = 'wp_stream_member';
	const DATA_API_URL   = 'https://wp-stream.com/json/';

	var $extensions;

	var $extension_data;

	var $stream_member = false;

	var $license_key = NULL;



	function __construct() {
		$this->extensions = $this->get_extension_data();

	}

	/**
	 * Settings page callback hook. Renders the extensions page.
	 *
	 * @return void
	 */
	function render_page() {
		?>
		<div class="wrap">

			<h2><?php _e( 'Stream Extensions', 'stream' ) ?></h2>
			<?php settings_errors() ?>

			<h1>Coming really, really soon. This is where the extensions will live.</h1>

		</div>
		<?php
	}

	/**
	 * Gets the extension data from the wp-stream.com json extension api
	 *
	 * @return array
	 */
	function get_extension_api() {
		return array();
	}

	/**
	 * Activates membership to access premium extensions
	 * stores a hashed membership key in the db
	 *
	 */
	private function activate_membership() {
		$license_key = wp_remote_get( self::DATA_API_URL, array() );
		set_transient( self::MEMBER_KEY, array( 'license_key' => $license_key ), MINUTE_IN_SECONDS * 60 * 48 );
		return $license_key;
	}

	/**
	 * Verifies membership status of current active member
	 *
	 * @return bool true if membership active
	 */
	private function verify_membership() {
		if ( false === ( $license_key = get_transient( self::MEMBER_KEY ) ) ) {
			$license_key = $this->activate_membership();
		}
		if ( $license_key)
		$key_hash = wp_hash_password( $license_key );
		if ( ! $this->stream_member /** request to wp-stream.com */ ) {
			/** Checks if the hash of the users key matches the hash of users key stored on wp-stream.com */
			return wp_remote_get( self::DATA_API_URL, array( 'hashed_key' => $key_hash ) );
		}
		return false;
	}

	/**
	 * Activates an already installed extension
	 *
	 */
	function activate_extension( $extension ) {
		if ( $this->verify_membership() ) {
			$current = get_option( 'active_plugins' );
			$plugin  = plugin_basename( trim( $extension ) );

			if ( ! in_array( $plugin, $current ) ) {
				$current[] = $plugin;
				sort( $current );
				do_action( 'activate_plugin', trim( $plugin ) );
				update_option( 'active_plugins', $current );
				do_action( 'activate_' . trim( $plugin ) );
				do_action( 'activated_plugin', trim( $plugin ) );
			}
		}

	}

	function download_extension() {

	}

	function check_for_extension_updates() {

	}

	function update_extension() {
		if ( $this->verify_membership() ) {
			/** Call Update Extension Method  @see https://github.com/x-team/wp-stream/issues/400 */
		}

	}

	/**
	 * Checks for extension data stored in transient
	 *
	 * @return array|mixed
	 */
	function get_extension_data() {
		if ( false === ( $api_transient = get_transient( self::EXTENSIONS_KEY ) ) ) {
			$api_transient = $this->get_extension_api();

			if ( $api_transient ) {
				set_transient( self::EXTENSIONS_KEY, $this->get_extension_api(), MINUTE_IN_SECONDS * 60 * 12 * 2 );
			}
			return $api_transient;
		}

		return $api_transient;
	}

	/**
	 * HTML Single extension display template
	 *
	 * @param $extension
	 * @return void
	 */
	function extension_template( $extension ) {
		?>
		<script id="tmpl-theme-single" type="text/template">
		<div class="theme-backdrop"></div>
		<div class="theme-wrap">
			<div class="theme-header">
				<button class="left dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show previous theme' ); ?></span></button>
				<button class="right dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show next theme' ); ?></span></button>
				<button class="close dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Close overlay' ); ?></span></button>
			</div>
			<div class="theme-about">
				<div class="theme-screenshots">
				<# if ( data.screenshot[0] ) { #>
					<div class="screenshot"><img src="{{ data.screenshot[0] }}" alt="" /></div>
				<# } else { #>
					<div class="screenshot blank"></div>
				<# } #>
			</div>

			<div class="theme-info">
				<# if ( data.active ) { #>
					<span class="current-label"><?php _e( 'Current Theme' ); ?></span>
				<# } #>
				<h3 class="theme-name">{{{ data.name }}}<span class="theme-version"><?php printf( __( 'Version: %s' ), '{{{ data.version }}}' ); ?></span></h3>
				<h4 class="theme-author"><?php printf( __( 'By %s' ), '{{{ data.authorAndUri }}}' ); ?></h4>

				<# if ( data.hasUpdate ) { #>
				<div class="theme-update-message">
					<h4 class="theme-update"><?php _e( 'Update Available' ); ?></h4>
					{{{ data.update }}}
				</div>
				<# } #>
				<p class="theme-description">{{{ data.description }}}</p>

				<# if ( data.parent ) { #>
					<p class="parent-theme"><?php printf( __( 'This is a child theme of %s.' ), '<strong>{{{ data.parent }}}</strong>' ); ?></p>
				<# } #>

				<# if ( data.tags ) { #>
					<p class="theme-tags"><span><?php _e( 'Tags:' ); ?></span> {{{ data.tags }}}</p>
				<# } #>
			</div>
		</div>

		<div class="theme-actions">
			<div class="active-theme">
				<a href="{{{ data.actions.customize }}}" class="button button-primary customize load-customize hide-if-no-customize"><?php _e( 'Customize' ); ?></a>
				<?php echo esc_html( implode( ' ', $current_theme_actions ) ) ?>
			</div>
			<div class="inactive-theme">
				<# if ( data.actions.activate ) { #>
					<a href="{{{ data.actions.activate }}}" class="button button-primary activate"><?php _e( 'Activate' ); ?></a>
				<# } #>
				<a href="{{{ data.actions.customize }}}" class="button button-secondary load-customize hide-if-no-customize"><?php _e( 'Live Preview' ); ?></a>
				<a href="{{{ data.actions.preview }}}" class="button button-secondary hide-if-customize"><?php _e( 'Preview' ); ?></a>
			</div>

			<# if ( ! data.active && data.actions['delete'] ) { #>
				<a href="{{{ data.actions['delete'] }}}" class="button button-secondary delete-theme"><?php _e( 'Delete' ); ?></a>
			<# } #>
		</div>
		</div>
		</script>

		<?php
	}
}