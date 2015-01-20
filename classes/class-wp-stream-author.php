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
	protected $user;

	/**
	 * @param int $user_id
	 * @param array $author_meta
	 */
	function __construct( $user_id, $author_meta = array() ) {
		$this->id   = $user_id;
		$this->meta = $author_meta;

		if ( $this->id ) {
			$this->user = new WP_User( $this->id );
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
		} elseif ( 'agent' === $name ) {
			return $this->get_agent();
		} elseif ( ! empty( $this->user ) && 0 !== $this->user->ID ) {
			return $this->user->$name;
		} else {
			throw new Exception( "Unrecognized magic '$name'" );
		}
	}

	/**
	 * @return string
	 */
	function get_display_name() {
		if ( 0 === $this->id ) {
			if ( isset( $this->meta['system_user_name'] ) ) {
				return esc_html( $this->meta['system_user_name'] );
			}
			return esc_html__( 'N/A', 'stream' );
		} else {
			if ( $this->is_deleted() ) {
				if ( ! empty( $this->meta['display_name'] ) ) {
					return $this->meta['display_name'];
				} elseif ( ! empty( $this->meta['user_login'] ) ) {
					return $this->meta['user_login'];
				} else {
					return esc_html__( 'N/A', 'stream' );
				}
			} elseif ( ! empty( $this->user->display_name ) ) {
				return $this->user->display_name;
			} else {
				return $this->user->user_login;
			}
		}
	}

	/**
	 * @return string
	 */
	function get_agent() {
		$agent = '';

		if ( ! empty( $this->meta['agent'] ) ) {
			$agent = $this->meta['agent'];
		} elseif ( ! empty( $this->meta['is_wp_cli'] ) ) {
			$agent = 'wp_cli'; // legacy
		}

		return $agent;
	}

	/**
	 * Return a Gravatar image as an HTML element.
	 *
	 * This function will not return an avatar if "Show Avatars" is unchecked in Settings > Discussion.
	 *
	 * @param int $size (optional) Size of Gravatar to return (in pixels), max is 512, default is 80
	 * @return string An img HTML element
	 */
	function get_avatar_img( $size = 80 ) {
		if ( ! get_option( 'show_avatars' ) ) {
			return false;
		}

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
	 * Return the URL of a Gravatar image.
	 *
	 * @param int $size (optional) Size of Gravatar to return (in pixels), max is 512, default is 80
	 * @return string Gravatar image URL
	 */
	function get_avatar_src( $size = 80 ) {
		$img = $this->get_avatar_img( $size );

		if ( ! $img ) {
			return false;
		}

		if ( 1 === preg_match( '/src=([\'"])(.*?)\1/', $img, $matches ) ) {
			$src = html_entity_decode( $matches[2] );
		} else {
			return false;
		}

		return $src;
	}

	/**
	 * Tries to find a label for the record's author_role.
	 *
	 * If the author_role exists, use the label associated with it.
	 *
	 * Otherwise, if there is a user role label stored as Stream meta then use that.
	 *
	 * Otherwise, if the user exists, use the label associated with their current role.
	 *
	 * Otherwise, use the role slug as the label.
	 *
	 * @return string
	 */
	function get_role() {
		global $wp_roles;

		if ( ! empty( $this->meta['author_role'] ) && isset( $wp_roles->role_names[ $this->meta['author_role'] ] ) ) {
			$author_role = $wp_roles->role_names[ $this->meta['author_role'] ];
		} elseif ( ! empty( $this->meta['user_role_label'] ) ) {
			$author_role = $this->meta['user_role_label'];
		} elseif ( isset( $this->user->roles[0] ) && isset( $wp_roles->role_names[ $this->user->roles[0] ] ) ) {
			$author_role = $wp_roles->role_names[ $this->user->roles[0] ];
		} else {
			$author_role = '';
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
		return ( 0 !== $this->id && 0 === $this->user->ID );
	}

	/**
	 * @return bool
	 */
	function is_wp_cli() {
		return ( 'wp_cli' === $this->get_agent() );
	}

	/**
	 * @return string
	 */
	function __toString() {
		return $this->get_display_name();
	}

	/**
	 * Look at the environment to detect if an agent is being used
	 *
	 * @return string
	 */
	static function get_current_agent() {
		$agent = '';

		if ( defined( 'WP_CLI' ) ) {
			$agent = 'wp_cli';
		} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$agent = 'wp_cron';
		}

		/**
		 * Filter the current agent string
		 *
		 * @since 1.4.4
		 *
		 * @return string
		 */
		$agent = apply_filters( 'wp_stream_current_agent', $agent );

		return $agent;
	}

	/**
	 * @param string $agent
	 * @return string
	 */
	static function get_agent_label( $agent ) {
		if ( 'wp_cli' === $agent ) {
			$label = esc_html__( 'via WP-CLI', 'stream' );
		} elseif ( 'wp_cron' === $agent ) {
			$label = esc_html__( 'during WP Cron', 'stream' );
		} else {
			$label = '';
		}

		/**
		 * Filter agent labels
		 *
		 * @since 1.4.4
		 *
		 * @param string $agent
		 *
		 * @return string
		 */
		$label = apply_filters( 'wp_stream_agent_label', $label, $agent );

		return $label;
	}

}
