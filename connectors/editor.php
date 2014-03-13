<?php

class WP_Stream_Connector_Editor extends WP_Stream_Connector {

	/**
	 * Context name
	 *
	 * @var string
	 */
	public static $name = 'editor';

	/**
	 * Actions registered for this context
	 *
	 * @var array
	 */
	public static $actions = array(
		'admin_footer',
	);

	/**
	 * Register all context hooks
	 *
	 * @return void
	 */
	public static function register() {
		parent::register();
	}

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Editor', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			
		);
	}

	public static function callback_admin_footer() {
		if( 'theme-editor' === get_current_screen()->id ) : ?>
			<script>
				var $content = jQuery('#newcontent');

				jQuery('<textarea></textarea>')
					.val($content.val())
					.attr('name', 'oldcontent')
					.hide()
					.insertBefore($content);
			</script>
		<?php endif;
	}

	public static function log_changes() {
		
	}

}
