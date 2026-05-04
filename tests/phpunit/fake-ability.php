<?php
/**
 * Concrete subclass of the Ability abstract base used by Test_Ability.
 *
 * Lives in its own file so the WPCS Generic.Files.OneObjectStructurePerFile
 * sniff is satisfied and so PHPUnit does not auto-discover it as a test.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Fake_Ability_For_Test
 */
class Fake_Ability_For_Test extends Ability {

	/**
	 * Annotations for the fake ability. Toggled by tests.
	 *
	 * @var array
	 */
	public $annotations = array();

	/**
	 * Last input received by execute(), for test inspection.
	 *
	 * @var array|null
	 */
	public $last_input;

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/fake';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Fake';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Fake ability used in unit tests.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'foo' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_output_schema() {
		return array( 'type' => 'string' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return $this->annotations;
	}

	/**
	 * Capture input and return a deterministic result.
	 *
	 * @param array $input Input matching get_input_schema().
	 * @return string
	 */
	public function execute( $input ) {
		$this->last_input = $input;
		return 'ok';
	}
}
