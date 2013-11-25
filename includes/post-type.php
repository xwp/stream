<?php

class X_Stream_Post_Type {

	public static function load() {
		self::register();
		self::customize();
	}

	public static function register() {
		$name     = __( 'Stream', 'stream' );
		$singular = __( 'Record', 'stream' );
		$plural   = __( 'Records', 'stream' );

		$labels = array(
			'name'                => $name,
			'singular_name'       => $singular,
			'add_new'             => sprintf( __( 'Add New %s', 'stream' ), $singular ),
			'add_new_item'        => sprintf( __( 'Add New %s', 'stream' ), $singular ),
			'edit_item'           => sprintf( __( 'Edit %s', 'stream' ), $singular ),
			'new_item'            => sprintf( __( 'New %s', 'stream' ), $singular ),
			'view_item'           => sprintf( __( 'View %s', 'stream' ), $singular ),
			'search_items'        => sprintf( __( 'Search %s', 'stream' ), $plural ),
			'not_found'           => sprintf( __( 'No %s found', 'stream' ), $plural ),
			'not_found_in_trash'  => sprintf( __( 'No %s found in Trash', 'stream' ), $plural ),
			'parent_item_colon'   => sprintf( __( 'Parent %s:', 'stream' ), $singular ),
			'menu_name'           => $name,
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

		$tax_singular = __( 'Context', 'stream' );
		$tax_plural   = __( 'Contexts', 'stream' );

		$tax_labels = array(
			'name'              => $tax_plural,
			'singular_name'     => $tax_singular,
			'search_items'      => sprintf( __( 'Search %s', 'stream' ), $tax_plural ),
			'all_items'         => sprintf( __( 'All %s', 'stream' ), $tax_plural ),
			'parent_item'       => sprintf( __( 'Parent %s', 'stream' ), $tax_singular ),
			'parent_item_colon' => sprintf( __( 'Parent %s:', 'stream' ), $tax_singular ),
			'edit_item'         => sprintf( __( 'Edit %s', 'stream' ), $tax_singular ),
			'update_item'       => sprintf( __( 'Update %s', 'stream' ), $tax_singular ),
			'add_new_item'      => sprintf( __( 'Add New %s', 'stream' ), $tax_singular ),
			'new_item_name'     => sprintf( __( 'New %s Name', 'stream' ), $tax_singular ),
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

		$tax_singular = __( 'Action', 'stream' );
		$tax_plural   = __( 'Actions', 'stream' );

		$tax_labels = array(
			'name'              => $tax_plural,
			'singular_name'     => $tax_singular,
			'search_items'      => sprintf( __( 'Search %s', 'stream' ), $tax_plural ),
			'all_items'         => sprintf( __( 'All %s', 'stream' ), $tax_plural ),
			'parent_item'       => sprintf( __( 'Parent %s', 'stream' ), $tax_singular ),
			'parent_item_colon' => sprintf( __( 'Parent %s:', 'stream' ), $tax_singular ),
			'edit_item'         => sprintf( __( 'Edit %s', 'stream' ), $tax_singular ),
			'update_item'       => sprintf( __( 'Update %s', 'stream' ), $tax_singular ),
			'add_new_item'      => sprintf( __( 'Add New %s', 'stream' ), $tax_singular ),
			'new_item_name'     => sprintf( __( 'New %s Name', 'stream' ), $tax_singular ),
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
		add_filter( 'manage_edit-stream_sortable_columns', array( __CLASS__, 'list_table_sortable_columns' ), null, 2 );

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
				'show_option_all' => __( 'Show all users', 'stream' ),
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
					'taxonomy' => 'stream_action',
					'name' => 'stream_action',
					'selected' => filter_input( INPUT_GET, 'stream_action' ),
					'show_option_all' => __( 'Show all actions', 'stream' ),
				)
			)
		);

		wp_dropdown_categories(
			array_merge(
				$dropdown_options,
				array(
					'taxonomy' => 'stream_context',
					'name' => 'stream_context',
					'selected' => filter_input( INPUT_GET, 'stream_context' ),
					'show_option_all' => __( 'Show all contexts', 'stream' ),
				)
			)
		);

	}

	public static function list_table_columns( $post_columns ) {
		$post_columns = array(
			'full_date' => __( 'Date', 'stream' ),
			'summary' => __( 'Summary', 'stream' ),
			'user' => __( 'User', 'stream' ),
			'taxonomy-stream_action' => __( 'Actions', 'stream' ),
			'taxonomy-stream_context' => __( 'Contexts', 'stream' ),
			'ip' => __( 'IP Address', 'stream' ),
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
				$author_ID   = isset( $authordata->ID ) ? $authordata->ID : 0;
				$author_name = isset( $authordata->display_name ) ? $authordata->display_name : null;
				$author_role = isset( $authordata->roles[0] ) ? $wp_roles->role_names[$authordata->roles[0]] : null;
				$out .= sprintf(
					'<a style="vertical-align:top" href="%s"><span style="float:left;padding-right:5px;">%s</span> %s</a><br /><small>%s</small>',
					add_query_arg( array( 'post_type' => $post->post_type, 'author' => $author_ID ), 'edit.php' ),
					get_avatar( $author_ID, 36 ),
					$author_name,
					$author_role
				);
				break;
			case 'full_date':
				$out .= date( 'Y/m/d \<\b\r\/\> h:i:s A' , strtotime( $post->post_date ) );
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

	public static function list_table_sortable_columns( $columns ) {
		$columns['full_date'] = 'full_date';
		return $columns;
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