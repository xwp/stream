<?php

class WP_Stream_Connector_Taxonomies extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'taxonomies';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'created_term',
		'delete_term',
		'edit_term',
		'edited_term',
	);

	/**
	 * Cache term values before update, used by callback_edit_term/callback_edited_term
	 *
	 * @var Object
	 */
	public static $cached_term_before_update;

	/**
	 * Cache taxonomy labels
	 *
	 * @var array
	 */
	public static $context_labels;

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'Taxonomies', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created' => __( 'Created', 'stream' ),
			'updated' => __( 'Updated', 'stream' ),
			'deleted' => __( 'Deleted', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		global $wp_taxonomies;

		$labels = wp_list_pluck( $wp_taxonomies, 'labels' );

		self::$context_labels  = wp_list_pluck( $labels, 'singular_name' );

		add_action( 'registered_taxonomy', array( __CLASS__, '_registered_taxonomy' ), 10, 3 );

		return self::$context_labels;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links     Previous links registered
	 * @param  object $record    Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( $record->object_id && 'deleted' !== $record->action && ( $term = get_term_by( 'term_taxonomy_id', $record->object_id, $record->context ) ) ) {
			if ( ! is_wp_error( $term ) ) {
				$tax_obj   = get_taxonomy( $term->taxonomy );
				$tax_label = isset( $tax_obj->labels->singular_name ) ? $tax_obj->labels->singular_name : null;

				$links[ sprintf( _x( 'Edit %s', 'Term singular name', 'stream' ), $tax_label ) ] = get_edit_term_link( $term->term_id, $term->taxonomy );
				$links[ __( 'View', 'stream' ) ] = get_term_link( $term->term_id, $term->taxonomy );
			}
		}

		return $links;
	}

	/**
	 * Catch registration of taxonomies after inital loading, so we can cache its labels
	 *
	 * @action registered_taxonomy
	 *
	 * @param string       $taxonomy    Taxonomy slug
	 * @param array|string $object_type Object type or array of object types
	 * @param array|string $args        Array or string of taxonomy registration arguments
	 */
	public static function _registered_taxonomy( $taxonomy, $object_type, $args ) {
		$taxonomy_obj = (object) $args;
		$label        = get_taxonomy_labels( $taxonomy_obj )->name;

		self::$context_labels[ $taxonomy ] = $label;

		WP_Stream_Connectors::$term_labels['stream_context'][ $taxonomy ] = $label;
	}

	/**
	 * Tracks creation of terms
	 *
	 * @action created_term
	 */
	public static function callback_created_term( $term_id, $tt_id, $taxonomy ) {
		if ( in_array( $taxonomy, self::get_excluded_taxonomies() ) ) {
			return;
		}

		$term           = get_term( $term_id, $taxonomy );
		$term_name      = $term->name;
		$taxonomy_label = strtolower( self::$context_labels[ $taxonomy ] );
		$term_parent    = $term->parent;

		self::log(
			_x(
				'"%1$s" %2$s created',
				'1: Term name, 2: Taxonomy singular label',
				'stream'
			),
			compact( 'term_name', 'taxonomy_label', 'term_id', 'taxonomy', 'term_parent' ),
			$tt_id,
			$taxonomy,
			'created'
		);
	}

	/**
	 * Tracks deletion of taxonomy terms
	 *
	 * @action delete_term
	 */
	public static function callback_delete_term( $term_id, $tt_id, $taxonomy, $deleted_term ) {
		if ( in_array( $taxonomy, self::get_excluded_taxonomies() ) ) {
			return;
		}

		$term_name      = $deleted_term->name;
		$term_parent    = $deleted_term->parent;
		$taxonomy_label = strtolower( self::$context_labels[ $taxonomy ] );

		self::log(
			_x(
				'"%1$s" %2$s deleted',
				'1: Term name, 2: Taxonomy singular label',
				'stream'
			),
			compact( 'term_name', 'taxonomy_label', 'term_id', 'taxonomy', 'term_parent' ),
			$tt_id,
			$taxonomy,
			'deleted'
		);
	}

	/**
	 * Tracks updates of taxonomy terms
	 *
	 * @action edit_term
	 */
	public static function callback_edit_term( $term_id, $tt_id, $taxonomy ) {
		self::$cached_term_before_update = get_term( $term_id, $taxonomy );
	}

	public static function callback_edited_term( $term_id, $tt_id, $taxonomy ) {
		if ( in_array( $taxonomy, self::get_excluded_taxonomies() ) ) {
			return;
		}

		$term = self::$cached_term_before_update;

		if ( ! $term ) { // For some reason!
			$term = get_term( $term_id, $taxonomy );
		}

		$term_name      = $term->name;
		$taxonomy_label = strtolower( self::$context_labels[ $taxonomy ] );
		$term_parent    = $term->parent;

		self::log(
			_x(
				'"%1$s" %2$s updated',
				'1: Term name, 2: Taxonomy singular label',
				'stream'
			),
			compact( 'term_name', 'taxonomy_label', 'term_id', 'taxonomy', 'term_parent' ),
			$tt_id,
			$taxonomy,
			'updated'
		);
	}

	/**
	 * Constructs list of excluded taxonomies for the Taxonomies connector
	 *
	 * @return array  List of excluded taxonomies
	 */
	public static function get_excluded_taxonomies() {
		return apply_filters(
			'wp_stream_taxonomies_exclude_taxonomies',
			array(
				'nav_menu',
			)
		);
	}

}
