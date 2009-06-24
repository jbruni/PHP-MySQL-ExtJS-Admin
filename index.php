<?php 

include('php_MySQL_ExtJS_Admin.php'); 

// create the application
$pmea = new PHP_MySQL_ExtJS_Admin( isset( $pmea_config ) ? $pmea_config : array() );

// run the application:
// the controller process the request and generates a response
$pmea->run();

// "echo" or "print" response out to the world!
$pmea->output();

// gently finishing the execution of the script
exit();

?>
