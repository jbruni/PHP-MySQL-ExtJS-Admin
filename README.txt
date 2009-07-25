PHP MySQL ExtJS Admin

Input: your MySQL database name, host, user and pass
Output: an ExtJS application to edit your data right in the browser

Requirements:
 - MySQL 5
 - PHP 5
 - ExtJS 3

Installation:
 - Copy the files in a browsable folder of your site

How to use it:
 - Edit "config.php" file with your specific configuration

And it is ready to run!

Configuration is simple; see example below:

$pmea_config = array(
  'extRoot'  => '/ext',
  'title'    => 'PHP MySQL ExtJS Admin',
  'pageSize' => 30,
  'host'  => 'localhost',
  'user'  => 'user',
  'pass'  => 'pass',
  'name'  => 'name',
  'debug' => false
);

 - extRoot  = path to your ExtJS 3 installation (root folder here refers to your public site root)
 - title    = browser window title and grid title
 - pageSize = number of rows to display at once
 - host  = your MySQL database server IP or URL
 - user  = your MySQL database user name
 - pass  = your MySQL database password
 - name  = your MySQL database name
 - debug = if true, send exceptions to client-side (as recommended by ExtJS)

Enjoy!