<?php

class WP_Stream_Notifications_Matcher {

	const CACHE_KEY = 'stream-notification-rules';

	/**
	 * @todo fix deprecated actions/filters
	 */
	public function __construct() {
		// Refresh rules cache on updating/deleting posts
		add_action( 'save_post', array( $this, 'refresh_cache_on_save' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'refresh_cache_on_delete' ), 10, 1 );

		// Match all new type=stream records
		add_action( 'wp_stream_records_inserted', array( $this, 'match' ), 10, 2 );
	}

	/**
	 * Refresh cache on saving a rule
	 *
	 * @action save_post
	 *
	 * @param      $post_id
	 * @param null $post
	 *
	 * @return void
	 */
	public function refresh_cache_on_save( $post_id, $post = null ) {
		if ( ! isset( $post ) ) {
			$post = get_post( $post_id );
		}

		if ( WP_Stream_Notifications_Post_Type::POSTTYPE === $post->post_type ) {
			$this->rules( true );
		}
	}

	/**
	 * Refresh cache on deleting a rule
	 *
	 * @action delete_post
	 *
	 * @param $post_id
	 *
	 * @return void
	 */
	public function refresh_cache_on_delete( $post_id ) {
		$post = get_post( $post_id );

		if ( WP_Stream_Notifications_Post_Type::POSTTYPE !== $post->post_type ) {
			return;
		}

		add_action(
			'deleted_post', function ( $deleted_id ) use ( $post_id ) {
				if ( $deleted_id === $post_id ) {
					$this->rules( true );
				}
			}
		);
	}

	/**
	 * Generate a proper format of triggers/alerts to be used/cached
	 *
	 * @param bool $force_refresh Ignore cached version
	 *
	 * @return array|mixed|void
	 */
	public function rules( $force_refresh = false ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$force_refresh = true;
		}

		// Check if we have a valid cache
		if ( ! $force_refresh && false !== ( $rules = get_transient( self::CACHE_KEY ) ) ) {
			return $rules;
		}

		// Get rules
		$args  = array(
			'post_type'      => WP_Stream_Notifications_Post_Type::POSTTYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);
		$query = new WP_Query( $args );
		$rules = $query->get_posts();

		/**
		 * Allow developers to add/modify rules
		 *
		 * @param array $rules Rules for the current blog
		 * @param array $args  Query args used
		 *
		 * @return array
		 */
		$rules = apply_filters( 'wp_stream_notifications_rules', $rules, $args );

		$rules = $this->format( $rules );

		// Cache the new rules
		set_transient( self::CACHE_KEY, $rules );

		return $rules;
	}

	public function match( $records ) {
		$rules      = $this->rules();
		$rule_match = array();

		foreach ( $records as $record ) {
			foreach ( $rules as $rule_id => $rule ) {
				$rule_match[ $rule_id ] = $this->match_group( $rule['triggers'], $record );
			}
		}

		$rule_match     = array_keys( array_filter( $rule_match ) );
		$matching_rules = array_intersect_key( $rules, array_flip( $rule_match ) );

		$this->alert( $matching_rules, $records );
	}

	/**
	 * Match a group of chunked triggers against a log operation
	 *
	 * @param  array $chunks Chunks of triggers, usually from group[triggers]
	 * @param  array $log    Log operation array
	 *
	 * @return bool           Matching result
	 */
	private function match_group( $chunks, $record ) {
		// Separate triggers by 'AND'/'OR' relation, to be able to fail early
		// and not have to traverse the whole trigger tree
		foreach ( $chunks as $chunk ) {
			$results = array();

			foreach ( $chunk as $trigger ) {
				$is_group = isset( $trigger['triggers'] );

				if ( $is_group ) {
					$results[] = $this->match_group( $trigger['triggers'], $record );
				} else {
					$results[] = $this->match_trigger( $trigger, $record );
				}
			}

			// If the whole chunk fails, fail the whole group
			if ( 0 === count( array_filter( $results ) ) ) {
				return false;
			}
		}

		// If nothing fails, group matches
		return true;
	}

	public function match_trigger( $trigger, $record ) {
		$type     = isset( $trigger['type'] )     ? $trigger['type']     : null;
		$needle   = isset( $trigger['value'] )    ? $trigger['value']    : null;
		$operator = isset( $trigger['operator'] ) ? $trigger['operator'] : null;
		$negative = ( isset( $operator[0] ) && '!' === $operator[0] );
		$haystack = null;

		// Post-specific triggers dirty work
		if ( false !== strpos( $trigger['type'], 'post_' ) ) {
			$post = get_post( $record['object_id'] );

			if ( empty( $post ) ) {
				return false;
			}
		}

		switch ( $type ) {
			case 'search':
				$haystack = strtolower( $record['summary'] );
				$needle   = strtolower( $needle );
				break;
			case 'object_id':
				$haystack = $record['object_id'];
				break;
			case 'author':
				$haystack = $record['author'];
				break;
			case 'author_role':
				$user     = get_userdata( $record['author'] );
				$haystack = ( is_object( $user ) && $user->exists() && $user->roles ) ? $user->roles[0] : false;
				break;
			case 'ip':
				$haystack = $record['ip'];
				break;
			case 'date':
				$haystack = get_date_from_gmt( date( 'Y-m-d H:i:s', strtotime( $record['created'] ) ), 'Ymd' );
				$needle   = get_date_from_gmt( date( 'Y-m-d H:i:s', strtotime( $needle ) ), 'Ymd' );
				break;
			case 'weekday':
				if ( isset( $needle[0] ) && preg_match( '#\d+#', $needle[0], $weekday_match ) ) {
					$haystack = get_date_from_gmt( date( 'Y-m-d H:i:s', strtotime( $record['created'] ) ), 'w' );
					$needle   = $weekday_match[0];
				}
				break;
			case 'connector':
				$haystack = $record['connector'];
				break;
			case 'context':
				$haystack = $record['context'];
				break;
			case 'action':
				$haystack = $record['action'];
				break;

			/* Context-aware triggers */
			case 'post':
			case 'user':
			case 'term':
				$haystack = $record['object_id'];
				break;
			case 'term_parent':
				$parent = get_term( $record['meta']['term_parent'], $record['meta']['taxonomy'] );
				if ( empty( $parent ) || is_wp_error( $parent ) ) {
					return false;
				} else {
					$haystack = $parent->term_taxonomy_id;
				}
				break;
			case 'tax':
				if ( empty( $record['meta']['taxonomy'] ) ) {
					return false;
				}
				$haystack = $record['meta']['taxonomy'];
				break;

			case 'post_title':
				$haystack = $post->post_title;
				break;
			case 'post_slug':
				$haystack = $post->post_name;
				break;
			case 'post_content':
				$haystack = $post->post_content;
				break;
			case 'post_excerpt':
				$haystack = $post->post_excerpt;
				break;
			case 'post_status':
				$haystack = get_post_status( $post->ID );
				break;
			case 'post_format':
				$haystack = get_post_format( $post );
				break;
			case 'post_parent':
				$haystack = wp_get_post_parent_id( $post->ID );
				break;
			case 'post_thumbnail':
				if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
					return false;
				}
				$haystack = get_post_thumbnail_id( $post->ID ) > 0;
				break;
			case 'post_comment_status':
				$haystack = $post->comment_status;
				break;
			case 'post_comment_count':
				$haystack = get_comment_count( $post->ID );
				break;
			default:
				return false;
				break;
		}

		$match = false;

		switch ( $operator ) {
			case '=':
			case '!=':
			case '>=':
			case '<=':
				$needle = is_array( $needle ) ? $needle : explode( ',', $needle );
				$match  = (bool) array_intersect( $needle, (array) $haystack );
				break;
			// string special comparison operators
			case 'contains':
			case '!contains':
				$match = ( false !== strpos( $haystack, $needle ) );
				break;
			case 'starts':
				$match = ( 0 === strpos( $haystack, $needle ) );
				break;
			case 'ends':
				$match = ( strlen( $haystack ) - strlen( $needle ) === strrpos( $haystack, $needle ) );
				break;
			case 'regex':
				$match = preg_match( $needle, $haystack ) > 0;
				break;
			// date operators
			case '<':
			case '<=':
				$match = $match || ( $haystack < $needle );
				break;
			case '>':
			case '>=':
				$match = $match || ( $haystack > $needle );
				break;
		}

		$result = ( $match == ! $negative ); // Loose comparison needed

		return $result;
	}

	/**
	 * Format rules to be usable during the matching process
	 *
	 * @param  array $rules Array of rule IDs
	 *
	 * @return array         Reformatted array of groups/triggers
	 */
	private function format( $rules ) {
		$output = array();

		foreach ( $rules as $rule ) {
			$rule_id = $rule->ID;
			$meta    = get_post_meta( $rule_id );
			$args    = array();

			foreach ( array( 'triggers', 'groups', 'alerts' ) as $key ) {
				if ( isset( $meta[ $key ] ) ) {
					$args[ $key ] = array_filter( maybe_unserialize( $meta[ $key ][0] ) );
				}
			}

			// Bail early if no triggers or alerts are defined
			if ( empty( $args['triggers'] ) || empty( $args['alerts'] ) ) {
				continue;
			}

			$output[ $rule_id ] = array();

			// Generate an easy-to-parse tree of triggers/groups
			$args['triggers'] = $this->generate_tree(
				$this->generate_flattened_tree(
					$args['triggers'],
					$args['groups']
				)
			);

			// Chunkify! @see generate_group_chunks
			$args['triggers'] = $this->generate_group_chunks(
				$args['triggers'][0]['triggers']
			);

			// Add alerts
			$output[ $rule_id ] = $args;
		}

		return $output;
	}

	/**
	 * Return all of group's ancestors starting with the root
	 */
	private function generate_group_chain( $groups, $group_id ) {
		$chain = array();

		while ( isset( $groups[ $group_id ] ) ) {
			$chain[] = $group_id;
			$group_id = $groups[ $group_id ]['group'];
		}

		return array_reverse( $chain );
	}

	/**
	 * Takes the groups and triggers and creates a flattened tree,
	 * which is an pre-order walkthrough of the tree we want to construct
	 * http://en.wikipedia.org/wiki/Tree_traversal#Pre-order
	 */
	private function generate_flattened_tree( $triggers, $groups ) {
		// Seed the tree with the universal group
		if ( ! isset( $groups[0] ) ) {
			$groups[0] = array( 'group' => null, 'relation' => 'and' );
		}

		$flattened_tree      = array( array( 'item' => $groups['0'], 'level' => 0, 'type' => 'group' ) );
		$current_group_chain = array( '0' );
		$level               = 1;

		foreach ( $triggers as $key => $trigger ) {
			$active_group = end( $current_group_chain );

			// If the trigger goes to any other than actually opened group, we need to traverse the tree first
			if ( $trigger['group'] != $active_group ) {
				$trigger_group_chain   = $this->generate_group_chain( $groups, $trigger['group'] );
				$common_ancestors      = array_intersect( $current_group_chain, $trigger_group_chain );
				$newly_inserted_groups = array_diff( $trigger_group_chain, $current_group_chain );
				$steps_back            = $level - count( $common_ancestors );

				// First take the steps back until we reach a common ancestor
				for ( $i = 0; $i < $steps_back; $i ++ ) {
					array_pop( $current_group_chain );
					$level --;
				}

				// Then go forward and generate group nodes until the trigger is ready to be inserted
				foreach ( $newly_inserted_groups as $group ) {
					$flattened_tree[]      = array( 'item' => $groups[ $group ], 'level' => $level ++, 'type' => 'group' );
					$current_group_chain[] = $group;
				}
			}

			// Now we're sure the trigger goes to a correct position
			$flattened_tree[] = array( 'item' => $trigger, 'level' => $level, 'type' => 'trigger' );
		}

		return $flattened_tree;
	}

	/**
	 * Takes the flattened tree and generates a proper tree
	 */
	private function generate_tree( $flattened_tree ) {
		// Our recurrent step
		$recurrent_step = function ( $level, $i ) use ( $flattened_tree, &$recurrent_step ) {
			$return = array();

			for ( $i; $i < count( $flattened_tree ); $i ++ ) {
				// If we're on the correct level, we're going to insert the node
				if ( $flattened_tree[ $i ]['level'] === $level ) {
					if ( 'trigger' === $flattened_tree[ $i ]['type'] ) {
						$return[] = $flattened_tree[ $i ]['item'];
						// If the node is a group, we need to call the recursive function
						// in order to construct the tree for us further
					} else {
						$return[] = array(
							'relation' => $flattened_tree[ $i ]['item']['relation'],
							'triggers' => call_user_func( $recurrent_step, $level + 1, $i + 1 ),
						);
					}
					// If we're on a lower level, we came back and we can return this branch
				} elseif ( $flattened_tree[ $i ]['level'] < $level ) {
					return $return;
				}
			}

			return $return;
		};

		return call_user_func( $recurrent_step, 0, 0 );
	}

	/**
	 * Split trigger trees by relation, so we can fail trigger trees early if
	 * an effective trigger is not matched
	 *
	 * A chunk would be a bulk of triggers that only matches if ANY of its
	 * nested triggers are matched
	 *
	 * @param $triggers
	 *
	 * @internal array $group Group array, ex: array(
	 *                      'relation' => 'and',
	 *                      'trigger'  => array( arr trigger1, arr trigger2 )
	 *                      );
	 *
	 * @return array         Chunks of triggers, split based on their relation
	 */
	private function generate_group_chunks( $triggers ) {
		$chunks        = array();
		$current_chunk = -1;

		foreach ( $triggers as $trigger ) {
			// If is a group, chunks its children as well
			if ( isset( $trigger['triggers'] ) ) {
				$trigger['triggers'] = $this->generate_group_chunks( $trigger['triggers'] );
			}

			// If relation=and, start a new chunk, else join the previous chunk
			if ( 'and' === $trigger['relation'] ) {
				$chunks[]     = array( $trigger );
				$current_chunk = count( $chunks ) - 1;
			} else {
				$chunks[ $current_chunk ][] = $trigger;
			}
		}

		return $chunks;
	}

	private function alert( $rules, $records ) {
		foreach ( $rules as $rule_id => $rule ) {
			// Update occurrences
			update_post_meta(
				$rule_id,
				'occurrences',
				( (int) get_post_meta( $rule_id, 'occurrences', true ) ) + 1
			);

			foreach ( $records as $record ) {
				foreach ( $rule['alerts'] as $alert ) {
					if ( ! isset( WP_Stream_Notifications::$adapters[ $alert['type'] ] ) ) {
						continue;
					}

					$adapter = new WP_Stream_Notifications::$adapters[ $alert['type'] ]['class'];
					$adapter->load( $alert )->send( $record );
				}
			}
		}
	}

}