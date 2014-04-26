<?php
/**
 * Extensions Class
 *
 * @author Chris Olbekson <chris@x-team.com>
 */

class WP_Stream_Extensions {

	const EXTENSIONS_KEY = 'wp_stream_extensions_';
	const MEMBER_KEY     = 'wp_stream_member';
	const API_EP         = '/wp-json/posts/';
	const API_LICENSE_EP = '/api/';
	const API_DOMAIN     = 'wp-stream.com';
	const API_TRANSPORT  = 'https://';
	const API_QUERY      = '?type=extension';

	/**
	 * @var array|mixed
	 */
	var $extensions;

	/**
	 * @var string
	 */
	var $api_uri;

	/**
	 * @var array
	 */
	var $plugin_paths;

	/**
	 * @var string|null
	 */
	var $license_key = NULL;

	/**
	 * @var bool
	 */
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

		add_filter( 'upgrader_pre_download', array( $this, 'pre_update_download_check' ), 10, 3 );
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
	 * @param bool $force If set to true stored transient by passed
	 * @return array|mixed
	 */
	function get_extension_data( $force = false ) {
		if ( $force || false === ( $api_transient = get_transient( self::EXTENSIONS_KEY ) ) ) {
			$api_transient = $this->get_extension_api();

			if ( $api_transient && ! empty( $api_transient ) ) {
				set_transient( self::EXTENSIONS_KEY, $this->get_extension_api(), MINUTE_IN_SECONDS * 60 * 6 );
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

	/**
	 * Filters the plugin install api
	 *
	 * @param $false
	 * @param $action
	 * @param $args
	 *
	 * @return stdClass
	 */
	function filter_plugin_api_info( $false, $action, $args ) {
		if ( ! $this->verify_membership() ) {
			$message = '<p>' . sprintf( __( 'You must connect to your %s account to install extensions.', 'stream' ), '<strong>' . esc_html__( 'Stream Premium', 'stream' ) . '</strong>' ) . '</p><p>' . esc_html__( "Don't have an account?", 'stream' ) . '</p><p><a href="https://wp-stream.com/join/" target="_blank" class="button">' . esc_html__( 'Join Stream Premium', 'stream' ) . '</a></p>';
			wp_die( $message, 'Stream Extension Installation', array( 'response' => 200, 'back_link' => true ) ); // xss ok
		}
		if ( 'plugin_information' == $action && empty( $false ) ) {
			/** @internal The querying the api using the filter endpoint doesn't seem to work. For now I'm looping through all the extensions to get the api info for using WordPress install api  */
			$site    = esc_url_raw( parse_url( get_option( 'siteurl' ), PHP_URL_HOST ) );
			$license = get_site_option( WP_Stream_Updater::LICENSE_KEY );
			foreach ( $this->get_extension_data() as $extension ) {
				if ( $extension->slug == $args->slug ) {
					$api = new stdClass();
					$api->name = $extension->title;
					$api->version = $extension->post_meta->current_version[0];
					$api->download_link = add_query_arg(
						array(
							'site'    => $site,
							'license' => $license,
							'key'  => 'install',
						),
						$extension->post_meta->download_url[0]
					);

					return $api;
				}
			}
		}

		return $false;
	}

	/**
	 * Filter to allow extension downloads from external domain
	 *
	 * @param bool $allow False by default. True when external domain is white listed
	 * @param string $host Host being checked against white list
	 * @param string $url
	 *
	 * @return bool
	 */
	function filter_allowed_external_host( $allow, $host, $url ) {
		if ( $host == self::API_DOMAIN ) {
			$allow = true;
		}

		return $allow;
	}

	/**
	 * Verifies membership status of current active member
	 *
	 * @return bool true if membership active
	 */
	private function verify_membership() {
		if ( get_site_option( WP_Stream_Updater::LICENSE_KEY ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Filters the WP_Upgrader download method to check if user is connected to stream premium before attempting the update download
	 *
	 * @param bool $false
	 * @param string $package The url to the download zip file
	 *
	 * @return mixed
	 */
	function pre_update_download_check( $false, $package ) {
		if ( false === strpos( $package, self::API_DOMAIN ) ) {
			return $false;
		}

		if ( ! $this->verify_membership() ) {
			wp_die( __( 'Please connect your site to stream premium to enable updates', 'stream' ), 'Stream Update Error', array( 'response' => 200, 'back_link' => true ) );
		}

		return $false;
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

	/**
	 * Output HTML header on extensions page. Contains the connect to stream verify membership button
	 *
	 * @param $extensions
	 *
	 * @return mixed
	 */
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
				<a href="<?php echo esc_url( self::API_TRANSPORT . self::API_DOMAIN . '/join/' ) ?>" class="stream-premium-join"><?php esc_html_e( 'Join Stream Premium', 'stream' ) ?></a>
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
	 *
	 * @return void
	 */
	function extensions_display_body( $extensions ) {
		if ( empty( $extensions ) ) { ?>
			<h2><?php _e( 'Stream Extensions', 'stream' ) ?></h2>
			<p>
				<em><?php esc_html_e( 'Sorry, there was a problem loading the list of extensions.', 'stream' ) ?></em></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::API_TRANSPORT . self::API_DOMAIN . '/#extensions' ) ?>" target="_blank"><?php esc_html_e( 'Browse All Extensions', 'stream' ) ?></a>
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
				?>

				<div class="theme<?php if ( $is_active ) { echo esc_attr( ' active' ); } ?> thickbox" tabindex="0" data-extension="<?php echo esc_attr( $extension->slug ); ?>">
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
		<?php
		$this->render_extension_overlay();
	}

	/**
	 * Prepares the extensions for javascript output
	 *
	 * @for wp_localize_script()
	 * @param array $extensions retrieved from the wp-stream.com extension api
	 *
	 * @return array
	 */
	function prepare_extensions_for_js( $extensions ) {
		$prepared_extensions = array();

		foreach ( (array) $extensions as $extension ) {
			$text_domain = isset( $extension->slug ) ? sprintf( 'stream-%s', $extension->slug ) : null;
			$plugin_path = array_key_exists( $text_domain, $this->plugin_paths ) ? $this->plugin_paths[ $text_domain ] : null;

			$prepared_extensions[ $extension->slug ] = array(
				'id'           => $extension->slug,
				'name'         => $extension->title,
				'screen_shot'  => isset( $extension->featured_image->source ) ? $extension->featured_image->source : null,
				'video'        => isset( $extension->post_meta->video_url[0] ) ? $extension->post_meta->video_url[0] : null,
				'remote_img'   => isset( $extension->post_meta->remote_image[0] ) ? $extension->post_meta->remote_image[0] : null,
				'content'      => $extension->content,
				'excerpt'      => $extension->excerpt,
				'version'      => isset( $extension->post_meta->current_version[0] ) ? $extension->post_meta->current_version[0] : null,
				'active'       => ( $plugin_path && is_plugin_active( $plugin_path ) ),
				'installed'    => ( $plugin_path && defined( 'WP_PLUGIN_DIR' ) && file_exists( trailingslashit( WP_PLUGIN_DIR ) . $plugin_path ) ),
				'update'       => false,
				'install18n'   => __( 'Install Now', 'stream' ),
				'activate18n'  => __( 'Activate', 'stream' ),
				'active18n'    => __( 'Active', 'stream' ),
				'actions'      => array(
					'activate' => wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'plugin' => $extension->post_meta->plugin_path[0], 'plugin_status' => 'all', 'paged' => '1' ), self_admin_url( 'plugins.php' ) ), 'activate-plugin_' . $extension->post_meta->plugin_path[0] ),
					'install'  => wp_nonce_url( add_query_arg( array( 'action' => 'install-plugin', 'plugin' => $extension->slug ), self_admin_url( 'update.php' ) ), 'install-plugin_' . $extension->slug ),
					'delete'   => null,
				),
			);
		}

		return $prepared_extensions;
	}

	/**
	 * HTML extension overlay template. Populated by javascript when extension is clicked
	 *
	 */
	function render_extension_overlay() {
		?>
		<div class="theme-overlay hidden">
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
						<h3 class="theme-name"><span class="theme-version"></span></h3>
						<p class="theme-description"></p>
					</div>
				</div>

				<div class="theme-actions"></div>
			</div>
		</div>
		<!-- CSS to make Youtube video container responsive -->
		<style>
			.video-container {
				position:       relative;
				padding-bottom: 56.25%;
				padding-top:    30px; height: 0; overflow: hidden;
			}
			.video-container iframe,
			.video-container object,
			.video-container embed {
				position: absolute;
				top:      0;
				left:     0;
				width:    100%;
				height:   100%;
			}
			.theme-overlay .screenshot {
				border: none!important;
				box-shadow: none;
				-webkit-box-shadow: none;
			}
		</style>
	<?php
	}
}
