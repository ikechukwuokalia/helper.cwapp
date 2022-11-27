<?php
namespace IO;
use \TymFrontiers\MySQLDatabase,
    \TymFrontiers\InstanceError,
    \TymFrontiers\Validator;
use function \query_conn;
use function \get_database;
use function \get_dbserver;
    
class Notice {
  use \TymFrontiers\Helper\MySQLDatabaseObject,
      \TymFrontiers\Helper\Pagination;

  protected static $_primary_key='id';
  protected static $_db_name;
  protected static $_table_name = "notifications";
  protected static $_db_fields = ["id", "is_read", "user", "heading", "message", "priority", "path", "action", "_created", "_updated"];

  const PRIORITY_CRITICAL   = 1;
  const PRIORITY_URGENT     = 2;
  const PRIORITY_IMPORTANT  = 3;
  const PRIORITY_NORMAL     = 4;
  const PRIORITY_LOW        = 5;
  const PRIORITY_DEFER      = 6;

  public $id;
  public $is_read;
  public $user;
  public $heading;
  public $message;
  public $priority;
  public $path;
  public $action = "popup";

  protected $_created;
  protected $_updated;

  public $errors = [];

  function __construct($conn = false) {
    global $database;
    $conn = $conn && $conn instanceof MySQLDatabase ? $conn : ($database && $database instanceof MySQLDatabase ? $database : false);
      // server name
    if (!$srv = get_constant("PRJ_SERVER_NAME")) {
      throw new \Exception("Database server not defined", 1);
    }
    // database name
    if (!$db_name = get_database("base", $srv)) {
      throw new \Exception("Base database name not set", 1);
    } 
    self::$_db_name = $db_name;
    // database server
    if (!$db_server = get_dbserver($srv)) {
      throw new \Exception("Base database-server not set", 1);
    } 
    // set database connection
    $conn = query_conn($srv, $conn);
    self::_setConn($conn);
  }

  final public function notify (string $user, string $heading, string $message, string $path, int $priority = self::PRIORITY_NORMAL, string $action = "popup"):bool {
    $valid = new Validator;
    if (!$this->user = $valid->pattern($user, ["user","pattern", "/^(289|002|252|052|352)([0-9]{8,11})$/"])) {
      if ($errs = (new InstanceError($valid))->get("pattern", true)) {
        unset($valid->errors["pattern"]);
        foreach ($errs as $er) {
          $this->errors["notify"][] = [1, 256, $er, __FILE__, __LINE__];
        }
      }
    } if (!$this->heading = $valid->text($heading, ["heading", "text", 5, 96])) {
      if ($errs = (new InstanceError($valid))->get("text", true)) {
        unset($valid->errors["text"]);
        foreach ($errs as $er) {
          $this->errors["notify"][] = [1, 256, $er, __FILE__, __LINE__];
        }
      }
    } if (!$this->message = $valid->script($message, ["message", "script", 15, 2048])) {
      if ($errs = (new InstanceError($valid))->get("script", true)) {
        unset($valid->errors["script"]);
        foreach ($errs as $er) {
          $this->errors["notify"][] = [1, 256, $er, __FILE__, __LINE__];
        }
      }
    } 
    $this->priority = $valid->int($priority, ["priority", "int", 1, 6]);
    if ($this->priority === false) {
      if ($errs = (new InstanceError($valid))->get("int", true)) {
        unset($valid->errors["int"]);
        foreach ($errs as $er) {
          $this->errors["notify"][] = [1, 256, $er, __FILE__, __LINE__];
        }
      }
    } if (!$this->path = $valid->url($path, ["path", "url"])) {
      if ($errs = (new InstanceError($valid))->get("url", true)) {
        unset($valid->errors["url"]);
        foreach ($errs as $er) {
          $this->errors["notify"][] = [1, 256, $er, __FILE__, __DIR__];
        }
      }
    } if (!$this->action = $valid->option($action, ["action", "option",["popup", "follow-link"]])) {
      if ($errs = (new InstanceError($valid))->get("option", true)) {
        unset($valid->errors["option"]);
        foreach ($errs as $er) {
          $this->errors["notify"][] = [1, 256, $er, __FILE__, __DIR__];
        }
      }
    }
    if (empty($this->errors["notify"])) {
      return $this->_create();
    }
    return false;
  }
  public function save ():bool { return false; }
  public function create ():bool { return false; }
  public function update ():bool { return $this->_update(); }

}
