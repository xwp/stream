<?php
if ( false === ( $extensions = get_transient( 'stream-extensions-list' ) ) ) {
	$response   = wp_remote_get( 'http://vvv.wp-stream.com/wp-json.php/posts/?type=extension' );
	$extensions = ! is_wp_error( $response ) ? json_decode( wp_remote_retrieve_body( $response ) ) : null;
	if ( $extensions ) {
		set_transient( 'stream-extensions-list', $extensions, DAY_IN_SECONDS );
	}
}

$count = 0;

// Create an array of all plugin paths, using the text-domain as a unique key slug
$plugin_paths = array();
foreach ( get_plugins() as $path => $data ) {
	if ( isset( $data['TextDomain'] ) && ! empty( $data['TextDomain'] ) ) {
		$plugin_paths[ $data['TextDomain'] ] = $path;
	}
}

if ( ! empty( $extensions ) ) :

	// Order the extensions by post title
	usort( $extensions, function( $a, $b ) { return strcmp( $a->title, $b->title ); } );

	?>
	<h2><?php esc_html_e( 'Stream Extensions', 'stream' ) ?>
		<span class="theme-count"><?php echo absint( count( $extensions ) ) ?></span>
		<?php if ( ! get_option( 'stream-license' ) ) : ?>
			<a href="#" class="button button-primary stream-premium-connect" data-stream-connect="1"><?php esc_html_e( 'Connect to Stream Premium', 'stream' ) ?></a>
		<?php else : ?>
			<a href="#" class="button button-secondary stream-premium-disconnect" data-stream-disconnect="1"><?php esc_html_e( 'Disconnect', 'stream' ) ?></a>
		<?php endif; ?>
		<span class="spinner" style="float: none"></span>
	</h2>

	<?php if ( ! get_option( 'stream-license' ) ) : ?>
		<p class="description">
			<?php esc_html_e( "Connect your Stream Premium account and authorize this domain to install and receive automatic updates for premium extensions. Don't have an account?", 'stream' ) ?> <a href="https://wp-stream.com/join/" class="stream-premium-join"><?php esc_html_e( 'Join Stream Premium', 'stream' ) ?></a>
		</p>
	<?php else : ?>
		<p class="description" style="color: green;">
			<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Your account is connected!', 'stream' ) ?>
		</p>
	<?php endif; ?>

	<p class="description stream-license-check-message"></p>

	<div class="theme-browser rendered">

		<div class="themes">

			<?php foreach ( $extensions as $extension ) : ?>

				<?php
				$text_domain  = isset( $extension->slug ) ? sprintf( 'stream-%s', $extension->slug ) : null;
				$plugin_path  = array_key_exists( $text_domain, $plugin_paths ) ? $plugin_paths[ $text_domain ] : null;
				$is_active    = ( $plugin_path && is_plugin_active( $plugin_path ) );
				$is_installed = ( $plugin_path && defined( 'WP_PLUGIN_DIR' ) && file_exists( trailingslashit( WP_PLUGIN_DIR )  . $plugin_path ) );
				$action_link  = isset( $extension->post_meta->external_url[0] ) ? $extension->post_meta->external_url[0] : $extension->link;
				$action_link  = ! empty( $action_link ) ? $action_link : $extension->link;
				$image_src    = isset( $extension->featured_image->source ) ? $extension->featured_image->source : null;
				$image_src    = ! empty( $image_src ) ? $image_src : null;
				?>

				<div class="theme<?php if ( $is_active ) { echo esc_attr( ' active' ); } ?>">
					<a href="<?php echo esc_url( $extension->link ) ?>" target="_blank">
						<div class="theme-screenshot<?php if ( ! $image_src ) { echo esc_attr( ' blank' ); } ?>">
							<?php if ( $image_src ) : ?>
								<img src="<?php echo esc_url( $image_src ) ?>" alt="<?php echo esc_attr( $extension->title ) ?>">
							<?php endif; ?>
						</div>
						<span class="more-details"><?php esc_html_e( 'View Details', 'stream' ) ?></span>
						<h3 class="theme-name">
							<span><?php echo esc_html( $extension->title ) ?></span>
							<?php if ( $is_installed && ! $is_active ) : ?>
								<span class="inactive"><?php esc_html_e( 'Inactive', 'stream' ) ?></span>
							<?php endif; ?>
						</h3>
					</a>
					<div class="theme-actions">
						<?php if ( ! $is_installed ) { ?>
							<?php if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) : ?>
								<a href="<?php echo esc_url( $action_link ) ?>" class="button button-secondary" target="_blank">
									<?php esc_html_e( 'Get This Extension', 'stream' ) ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( add_query_arg( 'install', 'stream-' . $extension->slug ) ) ?>" class="button button-primary">
									<?php esc_html_e( 'Install Now', 'stream' ) ?>
								</a>
							<?php endif; ?>
						<?php } elseif ( $is_installed && ! $is_active ) { ?>
							<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ) ?>" class="button button-primary">
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

	<?php else : ?>

		<h2><?php esc_html_e( 'Stream Extensions', 'stream' ) ?></h2>

		<p><em><?php esc_html_e( 'Sorry, there was a problem loading the list of extensions.', 'stream' ) ?></em></p>

		<p><a class="button button-primary" href="http://wp-stream.com/#extensions" target="_blank"><?php esc_html_e( 'Browse All Extensions', 'stream' ) ?></a></p>

	<?php endif; ?>
