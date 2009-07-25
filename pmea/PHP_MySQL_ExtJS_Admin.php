<?php

require( 'pmea_classes.php' );

class PHP_MySQL_ExtJS_Admin
{
  public $config = array(
    // BASIC configuration
    'extRoot'  => '/ext',
    'title'    => 'PHP MySQL ExtJS Admin',
    'pageSize' => 30,
    'host'  => 'localhost',
    'user'  => 'user',
    'pass'  => 'pass',
    'name'  => 'name',
    'debug' => false,
    // FEATURES
    'showtype' => false,   // show data type on headers
    'language' => 'en',    // default language is english
    'initial_table'   => '', // automatically start on this table
    'allowed_tables'  => array(), // only consider these tables
    'forbidden_tables'=> array(), // do not consider these tables
    // TEMPLATES
    'template_html'  => 'pmea_template.html',
    'template_js'    => 'pmea_template.js',
    // PATHS
    'path_languages' => 'languages',
    'path_templates' => 'templates'
  );
  
  public $root;
  public $request;
  public $response;
  public $db;   // database
  public $text; // i18n
  
  public $ds = DIRECTORY_SEPARATOR;
  
  // initialization
  public function __construct( $config = array() )
  {
    $this->root = dirname( __FILE__ );
    $this->adjustConfigPaths();
    new pmeaApplyConfig( $config, $this );
    $this->request  = new pmeaRequest();
    $this->response = new pmeaResponse();
    $this->db       = new pmeaDatabase( $this->config['host'], $this->config['user'], $this->config['pass'], $this->config['name'] );
    $this->text     = new pmeaText();
    $this->text->setSource( $this->config['path_languages'] . $this-> ds . 'pmea_text_en.txt' );
    if ( $this->config['language'] != 'en' )
      $this->text->setLanguage( $this->config['language'], $this->config['path_languages'] . $this-> ds .'pmea_text_' . $this->config['language'] . '.txt' );
  }
  
  public function adjustConfigPaths()
  {
    foreach( $this->config as $key => $value )
      if ( strpos( $key, 'path_' ) === 0 )
        $this->config[$key] = $this->root . DIRECTORY_SEPARATOR . $value;
  }
  
  // render view
  public function getHTML()
  {
    $view = new pmeaView( $this->config['path_templates'] . $this->ds . $this->config['template_html'] );
    $view->set( 'ext_root', $this->config['extRoot'] );
    $view->set( 'pmea_js', $this->config['path_templates'] . $this->ds . $this->config['template_js'], true );
    $view->set( 'pmea_title', $this->config['title'] );
    $view->set( 'pmea_actions', $this->getPmeaActionsAPI() );
    $view->set( 'pageSize', $this->config['pageSize'] );
    $view->set( 'debug', $this->config['debug'] ? '-debug' : '' );
    $view->set( 'language', $this->config['language'] );
    $view->set( 'initial_table', $this->config['initial_table'] );
    $view->set( 'tables', $this->db->getTables( $this->config['allowed_tables'], $this->config['forbidden_tables'] ) );
    $view->set( 'txt_tables', $this->text->get( 'Tables' ) );
    $view->set( 'txt_newrecord', $this->text->get( 'Add new record' ) );
    $view->set( 'txt_delrecord', $this->text->get( 'Remove selected records' ) );
    $view->set( 'txt_failure', $this->text->get( 'Failure' ) );
    $view->set( 'txt_failuremsg', $this->text->get( 'The server was not able to respond' ) );
    return $view->render();
  }
  
  // ajax API
  protected function getPmeaActionsAPI()
  {
    $methods = array();
    $reflection = new ReflectionClass( 'pmeaActions' );
    foreach( $reflection->getMethods() as $method )
      if ( $method->isPublic() && ( $method->getDeclaringClass()->name == 'pmeaActions' ) )
        $methods[] = array( 'name' => $method->getName(), 'len' => $method->getNumberOfRequiredParameters() );
    return json_encode( $methods );
  }
  
  // controller
  public function run()
  {
    if ( empty( $this->request->actions ) )
      $this->response->content = $this->getHTML();
    
    else
    {
      $response = array();
      foreach( $this->request->actions as $action )
      {
        $action->debug  = $this->config['debug'];
        $action->db     = $this->db;
        $action->config = &$this->config;
        $response[] = $action->run();
      }
      
      if ( count( $response ) > 1 )
        $this->response->content = utf8_encode( json_encode( $response ) );
      else
        $this->response->content = utf8_encode( json_encode( $response[0] ) );
      
      $this->response->headers[] = 'Content-Type: text/javascript';
    }
  }
  
  // echo view or ajax results
  public function output()
  {
    foreach( $this->response->headers as $header )
      header( $header );
    
    echo $this->response->content;
  }
}

?>
