<?php
/**
 * Lightweight WordPress readme.txt parser and converter to Markdown
 * The WordPress-Plugin-Readme-Parser project is too heavy and has too many dependencies for what we need (we don't need conversion to HTML)
 * @link https://github.com/markjaquith/WordPress-Plugin-Readme-Parser Alternative to WordPress-Plugin-Readme-Parser
 * @version 1.1.1
 * @author Weston Ruter <weston@x-team.com> (@westonruter)
 * @copyright Copyright (c) 2013, X-Team <http://x-team.com/wordpress/>
 * @license GPLv2+
 */

class WordPress_Readme_Parser {
	public $path;
	public $source;
	public $title = '';
	public $short_description = '';
	public $metadata = array();
	public $sections = array();

	function __construct( $args = array() ) {
		$args = array_merge( get_object_vars( $this ), $args );
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}

		$this->source = file_get_contents( $this->path );
		if ( ! $this->source ) {
			throw new Exception( 'readme.txt was empty or unreadable' );
		}

		// Parse metadata
		$syntax_ok = preg_match( '/^=== (.+?) ===\n(.+?)\n\n(.+?)\n(.+)/s', $this->source, $matches );
		if ( ! $syntax_ok ) {
			throw new Exception( 'Malformed metadata block' );
		}
		$this->title = $matches[1];
		$this->short_description = $matches[3];
		$readme_txt_rest = $matches[4];
		$this->metadata = array_fill_keys( array( 'Contributors', 'Tags', 'Requires at least', 'Tested up to', 'Stable tag', 'License', 'License URI' ), null );
		foreach ( explode( "\n", $matches[2] ) as $metadatum ) {
			if ( ! preg_match( '/^(.+?):\s+(.+)$/', $metadatum, $metadataum_matches ) ) {
				throw new Exception( "Parse error in $metadatum" );
			}
			list( $name, $value )  = array_slice( $metadataum_matches, 1, 2 );
			$this->metadata[$name] = $value;
		}
		$this->metadata['Contributors'] = preg_split( '/\s*,\s*/', $this->metadata['Contributors'] );
		$this->metadata['Tags'] = preg_split( '/\s*,\s*/', $this->metadata['Tags'] );

		$syntax_ok = preg_match_all( '/(?:^|\n)== (.+?) ==\n(.+?)(?=\n== |$)/s', $readme_txt_rest, $section_matches, PREG_SET_ORDER );
		if ( ! $syntax_ok ) {
			throw new Exception( 'Failed to parse sections from readme.txt' );
		}
		foreach ( $section_matches as $section_match ) {
			array_shift( $section_match );

			$heading     = array_shift( $section_match );
			$body        = trim( array_shift( $section_match ) );
			$subsections = array();

			// @todo Parse out front matter /(.+?)(\n=\s+.+$)/s

			// Parse subsections
			if ( preg_match_all( '/(?:^|\n)= (.+?) =\n(.+?)(?=\n= |$)/s', $body, $subsection_matches, PREG_SET_ORDER ) ) {
				$body = null;
				foreach ( $subsection_matches as $subsection_match ) {
					array_shift( $subsection_match );
					$subsections[] = array(
						'heading' => array_shift( $subsection_match ),
						'body' => trim( array_shift( $subsection_match ) ),
					);
				}
			}

			$this->sections[] = compact( 'heading', 'body', 'subsections' );
		}
	}

	/**
	 * Convert the parsed readme.txt into Markdown
	 * @param array|string [$params]
	 * @return string
	 */
	function to_markdown( $params = array() ) {

		$general_section_formatter = function ( $body ) use ( $params ) {
			$body = preg_replace(
				'#\[youtube\s+(?:http://www\.youtube\.com/watch\?v=|http://youtu\.be/)(.+?)\]#',
				'[![Play video on YouTube](http://i1.ytimg.com/vi/$1/hqdefault.jpg)](http://www.youtube.com/watch?v=$1)',
				$body
			);
			return $body;
		};

		// Parse sections
		$section_formatters = array(
			'Description' => function ( $body ) use ( $params ) {
				if ( isset( $params['travis_ci_url'] ) ) {
					$body .= sprintf( "\n\n[![Build Status](%s.png?branch=master)](%s)", $params['travis_ci_url'], $params['travis_ci_url'] );
				}
				return $body;
			},
			'Screenshots' => function ( $body ) {
				$body = trim( $body );
				$new_body = '';
				if ( ! preg_match_all( '/^\d+\. (.+?)$/m', $body, $screenshot_matches, PREG_SET_ORDER ) ) {
					throw new Exception( 'Malformed screenshot section' );
				}
				foreach ( $screenshot_matches as $i => $screenshot_match ) {
					$img_extensions = array( 'jpg', 'gif', 'png' );
					foreach ( $img_extensions as $ext ) {
						$filepath = sprintf( 'assets/screenshot-%d.%s', $i + 1, $ext );
						if ( file_exists( dirname( $this->path ) . DIRECTORY_SEPARATOR . $filepath ) ) {
							break;
						}
						else {
							$filepath = null;
						}
					}
					if ( empty( $filepath ) ) {
						continue;
					}

					$screenshot_name = $screenshot_match[1];
					$new_body .= sprintf( "### %s\n", $screenshot_name );
					$new_body .= "\n";
					$new_body .= sprintf( "![%s](%s)\n", $screenshot_name, $filepath );
					$new_body .= "\n";
				}
				return $new_body;
			},
		);

		// Format metadata
		$formatted_metadata = $this->metadata;
		$formatted_metadata['Contributors'] = join(
			', ',
			array_map(
				function ( $contributor ) {
					$contributor = strtolower( $contributor );
					// @todo Map to GitHub account
					return sprintf( '[%1$s](http://profiles.wordpress.org/%1$s)', $contributor );
				},
				$this->metadata['Contributors']
			)
		);
		$formatted_metadata['Tags'] = join(
			', ',
			array_map(
				function ( $tag ) {
					return sprintf( '[%1$s](http://wordpress.org/plugins/tags/%1$s)', $tag );
				},
				$this->metadata['Tags']
			)
		);
		$formatted_metadata['License'] = sprintf( '[%s](%s)', $formatted_metadata['License'], $formatted_metadata['License URI'] );
		unset( $formatted_metadata['License URI'] );
		if ( $this->metadata['Stable tag'] === 'trunk' ) {
			$formatted_metadata['Stable tag'] .= ' (master)';
		}

		// Render metadata
		$markdown  = "<!-- DO NOT EDIT THIS FILE; it is auto-generated from readme.txt -->\n";
		$markdown .= sprintf( "# %s\n", $this->title );
		$markdown .= "\n";
		if ( file_exists( 'assets/banner-1544x500.png' ) ) {
			$markdown .= '![Banner](assets/banner-1544x500.png)';
			$markdown .= "\n";
		}
		$markdown .= sprintf( "%s\n", $this->short_description );
		$markdown .= "\n";
		foreach ( $formatted_metadata as $name => $value ) {
			$markdown .= sprintf( "**%s:** %s  \n", $name, $value );
		}
		$markdown .= "\n";

		foreach ( $this->sections as $section ) {
			$markdown .= sprintf( "## %s ##\n", $section['heading'] );
			$markdown .= "\n";

			$body = $section['body'];

			$body = call_user_func( $general_section_formatter, $body );
			if ( isset( $section_formatters[$section['heading']] ) ) {
				$body = trim( call_user_func( $section_formatters[$section['heading']], $body ) );
			}

			if ( $body ) {
				$markdown .= sprintf( "%s\n", $body );
			}
			foreach ( $section['subsections'] as $subsection ) {
				$markdown .= sprintf( "### %s ###\n", $subsection['heading'] );
				$markdown .= sprintf( "%s\n", $subsection['body'] );
				$markdown .= "\n";
			}

			$markdown .= "\n";
		}

		return $markdown;
	}

}
