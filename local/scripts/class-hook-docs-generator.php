<?php
/**
 * Extract Stream hook call sites and render markdown reference output.
 *
 * @package WP_Stream
 */

/**
 * Hook documentation generator.
 */
class Stream_Hook_Docs_Generator {

	/**
	 * Plugin-relative directories to scan for hook calls.
	 *
	 * @var array<int, string>
	 */
	private const SCAN_DIRS = array(
		'classes',
		'connectors',
		'alerts',
		'abilities',
		'includes',
	);

	/**
	 * Hook functions to scan for.
	 *
	 * @var array<int, string>
	 */
	private const HOOK_FUNCTIONS = array(
		'apply_filters',
		'do_action',
		'apply_filters_ref_array',
		'do_action_ref_array',
		'apply_filters_deprecated',
		'do_action_deprecated',
	);

	/**
	 * Absolute plugin root path.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Constructor.
	 *
	 * @param string $root Absolute plugin root.
	 */
	public function __construct( string $root ) {
		$this->root = rtrim( $root, '/' );
	}

	/**
	 * Whether generated markdown differs from the file on disk.
	 *
	 * @param string $output_path Absolute path to docs/hooks.md.
	 * @return bool
	 */
	public function is_stale( string $output_path ): bool {
		$expected = $this->render_markdown( $this->collect_calls() );
		if ( ! is_readable( $output_path ) ) {
			return true;
		}
		$actual = file_get_contents( $output_path );
		if ( false === $actual ) {
			return true;
		}
		return $expected !== $actual;
	}

	/**
	 * Write markdown to a path.
	 *
	 * @param string $output_path Absolute output path.
	 * @return bool
	 */
	public function write_markdown( string $output_path ): bool {
		$markdown = $this->render_markdown( $this->collect_calls() );
		return false !== file_put_contents( $output_path, $markdown );
	}

	/**
	 * Collect hook calls from all scan directories.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function collect_calls(): array {
		$calls = array();
		foreach ( self::SCAN_DIRS as $dir ) {
			$absolute_dir = $this->root . '/' . $dir;
			if ( ! is_dir( $absolute_dir ) ) {
				continue;
			}
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $absolute_dir, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || ! str_ends_with( $file->getPathname(), '.php' ) ) {
					continue;
				}
				$relative = substr( $file->getPathname(), strlen( $this->root ) + 1 );
				$calls    = array_merge( $calls, $this->extract_from_file( $file->getPathname(), $relative ) );
			}
		}
		return $calls;
	}

	/**
	 * Extract hook calls from one PHP file.
	 *
	 * @param string $absolute Absolute file path.
	 * @param string $relative Path relative to plugin root.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_file( string $absolute, string $relative ): array {
		$source = file_get_contents( $absolute );
		if ( false === $source ) {
			return array();
		}

		$lines  = preg_split( '/\r\n|\r|\n/', $source );
		$tokens = token_get_all( $source );
		$calls  = array();
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
				continue;
			}
			if ( ! in_array( $token[1], self::HOOK_FUNCTIONS, true ) ) {
				continue;
			}

			$function = $token[1];
			$line     = $token[2];
			$j        = $i + 1;
			while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				++$j;
			}
			if ( $j >= $count || '(' !== $tokens[ $j ] ) {
				continue;
			}

			$name_range = $this->find_first_argument_range( $tokens, $j );
			if ( null === $name_range ) {
				continue;
			}

			list( $name_start, $name_end ) = $name_range;
			$name_tokens                   = array_slice( $tokens, $name_start, $name_end - $name_start );
			$hook_name                     = $this->stringify_hook_name( $name_tokens );
			if ( '' === $hook_name ) {
				continue;
			}

			$arguments = $this->extract_top_level_arguments( $tokens, $j, $name_end );
			$docblock  = $this->docblock_before_line( $lines, $line );
			$parsed    = $this->parse_docblock( $docblock );

			$calls[] = array(
				'name'        => $hook_name,
				'type'        => str_starts_with( $function, 'apply_' ) ? 'filter' : 'action',
				'function'    => $function,
				'file'        => $relative,
				'line'        => $line,
				'arguments'   => $arguments,
				'summary'     => $parsed['summary'],
				'params'      => $parsed['params'],
				'is_pointer'  => $parsed['is_pointer'],
				'deprecated'  => str_contains( $function, '_deprecated' ),
			);
		}

		return $calls;
	}

	/**
	 * Render grouped markdown for all calls.
	 *
	 * @param array<int, array<string, mixed>> $calls Hook calls.
	 * @return string
	 */
	public function render_markdown( array $calls ): string {
		$grouped = array();
		foreach ( $calls as $call ) {
			$name = $call['name'];
			if ( ! isset( $grouped[ $name ] ) ) {
				$grouped[ $name ] = array();
			}
			$grouped[ $name ][] = $call;
		}
		ksort( $grouped );

		$stream   = array();
		$external = array();
		foreach ( $grouped as $name => $sites ) {
			if ( $this->is_stream_owned_hook( $name ) ) {
				$stream[ $name ] = $sites;
			} else {
				$external[ $name ] = $sites;
			}
		}

		$lines   = array();
		$lines[] = '# Stream hook reference';
		$lines[] = '';
		$lines[] = 'Auto-generated from PHPDoc at hook call sites. Do not edit by hand; run `npm run docs:hooks`.';
		$lines[] = '';

		$lines[] = '## Stream hooks';
		$lines[] = '';
		if ( empty( $stream ) ) {
			$lines[] = '_None found._';
			$lines[] = '';
		} else {
			foreach ( $stream as $name => $sites ) {
				$lines = array_merge( $lines, $this->render_hook_section( $name, $sites ) );
			}
		}

		$lines[] = '## External hooks invoked';
		$lines[] = '';
		if ( empty( $external ) ) {
			$lines[] = '_None found._';
			$lines[] = '';
		} else {
			foreach ( $external as $name => $sites ) {
				$lines = array_merge( $lines, $this->render_hook_section( $name, $sites ) );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Render one hook section.
	 *
	 * @param string                             $name  Hook name.
	 * @param array<int, array<string, mixed>> $sites Call sites.
	 * @return array<int, string>
	 */
	private function render_hook_section( string $name, array $sites ): array {
		$first = $sites[0];
		$lines = array();
		$lines[] = '### `' . stream_hook_docs_h( $name ) . '`';
		$lines[] = '';
		$lines[] = '- **Type:** ' . stream_hook_docs_h( (string) $first['type'] );
		if ( ! empty( $first['deprecated'] ) ) {
			$lines[] = '- **Deprecated:** via `' . stream_hook_docs_h( (string) $first['function'] ) . '`';
		}
		foreach ( $sites as $site ) {
			$lines[] = '- **Location:** `' . stream_hook_docs_h( $site['file'] ) . ':' . (int) $site['line'] . '`';
		}

		$param_text = $this->format_parameters( $first );
		if ( '' !== $param_text ) {
			$lines[] = '- **Parameters:** ' . $param_text;
		}

		$summary = trim( (string) $first['summary'] );
		if ( '' !== $summary ) {
			$lines[] = '';
			$lines[] = $summary;
		}

		$lines[] = '';
		return $lines;
	}

	/**
	 * Whether a hook name is Stream-owned.
	 *
	 * @param string $name Hook name.
	 * @return bool
	 */
	private function is_stream_owned_hook( string $name ): bool {
		return str_starts_with( $name, 'wp_stream_' )
			|| str_starts_with( $name, 'stream_' )
			|| str_contains( $name, 'wp_stream_' );
	}

	/**
	 * Format @param lines for markdown.
	 *
	 * @param array<string, mixed> $call Hook call.
	 * @return string
	 */
	private function format_parameters( array $call ): string {
		if ( empty( $call['params'] ) ) {
			return '';
		}
		$parts = array();
		foreach ( $call['params'] as $param ) {
			$parts[] = '`$' . stream_hook_docs_h( $param['name'] ) . '`'
				. ( '' !== $param['description'] ? ' — ' . stream_hook_docs_h( $param['description'] ) : '' );
		}
		return implode( '; ', $parts );
	}

	/**
	 * Parse a hook docblock.
	 *
	 * @param string $docblock Docblock source.
	 * @return array{summary: string, params: array<int, array{name: string, description: string}>, is_pointer: bool}
	 */
	private function parse_docblock( string $docblock ): array {
		$result = array(
			'summary'    => '',
			'params'     => array(),
			'is_pointer' => false,
		);
		if ( '' === trim( $docblock ) ) {
			return $result;
		}
		if ( preg_match( '/This (filter|action) is documented/i', $docblock ) ) {
			$result['is_pointer'] = true;
			$result['summary']    = trim( preg_replace( '/^\s*\/?\*+\/?\s*|\s*\*+\/?\s*$/m', '', $docblock ) );
			return $result;
		}

		$lines   = preg_split( '/\r\n|\r|\n/', $docblock );
		$summary = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			$line = preg_replace( '/^\/\*\*\s*|\s*\*\/$/', '', $line );
			$line = preg_replace( '/^\*\s?/', '', $line );
			if ( '' === $line ) {
				continue;
			}
			if ( str_starts_with( $line, '@' ) ) {
				if ( preg_match( '/^@param\s+(\S+)\s+\$(\w+)(?:\s+(.*))?/', $line, $m ) ) {
					$result['params'][] = array(
						'type'        => $m[1],
						'name'        => $m[2],
						'description' => isset( $m[3] ) ? trim( $m[3] ) : '',
					);
				}
				continue;
			}
			$summary[] = $line;
		}
		$result['summary'] = trim( implode( ' ', $summary ) );
		return $result;
	}

	/**
	 * Docblock immediately above a hook call line.
	 *
	 * @param array<int, string> $lines     Source lines.
	 * @param int                $hook_line 1-based line of hook function token.
	 * @return string
	 */
	private function docblock_before_line( array $lines, int $hook_line ): string {
		$scan = $hook_line - 2;
		while ( $scan >= 0 ) {
			$trim = trim( $lines[ $scan ] );
			if ( '' === $trim ) {
				--$scan;
				continue;
			}
			if ( str_starts_with( $trim, '*' ) || str_starts_with( $trim, '/**' ) ) {
				$end = $scan;
				while ( $scan >= 0 && ! str_contains( $lines[ $scan ], '/**' ) ) {
					--$scan;
				}
				if ( $scan < 0 ) {
					return '';
				}
				return implode( "\n", array_slice( $lines, $scan, $end - $scan + 1 ) );
			}
			if ( preg_match( '/^\$\w+\s*=\s*$/', $trim ) ) {
				--$scan;
				continue;
			}
			if ( str_contains( $trim, '/** This' ) && str_contains( $trim, 'documented' ) ) {
				return $trim;
			}
			break;
		}
		return '';
	}

	/**
	 * Range of tokens for the first argument of a function call.
	 *
	 * @param array<int, mixed> $tokens Token list.
	 * @param int               $open   Index of `(`.
	 * @return array{0: int, 1: int}|null
	 */
	private function find_first_argument_range( array $tokens, int $open ): ?array {
		$count = count( $tokens );
		$start = $open + 1;
		while ( $start < $count && is_array( $tokens[ $start ] ) && T_WHITESPACE === $tokens[ $start ][0] ) {
			++$start;
		}
		if ( $start >= $count || ')' === $tokens[ $start ] ) {
			return null;
		}
		$depth    = 0;
		$end      = $start;
		$started  = false;
		for ( $i = $start; $i < $count; $i++ ) {
			$t = $tokens[ $i ];
			if ( ! $started ) {
				$started = true;
			}
			if ( is_string( $t ) ) {
				if ( '(' === $t || '[' === $t ) {
					++$depth;
				} elseif ( ')' === $t || ']' === $t ) {
					if ( 0 === $depth && ')' === $t ) {
						$end = $i;
						break;
					}
					--$depth;
				} elseif ( ',' === $t && 0 === $depth ) {
					$end = $i;
					break;
				}
			}
		}
		if ( ! $started ) {
			return null;
		}
		return array( $start, $end );
	}

	/**
	 * Build hook name string from tokens of the first argument.
	 *
	 * @param array<int, mixed> $tokens Name expression tokens.
	 * @return string
	 */
	private function stringify_hook_name( array $tokens ): string {
		$parts = array();
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) ) {
				if ( T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
					$parts[] = stripcslashes( substr( $token[1], 1, -1 ) );
				} elseif ( T_ENCAPSED_AND_WHITESPACE === $token[0] ) {
					$parts[] = $token[1];
				} elseif ( T_CURLY_OPEN === $token[0] ) {
					$parts[] = '{';
				} elseif ( T_OBJECT_OPERATOR === $token[0] ) {
					continue;
				} elseif ( T_STRING === $token[0] && ! empty( $parts ) && str_ends_with( $parts[ count( $parts ) - 1 ], '}' ) ) {
					$parts[] = '{' . $token[1] . '}';
				} elseif ( in_array( $token[0], array( T_STRING, T_VARIABLE ), true ) ) {
					$var = $token[1];
					if ( str_starts_with( $var, '$' ) ) {
						$inner = substr( $var, 1 );
						if ( ! empty( $parts ) && '{' === $parts[ count( $parts ) - 1 ] ) {
							$parts[ count( $parts ) - 1 ] = '{' . $inner . '}';
						} else {
							$parts[] = '{' . $inner . '}';
						}
					} else {
						$parts[] = $var;
					}
				} else {
					$parts[] = $token[1];
				}
			} else {
				if ( '"' === $token || "'" === $token || '.' === $token || '->' === $token ) {
					continue;
				}
				if ( '}' === $token && ! empty( $parts ) && str_ends_with( $parts[ count( $parts ) - 1 ], '}' ) ) {
					continue;
				}
				$parts[] = $token;
			}
		}

		$name = implode( '', $parts );
		$name = preg_replace( '/\{[^{}]+\}\{(\w+)\}/', '{$1}', $name );
		$name = preg_replace( '/\{[^{}]+\}(\w+)/', '{$1}', $name );
		$name = preg_replace( '/\s+/', '', $name );
		return is_string( $name ) ? $name : '';
	}

	/**
	 * Extract remaining hook arguments after the hook name.
	 *
	 * @param array<int, mixed> $tokens   Token list.
	 * @param int               $open     Opening `(` index.
	 * @param int               $name_end Index after hook name (comma or closing paren).
	 * @return array<int, string>
	 */
	private function extract_top_level_arguments( array $tokens, int $open, int $name_end ): array {
		$count = count( $tokens );
		if ( $name_end >= $count ) {
			return array();
		}
		$close = $this->find_balanced_close( $tokens, $open );
		if ( null === $close ) {
			return array();
		}
		$slice = array_slice( $tokens, $name_end, $close - $name_end );
		$args  = array();
		$depth = 0;
		$buf   = '';
		foreach ( $slice as $token ) {
			if ( is_string( $token ) ) {
				if ( '(' === $token || '[' === $token ) {
					++$depth;
				} elseif ( ')' === $token || ']' === $token ) {
					--$depth;
				} elseif ( ',' === $token && 0 === $depth ) {
					$args[] = trim( $buf );
					$buf    = '';
					continue;
				}
				$buf .= $token;
			} elseif ( is_array( $token ) ) {
				$buf .= $token[1];
			}
		}
		$buf = trim( $buf );
		if ( '' !== $buf && ')' !== $buf ) {
			$args[] = rtrim( $buf, ')' );
		}
		return array_values( array_filter( array_map( 'trim', $args ) ) );
	}

	/**
	 * Find closing paren for an opening paren token index.
	 *
	 * @param array<int, mixed> $tokens Token list.
	 * @param int               $open   Index of `(` token.
	 * @return int|null
	 */
	private function find_balanced_close( array $tokens, int $open ): ?int {
		$depth = 0;
		$count = count( $tokens );
		for ( $i = $open; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( '(' === $token ) {
				++$depth;
			} elseif ( ')' === $token ) {
				--$depth;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}
		return null;
	}
}

/**
 * Escape text for hook reference markdown.
 *
 * @param string $text Raw text.
 * @return string
 */
function stream_hook_docs_h( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}
