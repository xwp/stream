<div class="stream-section-wrapper">
	<div class="configure">
		<div class="inside">
			<select class="chart-options">
				<option value="custom">Custom</option>
				<optgroup label="All">>
					<option value="all">All Activity</option>
					<option value="role">All Activity by Role</option>
					<option value="author">All Activity by Author</option>
					<option value="connector">All Activity by Connector</option>
					<option value="context">All Activity by Context</option>
					<option value="action">All Activity by Action</option>
				</optgroup>
				<optgroup label="Connector: Posts">>
					<option value="all">All Posts Activity</option>
					<option value="role">Posts Activity by Role</option>
					<option value="author">Posts Activity by Author</option>
					<option value="context">Posts Activity by Context</option>
					<option value="action">Posts Activity by Action</option>
				</optgroup>
				<optgroup label="Context: Pages">>
					<option value="all">All Pages Activity</option>
					<option value="role">Pages Activity by Role</option>
					<option value="author">Pages Activity by Author</option>
					<option value="action">Pages Activity by Action</option>
				</optgroup>
				<optgroup label="Action: Failed Login">>
					<option value="all">All Failed Login Activity</option>
					<option value="role">Failed Login Activity by Role</option>
					<option value="role">Failed Login Activity by Author</option>
				</optgroup>
				<optgroup label="Role: Administrator">>
					<option value="all">All Administrator Activity</option>
					<option value="author">Administrator Activity by Author</option>
					<option value="connector">Administrator Activity by Connector</option>
					<option value="context">Administrator Activity by Context</option>
					<option value="action">Administrator Activity by Action</option>
				</optgroup>
				<optgroup label="Author: Frankie Jarrett">>
					<option value="all">All of Frankie Jarrett's Activity</option>
					<option value="connector">Frankie Jarrett's Activity by Connector</option>
					<option value="context">Frankie Jarrett's Activity by Context</option>
					<option value="action">Frankie Jarrett's Activity by Action</option>
				</optgroup>
			</select>
			<div class="chart-types">
				<?php foreach ( $chart_types as $type => $class ): ?>
				<div data-type="<?php echo esc_attr( $type ); ?>"
						 class="dashicons <?php echo esc_attr( $class ); ?>"></div>
				<?php endforeach; ?>
			</div>
			<input type="button"
						 name="submit"
						 class="button button-primary configure-submit disabled"
						 value="<?php esc_html_e( 'Save', 'stream-reports' ) ?>"
						 data-id="<?php echo esc_attr( $key ) ?>">
		</div>
	</div>

	<?php
	$args = array(
		'type' => 'line',
		'guidelines' => true,
		'tooltip' => array(
			'show' => true,
		),
		'values' => array(
			array(
				'key' => 'One',
				'values' => array(
					array(
						'x' => 0,
						'y' => 0,
					),
					array(
						'x' => 1,
						'y' => 1,
					),
					array(
						'x' => 2,
						'y' => 2,
					),
					array(
						'x' => 3,
						'y' => 3,
					),
				),
			),
			array(
				'key' => 'Three',
				'values' => array(
					array(
						'x' => 0,
						'y' => 0,
					),
					array(
						'x' => 1,
						'y' => 4,
					),
					array(
						'x' => 2,
						'y' => 1,
					),
					array(
						'x' => 3,
						'y' => 2,
					),
				),
			),
			array(
				'key' => 'Two',
				'values' => array(
					array(
						'x' => 0,
						'y' => 1,
					),
					array(
						'x' => 1,
						'y' => 2,
					),
					array(
						'x' => 2,
						'y' => 3,
					),
					array(
						'x' => 3,
						'y' => 4,
					),
				),
			),
		),
	);
	?>
	<div class="chart" data-report='<?php echo json_encode( $args ) ?>'><svg></svg></div>

</div>
