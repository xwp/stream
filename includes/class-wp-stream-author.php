<?php

class WP_Stream_Author {

	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var array
	 */
	public $meta = array();

	/**
	 * @var WP_User
	 */
	protected $user_obj;

	/**
	 * @param int $user_id
	 * @param array $author_meta
	 */
	function __construct( $user_id, $author_meta = array() ) {
		$this->id   = $user_id;
		$this->meta = $author_meta;
		if ( $this->id ) {
			$this->user_obj = new WP_User( $this->id );
		}
	}

	/**
	 * @param string $name
	 * @throws Exception
	 * @return string|mixed
	 */
	function __get( $name ) {
		if ( 'display_name' === $name ) {
			return $this->get_display_name();
		} elseif ( 'avatar_img' === $name ) {
			return $this->get_avatar_img();
		} elseif ( 'avatar_src' === $name ) {
			return $this->get_avatar_src();
		} elseif ( 'role' === $name ) {
			return $this->get_role();
		} elseif ( ! empty( $this->user_obj ) && 0 !== $this->user_obj->ID ) {
			return $this->user_obj->$name;
		} else {
			throw new Exception( "Unrecognized magic '$name'" );
		}
	}

	/**
	 * @return string
	 */
	function get_display_name() {
		if ( 0 === $this->id ) {
			return __( 'N/A', 'stream' );
		} else {
			if ( $this->is_deleted() ) {
				if ( ! empty( $this->meta['display_name'] ) ) {
					return $this->meta['display_name'];
				} elseif ( ! empty( $this->meta['user_login'] ) ) {
					return $this->meta['user_login'];
				} else {
					return __( 'N/A', 'stream' );
				}
			} elseif ( ! empty( $this->user_obj->display_name ) ) {
				return $this->user_obj->display_name;
			} else {
				return $this->user_obj->user_login;
			}
		}
	}

	/**
	 * @param int $size
	 * @return string
	 */
	function get_avatar_img( $size = 80 ) {
		if ( 0 === $this->id ) {
			$url    = WP_STREAM_URL . 'ui/stream-icons/wp-cli.png';
			$avatar = sprintf( '<img alt="%1$s" src="%2$s" class="avatar avatar-%3$s photo" height="%3$s" width="%3$s">', esc_attr( $this->get_display_name() ), esc_url( $url ), esc_attr( $size ) );
		} else {
			if ( $this->is_deleted() ) {
				$email  = $this->meta['user_email'];
				$avatar = get_avatar( $email, $size );
			} else {
				$avatar = get_avatar( $this->id, $size );
			}
		}
		return $avatar;
	}

	/**
	 * @param int $size
	 * @return string
	 */
	function get_avatar_src( $size = 80 ) {
		$img = $this->get_avatar_img( $size );
		assert( preg_match( '/src=([\'"])(.*?)\1/', $img, $matches ) );
		$src = html_entity_decode( $matches[2] );
		return $src;
	}

	/**
	 * Tries to find a label for the record's author_role.
	 *
	 * If the author_role exists, use the label associated with it
	 * Otherwise, if there is a user role label stored as Stream meta then use that
	 * Otherwise, if the user exists, use the label associated with their current role
	 * Otherwise, use the role slug as the label
	 *
	 * @return string|null
	 */
	function get_role() {
		global $wp_roles;
		if ( ! empty( $this->meta['author_role'] ) && isset( $wp_roles->role_names[ $this->meta['author_role'] ] ) ) {
			$author_role = $wp_roles->role_names[ $this->meta['author_role'] ];
		} elseif ( ! empty( $this->meta['user_role_label'] ) ) {
			$author_role = $this->meta['user_role_label'];
		} elseif ( isset( $this->user_obj->roles[0] ) && isset( $wp_roles->role_names[ $this->user_obj->roles[0] ] ) ) {
			$author_role = $wp_roles->role_names[ $this->user_obj->roles[0] ];
		} else {
			$author_role = null;
		}
		return $author_role;
	}

	/**
	 * @return string
	 */
	function get_records_page_url() {
		$url = add_query_arg(
			array(
				'page'   => WP_Stream_Admin::RECORDS_PAGE_SLUG,
				'author' => absint( $this->id ),
			),
			self_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
		);
		return $url;
	}

	/**
	 * @return bool
	 */
	function is_deleted() {
		return ( 0 !== $this->id && 0 === $this->user_obj->ID );
	}

	/**
	 * @return bool
	 */
	function is_wp_cli() {
		return ! empty( $this->meta['is_wp_cli'] );
	}

	/**
	 * @return string
	 */
	function __toString() {
		return $this->get_display_name();
	}

}