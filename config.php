<?php

/*
 * WARNING: there is no built-in authentication in this application (yet)
 * so, you need to provide some SECURITY either by configuring at least an HTTP authorization
 * or creating a MySQL read-only user (in this case, you will not be able to edit data, only browse)
 * or any other way more suitable for your needs
 */

$pmea_config = array(
  
  // extRoot = path to your ExtJS 3 installation (root folder here refers to your public site root)
  'extRoot'  => '/ext',
  
  // title = browser window title and grid title
  'title'    => 'PHP MySQL ExtJS Admin',
  
  // pageSize = number of rows to display at once
  'pageSize' => 30,
  
  /*
   * host = your MySQL database server IP or URL
   * user = your MySQL database user name
   * pass = your MySQL database password
   * name = your MySQL database name
   */
  'host'  => 'localhost',
  'user'  => 'user',
  'pass'  => 'pass',
  'name'  => 'name',
  
  // debug = if true, send exceptions to client-side (as recommended by ExtJS)
  'debug' => false,
  
  // showtype = display MySQL data type in the column headers
  'showtype' => false,
  
  // language = language of user interface
  'language' => 'en'
  
);

?>
