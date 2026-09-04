<?php
/**
 * Renders the Stream Records admin screen and constructs its list table.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Admin_Screen_Records
 */
class Admin_Screen_Records {

	/**
	 * Class constructor.
	 *
	 * @param Admin $admin Admin façade.
	 */
	public function __construct( private Admin $admin ) {
	}

	/**
	 * Render main page
	 */
	public function render_list_table() {
		$this->admin->list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->admin->list_table->display(); ?>
		</div>
		<?php
	}

	/**
	 * Instantiate the list table
	 */
	public function register_list_table() {
		$this->admin->list_table = new List_Table(
			$this->admin->plugin,
			array(
				'screen' => $this->admin->menu->screen_id['main'],
			)
		);
	}
}
