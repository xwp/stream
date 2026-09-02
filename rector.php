<?php
/**
 * Rector configuration for the Stream plugin.
 *
 * Rules are registered by the XWPENG-47 sub-PR that applies them, so this file
 * always describes the transformations actually present in the committed source.
 * Run `composer lint-rector` (dry run) or `composer rector` (apply).
 *
 * @package WP_Stream
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
	->withPaths(
		array(
			__DIR__ . '/stream.php',
			__DIR__ . '/abilities',
			__DIR__ . '/alerts',
			__DIR__ . '/classes',
			__DIR__ . '/connectors',
			__DIR__ . '/exporters',
			__DIR__ . '/includes',
		)
	)
	->withSkip(
		array(
			__DIR__ . '/.ai',
			__DIR__ . '/artifacts',
			__DIR__ . '/build',
			__DIR__ . '/local',
			__DIR__ . '/node_modules',
			__DIR__ . '/tests',
			__DIR__ . '/vendor',
			TypedPropertyFromAssignsRector::class => array(
				// Public extension-point bases. Children are typed with the
				// parent in later PRs (Q7). Directory skips keep this PR inside
				// classes/ so a first-pass run cannot type a child while its
				// parent stays untyped (T1.3).
				__DIR__ . '/classes/class-connector.php',
				__DIR__ . '/classes/class-alert-type.php',
				__DIR__ . '/classes/class-alert-trigger.php',
				__DIR__ . '/classes/class-exporter.php',
				__DIR__ . '/connectors',
				__DIR__ . '/alerts',
				__DIR__ . '/exporters',
			),
		)
	)
	->withPhpVersion( PhpVersion::PHP_82 )
	->withCache( __DIR__ . '/artifacts/rector' )
	->withRules( array() )
	->withConfiguredRule(
		TypedPropertyFromAssignsRector::class,
		array( TypedPropertyFromAssignsRector::INLINE_PUBLIC => true )
	);
