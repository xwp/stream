<?php

class WP_Stream_Notification_Rule_Matcher {

	const CACHE_KEY = 'stream-notification-rules';

	public function __construct() {
		// Refresh cache on update/create of a new rule
		add_action( 'saved_stream_notification_rule', array( $this, 'refresh' ) );

		// Match all new type=stream records
		add_action( 'wp_stream_post_inserted', array( $this, 'match' ), 10, 2 );

		# DEBUG
		$this->rules();
	}

	public function refresh() {
		$this->rules( true );
	}

	public function rules( $force_refresh = false ) {
		# DEBUG
		$force_refresh = true;
		// Check if we have a valid cache
		if ( ! $force_refresh && false !== ( $rules = get_transient( self::CACHE_KEY ) ) ) {
			return $rules;
		}

		// Get rules
		$args = array(
			'type' => 'notification_rule',
			'ignore_context' => true,
			'records_per_page' => -1,
			'fields' => 'ID',
			'visibility' => 1, // Active rules only
			);
		$rules = stream_query( $args );
		$rules = wp_list_pluck( $rules, 'ID' );

		$rules = $this->format( $rules );

		// Cache the new rules
		set_transient( self::CACHE_KEY, $rules );
		return $rules;
	}

	public function match( $record_id, $log ) {

		$matching_rules = array();

		foreach ( $this->rules() as $rule_id => $rule ) {

			$trigger_groups = $rule['trigger_groups'];

			$group_match = array();

			foreach ( $trigger_groups as $group_idx => $group ) {
				
				$trigger_match = array();

				foreach ( $group['triggers'] as $i => $trigger ) {

					$trigger_match[$i] = $this->match_trigger( $trigger, $log );

					if ( ! isset( $last_and_trigger ) ) {
						$last_and_trigger = null;
					}

					if ( $this->does_it_fail( $i, $last_and_trigger, $group['triggers'], $trigger_match ) ) {
						$group_match[ $group_idx ] = false;
						// Break off triggers loop
						break;
					}

					if ( $trigger['relation'] == 'and' ) {
						$last_and_trigger = $i;
					}
				}

				// If we have not failed it before, make it true
				if ( ! isset( $group_match[ $group_idx ] ) ) {
					$group_match[ $group_idx ] = true;
				}

				if ( ! isset( $last_and_group ) ) {
					$last_and_group = null;
				}

				if ( $this->does_it_fail( $group_idx, $last_and_group, $trigger_groups, $group_match ) ) {
					break; // Break off groups loop
				}
			}

			// By the time we get here, the rule actually matches
			if ( ( count( array_filter( $group_match ) ) > 0 ) ) {
				$matching_rules[] = $rule_id;
			}
		}

			// {echo '<pre>';var_dump($matching_rules);echo '</pre>';die();}

	}

	private function does_it_fail( $index, $last_and, $items, $matches ) {

		$is_last = ( $index + 1 == count( $items ) );
		$next_is_and = $is_last || ( $items[ $index + 1 ]['relation'] == 'and' );

		$relation_is_and = ( $index == 0 ) || ( $items[$index]['relation'] == 'and' );

		$is_match = $matches[ $index ];

		// If an effective node, last in items, or next item has relation=AND
		if ( $is_last || $next_is_and ) {
			if ( $relation_is_and ) {
				$passed = $is_match;
			} else {
				$subset = array_slice( $matches, $last_and );
				$passed = count( array_filter( $subset ) ) > 0;
			}

			if ( ! $passed ) {
				return true; // Does fail the chain
			}
		}

		return false; // Does not fail, continue the loop
	}

	public function match_trigger( $trigger, $log ) {
		# DEBUG
		return ( in_array( $trigger['type'], array( 'object_type' ) ) );
	}

	/**
	 * Format rules to be usable during the matching process
	 * @param  array  $rules Array of rule IDs
	 * @return array         Reformatted array of groups/triggers
	 */
	public function format( $rules ) {
		return array();
		/*
		$output = array();
		foreach ( $rules as $rule_id ) {
			$output[ $rule_id ] = array();
			$rule = new WP_Stream_Notification_Rule( $rule_id );
			$rule_output = array();
			$triggers = array();
			$groups = array();
			foreach ( $rule->triggers as $trigger ) {
				if ( $trigger['group'] == 0 ) {
					$triggers[] = $trigger;
				}
				if ( isset( $groups[ $trigger['group'] ] ) ) {
					$groups[ $trigger['group'] ][] = $trigger;
				} else {
					$group = $rule->groups[ $trigger['group'] ];
					$group_chain = array();
					while ( ! is_null( $rule->groups[$group['group']] ) != null ) {

					}
					// while ( $group )
				}
			}

			/*
			$groups = array();//$rule->groups;
			$groups[2] = array( 'group' => 0, 'relation' => 'or' );
			$groups[3] = array( 'group' => 2, 'relation' => 'or' );
			$groups[4] = array( 'group' => 3, 'relation' => 'or' );

			arsort( $groups );
			$groups[0] = array( 'group' => null, 'relation' => 'and', 'triggers' => array() );

			while ( $group = reset( $groups ) ) {
				if ( $group['group'] === null ) break;
				$group['triggers'] = array();
				$groups[ $group['group'] ]['triggers'][] = $group;
				unset ( $groups[ key( $groups ) ] );
			}

			$triggers = array_reverse( $rule->triggers );
			foreach ( $triggers as $trigger ) {
				array_unshift( $groups[ $trigger['group'] ]['triggers'], $trigger );
			}

			{echo '<pre>';var_dump($groups);echo '</pre>';die();}
			*/
		
			// foreach ( $groups as $group )
			/*
			$rule_output['trigger_groups'] = array();
			foreach ( array_values( $rule->triggers ) as $i => $trigger ) {
				$rule_output['trigger_groups'][ $trigger['group'] ]['triggers'][] = $trigger;
				$group_relation = ( $trigger['group'] == 0 ) ? 'and' : $groups[ $trigger['group'] ]['relation'];
				$group_parent   = ( $trigger['group'] == 0 ) ? null : $groups[ $trigger['group'] ]['group'];
				$rule_output['trigger_groups'][ $trigger['group'] ]['relation'] = $group_relation;
				if ( ! is_null( $group_parent ) ) {
					$rule_output['trigger_groups'][ $group_parent ]['subgroups'][$trigger['group']] = $i;
				}
			}
			{echo '<pre>';var_dump($rule_output, $rule_id);echo '</pre>';die();}
			$rule_output['alerts'] = $rule->alerts;
			$output[ $rule_id ] = $rule_output;
		}
		return $output;
		*/
	}



}