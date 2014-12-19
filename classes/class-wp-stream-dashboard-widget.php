<?php

class WP_Stream_Dashboard_Widget {

	public static function load() {
		// Load Dashboard widget
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'stream_activity' ) );

		// Dashboard AJAX pagination
		add_action( 'wp_ajax_stream_activity_dashboard_update', array( __CLASS__, 'stream_activity_update_contents' ) );
	}

	/**
	 * Add Stream Activity widget to the dashboard
	 *
	 * @action wp_dashboard_setup
	 */
	public static function stream_activity() {
		if ( ! current_user_can( WP_Stream_Admin::VIEW_CAP ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'dashboard_stream_activity',
			esc_html__( 'Stream Activity', 'stream' ),
			array( __CLASS__, 'stream_activity_initial_contents' ),
			array( __CLASS__, 'stream_activity_options' )
		);
	}

	public static function stream_activity_initial_contents() {
		self::stream_activity_contents();
	}

	public static function stream_activity_update_contents() {
		$paged = ! empty( $_POST['stream-paged'] ) ? absint( $_POST['stream-paged'] ) : 1;
		self::stream_activity_contents( $paged );
		die;
	}

	/**
	 * Contents of the Stream Activity dashboard widget
	 */
	public static function stream_activity_contents( $paged = 1 ) {
		$options          = get_option( 'dashboard_stream_activity_options', array() );
		$records_per_page = isset( $options['records_per_page'] ) ? absint( $options['records_per_page'] ) : 5;
		$args             = array(
			'records_per_page' => $records_per_page,
			'paged'            => $paged,
		);

		$records     = wp_stream_query( $args );
		$total_items = WP_Stream::$db->get_found_rows();

		if ( ! $records ) {
			?>
			<p class="no-records"><?php esc_html_e( 'Sorry, no activity records were found.', 'stream' ) ?></p>
			<?php
			return;
		}

		printf(
			'<ul>%s</ul>',
			implode( '', array_map( array( __CLASS__, 'widget_row' ), $records ) )
		);

		$args = array(
			'current'     => $paged,
			'total_pages' => absint( ceil( $total_items / $records_per_page ) ), // Cast as an integer, not a float
		);

		self::pagination( $args );
	}

	/*
	 * Display pagination links for Dashboard Widget
	 * Copied from private class WP_List_Table::pagination()
	 */
	public static function pagination( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'current'     => 1,
				'total_pages' => 1,
			)
		);

		$current     = $args['current'];
		$total_pages = $args['total_pages'];

		$records_link = add_query_arg(
			array( 'page' => WP_Stream_Admin::RECORDS_PAGE_SLUG ),
			self_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
		);

		$html_view_all = sprintf(
			'<a class="%s" title="%s" href="%s">%s</a>',
			'view-all',
			esc_attr__( 'View all records', 'stream' ),
			esc_url( $records_link ),
			esc_html__( 'View All', 'stream' )
		);

		$page_links    = array();
		$disable_first = '';
		$disable_last  = '';

		if ( 1 === $current ) {
			$disable_first = ' disabled';
		}

		if ( $current === $total_pages ) {
			$disable_last = ' disabled';
		}

		$page_links[] = sprintf(
			'<a class="%s" title="%s" href="%s" data-page="1">%s</a>',
			'first-page' . $disable_first,
			esc_attr__( 'Go to the first page', 'stream' ),
			esc_url( remove_query_arg( 'paged', $records_link ) ),
			'&laquo;'
		);

		$page_links[] = sprintf(
			'<a class="%s" title="%s" href="%s" data-page="%s">%s</a>',
			'prev-page' . $disable_first,
			esc_attr__( 'Go to the previous page', 'stream' ),
			esc_url( add_query_arg( 'paged', max( 1, $current - 1 ), $records_link ) ),
			max( 1, $current - 1 ),
			'&lsaquo;'
		);

		$html_total_pages = sprintf( '<span class="total-pages">%s</span>', number_format_i18n( $total_pages ) );
		$page_links[]    = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging', 'stream' ), number_format_i18n( $current ), $html_total_pages ) . '</span>';

		$page_links[] = sprintf(
			'<a class="%s" title="%s" href="%s" data-page="%s">%s</a>',
			'next-page' . $disable_last,
			esc_attr__( 'Go to the next page', 'stream' ),
			esc_url( add_query_arg( 'paged', min( $total_pages, $current + 1 ), $records_link ) ),
			min( $total_pages, $current + 1 ),
			'&rsaquo;'
		);

		$page_links[] = sprintf(
			'<a class="%s" title="%s" href="%s" data-page="%s">%s</a>',
			'last-page' . $disable_last,
			esc_attr__( 'Go to the last page', 'stream' ),
			esc_url( add_query_arg( 'paged', $total_pages, $records_link ) ),
			$total_pages,
			'&raquo;'
		);

		$html_pagination_links = '
			<div class="tablenav">
				<div class="tablenav-pages">
					<span class="pagination-links">' . join( "\n", $page_links ) . '</span>
				</div>
				<div class="clear"></div>
			</div>';

		echo '<div>' . $html_view_all . $html_pagination_links . '</div>'; // xss ok
	}

	/**
	 * Configurable options for the Stream Activity dashboard widget
	 */
	public static function stream_activity_options() {
		$options = get_option( 'dashboard_stream_activity_options', array() );

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['dashboard_stream_activity_options'] ) ) {
			$options['records_per_page'] = absint( $_POST['dashboard_stream_activity_options']['records_per_page'] );
			$options['live_update']      = isset( $_POST['dashboard_stream_activity_options']['live_update'] ) ? 'on' : 'off';;
			update_option( 'dashboard_stream_activity_options', $options );
		}

		if ( ! isset( $options['records_per_page'] ) ) {
			$options['records_per_page'] = 5;
		}

		?>
		<div id="dashboard-stream-activity-options">
			<p>
				<input type="number" step="1" min="1" max="999" class="screen-per-page" name="dashboard_stream_activity_options[records_per_page]" id="dashboard_stream_activity_options[records_per_page]" value="<?php echo absint( $options['records_per_page'] ) ?>">
				<label for="dashboard_stream_activity_options[records_per_page]"><?php esc_html_e( 'Records per page', 'stream' ) ?></label>
			</p>
			<?php $value = isset( $options['live_update'] ) ? $options['live_update'] : 'on'; ?>
			<p>
				<input type="checkbox" name="dashboard_stream_activity_options[live_update]" id="dashboard_stream_activity_options[live_update]" value='on' <?php checked( $value, 'on' ) ?> />
				<label for="dashboard_stream_activity_options[live_update]"><?php esc_html_e( 'Enable live updates', 'stream' ) ?></label>
			</p>
		</div>
	<?php
	}

	/**
	 * Renders rows for Stream Activity Dashboard Widget
	 *
	 * @param  obj     Record to be inserted
	 * @param  int     Row number
	 *
	 * @return string  Contents of new row
	 */
	public static function widget_row( $item ) {
		$author = new WP_Stream_Author( (int) $item->author, (array) $item->author_meta );

		$time_author = sprintf(
			_x(
				'%1$s ago by <a href="%2$s">%3$s</a>',
				'1: Time, 2: User profile URL, 3: User display name',
				'stream'
			),
			human_time_diff( strtotime( $item->created ) ),
			esc_url( $author->get_records_page_url() ),
			esc_html( $author->get_display_name() )
		);

		if ( $author->get_agent() ) {
			$time_author .= sprintf( ' %s', WP_Stream_Author::get_agent_label( $author->get_agent() ) );
		}

		ob_start()
		?>
		<li data-datetime="<?php echo wp_stream_get_iso_8601_extended_date( strtotime( $item->created ) ) ?>">
		<div class="record-avatar">
				<a href="<?php echo esc_url( $author->get_records_page_url() ) ?>">
					<?php echo $author->get_avatar_img( 72 ); // xss ok ?>
				</a>
			</div>
		<span class="record-meta"><?php echo $time_author; // xss ok ?></span>
		<br/>
		<?php echo esc_html( $item->summary ) ?>
		</li>
		<?php

		return ob_get_clean();
	}

	/**
	 * Handles Live Updates for Stream Activity Dashboard Widget.
	 *
	 * @uses gather_updated_items
	 *
	 * @param  array  Response to heartbeat
	 * @param  array  Response from heartbeat
	 *
	 * @return array  Data sent to heartbeat
	 */
	public static function live_update( $response, $data ) {
		if ( ! isset( $data['wp-stream-heartbeat-last-time'] ) ) {
			return;
		}

		$send = array();

		$last_time = $data['wp-stream-heartbeat-last-time'];

		$updated_items = WP_Stream_Live_Update::gather_updated_items( $last_time );

		if ( ! empty( $updated_items ) ) {
			ob_start();
			foreach ( $updated_items as $item ) {
				echo self::widget_row( $item ); //xss okay
			}

			$send = ob_get_clean();
		}

		return $send;
	}

}
