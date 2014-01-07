<?php
$response   = wp_remote_get( 'http://wp-stream.com/wp-json.php/posts?type=download' );
$extensions = ! is_wp_error( $response ) ? json_decode( wp_remote_retrieve_body( $response ) ) : null;
$count      = 0;

if ( ! empty( $extensions ) ) {
	foreach ( $extensions as $extension ) {
		$plugin_path  = isset( $extension->post_meta->plugin_path[0] ) ? $extension->post_meta->plugin_path[0] : null;
		$is_installed = ( $plugin_path && ( is_plugin_active( $plugin_path ) || is_plugin_inactive( $plugin_path ) ) );

		if ( $is_installed ) {
			$count++;
		}
	}
}
?>

<?php if ( ! empty( $extensions ) ) : ?>

	<h2><?php esc_html_e( 'Stream Extensions', 'stream' ) ?>
		<span class="theme-count"><?php echo absint( $count ) ?></span>
		<a class="button button-primary" href="http://wp-stream.com/extensions/" target="_blank"><?php esc_html_e( 'Browse All Extensions', 'stream' ) ?></a>
	</h2>

	<p><em><?php esc_html_e( 'Take your user activity data to the next level with Stream Extensions! These plugins extend the base functionality of Stream and are available as separate downloads.', 'stream' ) ?></em></p>

	<div class="theme-browser rendered">

		<div class="themes">

			<?php foreach ( $extensions as $extension ) : ?>

				<?php
				$plugin_path  = isset( $extension->post_meta->plugin_path[0] ) ? $extension->post_meta->plugin_path[0] : null;
				$is_active    = ( $plugin_path && is_plugin_active( $plugin_path ) );
				$is_installed = ( $plugin_path && ( $is_active || is_plugin_inactive( $plugin_path ) ) );
				$action_link  = isset( $extension->post_meta->external_url[0] ) ? $extension->post_meta->external_url[0] : $extension->link;
				?>

				<div class="theme<?php if ( $is_installed ) { echo esc_attr( ' active' ); } ?>">
					<a href="<?php echo esc_url( $extension->link ) ?>" target="_blank">
						<div class="theme-screenshot">
							<img src="http://local.wordpress-trunk.dev/wp-content/themes/twentyeleven/screenshot.png" alt="<?php echo esc_attr( $extension->title ) ?>">
						</div>
						<span class="more-details"><?php esc_html_e( 'View Details', 'stream' ) ?></span>
						<h3 class="theme-name"><span><?php echo esc_html( $extension->title ) ?></span></h3>
					</a>
					<div class="theme-actions">
						<?php if ( ! $is_installed ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( $action_link ) ?>" target="_blank">
								<?php esc_html_e( 'Get This Extension', 'stream' ) ?>
							</a>
						<?php elseif ( ! $is_active ) : ?>
							<?php esc_html_e( 'Inactive', 'stream' ) ?>
						<?php else : ?>
							<?php esc_html_e( 'Active', 'stream' ) ?>
						<?php endif; ?>
					</div>
				</div>

			<?php endforeach; ?>

		</div>

		<br class="clear">

	</div>

<?php else : ?>

	<h2><?php esc_html_e( 'Stream Extensions', 'stream' ) ?></h2>

	<p><?php esc_html_e( 'Sorry, there was a problem loading the extensions list.', 'stream' ) ?></p>

	<p><a class="button button-primary" href="http://wp-stream.com/extensions/" target="_blank"><?php esc_html_e( 'Browse All Extensions', 'stream' ) ?></a></p>

<?php endif; ?>
