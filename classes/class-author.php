<?php
namespace WP_Stream;

class Author {
	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var array
	 */
	public $meta = array();

	/**
	 * @var \WP_User
	 */
	protected $user;

	/**
	 * Class constructor.
	 *
	 * @param int          $user_id   The user ID.
	 * @param array|string $user_meta The user meta array, or a serialized string of user meta.
	 */
	function __construct( $user_id, $user_meta = array() ) {
		$this->id   = absint( $user_id );
		$this->meta = maybe_unserialize( $user_meta );

		if ( $this->id ) {
			$this->user = new \WP_User( $this->id );
		}
	}

	/**
	 * Get various user meta data
	 *
	 * @param string $name
	 *
	 * @throws \Exception
	 *
	 * @return string
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
			throw new \Exception( "Unrecognized magic '$name'" );
		}
	}

	/**
	 * Get the display name of the user
	 *
	 * @return string
	 */
	function get_display_name() {
		if ( 0 === $this->id ) {
			if ( isset( $this->meta['system_user_name'] ) ) {
				return esc_html( $this->meta['system_user_name'] );
			} elseif ( 'wp_cli' === $this->get_current_agent() ) {
				return 'WP-CLI'; // No translation needed
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
	 * Get the agent of the user
	 *
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
	 *
	 * @return string|bool  An img HTML element, or false if avatars are disabled
	 */
	function get_avatar_img( $size = 80 ) {
		if ( ! get_option( 'show_avatars' ) ) {
			return false;
		}

		if ( 0 === $this->id ) {
			$stream = wp_stream_get_instance();
			$url    = $stream->locations['url'] . 'ui/stream-icons/wp-cli.png';
			$avatar = sprintf( '<img alt="%1$s" src="%2$s" class="avatar avatar-%3$s photo" height="%3$s" width="%3$s">', esc_attr( $this->get_display_name() ), esc_url( $url ), esc_attr( $size ) );
		} else {
			if ( $this->is_deleted() && isset( $this->meta['user_email'] ) ) {
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
	 * @param int $size (optional)  Size of Gravatar to return (in pixels), max is 512, default is 80
	 *
	 * @return string|bool  Gravatar image URL, or false on failure
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
	 * Tries to find a label for the record's user_role.
	 *
	 * If the user_role exists, use the label associated with it.
	 *
	 * Otherwise, if there is a user role label stored as Stream meta then use that.
	 * Otherwise, if the user exists, use the label associated with their current role.
	 * Otherwise, use the role slug as the label.
	 *
	 * @return string
	 */
	function get_role() {
		global $wp_roles;

		if ( ! empty( $this->meta['user_role'] ) && isset( $wp_roles->role_names[ $this->meta['user_role'] ] ) ) {
			$user_role = $wp_roles->role_names[ $this->meta['user_role'] ];
		} elseif ( ! empty( $this->meta['user_role_label'] ) ) {
			$user_role = $this->meta['user_role_label'];
		} elseif ( isset( $this->user->roles[0] ) && isset( $wp_roles->role_names[ $this->user->roles[0] ] ) ) {
			$user_role = $wp_roles->role_names[ $this->user->roles[0] ];
		} else {
			$user_role = '';
		}

		return $user_role;
	}

	/**
	 * True if user no longer exists, otherwise false
	 *
	 * @return bool
	 */
	function is_deleted() {
		return ( 0 !== $this->id && 0 === $this->user->ID );
	}

	/**
	 * True if user is WP-CLI, otherwise false
	 *
	 * @return bool
	 */
	function is_wp_cli() {
		return ( 'wp_cli' === $this->get_agent() );
	}

	/**
	 * True if doing WP Cron, otherwise false
	 *
	 * Note: If native WP Cron has been disabled and you are
	 * hitting the cron endpoint with a system cron job, this
	 * method will always return false.
	 *
	 * @return bool
	 */
	function is_doing_wp_cron() {
		return (
			wp_stream_is_cron_enabled()
			&&
			defined( 'DOING_CRON' )
			&&
			DOING_CRON
		);
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
	function get_current_agent() {
		$agent = '';

		if ( defined( '\WP_CLI' ) && \WP_CLI ) {
			$agent = 'wp_cli';
		} elseif ( $this->is_doing_wp_cron() ) {
			$agent = 'wp_cron';
		}

		/**
		 * Filter the current agent string
		 *
		 * @return string
		 */
		$agent = apply_filters( 'wp_stream_current_agent', $agent );

		return $agent;
	}

	/**
	 * Get the agent label
	 *
	 * @param string $agent
	 *
	 * @return string
	 */
	function get_agent_label( $agent ) {
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
		 * @param string $agent
		 *
		 * @return string
		 */
		$label = apply_filters( 'wp_stream_agent_label', $label, $agent );

		return $label;
	}
}
