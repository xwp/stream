<?php
namespace WP_Stream;

class Export_JSON {

  public function output_file ( $data ) {

    header( 'Content-type: text/json' );
    header( 'Content-Disposition: attachment; filename="stream.json"' );

    $output = json_encode( $data );
    die( $output ); // @codingStandardsIgnoreLine text-only output

  }

}
