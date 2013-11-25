<?php

class X_Stream_Contexts {

	/**
	 * Contexts registered
	 * @var array
	 */
	public static $contexts = array();

	/**
	 * Context Taxonomy
	 * @var string
	 */
	public static $context_taxonomy = 'stream_context';

	/**
	 * Actions taxonomy
	 * @var string
	 */
	public static $action_taxonomy = 'stream_action';

	/**
	 * Action taxonomy terms
	 * Holds slug to -localized- label association
	 * @var array
	 */
	public static $term_labels = array(
		'stream_action'  => array(),
		'stream_context' => array(),
		);

	/**
	 * Load built-in contexts
	 */
	public static function load() {
		require_once X_STREAM_CLASS_DIR . 'context.php';

		$classes = array();
		if ( $found = glob( X_STREAM_DIR . 'contexts/*.php' ) ) {
			foreach ( $found as $class ) {
				include_once $class;
				$class     = ucwords( preg_match( '#(.+)\.php#', basename( $class ), $matches ) ? $matches[1] : '' );
				$classes[] = "X_Stream_Context_$class";
			}
		}
		self::$contexts = apply_filters( 'wp_stream_contexts', $classes );

		foreach ( self::$contexts as $context ) {
			$context::register();

			// Add new terms to our label lookup array
			self::$term_labels['stream_action'] = array_merge(
				self::$term_labels['stream_action'],
				$context::get_action_term_labels()
				);
			self::$term_labels['stream_context'][$context::$name] = $context::get_label();
		}

		// Filter taxonomy names to use translated labels
		add_filter( 'get_terms', array( __CLASS__, 'term_label_translation' ) );
		add_filter( 'get_the_terms', array( __CLASS__, 'term_label_translation' ) );
	}

	/**
	 * Update term name to use registered labels
	 *
	 * @action get_terms
	 * @param  array  $terms
	 * @param  array  $taxonomies
	 * @param  array  $args
	 * @return array                Updated term list
	 */
	public static function term_label_translation( $terms ) {
		if ( empty( $terms ) ) {
			return $terms;
		}

		if ( ! in_array( reset( $terms )->taxonomy, array( 'stream_action', 'stream_context' ) ) ) {
			return $terms;
		}

		foreach ( $terms as $idx => $term ) {
			if ( ! empty( self::$term_labels[ $term->taxonomy ][ $term->slug ] ) ) {
				$terms[$idx]->name = self::$term_labels[ $term->taxonomy ][ $term->slug ];
			}
		}

		return $terms;
	}

}