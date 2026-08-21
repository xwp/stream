<?php
namespace WP_Stream;

class Test_List_Table extends WP_StreamTestCase {

	/**
	 * @dataProvider provide_format_summary_preview_html_cases
	 */
	public function test_format_summary_preview_html( string $input, string $expected_pattern, string $description ): void {
		$result = List_Table::format_summary_preview_html( $input );
		$this->assertMatchesRegularExpression( $expected_pattern, $result, $description );
	}

	public static function provide_format_summary_preview_html_cases(): array {
		return array(
			'empty string returns empty'                   => array(
				'',
				'/^$/',
				'Empty summary should return empty string.',
			),
			'single-line returns as-is'                    => array(
				'Post "Hello World" was published',
				'/^Post &quot;Hello World&quot; was published$|^Post "Hello World" was published$/',
				'Summary without newlines should be returned verbatim.',
			),
			'LF newline shows only first line'             => array(
				"First line\nSecond line",
				'/^First line$/',
				'LF-separated summary should return only the first line.',
			),
			'CRLF newline shows only first line'           => array(
				"First line\r\nSecond line",
				'/^First line$/',
				'CRLF-separated summary should return only the first line.',
			),
			'CR newline shows only first line'             => array(
				"First line\rSecond line",
				'/^First line$/',
				'CR-separated summary should return only the first line.',
			),
			'only trailing newline treated as single line' => array(
				"Single line\n",
				'/^Single line\n$|^Single line$/',
				'A summary with only a trailing newline should be returned as-is.',
			),
		);
	}

	public function test_format_summary_preview_html_single_line_no_wrapper(): void {
		$input  = 'A simple one-line summary';
		$result = List_Table::format_summary_preview_html( $input );

		$this->assertStringNotContainsString( '<span', $result, 'Single-line summary must not be wrapped in a span.' );
		$this->assertSame( $input, $result, 'Single-line summary must be returned unchanged.' );
	}

	public function test_format_summary_preview_html_multiline_does_not_contain_second_line(): void {
		$input  = "Line one is shown\nLine two is hidden";
		$result = List_Table::format_summary_preview_html( $input );

		$this->assertSame( 'Line one is shown', $result, 'Multiline summary must return only the first line.' );
		$this->assertStringNotContainsString( 'Line two is hidden', $result, 'Second line must not appear in output.' );
		$this->assertStringNotContainsString( '<span', $result, 'Multiline summary must not be wrapped in a span.' );
		$this->assertStringNotContainsString( 'title=', $result, 'Multiline summary must not include a title attribute.' );
	}
}
