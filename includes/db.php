<?php

abstract class WP_Stream_DB_Abstract {

	abstract function query( $args );

	abstract function store( $data );

}
