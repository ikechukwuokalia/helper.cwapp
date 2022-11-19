<?php
namespace IO;

use \TymFrontiers\MySQLDatabase,
    \TymFrontiers\InstanceError,
    \TymFrontiers\Validator;
use function IO\get_constant;
use function IO\setting_get_value;

class ShoppingCart {
  use \TymFrontiers\Helper\MySQLDatabaseObject,
      \TymFrontiers\Helper\Pagination;

  protected static $_primary_key='id';
  protected static $_db_name;
  protected static $_table_name = "shopping_cart";
  protected static $_db_fields = ["id", "user", "item", "type", "quantity", "currency", "price", "discount", "description", "_created"];

  public $id;
  public $user;
  public $currency;

  public $item;
  public $type;
  public $quantity;
  public $price;
  public $discount;
  public $description;
  protected $_created;

  public $errors = [];

  function __construct($user= "", $ws = "", $conn = false) {
    global $database;
    $conn = $conn && $conn instanceof MySQLDatabase ? $conn : ($database && $database instanceof MySQLDatabase ? $database : false);
      // server name
    if (!$srv = get_constant("PRJ_SERVER_NAME")) {
      throw new \Exception("Database server not defined", 1);
    }
    // database name
    if (!$db_name = get_database($srv, "base")) {
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
    if (!empty($user) && $user = (new Validator)->pattern($user, ["user","pattern", "/^(289|002|252|052|352)([0-9]{8,11})$/"])) {
      $this->user = $user;
    } if (!empty($ws) && $ws = (new Validator)->pattern($ws, ["ws","pattern", "/^289([0-9]{8,11})$/"])) {
      $this->ws = $ws;
      self::$_db_fields[] = "ws";
    }
    // get region's payment currency
    $region = get_constant("WS_REGION");
    if (empty($region)) {
      throw new \Exception("No defined region", 1);
    }
    $currency = setting_get_value("SYSTEM", "RGN-{$region}.PAY-CURRENCY", get_constant("PRJ_BASE_DOMAIN"));
    if (!$currency) {
      throw new \Exception("Regional default currency is not defined.", 1);
    }
    $this->currency = $currency;
  }

  final public function add (string $item, int $quantity, float $price, string $description, float $discount = 0.0):bool {
    if (!empty($this->user) && !empty($this->currency)) {
      $valid = new Validator;
      $wsq = !empty($this->ws) ? " AND `ws` = '{$this->ws}' " : "";
      if (!$this->item = $valid->pattern($item, ["item","pattern", "/^(457|296|813|382|243|506|592)([0-9]{8,11})$/"])) {
        if ($errs = (new InstanceError($valid))->get("pattern", true)) {
          unset($valid->errors["pattern"]);
          foreach ($errs as $er) {
            $this->errors["add"][] = [1, 256, $er, __FILE__, __LINE__];
          }
        }
      } else if ($this->item && self::findBySql("SELECT id FROM :db:.:tbl: WHERE `user` = '{$this->user}' {$wsq} AND `item` = '{$this->item}' LIMIT 1")) {
        $this->errors["add"][] = [7, 256, "Item already exist in user's cart. Use ->setQuantity to update.", __FILE__, __LINE__];
      } else if ($quantity < 1) {
        $this->errors["add"][] = "[quantity] must be 1 or greater.";
        return false;
      } else if (!$this->description = $valid->text($description, ["description","text", 5, 2040])) {
        if ($errs = (new InstanceError($valid))->get("text", true)) {
          unset($valid->errors["text"]);
          foreach ($errs as $er) {
            $this->errors["add"][] = [1, 256, $er, __FILE__, __LINE__];
          }
        } 
      } else if (!$this->price = $valid->float($price, ["price","float", 0.0, 0])) {
        if ($errs = (new InstanceError($valid))->get("float", true)) {
          unset($valid->errors["float"]);
          foreach ($errs as $er) {
            $this->errors["add"][] = [1, 256, $er, __FILE__, __LINE__];
          }
        }
      } else if ($discount > 0 && !$this->discount = $valid->float($discount, ["discount","float", 0.0, 0])) {
        if ($errs = (new InstanceError($valid))->get("float", true)) {
          unset($valid->errors["float"]);
          foreach ($errs as $er) {
            $this->errors["add"][] = [1, 256, $er, __FILE__, __LINE__];
          }
        }
      } else {
        // proceed
        if ($storage = code_storage($item, get_constant("PRJ_SERVER_NAME"))) {
          $this->quantity = $quantity;
          $this->type = $storage[1];
          return $this->_create();
        } else {
          $this->errors["add"][] = [1, 256, "Failed to obtain [type] from [item].", __FILE__, __LINE__];
        }
      }
    }
    return false;
  } 
  final public function setQuantity (string $item, int $quantity):bool {
    $valid = new Validator;
    $wsq = !empty($this->ws) ? " AND `ws` = '{$this->ws}' " : "";
    if (!$item = $valid->pattern($item, ["item","pattern", "/^(457|296|813|382|243|506|592)([0-9]{8,11})$/"])) {
      if ($errs = (new InstanceError($valid))->get("pattern", true)) {
        unset($valid->errors["pattern"]);
        foreach ($errs as $er) {
          $this->errors["setQuantity"][] = [1, 256, $er, __FILE__, __LINE__];
        }
      }
    } else if ($quantity < 1) {
      $this->errors["setQuantity"][] = "[quantity] must be 1 or greater.";
    } else {
      if (!$found = self::findBySql("SELECT `id`, `price`, `discount`, `quantity` FROM :db:.:tbl: WHERE `user` = '{$this->user}' {$wsq} AND `item` = '{$item}' LIMIT 1")) {
        $this->errors["setQuantity"][] = [1, 256, "Record of given [item] was not found.", __FILE__, __LINE__];
      } else {
        $found = $found[0];
        $found->id = (int)$found->id;
        $found->price = (float)$found->price;
        $found->discount = (float)$found->discount;
        $unit_price = $found->price > 0 ? $found->price/$found->quantity : 0;
        $unit_discount = $found->discount > 0 ? $found->discount/$found->quantity : 0;
        $dbname = self::$_db_name;
        $tbl = self::$_table_name;
        $query = "UPDATE `{$dbname}`.`{$tbl}` SET `quantity` = {$quantity}, `price` = ({$unit_price} * {$quantity}), `discount` = ({$unit_discount} * {$quantity}) WHERE id = {$found->id}";
        if (!empty(self::$_conn->errors['query'])) unset(self::$_conn->errors['query']);
        if (!self::$_conn->query($query)) {
          $this->errors["setQuantity"][] = [1, 256, "Failed to update [quantity].", __FILE__, __LINE__];
          if ($errs = (new InstanceError(self::$_conn))->get("query", true)) {
            unset(self::$_conn->errors['query']);
            foreach ($errs as $er) {
              $this->errors["setQuantity"][] = [1, 256, $er, __FILE__, __LINE__];
            }
          }
        } else {
          return true;
        }
      }
    }
    return false;
  }

  public function save ():bool { return false; }
  public function create ():bool { return false; }
  public function update ():bool { return false; }
}
