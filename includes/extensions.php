<?php
$response   = wp_remote_get( 'http://vvv.wp-stream.com/wp-json.php/posts/?type=extension' );
$extensions = ! is_wp_error( $response ) ? json_decode( wp_remote_retrieve_body( $response ) ) : null;
$count      = 0;

if ( ! empty( $extensions ) ) {

	// Order the extensions by post title
	usort( $extensions, function( $a, $b ) { return strcmp( $a->title, $b->title ); } );

	foreach ( $extensions as $key => $extension ) {
		$type = isset( $extension->post_meta->plugin_type[0] ) ? $extension->post_meta->plugin_type[0] : null;

		if ( 'premium' != $type ) {
			unset( $extensions[$key] );
			continue;
		}

		$plugin_path = isset( $extension->post_meta->plugin_path[0] ) ? $extension->post_meta->plugin_path[0] : null;
		$is_active   = ( $plugin_path && is_plugin_active( $plugin_path ) );
	}
}
?>

<?php if ( ! empty( $extensions ) ) : ?>

	<h2><?php esc_html_e( 'Stream Premium Extensions', 'stream' ) ?>
		<span class="theme-count"><?php echo absint( count( $extensions ) ) ?></span>
		<a href="#" class="button button-primary stream-premium-connect"><?php esc_html_e( 'Connect to Stream Premium', 'stream' ) ?></a>
	</h2>

	<p class="description">
		<?php esc_html_e( "Connect to your Stream Premium account and authorize this domain to install and receive automatic updates for premium extensions. Don't have an account?", 'stream' ) ?> <a href="#" class="stream-premium-signup"><?php esc_html_e( 'Join Stream Premium', 'stream' ) ?></a>
	</p>

	<?php
	/*
	<h2><?php esc_html_e( 'Stream Premium Extensions', 'stream' ) ?>
		<span class="theme-count"><?php echo absint( count( $extensions ) ) ?></span>
		<a href="#" class="button button-secondary stream-premium-disconnect"><?php esc_html_e( 'Disconnect', 'stream' ) ?></a>
	</h2>

	<p class="description" style="color: green;">
		<div class="dashicons dashicons-yes"></div> Your account is connected!
	</p>
	*/
	?>

	<p class="description stream-license-check-message"></p>

	<div class="theme-browser rendered">

		<div class="themes">

			<?php foreach ( $extensions as $extension ) : ?>

				<?php
				$plugin_path  = isset( $extension->post_meta->plugin_path[0] ) ? $extension->post_meta->plugin_path[0] : null;
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
						<h3 class="theme-name"><span><?php echo esc_html( $extension->title ) ?></span></h3>
					</a>
					<div class="theme-actions">
						<?php if ( ! $is_installed ) { ?>
							<a class="button button-primary" href="<?php echo esc_url( $action_link ) ?>" target="_blank">
								<?php esc_html_e( 'Get This Extension', 'stream' ) ?>
							</a>
						<?php } elseif ( ! $is_active ) { ?>
							<a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ) ?>">
								<?php esc_html_e( 'Activate', 'stream' ) ?>
							</a>
						<?php } else { ?>
							<?php esc_html_e( 'Active', 'stream' ) ?>
						<?php } ?>
					</div>
				</div>

			<?php endforeach; ?>

		</div>

		<br class="clear">

	</div>

	<?php else : ?>

		<h2><?php esc_html_e( 'Stream Premium Extensions', 'stream' ) ?></h2>

		<p><em><?php esc_html_e( 'Sorry, there was a problem loading the list of extensions.', 'stream' ) ?></em></p>

		<p><a class="button button-primary" href="http://wp-stream.com/#extensions" target="_blank"><?php esc_html_e( 'Browse All Extensions', 'stream' ) ?></a></p>

	<?php endif; ?>
