<?php
namespace IO;
use \TymFrontiers\Helper,
    \TymFrontiers\Session,
    \TymFrontiers\Location,
    \TymFrontiers\BetaTym,
    \TymFrontiers\MySQLDatabase;

class Event{
  use Helper\MySQLDatabaseObject,
      Helper\Pagination;

  protected static $_primary_key='id';
  protected static $_db_name;
  protected static $_table_name='event_log';
  protected static $_prop_type = [];
  protected static $_prop_size = [];
	protected static $_db_fields = ["id","user","channel","title","info","_created"];

  public $id;
  public $user;
  public $channel;
  public $title;
  public $info;

  protected $_created;

  function __construct(string $title, string $info, string $channel='', string $user=''){
    $server_name = get_constant ("PRJ_SERVER_NAME");
    if (!self::$_db_name = get_database($server_name, "log")) throw new \Exception("[log] type database not set for server [{$server_name}]", 1);
    global $database;
    $conn = $database && $database instanceof MySQLDatabase ? $database : false;
    $conn = query_conn($server_name, $conn);
    self::_setConn($conn);
    // set database
    $this->_prep($title,$info,$channel,$user);
  }
  private function _prep(string $title, string $info, string $channel='', string $user=''){
    global $session;
    if( !($session instanceof Session) && empty($user)){
      throw new \Exception('There must be an instance of TymFrontiers\Session in the name of \'$session\' on global scope, otherwise user must be provided as parameter', 1);
    }
    $this->user = !empty($user) ? \strtoupper($user) : $session->name;
    $this->channel = $channel;
    $this->title = $title;
    $this->info = $info;
  }
  public function record(string $filename=''){
    global $session;
    // $session = ($session instanceof Session) ? $session instanceof Session : false;

    $db_tbl_help = "1. Define [MYSQL_LOG_DB]: log storage database name.\r\n";
    $db_tbl_help .= "2. Create database with defined name.\r\n";
    $db_tbl_help .= "3. Create table: [name]: event_log [fields]: id - int(11), user - char(16), title - char(35), info(text), _created - datetime >> defualt = datetime_stamp.";

    if( empty($filename) ){
      // record to database
      $location = ( !empty( $session->location ) && \is_object($session->location) ) ? $session->location : new Location();
      $info = $this->info;
      $info .= "\r\n#################################\r\n";
      $info .= "IP: {$location->ip} \r\n";
      $info .= "Country: {$location->country} \r\n";
      $info .= "State: {$location->state} \r\n";
      $info .= "City: {$location->city} \r\n";
      $info .= "Latitude: {$location->latitude} \r\n";
      $info .= "Longitude: {$location->longitude}";
      $this->info = $info;
      if( !$this->_create() ){
        $errs = "Failed to create event record.";
        $errs .= "\r\nBe sure you have completed following: \r\n";
        $errs .= $db_tbl_help;
        $this->errors['record'][] = [3,256,$errs,__FILE__,__LINE__];
        return false;
      }else{ return true; }
    }else{
      if( !\file_exists( \pathinfo($filename, PATHINFO_DIRNAME) ) ){
        $this->errors['record'][] = [3,256,"File name/path does not exist.",__FILE__,__LINE__];
        return false;
      }

      $txt = '#:';
      $txt .= BetaTym::MYSQL_DATETYM_STRING;
      $txt .= " #:{$this->user}";
      $txt .= " #:{$this->channel}";
      $txt .= " #:{$this->title}";
      $txt .= " #:{$this->info}";
      $txt .= " ####";
      $txt .= " [IP]: {$location->ip}";
      $txt .= " [Country]: {$location->country}";
      $txt .= " [State]: {$location->state}";
      $txt .= " [City]: {$location->city}";
      $txt .= " [Latitude]: {$location->latitude}";
      $txt .= " [Longitude]: {$location->longitude}";
      $file = new File($filename);
      return $file->append($txt);
    }
    return false;
  }

}
