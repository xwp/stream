<?php
/**
 * Manages the state of a single record
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Record
 */
class Record {
	/**
	 * Record ID
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * Date record created
	 *
	 * @var string
	 */
	public $created;

	/**
	 * Site ID of the site where the record was created
	 *
	 * @var int
	 */
	public $site_id;

	/**
	 * Blog ID of the site where the record was created
	 *
	 * @var int
	 */
	public $blog_id;

	/**
	 * Record Object ID
	 *
	 * @var int
	 */
	public $object_id;

	/**
	 * User ID of the record creator
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * User role of the record creator
	 *
	 * @var string
	 */
	public $user_role;

	/**
	 * Record user meta data.
	 *
	 * @var string
	 */
	public $user_meta;

	/**
	 * Record summary
	 *
	 * @var string
	 */
	public $summary;

	/**
	 * Record connector
	 *
	 * @var string
	 */
	public $connector;

	/**
	 * Context record was made in.
	 *
	 * @var string
	 */
	public $context;

	/**
	 * Record action
	 *
	 * @var string
	 */
	public $action;

	/**
	 * IP of event requestee
	 *
	 * @var string
	 */
	public $ip;

	/**
	 * Record meta data
	 *
	 * @var array
	 */
	public $meta;

	/**
	 * Class constructor
	 *
	 * @param object $item  Record data object.
	 */
	public function __construct( $item ) {
		$this->ID        = isset( $item->ID ) ? $item->ID : null;
		$this->created   = isset( $item->created ) ? $item->created : null;
		$this->site_id   = isset( $item->site_id ) ? $item->site_id : null;
		$this->blog_id   = isset( $item->blog_id ) ? $item->blog_id : null;
		$this->object_id = isset( $item->object_id ) ? $item->object_id : null;
		$this->user_id   = isset( $item->user_id ) ? $item->user_id : null;
		$this->user_role = isset( $item->user_role ) ? $item->user_role : null;
		$this->user_meta = isset( $item->meta['user_meta'] ) ? $item->meta['user_meta'] : null;
		$this->summary   = isset( $item->summary ) ? $item->summary : null;
		$this->connector = isset( $item->connector ) ? $item->connector : null;
		$this->context   = isset( $item->context ) ? $item->context : null;
		$this->action    = isset( $item->action ) ? $item->action : null;
		$this->ip        = isset( $item->ip ) ? $item->ip : null;
		$this->meta      = isset( $item->meta ) ? $item->meta : null;

		if ( isset( $this->meta['user_meta'] ) ) {
			unset( $this->meta['user_meta'] );
		}
	}

	/**
	 * Fetch a single record row by ID, including its metadata.
	 *
	 * Stream's Query class doesn't expose a single-ID filter (record__in is
	 * broken for one-element arrays due to array_shift() in Query::query()),
	 * so query the table directly. Pass $blog_id to scope the lookup to a
	 * specific blog on multisite — callers running in REST/non-network-admin
	 * contexts should pass get_current_blog_id() to prevent cross-site reads.
	 *
	 * @param int      $id      Record ID.
	 * @param int|null $blog_id Optional blog ID to constrain the lookup to.
	 *
	 * @return array|null Associative row with merged 'meta' key, or null when
	 *                   no matching record exists.
	 */
	public static function get_by_id( $id, $blog_id = null ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}

		$where    = '';
		$prepared = array( $id );
		if ( null !== $blog_id ) {
			$where      = ' AND blog_id = %d';
			$prepared[] = (int) $blog_id;
		}

		// $wpdb->stream and {$where} are constructed from string literals (no
		// user input), and $prepared holds only integer IDs.
		$sql = "SELECT * FROM {$wpdb->stream} WHERE ID = %d{$where}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $prepared ), ARRAY_A );

		if ( empty( $row ) ) {
			return null;
		}

		$row['meta'] = self::normalize_meta( get_metadata( 'record', $row['ID'] ) );

		return $row;
	}

	/**
	 * Normalize Stream's flat stored metadata into the public record shape.
	 *
	 * @param array $meta Raw metadata from the database or get_metadata().
	 * @return array
	 */
	public static function normalize_meta( $meta ) {
		$normalized = array();

		foreach ( (array) $meta as $key => $value ) {
			$value = self::normalize_meta_value( $value );

			if ( is_string( $key ) && false !== strpos( $key, '[' ) && ']' === substr( $key, -1 ) ) {
				$main_key = substr( $key, 0, strpos( $key, '[' ) );
				$sub_key  = substr( $key, strpos( $key, '[' ) + 1, -1 );

				if ( '' !== $main_key && '' !== $sub_key ) {
					if ( ! isset( $normalized[ $main_key ] ) || ! is_array( $normalized[ $main_key ] ) ) {
						$normalized[ $main_key ] = array();
					}

					$normalized[ $main_key ][ $sub_key ] = $value;
					continue;
				}
			}

			$normalized[ $key ] = $value;
		}

		return $normalized;
	}

	/**
	 * Normalize a metadata value from direct SQL or get_metadata().
	 *
	 * @param mixed $value Metadata value.
	 * @return mixed
	 */
	protected static function normalize_meta_value( $value ) {
		if ( is_array( $value ) ) {
			$value = array_map( 'maybe_unserialize', $value );

			if ( 1 === count( $value ) ) {
				return reset( $value );
			}

			return $value;
		}

		return maybe_unserialize( $value );
	}

	/**
	 * Save record.
	 *
	 * @return int|WP_Error
	 */
	public function save() {
		if ( ! $this->validate() ) {
			return new \WP_Error( 'validation-error', esc_html__( 'Could not validate record data.', 'stream' ) );
		}

		return wp_stream_get_instance()->db->insert( (array) $this );
	}

	/**
	 * Populate "$this" object with provided data.
	 *
	 * @param array $raw  Data to be used to populate $this object.
	 */
	public function populate( array $raw ) {
		$keys = get_class_vars( $this );
		$data = array_intersect_key( $raw, $keys );
		foreach ( $data as $key => $val ) {
			$this->{$key} = $val;
		}
	}

	/**
	 * Validates this record
	 *
	 * @todo Add actual validation measures.
	 *
	 * @return bool
	 */
	public function validate() {
		return true;
	}

	/**
	 * Query record meta
	 *
	 * @param string $meta_key Meta key (optional).
	 * @param bool   $single   Return only first found (optional).
	 *
	 * @return array
	 */
	public function get_meta( $meta_key = '', $single = false ) {
		$meta = get_metadata( 'record', $this->ID, $meta_key, $single );

		if ( '' === $meta_key || ! empty( $meta ) ) {
			return '' === $meta_key ? self::normalize_meta( $meta ) : $meta;
		}

		$all_meta = self::normalize_meta( get_metadata( 'record', $this->ID ) );
		if ( ! array_key_exists( $meta_key, $all_meta ) ) {
			return $single ? '' : array();
		}

		return $single ? $all_meta[ $meta_key ] : array( $all_meta[ $meta_key ] );
	}

	/**
	 * Update record meta
	 *
	 * @param string $meta_key    Meta key.
	 * @param mixed  $meta_value  New meta value.
	 * @param mixed  $prev_value  Old meta value (optional).
	 *
	 * @return bool
	 */
	public function update_meta( $meta_key, $meta_value, $prev_value = '' ) {
		return update_metadata( 'record', $this->ID, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Determine the title of an object that a record is for.
	 *
	 * @return mixed The title of the object as a string, otherwise false
	 */
	public function get_object_title() {
		if ( ! isset( $this->object_id ) || empty( $this->object_id ) ) {
			return false;
		}

		$output = false;

		if ( isset( $this->meta->post_title ) && ! empty( $this->meta->post_title ) ) {
			$output = (string) $this->meta->post_title;
		} elseif ( isset( $this->meta->display_name ) && ! empty( $this->meta->display_name ) ) {
			$output = (string) $this->meta->display_name;
		} elseif ( isset( $this->meta->name ) && ! empty( $this->meta->name ) ) {
			$output = (string) $this->meta->name;
		}

		return $output;
	}
}
