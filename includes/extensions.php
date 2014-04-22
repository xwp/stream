<?php
/**
 * Extensions Class
 *
 * @author Chris Olbekson <chris@x-team.com>
 */

class WP_Stream_Extensions {

	const EXTENSIONS_KEY = 'wp_stream_extensions_';
	const MEMBER_KEY     = 'wp_stream_member';
	const API_EP         = '/wp-json.php/posts/';
	const API_LICENSE_EP = '/api/';
	const API_DOMAIN     = 'vvv.wp-stream.com';
	const API_TRANSPORT  = 'http://'; /** @internal will need valid ssl cert before using https:// transport  */
	const API_QUERY      = '?type=extension';

	var $extensions;

	var $extension_data;

	var $api_uri;

	var $license_api_uri;

	var $plugin_paths;

	var $stream_member = true; // /** @internal setting to true for testing the page output */

	var $license_key = NULL;

	public static $instance = false;

	public static function get_instance() {
		if ( ! self::$instance )
			self::$instance = new self();

		return self::$instance;
	}

	function __construct() {
		$this->api_uri = self::API_TRANSPORT . self::API_DOMAIN . self::API_EP;
		$this->license_api_uri = self::API_TRANSPORT . self::API_DOMAIN . self::API_LICENSE_EP;

		$this->extensions   = $this->get_extension_data();
		$this->plugin_paths = $this->get_plugin_paths();

		add_filter( 'plugins_api', array( $this, 'filter_plugin_api_info' ), 99, 3 );
		add_filter( 'http_request_host_is_external', array( $this, 'filter_allowed_external_host' ), 10, 3 );
	}

	/**
	 * Settings page callback hook. Renders the extensions page.
	 *
	 * @return void
	 */
	function render_page() {
		$this->extensions_display_body( $this->extensions );
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
	 * Gets the extension data from the wp-stream.com json extension api
	 *
	 * @return array|bool Array of extensions on success or false on WP_Error
	 */
	function get_extension_api() {
		$response = wp_remote_get( $this->api_uri . self::API_QUERY );
		if ( ! is_wp_error( $response ) ) {
			return json_decode( wp_remote_retrieve_body( $response ) );
		}

		return false;
	}

	function filter_plugin_api_info( $false, $action, $args ) {
		if ( 'plugin_information' == $action && empty( $false ) ) {

			/** @internal The querying the api using the filter endpoint doesn't seem to work. For now I'm looping through all the extensions to get the api info for using WordPress install api  */
			foreach ( $this->get_extension_data() as $extension ) {
				if ( $extension->slug == $args->slug ) {
					$api = new stdClass();
					$api->name = $extension->title;
					$api->version = $extension->post_meta->current_version[0];
					$api->download_link = esc_url_raw( self::API_TRANSPORT . self::API_DOMAIN . $extension->post_meta->download_url[0] );

					return $api;
				}
			}
		}

		return $false;
	}

	function filter_allowed_external_host( $allow, $host, $url ) {
		if ( $host == self::API_DOMAIN ) {
			$allow = true;
		}

		return $allow;
	}

	/**
	 * Activates membership to access premium extensions
	 * stores a hashed membership key in the db
	 *
	 */
	private function activate_membership() {
		$license_key = wp_remote_get( $this->api_uri, array() );
		set_transient( self::MEMBER_KEY, array( 'license_key' => $license_key ), MINUTE_IN_SECONDS * 60 * 48 );

		return $license_key;
	}

	/**
	 * Verifies membership status of current active member
	 *
	 * @return bool true if membership active
	 */
	private function verify_membership() {
		if ( get_option( 'stream-license' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Create an array of all plugin paths, using the text-domain as a unique key slug
	 *
	 * @return array
	 */
	function get_plugin_paths() {
		$plugin_paths = array();
		foreach ( get_plugins() as $path => $data ) {
			if ( isset( $data['TextDomain'] ) && ! empty( $data['TextDomain'] ) ) {
				$plugin_paths[$data['TextDomain']] = $path;
			}
		}
		return $plugin_paths;
	}

	function extensions_display_header( $extensions ) {
		?>
		<h2><?php esc_html_e( 'Stream Extensions', 'stream' ) ?>
			<span class="theme-count"><?php echo absint( count( $extensions ) ) ?></span>
			<?php if ( ! $this->verify_membership() ) : ?>
				<a href="#" class="button button-primary stream-premium-connect" data-stream-connect="1"><?php esc_html_e( 'Connect to Stream Premium', 'stream' ) ?></a>
			<?php else : ?>
				<a href="#" class="button button-secondary stream-premium-disconnect" data-stream-disconnect="1"><?php esc_html_e( 'Disconnect', 'stream' ) ?></a>
			<?php endif; ?>
			<span class="spinner" style="float: none"></span>
		</h2>

		<?php if ( ! $this->verify_membership() ) : ?>
			<p class="description">
			<?php esc_html_e( "Connect your Stream Premium account and authorize this domain to install and receive automatic updates for premium extensions. Don't have an account?", 'stream' ) ?>
				<a href="https://wp-stream.com/join/" class="stream-premium-join"><?php esc_html_e( 'Join Stream Premium', 'stream' ) ?></a>
			</p>
		<?php else : ?>
			<p class="description" style="color: green;">
			<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Your account is connected!', 'stream' ) ?>
			</p>
		<?php endif;

		return $extensions;
	}

	/**
	 * Output HTML to display grid of extensions
	 *
	 * @param array $extensions Array of available extensions from the json api
	 * @return void
	 */
	function extensions_display_body( $extensions ) {
		if ( empty( $extensions ) ) { ?>
			<h2><?php _e( 'Stream Extensions', 'stream' ) ?></h2>
			<p>
				<em><?php esc_html_e( 'Sorry, there was a problem loading the list of extensions.', 'stream' ) ?></em></p>
			<p>
				<a class="button button-primary" href="http://wp-stream.com/#extensions" target="_blank"><?php esc_html_e( 'Browse All Extensions', 'stream' ) ?></a>
			</p>
			<?php
			return;
		} else {
			// Order the extensions by post title
			usort(
				$extensions, function ( $a, $b ) {
					return strcmp( $a->title, $b->title );
				}
			);
			$this->extensions_display_header( $extensions );
		} ?>

		<p class="description stream-license-check-message"></p>

		<div class="theme-browser rendered">

			<div class="themes">

				<?php foreach ( $extensions as $extension ) : ?>

					<?php
					$text_domain   = isset( $extension->slug ) ? sprintf( 'stream-%s', $extension->slug ) : null;
					$plugin_path   = array_key_exists( $text_domain, $this->plugin_paths ) ? $this->plugin_paths[ $text_domain ] : null;
					$is_active     = ( $plugin_path && is_plugin_active( $plugin_path ) );
					$is_installed  = ( $plugin_path && defined( 'WP_PLUGIN_DIR' ) && file_exists( trailingslashit( WP_PLUGIN_DIR )  . $plugin_path ) );
					$action_link   = isset( $extension->post_meta->external_url[0] ) ? $extension->post_meta->external_url[0] : $extension->link;
					$action_link   = ! empty( $action_link ) ? $action_link : $extension->link;
					$image_src     = isset( $extension->featured_image->source ) ? $extension->featured_image->source : null;
					$image_src     = ! empty( $image_src ) ? $image_src : null;
					$install_link  = wp_nonce_url( add_query_arg( array( 'action' => 'install-plugin', 'plugin' => $extension->slug ), self_admin_url( 'update.php' ) ), 'install-plugin_' . $extension->slug );
					$activate_link = wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'plugin' => $extension->post_meta->plugin_path[0], 'plugin_status' => 'all', 'paged' => '1' ), self_admin_url( 'plugins.php' ) ), 'activate-plugin_' . $extension->post_meta->plugin_path[0] );
					$aria_action = esc_attr( $extension->slug . '-action' );
					$aria_name   = esc_attr( $extension->slug . '-name' );
					?>

					<div class="theme<?php if ( $is_active ) { echo esc_attr( ' active' ); } ?>" tabindex="0" data-extension="<?php echo esc_attr( $extension->slug ); ?>">
<!--						<a href="--><?php //echo esc_url( $extension->link ) ?><!--" target="_blank">-->
							<div class="theme-screenshot<?php if ( ! $image_src ) { echo esc_attr( ' blank' ); } ?>">
								<?php if ( $image_src ) : ?>
									<img src="<?php echo esc_url( $image_src ) ?>" alt="<?php echo esc_attr( $extension->title ) ?>">
								<?php endif; ?>
							</div>
							<span class="more-details" id="<?php echo esc_attr( $aria_action ); ?>"><?php esc_html_e( 'View Details', 'stream' ) ?></span>
							<h3 class="theme-name">
								<span><?php echo esc_html( $extension->title ) ?></span>
								<?php if ( $is_installed && ! $is_active ) : ?>
								<span class="inactive"><?php esc_html_e( 'Inactive', 'stream' ) ?></span>
								<?php endif; ?>
							</h3>
<!--						</a>-->
					<div class="theme-actions">
						<?php if ( ! $is_installed ) { ?>
							<?php if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) : ?>
								<a href="<?php echo esc_url( $action_link ) ?>" class="button button-secondary" target="_blank">
									<?php esc_html_e( 'Get This Extension', 'stream' ) ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( $install_link ) ?>" class="button button-primary">
									<?php esc_html_e( 'Install Now', 'stream' ) ?>
								</a>
							<?php endif; ?>
						<?php } elseif ( $is_installed && ! $is_active ) { ?>
							<a href="<?php echo esc_url( $activate_link ) ?>" class="button button-primary">
								<?php esc_html_e( 'Activate', 'stream' ) ?>
							</a>
						<?php } elseif ( $is_installed && $is_active ) { ?>
							<?php esc_html_e( 'Active', 'stream' ) ?>
						<?php } ?>
					</div>
				</div>

			<?php endforeach; ?>

			</div>

			<br class="clear">

		</div>
		<div class="theme-overlay"></div>
		<?php
		$this->render_extension_about_template();
	}

	function prepare_extensions_for_js( $extensions ) {
		$prepared_extensions = array();
		foreach ( $extensions as $extension ) {

			$prepared_extensions[ $extension->slug ] = array(
				'id'           => $extension->slug,
				'name'         => $extension->title,
				'screen_shot'   => isset( $extension->featured_image->source ) ? $extension->featured_image->source : null,
				'video'         => '', /** @todo Get video embed code from json api */
				'description'  => $extension->content,
				'author'       => $extension->author->name,
				'authorAndUri' => $extension->author->name,
				'version'      => '1.0', /** @todo Add version number to json api */
				'active'       => true,
				'hasUpdate'    => false,
				'update'       => false,
				'actions'      => array(
					'activate'  => wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'plugin' => $extension->post_meta->plugin_path[0], 'plugin_status' => 'all', 'paged' => '1' ), self_admin_url( 'plugins.php' ) ), 'activate-plugin_' . $extension->post_meta->plugin_path[0] ),
					'install' => null,
					'delete'    => null,
				),
			);
		}
		return $prepared_extensions;
	}

	function render_extension_about_template() {
		?>
			<div class="theme-wrap">
				<div class="theme-header">
					<button class="left dashicons dashicons-no"><span class="screen-reader-text">Show previous extension</span></button>
					<button class="right dashicons dashicons-no"><span class="screen-reader-text">Show next extension</span></button>
					<button class="close dashicons dashicons-no"><span class="screen-reader-text">Close overlay</span></button>
				</div>
			<div class="theme-about">
				<div class="theme-screenshots">
					<div class="screenshot"></div>
				</div>

			<div class="theme-info">
				<p class="theme-description"></p>
			</div>
		</div>

		<div class="theme-actions">
			<div class="active-theme"></div>
		</div>
		</div>
	<?php
	}
}
