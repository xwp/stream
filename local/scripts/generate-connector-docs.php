<?php
/**
 * Helper script to generate Connector documentation.
 */

$plugin_dir     = realpath( '.' );
$connectors_dir = "{$plugin_dir}/connectors";
$connectors     = scandir( $connectors_dir );

include_once( realpath( './classes/class-connector.php' ) );

foreach( $connectors as $connector_path ) {

	if ( false === strpos( $connector_path, 'class' ) ) {
		continue;
	}

	include_once( "{$connectors_dir}/{$connector_path}" );
}

// There are other ways to do this! I get about 250 classes here which for a one off run I think is ok.
$classes = get_declared_classes();

if ( empty( $classes ) ) {
	return;
}

$connectors_doc = fopen(
	"{$plugin_dir}/connectors.md",
	'w', // empty the file, we're going to rewrite it.
);

foreach( $classes as $possible_connector ) {
	if ( false === strpos( $possible_connector, 'WP_Stream\Connector_' ) ) {
		continue;
	}

	print( "Connector: {$possible_connector} in progress \n" );

	$connector      = new $possible_connector();
	$connector_info = wp_stream_create_connector_information( $connector );

	fwrite( $connectors_doc, $connector_info );

}

print( "All done! \n" );

function wp_stream_create_connector_information( $connector ) {
	ob_start();

	$class_name      = get_class( $connector );
	$actions         = $connector->actions;
	$register_method = wp_stream_export_method_from_class( $class_name, 'register' );
?>

## Connector: <?php echo strip_tags( $class_name ); ?>


### Actions

<?php foreach( $actions as $action ) : ?>
	- <?php echo strip_tags( $action ); ?>

<?php endforeach; ?>

### Class register()

<details>
<summary>This is the register method for the Connector. Occasionally there are additional actions in here.</summary>

```php
<?php echo $register_method; ?>
```
</details>

<?php
	return ob_get_clean();
}

/**
 * Get the code from the method.
 * Thank you https://stackoverflow.com/questions/7026690/reconstruct-get-source-code-of-a-php-function.
 *
 * @param string $class_name The class name.
 * @param string $method_name The method name.
 * @return string The code.
 */
function wp_stream_export_method_from_class( string $class_name, string $method_name ): string {

	$reflection_method = new ReflectionMethod( $class_name, $method_name );

	$filename   = $reflection_method->getFileName();
	$start_line = $reflection_method->getStartLine() - 1;
	$end_line   = $reflection_method->getEndLine();

	$source = file( $filename );
	$source = implode('', array_slice( $source, 0, count( $source ) ) );


	$source = preg_split( "/" . PHP_EOL . "/", $source );

	$method_code = '';
	for( $i = $start_line; $i < $end_line; $i++ ) {
		$method_code .= "{$source[ $i ]}\n";
	}

	return $method_code;
}
