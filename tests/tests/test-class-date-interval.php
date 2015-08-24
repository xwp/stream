<?php
namespace WP_Stream;

class Test_Date_Interval extends WP_StreamTestCase {
	/**
	 * Holds the date interval base class
	 *
	 * @var Date_Interval
	 */
	protected $date_interval;

	public function setUp() {
		parent::setUp();

		$this->date_interval = new Date_Interval();
		$this->assertNotEmpty( $this->date_interval );
	}

	public function test_construct() {
		$this->assertNotEmpty( $this->date_interval->intervals );
	}

	public function test_get_predefined_intervals() {
		$intervals = $this->date_interval->get_predefined_intervals();

		$expected_intervals = array(
			'today',
			'yesterday',
			'last-7-days',
			'last-14-days',
			'last-30-days',
			'this-month',
			'last-month',
			'last-3-months',
			'last-6-months',
			'last-12-months',
			'this-year',
			'last-year',
		);

		foreach ( $expected_intervals as $expected_interval ) {
			$this->assertArrayHasKey( $expected_interval, $intervals );
		}

		foreach ( $intervals as $interval ) {
			$this->assertArrayHasKey( 'label', $interval );
			$this->assertArrayHasKey( 'start', $interval );
			$this->assertArrayHasKey( 'end', $interval );
		}
	}
}
