<?php
/**
 * Displays a list of provided records in a HTML Table
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Preview_List_Table
 */
class Preview_List_Table extends List_Table {

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Plugin object.
	 *
	 * @return void
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		parent::__construct( $plugin );
	}

	/**
	 * Sets up the records for display.
	 *
	 * @param array $items List of items for display.
	 *
	 * @return void
	 */
	public function set_records( $items ) {
		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();
		$hidden   = $this->get_hidden_columns();
		$primary  = $columns['summary'];

		$this->_column_headers = array(
			$columns,
			$hidden,
			$sortable,
			$primary,
		);

		$this->items = $items;
	}

	/**
	 * Display the table
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function display() {
		?>
		<table class="wp-list-table <?php esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tbody id="the-list">
			<?php $this->display_rows_or_placeholder(); ?>
			</tbody>

			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>

		</table>
		<?php
	}
}
