<?php

include( 'config.php' );
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
    'showtype' => false,  // show data type on headers
    'language' => 'en'
  );
  
  public $request;
  public $response;
  public $db;   // database
  public $text; // i18n
  
  // initialization
  public function __construct( $config = array() )
  {
    new pmeaApplyConfig( $config, $this );
    $this->request  = new pmeaRequest();
    $this->response = new pmeaResponse();
    $this->db       = new pmeaDatabase( $this->config['host'], $this->config['user'], $this->config['pass'], $this->config['name'] );
    $this->text     = new pmeaText();
    $this->text->setSource( dirname(__FILE__). DIRECTORY_SEPARATOR . 'pmea_text_en.txt' );
    if ( $this->config['language'] != 'en' )
      $this->text->setLanguage( $this->config['language'], 'pmea_text_' . $this->config['language'] . '.txt' );
  }
  
  // render view
  public function getHTML()
  {
    $view = new pmeaView( 'pmea_template.html' );
    $view->set( 'ext_root', $this->config['extRoot'] );
    $view->set( 'pmea_js', 'pmea_template.js', true );
    $view->set( 'pmea_title', $this->config['title'] );
    $view->set( 'pmea_actions', $this->getPmeaActionsAPI() );
    $view->set( 'pageSize', $this->config['pageSize'] );
    $view->set( 'debug', $this->config['debug'] ? '-debug' : '' );
    $view->set( 'tables', $this->db->getTables() );
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
