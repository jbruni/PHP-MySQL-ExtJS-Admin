<?php

class pmeaRequest
{
  public $actions = array();
  
  public function __construct()
  {
    if ( isset( $_POST['extAction'] ) )
      $this->getFormAction();
    else
      $this->getRequestActions();
  }
  
  protected function getFormAction()
  {
    $extParameters = $_POST;
    foreach( array( 'extAction', 'extMethod', 'extTID', 'extUpload' ) as $variable )
    {
      if ( !isset( $extParameters[$variable] ) )
        $$variable = '';
      else
      {
        $$variable = $extParameters[$variable];
        unset( $extParameters[$variable] );
      }
    }
    $this->actions[] = new pmeaActions( $extAction, $extMethod, $extParameters, $extTID, $extUpload );
  }
  
  protected function getRequestActions()
  {
    $input = file_get_contents( 'php://input' );
    
    $request = json_decode( $input );
    
    if ( !is_array( $request ) )
      $request = array( $request );
    
    foreach( $request as $rpc )
    {
      foreach( array( 'type', 'action', 'method', 'data', 'tid' ) as $variable )
        $$variable = ( isset( $rpc->$variable ) ? $rpc->$variable : '' );
      if ( $type == 'rpc' )
        $this->actions[] = new pmeaActions( $action, $method, $data, $tid, false );
    }
  }
}

class pmeaResponse
{
  public $headers = array();
  public $content = '';
}

// pmeaFunction - see "Function Object" pattern at 
// http://homepages.ecs.vuw.ac.nz/~tk/publications/papers/function-object.pdf
abstract class pmeaFunction
{
  private $function_parameters;
  private $function_defaults;
  private $function_result;
  
  final public function __construct()
  {
    $input = func_get_args();
    
    $this->beforeConstruct( $input );
    
    $reflection = new ReflectionClass( get_class( $this ) );
    $this->function_defaults   = $reflection->getDefaultProperties();
    $this->function_parameters = array_keys( $this->function_defaults );
    
    $this->parseParameters( $input, true );
    
    $this->afterConstruct( $input );
    
    if ( isset( $this->function_autorun ) && ( $this->function_autorun === true ) )
      $this->run();
  }
  
  public function run()
  {
    $input = func_get_args();
    $this->parseParameters( $input, false );
    $this->function_result = $this->execute( $input );
    return $this->function_result;
  }
  
  public function repeat()
  {
    $input = func_get_args();
    $this->parseParameters( $input, true );
    $this->function_result = $this->execute( $input );
    return $this->function_result;
  }
  
  public function getParameters()
  {
    return $this->function_parameters;
  }
  
  public function getDefaults()
  {
    return $this->function_defaults;
  }
  
  public function getResult()
  {
    return $this->function_result;
  }
  
  private function parseParameters( $input, $repeat = false )
  {
    if ( empty( $input ) )
      return;
    
    foreach( $this->function_parameters as $key => $parameter )
      if ( isset( $input[$key] ) )
        $this->$parameter = $input[$key];
      elseif ( !$repeat )
        $this->$parameter = $this->function_defaults[$parameter];
  }
  
  protected function beforeConstruct() {}
  
  protected function afterConstruct() {}
  
  abstract protected function execute();
}

class pmeaApplyConfig extends pmeaFunction
{
  public $config;
  public $object;
  
  protected $function_autorun = true;
  
  protected function execute()
  {
    foreach( $this->config as $key => $value )
      $this->object->config[$key] = $value;
    return $this->object->config;
  }
}

class pmeaView extends pmeaFunction
{
  public $filename;
  protected $elements = array();
  protected $blocks   = array();
  
  protected function execute()
  {
    $this->renderBlocks();
    return $this->renderBlock( $this->filename );
  }
  
  public function render()
  {
    $input = func_get_args();
    return call_user_func_array( array( $this, 'run' ), $input );
  }
  
  public function set( $element, $content, $block = false )
  {
    $token = '[%'.$element.'%]';
    
    if ( $block )
      $this->blocks[$token] = $content;
    else
      $this->elements[$token] = $content;
  }
  
  protected function renderBlocks()
  {
    foreach( $this->blocks as $token => $filename )
      $this->elements[$token] = $this->renderBlock( $filename );
  }
  
  protected function renderBlock( $filename )
  {
    return strtr( file_get_contents( $filename ), $this->elements );
  }
}

class pmeaBuildSelect extends pmeaFunction
{
  public $fields = '*';
  public $tables;
  public $filter = '';
  public $page = 1;
  public $size = 25;
  public $sort = '';
  public $direction = 'ASC';
  
  protected function execute()
  {
    $sql =  'SELECT ' . $this->getEscaped( $this->fields, 'mysql' );
    $sql .= ' FROM ' . $this->getEscaped( $this->tables, 'mysql' );
    if ( !empty( $this->filter ) )
      $sql .= ' WHERE ' . $this->filter;  // TODO: parse/escape filter
    if ( !empty( $this->sort ) )
      $sql .= ' ORDER BY ' . $this->getEscaped( $this->sort, 'sort' ); 
    if ( !empty( $this->size ) )
      $sql .= ' LIMIT ' . (int) ( $this->size * ( $this->page - 1 ) ) . ', ' . (int) ( $this->size * 1 );
    return $sql;
  }
  
  protected function getEscaped( $array, $type = 'normal' )
  {
    if ( !is_array( $array ) )
      $array = explode( ',', $array );
    
    array_walk( $array, array( $this, 'prepareString' ), $type );
    
    return implode( ', ', $array );
  }
  
  protected function &prepareString( &$string, $key = 0, $type = 'normal' )
  {
    if ( $type == 'mysql' )
      $string = mysql_real_escape_string( $string );
    
    elseif ( $type == 'normal' )
      $string = '"' . str_replace( '"', '\"', $string ) . '"'; 
      
    elseif( $type == 'sort' )
    {
      $sorts = preg_split( '/\s+(DESC|ASC)\s*$/i' , $string, 2, PREG_SPLIT_DELIM_CAPTURE );
      if ( count( $sorts ) == 1 )
        $string = mysql_real_escape_string( $string ) . ' ' .$this->getDirectionString( $this->direction );
      else
        $string = mysql_real_escape_string( $sorts[0] ) . ' ' . strtoupper( $sorts[1] );
    }
    
    return $string;
  }
  
  protected function getDirectionString( $value )
  {
    if ( is_string( $value ) && ( strtoupper( $value ) == 'DESC' ) )
      return 'DESC';
    
    if ( is_numeric( $value ) && ( $value < 0 ) )
      return 'DESC';
    
    return ( $value ? 'ASC' : 'DESC' );
  }
}

class pmeaActions extends pmeaFunction
{
  public $action;
  public $method;
  public $parameters;
  public $transaction_id;
  public $upload = false;
  public $debug  = false;
  public $db; // database
  public $config = array(); // pmea config
  
  protected $dataTypes = array(
    'string'  => '/char|blob|text|enum|set|point/i',
    'int'     => '/bit|int/i',
    'float'   => '/float|double|real|decimal|numerical/i',
    'boolean' => '/bool/i',
    'date'    => '/date|time|year/i',
    'auto'    => '//'
  );
  
  protected $editorTypes = array(
    'combo'   => '/enum/i',
    'textarea'    => '/blob|text/i',
    'numberfield' => '/bit|int|float|double|real|decimal|numerical/i',
    'checkbox'    => '/bool/i',
    'timefield'   => '/^time$/i',
    'datefield'   => '/date|time/i',
    'textfield'   => '//'
  );
  
  protected function execute()
  {
    $response = array(
      'type'    => 'rpc',
      'tid'     => $this->transaction_id,
      'action'  => __CLASS__,
      'method'  => $this->method
    );
    
    try
    {
      $result = $this->callAction();
      $response['result'] = $result;
    }
    
    catch ( Exception $e )
    {
      $response['result'] = 'pmeaFailure';
      if ( $this->debug )
        $response = array(
          'type'    => 'exception',
          'tid'     => $this->transaction_id,
          'message' => $e->getMessage(),
          'where'   => $e->getTraceAsString()
        );
    }
    array_walk_recursive( $response, array( $this, 'utf8_encode' ) );
    return $response;
  }
  
  protected function &utf8_encode( &$value, $key )
  {
    if ( is_string( $value ) )
      $value = utf8_encode( $value );
    return $value;
  }
  
  protected function callAction()
  {
    if ( $this->action != __CLASS__ )
      throw new Exception( 'Only calls to pmeaActions are allowed; tried to call ' . $this->action, E_USER_ERROR );
    
    if ( !method_exists( $this, $this->method ) )
      throw new Exception( 'Call to undefined or not allowed pmeaActions method ' . $this->method, E_USER_ERROR );
    
    $method = new ReflectionMethod( __CLASS__, $this->method );
    $params = $method->getNumberOfRequiredParameters();
    if ( count( $this->parameters ) < $params )
      throw new Exception( 'Call to pmeaActions method ' . $this->method . ' needs at least ' . $params . ' parameters', E_USER_ERROR );
    
    return call_user_func_array( array( $this, $this->method ), $this->parameters );
  }
  
  protected function isAllowedTable( $table )
  {
    if ( !empty( $this->config['allowed_tables'] ) && !in_array( $table, $this->config['allowed_tables'] ) )
      return false;
    
    if ( !empty( $this->config['forbidden_tables'] ) && in_array( $table, $this->config['forbidden_tables'] ) )
      return false;
    
    return true;
  }
  
  protected function getExtType( $mysql_type )
  {
    foreach ( $this->dataTypes as $type => $regex )
      if ( preg_match( $regex, $mysql_type ) )
        break;
    return $type;
  }
  
  protected function getExtEditor( $mysql_type )
  {
    if ( stripos( $mysql_type, 'point' ) !== false )
      return 'textfield';
    
    foreach ( $this->editorTypes as $type => $regex )
      if ( preg_match( $regex, $mysql_type ) )
        break;
    return $type;
  }
  
  protected function getExtFieldMaxLength( $mysql_type, $extjs_xtype = 'textfield' )
  {
    if ( preg_match( '/\(([0-9,]+)\)/', $mysql_type, $result ) == 0 )
      return 1.7976931348623157e+308;  // Number.MAX_VALUE
    
    $maxlength = 0;
    $nums = explode( ',', $result['1'] );
    $max =  count( $nums );
    for ( $count = 0; $count < $max; $count++ )
      $maxlength += $count + $nums[$count];
    
    if ( ( $extjs_xtype = 'numberfield' ) && ( strpos( $mysql_type, 'unsigned' ) === false ) )
      $maxlength += 1;
    
    return $maxlength;
  }
  
  // TODO: refactor - split this method in several smaller ones
  public function getFields( $table, $grid )
  {
    if ( !$this->isAllowedTable( $table ) )
      throw new Exception( 'Table ' . $table . ' not allowed', E_USER_ERROR );
    
    $fields = array();
    $fields[] = $this->getDummyField( $table );
    $columns = array();
    
    $results = $this->db->getFieldsFromTable( $table );
    // $key = $this->db->getKeyFromTable( $table );
    $key = '';
    foreach ( $results as $result )
    {
      // TODO: improve here creating classes for field and column
      $field = new stdClass();
      $field->name = $result['Field'];
      $field->defaultValue = $result['Default'];
      $field->type = $this->getExtType( $result['Type'] );
      $fields[] = $field;
      $column = new stdClass();
      $column->dataIndex = $result['Field'];
      // TODO: handle multiple primary keys and other field details
      $column->header = '<b' . ( $result['Key'] == 'PRI' ? ' style="color:red"' : '' ) . '>' . $result['Field'] . '</b>';
      if ( $this->config['showtype'] )
        $column->header .= '<br />' . $result['Type'];
      $column->tooltip = $result['Extra'];
      $column->editor = new stdClass();
      $column->editor->xtype = $this->getExtEditor( $result['Type'] );
      if ( $column->editor->xtype == 'combo' )
      {
        preg_match_all( "/'(([^']|'')*)'/i", $result['Type'], $enum );
        $column->editor->enums = $enum[1];
      }
      if ( in_array( $column->editor->xtype, array( 'textfield', 'numberfield' ) ) )
        $column->editor->maxLength = $this->getExtFieldMaxLength( $result['Type'], $column->editor->xtype );
      $columns[] = $column;
      // TODO: handle multiple primary keys
      if ( empty( $key ) && ( $result['Key'] == 'PRI' ) )
        $key = $result['Field'];
    }
    return array(
      'table' => $table,
      'grid' => $grid,
      'idProperty' => $key,
      'fields' => $fields,
      'columns' => $columns
    );
  }
  
  protected function getDummyField( $table )
  {
    $field = new stdClass();
    $field->name = 'pmea_table';
    $field->defaultValue = $table;
    $field->type = 'string';
    return $field;
  }
  
  public function getData( $table, $start, $limit, $sort, $dir )
  {
    if ( !$this->isAllowedTable( $table ) )
      throw new Exception( 'Table ' . $table . ' not allowed', E_USER_ERROR );
    
    $page = ( $start / $limit ) + 1;
    $sql = new pmeaBuildSelect( '*', $table, '', $page, $limit, $sort, $dir );
    $sql = $sql->run();
    $results = $this->db->runSQL( $sql );
    
    if ( !$results )
      return array( 'id' => '', 'total' => 0, 'success' => false, 'rows' => array() );
    
    $rows = array();;
    while( $row = mysql_fetch_assoc( $results ) )
    {
      $row['pmea_table'] = $table;
      $rows[] = $row;
    }
    
    // TODO: handle multiple primary keys
    return array(
      // 'id'      => $this->db->getKeyFromTable( $table ),
      'total'   => $this->db->getCountForTable( $table ),
      'success' => true,
      'rows'    => $rows
    );
  }
  
  public function setData( $key, $data )
  {
    $table = mysql_real_escape_string( $data->pmea_table );
    
    if ( !$this->isAllowedTable( $table ) )
      throw new Exception( 'Table ' . $table . ' not allowed', E_USER_ERROR );
    
    $key_field = $this->db->getKeyFromTable( $table );
    $where = mysql_real_escape_string( $key_field ) . ' = "' . mysql_real_escape_string( $key ) . '"';
    
    $changes = array();
    $ignore = array( 'pmea_table', $key_field );
    foreach( $data as $field => $value )
      if ( !in_array( $field, $ignore ) )
        $changes[] = mysql_real_escape_string( $field ) . ' = "' . mysql_real_escape_string( $value ) . '"';
    
    $sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $changes ) . ' WHERE ' . $where;
    if ( !$this->db->runSQL( $sql ) )
    {
      $this->debug = true; // workaround for ExtJS impossible requirements
      throw new Exception( 'Update failed - ' . mysql_error(), E_USER_ERROR );
    }
    
    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
    $result = $this->db->runSQL( $sql );
    $data = mysql_fetch_assoc( $result );
    $data = array_merge( array( 'pmea_table' => $table ), $data );
    return $data;
  }
  
  public function newData( $data )
  {
  }
  
  public function delData( $key  )
  {
  }
}

class pmeaDatabase
{
  public  $host = '';
  public  $user = '';
  public  $name = '';
  
  public $connection;
  public $connected = false;
  public $ready     = false;
  
  public $query     = '';
  public $tables    = array();
  
  public function __construct( $host = '', $user = '', $pass = '', $name = '' )
  {
    $this->connect( $host, $user, $pass, $name );
  }
  
  public function connect( $host = '', $user = '', $pass = '', $name = '' )
  {
    foreach( array( 'host', 'user', 'name' ) as $config )
      $this->$config = $$config;
    
    $connection = mysql_connect( $host, $user, $pass );
    
    if ( !$connection )
      throw new Exception( 'Could not connect to the database', E_USER_ERROR );
    
    $this->connection = $connection;
    $this->connected  = true;
    
    $this->ready = mysql_select_db( $name, $this->connection );
    if ( !$this->ready )
      throw new Exception( 'Could not select database ' . $name, E_USER_ERROR );
    
    return true;
  }
  
  public function runSQL( $query )
  {
    $this->query = $query;
    return mysql_query( $this->query, $this->connection );
  }
  
  public function getTables( $allow = array(), $forbid = array() )
  {
    $result = $this->runSQL( 'SHOW TABLES FROM ' . $this->name );
    if ( !$result )
      throw new Exception( 'Could not get tables from database ' . $this->name, E_USER_WARNING );
    
    $this->tables = array();
    while( $table = mysql_fetch_row( $result ) )
      if ( !empty( $allow ) && !in_array( $table[0], $allow ) )
        continue;
      elseif ( !empty( $forbid ) && in_array( $table[0], $forbid ) )
        continue;
      else
        $this->tables[] = $table[0];
    
    $tables = array();
    foreach( $this->tables as $table )
      $tables[] = array( $table, $table );
    
    return json_encode( $tables );
  }
  
  public function getFieldsFromTable( $table_name )
  {
    $result = $this->runSQL( 'DESCRIBE ' . $table_name );
    if ( !$result )
      throw new Exception( 'Could not get fields from table ' . $table_name, E_USER_ERROR );
    
    $table_fields = array();  
    while ( $field = mysql_fetch_assoc( $result ) )
      $table_fields[] = $field;
    
    return $table_fields;
  }
  
  public function getKeyFromTable( $table_name )
  {
    $result = $this->runSQL( 'SHOW INDEX FROM ' . $table_name );
    if ( !$result )
      throw new Exception( 'Could not get keys from table ' . $table_name, E_USER_ERROR );
    
    // TODO: handle multiple primary keys
    while ( $key = mysql_fetch_assoc( $result ) )
      if ( $key['Key_name'] == 'PRIMARY' )
        return $key['Column_name'];
    
    return '';
  }
  
  public function getCountForTable( $table_name )
  {
    $result = $this->runSQL( 'SELECT COUNT(*) FROM ' . $table_name );
    if ( !$result )
      throw new Exception( 'Could not get count for table ' . $table_name, E_USER_ERROR );
    
    return mysql_result( $result, 0, 0 );
  }
}

class pmeaText extends pmeaFunction
{
  public $text;
  public $translate = true;
  
  protected $language;
  protected $translation_filename;
  protected $translation = array();
  
  protected $source_filename;
  protected $source  = array();
  protected $max_key = -1;
  
  protected $new = array();
  
  public function get()
  {
    $input = func_get_args();
    return call_user_func_array( array( $this, 'repeat' ), $input );
  }
  
  public function execute()
  {
    $this->text = trim( $this->text );
    
    // find key for text in source
    $key = array_search( $this->text, $this->source );
    
    // add text to source if not existent and get its key
    if ( $key === false )
      $key = $this->addToSource();
    
    // search for key in translated texts
    if ( $this->translate && isset( $this->translation[$key] ) )
      $text = $this->translation[$key];
    else
      $text = $this->text;
    
    return "$text";
  }
  
  protected function addToSource()
  {
    $this->max_key++;
    $this->source[$this->max_key] = $this->text;
    $this->new[$this->max_key] = $this->text;
    return $this->max_key;
  }
  
  public function getLanguage()
  {
    return $this->language;
  }
  
  public function setLanguage( $language, $translation_filename )
  {
    $this->language = $language;
    $this->translation_filename = $translation_filename;
    $this->parseLanguageFile( 'translation' );
  }
  
  public function setSource( $source_filename )
  {
    $this->source_filename = $source_filename;
    $this->parseLanguageFile( 'source' );
  }
  
  protected function parseLanguageFile( $type )
  {
    $texts = array();
    unset( $this->$type );
    $this->$type = &$texts;
    
    if ( $type == 'source' )
      $this->max_key = -1;
    
    $filename = $type . '_filename';
    if ( !file_exists( $this->$filename ) )
      return;
    
    $lines = file( $this->$filename );
    
    foreach( $lines as $line )
    {
      $parts = preg_split( '/=>/', $line, 2 );
      if ( count( $parts ) < 2 )
        continue;
        
      $key = trim( $parts[0] );
      if ( $key != (int) $key )
        continue;
      
      if ( ( $type == 'source' ) && ( $key > $this->max_key ) )
        $this->max_key = $key;
      
      $key = (int)$key;
      $texts[] = trim( $parts[1] );
    }
  }
  
  function __destruct()
  {
    if ( empty( $this->new ) )
      return;
    
    $this->parseLanguageFile( 'source' );
    
    $append = '';
    foreach( $this->new as $key => $text )
      if ( array_key_exists( $key, $this->source ) || ( array_search( $text, $this->source ) !== false ) )
        unset( $this->new[$key] ); 
      else
        $append .= $key . ' => ' . $text . "\r\n";
    
    if ( !empty( $append ) )
      file_put_contents( $this->source_filename, $append, FILE_APPEND );
  }
}

?>
