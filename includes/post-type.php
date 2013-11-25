<?php

class X_Stream_Post_Type {

	public static function load() {
		self::register();
		self::customize();
	}

	public static function register() {
		$singular = __( 'Drop', 'wp_stream' );
		$plural   = __( 'Streams', 'wp_stream' );

		$labels = array(
			'name'                => $plural,
			'singular_name'       => $singular,
			'add_new'             => sprintf( __( 'Add New %s', 'wp_stream' ), $singular ),
			'add_new_item'        => sprintf( __( 'Add New %s', 'wp_stream' ), $singular ),
			'edit_item'           => sprintf( __( 'Edit %s', 'wp_stream' ), $singular ),
			'new_item'            => sprintf( __( 'New %s', 'wp_stream' ), $singular ),
			'view_item'           => sprintf( __( 'View %s', 'wp_stream' ), $singular ),
			'search_items'        => sprintf( __( 'Search %s', 'wp_stream' ), $plural ),
			'not_found'           => sprintf( __( 'No %s found', 'wp_stream' ), $plural ),
			'not_found_in_trash'  => sprintf( __( 'No %s found in Trash', 'wp_stream' ), $plural ),
			'parent_item_colon'   => sprintf( __( 'Parent %s:', 'wp_stream' ), $singular ),
			'menu_name'           => $plural,
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => false,
			'description'         => 'description',
			'taxonomies'          => array(),
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'menu_position'       => null,
			'menu_icon'           => null,
			'show_in_nav_menus'   => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'has_archive'         => true,
			'query_var'           => true,
			'can_export'          => true,
			'rewrite'             => true,
			'capability_type'     => 'post',
			// 'map_meta_cap'        => false,
			'capabilities'        => array(
				'create_posts' => false,
				),
			'supports'            => array(
				'title', 'author',
				),
		);

		register_post_type(
			'stream',
			apply_filters( 'wp_stream_register_post_type_args', $args )
			);

		// Register 'Context' taxonomy

		$tax_singular = __( 'Context', 'wp_stream' );
		$tax_plural   = __( 'Contexts', 'wp_stream' );

		$tax_labels = array(
			'name'              => $tax_plural,
			'singular_name'     => $tax_singular,
			'search_items'      => sprintf( __( 'Search %s', 'wp_stream' ), $tax_plural ),
			'all_items'         => sprintf( __( 'All %s', 'wp_stream' ), $tax_plural ),
			'parent_item'       => sprintf( __( 'Parent %s', 'wp_stream' ), $tax_singular ),
			'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp_stream' ), $tax_singular ),
			'edit_item'         => sprintf( __( 'Edit %s', 'wp_stream' ), $tax_singular ),
			'update_item'       => sprintf( __( 'Update %s', 'wp_stream' ), $tax_singular ),
			'add_new_item'      => sprintf( __( 'Add New %s', 'wp_stream' ), $tax_singular ),
			'new_item_name'     => sprintf( __( 'New %s Name', 'wp_stream' ), $tax_singular ),
			'menu_name'         => $tax_plural,
		);

		$tax_args = array(
			'hierarchical'      => false,
			'labels'            => $tax_labels,
			'show_ui'           => false,
			'show_admin_column' => true,
			'query_var'         => true,
		);

		register_taxonomy(
			'stream_context',
			'stream',
			apply_filters( 'wp_stream_register_taxonomy_args', $tax_args )
			);

		// Register 'Action' taxonomy

		$tax_singular = __( 'Action', 'wp_stream' );
		$tax_plural   = __( 'Actions', 'wp_stream' );

		$tax_labels = array(
			'name'              => $tax_plural,
			'singular_name'     => $tax_singular,
			'search_items'      => sprintf( __( 'Search %s', 'wp_stream' ), $tax_plural ),
			'all_items'         => sprintf( __( 'All %s', 'wp_stream' ), $tax_plural ),
			'parent_item'       => sprintf( __( 'Parent %s', 'wp_stream' ), $tax_singular ),
			'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp_stream' ), $tax_singular ),
			'edit_item'         => sprintf( __( 'Edit %s', 'wp_stream' ), $tax_singular ),
			'update_item'       => sprintf( __( 'Update %s', 'wp_stream' ), $tax_singular ),
			'add_new_item'      => sprintf( __( 'Add New %s', 'wp_stream' ), $tax_singular ),
			'new_item_name'     => sprintf( __( 'New %s Name', 'wp_stream' ), $tax_singular ),
			'menu_name'         => $tax_plural,
		);

		$tax_args = array(
			'hierarchical'      => false,
			'labels'            => $tax_labels,
			'show_ui'           => false,
			'show_admin_column' => true,
			'query_var'         => true,
			);

		register_taxonomy(
			'stream_action',
			'stream',
			apply_filters( 'wp_stream_register_taxonomy_args', $tax_args )
			);
	}

	public static function customize() {
		// Remove custom views on list page
		add_filter( 'views_edit-stream', '__return_false' );

		// Add new filter dropdowns
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_table_filters' ) );

		// Manage list table columns
		add_filter( 'manage_stream_posts_columns', array( __CLASS__, 'list_table_columns' ), null, 2 );
		add_filter( 'manage_stream_posts_custom_column', array( __CLASS__, 'list_table_columns_data' ), null, 2 );

		add_filter( 'bulk_actions-edit-stream', '__return_empty_array' );

	}

	public static function list_table_filters() {
		if ( get_current_screen()->id != 'edit-stream' ) {
			return;
		}
		$dropdown_options = array(
			'hide_empty' => 0,
			'hierarchical' => 0,
			'show_count' => 0,
			'orderby' => 'name',
			'walker' => new X_Walker_CategoryDropdown,
		);

		wp_dropdown_users(
			array(
				'show_option_all' => __( 'View all users', 'wp_stream' ),
				'who' => 'all',
				'name' => 'author',
				'selected' => filter_input( INPUT_GET, 'author' ),
				'include_selected' => true,
			)
			);

		wp_dropdown_categories(
			array_merge(
				$dropdown_options,
				array(
					'taxonomy' => 'stream_context',
					'name' => 'stream_context',
					'selected' => filter_input( INPUT_GET, 'stream_context' ),
					'show_option_all' => __( 'View all contexts', 'wp_stream' ),
					)
				)
			);

		wp_dropdown_categories(
			array_merge(
				$dropdown_options,
				array(
					'taxonomy' => 'stream_action',
					'name' => 'stream_action',
					'selected' => filter_input( INPUT_GET, 'stream_action' ),
					'show_option_all' => __( 'View all actions', 'wp_stream' ),
					)
				)
			);

	}

	public static function list_table_columns( $post_columns ) {
		$post_columns = array(
			'full_date' => __( 'Date', 'wp_stream' ),
			'summary' => __( 'Summary', 'wp_stream' ),
			'user' => __( 'User', 'wp_stream' ),
			'taxonomy-stream_action' => __( 'Actions', 'wp_stream' ),
			'taxonomy-stream_context' => __( 'Contexts', 'wp_stream' ),
			'ip' => __( 'IP Address', 'wp_stream' ),
			'id' => __( 'ID' )
			);
		$post_columns = apply_filters( 'wp_stream_post_post_columns', $post_columns );
		return $post_columns;
	}

	public static function list_table_columns_data( $column_name, $post_id ) {
		global $wp_roles;
		$post = get_post();
		$out  = '';
		switch ( $column_name ) {
			case 'summary':
				$out .= sprintf(
					'<strong>%s</strong>',
					apply_filters( 'the_title', $post->post_title )
					);
				$out .= self::get_action_links( $post );
				break;
			case 'user':
				global $authordata;
				$out .= sprintf(
					'<a style="vertical-align:top" href="%s"><span style="float:left;padding-right:5px;">%s</span> %s <small>%s</small></a>',
					add_query_arg( array( 'post_type' => $post->post_type, 'author' => $authordata->ID ), 'edit.php' ),
					get_avatar( $authordata->ID, 48 ),
					$authordata->display_name,
					$wp_roles->role_names[$authordata->roles[0]]
				);
				break;
			case 'full_date':
				$out .= date( 'd/m/Y \<\b\r\/\> h:i:s a' , strtotime( $post->post_date ) );
				break;
			case 'ip':
				$out .= sprintf(
					'<code>%s</code>',
					esc_html( get_post_meta( $post_id, '_ip_address', true ) )
					);
				break;
			case 'id':
				$out .= "#$post->ID";
				break;
		}
		echo $out; // xss okay
	}

	public static function get_action_links( $post ){
		$out          = '';
		$contexts     = wp_get_post_terms( $post->ID, 'stream_context' );
		$object_id    = get_post_meta( $post->ID, '_object_id', true );
		$action_links = array();
		foreach ( $contexts as $context ) {
			$action_links = apply_filters( 'wp_stream_action_links_' . $context->name , array(), $post->ID, $object_id );
		}
		if ( $action_links ) {
			$out  .= '<div class="row-actions">';
			$links = array();
			foreach ( $action_links as $al_title => $al_href ) {
				$links[] = sprintf(
					'<span><a href="%s" class="action-link">%s</a></span>',
					$al_href,
					$al_title
					);
			}
			$out .= implode( ', ', $links );
			$out .= '</div>';
		}
		return $out;
	}

}

/**
 * Create HTML dropdown list of Categories, using term slugs for option value
 *
 * @package WordPress
 * @since 2.1.0
 * @uses Walker
 */
class X_Walker_CategoryDropdown extends Walker {
	/**
	 * @see Walker::$tree_type
	 * @since 2.1.0
	 * @var string
	 */
	var $tree_type = 'category';

	/**
	 * @see Walker::$db_fields
	 * @since 2.1.0
	 * @todo Decouple this
	 * @var array
	 */
	var $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

	/**
	 * Start the element output.
	 *
	 * @see Walker::start_el()
	 * @since 2.1.0
	 *
	 * @param string $output   Passed by reference. Used to append additional content.
	 * @param object $category Category data object.
	 * @param int    $depth    Depth of category. Used for padding.
	 * @param array  $args     Uses 'selected' and 'show_count' keys, if they exist. @see wp_dropdown_categories()
	 */
	function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		$pad = str_repeat( '&nbsp;', $depth * 3 );

		$cat_name = apply_filters( 'list_cats', $category->name, $category );
		$output  .= "\t<option class=\"level-$depth\" value=\"" . $category->slug . '"';
		if ( $category->slug == $args['selected'] ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad.$cat_name;
		if ( $args['show_count'] ) {
			$output .= '&nbsp;&nbsp;('. $category->count .')';
		}
		$output .= "</option>\n";
	}
}