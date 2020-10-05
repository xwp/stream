<?php
/**
 * Connector for User-Switching
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_User_Switching
 */
class Connector_User_Switching extends Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'userswitching';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'wp_stream_after_connectors_registration',
		'switch_to_user',
		'switch_back_user',
		'switch_off_user',
	);

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'user_switching' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html_x( 'User Switching', 'user-switching', 'stream' );
	}

	/**
	 * Return translated action term labels
	 *
	 * @return array Action terms label translation
	 */
	public function get_action_labels() {
		return array();
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array();
	}

	/**
	 * Register this connector.
	 *
	 * Overrides the default `Connector::register()` method.
	 */
	public function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( $this, 'log_override' ) );
	}

	/**
	 * Override connector log for our own actions
	 *
	 * This changes the connector property to the Users connector if the log entry is from
	 * our User_Switching connector.
	 *
	 * @param  array $data The log data.
	 * @return array The updated log data.
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( 'User_Switching' === $data['connector'] ) {
			$data['connector'] = 'Users';
		}

		return $data;
	}

	/**
	 * Fired after Stream has instantiated all of its connectors.
	 *
	 * This unhooks the Users connector's login and logout actions so they don't appear when a user switches
	 * user with the User Switching plugin.
	 *
	 * @param array      $labels     All registered connector labels.
	 * @param Connectors $connectors The Connectors object instance.
	 */
	public function callback_wp_stream_after_connectors_registration( array $labels, Connectors $connectors ) {
		$action = wp_stream_filter_input( INPUT_GET, 'action' );

		if ( ! $action ) {
			$action = wp_stream_filter_input( INPUT_POST, 'action' );
		}

		if ( ! $action ) {
			return;
		}

		switch ( $action ) {

			case 'switch_to_user':
				$this->unhook_user_action( $connectors, 'clear_auth_cookie' );
				$this->unhook_user_action( $connectors, 'set_logged_in_cookie' );
				break;

			case 'switch_to_olduser':
				$this->unhook_user_action( $connectors, 'clear_auth_cookie' );
				$this->unhook_user_action( $connectors, 'set_logged_in_cookie' );
				break;

			case 'switch_off':
				$this->unhook_user_action( $connectors, 'clear_auth_cookie' );
				break;

		}

	}

	/**
	 * Fired when a user switches user with the User Switching plugin.
	 *
	 * @param int $user_id     The ID of the user being switched to.
	 * @param int $old_user_id The ID of the user being switched from.
	 */
	public function callback_switch_to_user( $user_id, $old_user_id ) {

		$user     = get_userdata( $user_id );
		$old_user = get_userdata( $old_user_id );
		/* translators: %1$s: a user display name, %2$s: a username (e.g. "Jane Doe", "administrator") */
		$message = _x(
			'Switched user to %1$s (%2$s)',
			'1: User display name, 2: User login',
			'stream'
		);

		$this->log(
			$message,
			array(
				'display_name' => $user->display_name,
				'user_login'   => $user->user_login,
			),
			$old_user->ID,
			'sessions',
			'switched-to',
			$old_user->ID
		);

	}

	/**
	 * Fired when a user switches back to their previous user account with the User Switching plugin.
	 *
	 * @param int       $user_id     The ID of the user being switched back to.
	 * @param int|false $old_user_id The ID of the user being switched from, or false if the user is switching back
	 *                               after having been switched off.
	 */
	public function callback_switch_back_user( $user_id, $old_user_id ) {

		$user = get_userdata( $user_id );
		/* translators: Placeholders refer to a user display name, and a username (e.g. "Jane Doe", "administrator") */
		$message = _x(
			'Switched back to %1$s (%2$s)',
			'1: User display name, 2: User login',
			'stream'
		);

		if ( $old_user_id ) {
			$old_user = get_userdata( $old_user_id );
		} else {
			$old_user = $user;
		}

		$this->log(
			$message,
			array(
				'display_name' => $user->display_name,
				'user_login'   => $user->user_login,
			),
			$old_user->ID,
			'sessions',
			'switched-back',
			$old_user->ID
		);

	}

	/**
	 * Fired when a user switches off with the User Switching plugin.
	 *
	 * @param int $old_user_id The ID of the user switching off.
	 */
	public function callback_switch_off_user( $old_user_id ) {

		$old_user = get_userdata( $old_user_id );

		$this->log(
			__( 'Switched off', 'stream' ),
			array(),
			$old_user->ID,
			'sessions',
			'switched-off',
			$old_user->ID
		);

	}

	/**
	 * Unhook the requested action from the Users connector.
	 *
	 * @param Connectors $connectors The Connectors instance.
	 * @param string     $action     The name of the action to unhook.
	 */
	protected function unhook_user_action( Connectors $connectors, $action ) {
		foreach ( $connectors->connectors as $connector ) {
			if ( 'users' === $connector->name ) {
				remove_action( $action, array( $connector, 'callback' ) );
			}
		}
	}
}
