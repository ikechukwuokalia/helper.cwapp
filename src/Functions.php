<?php
namespace IO; // IkechukwuOkalia
use \TymFrontiers\MultiForm,
    \TymFrontiers\InstanceError,
    \TymFrontiers\Generic,
    \TymFrontiers\Data,
    \TymFrontiers\BetaTym,
    \TymFrontiers\Validator,
    \TymFrontiers\API,
    \TymFrontiers\HTTP,
    \TymFrontiers\MySQLDatabase,
    \TymFrontiers\Session;

function get_constant (string $name) {
  return \defined("CPRJ_PREFIX")
    ? (\defined(CPRJ_PREFIX . $name) ? \constant(CPRJ_PREFIX . $name) : null)
    : (\defined($name) ? \constant($name) : null);
}
function set_constant (string $name, $value) {
  $prfx = \defined("CPRJ_PREFIX") ? CPRJ_PREFIX : "";
  if (empty(get_constant($name))) {
    \define($prfx. $name, $value);
  }
}

function get_serverapp (string $server_name, string $app_name) {
  $db_user = get_dbuser($server_name, "developer");
  $db_name = get_database($server_name, "developer");
  if ($db_user && $conn = new MySQLDatabase(get_dbserver($server_name), $db_user[0], $db_user[1], $db_name)) {
    if ($found = (new MultiForm($db_name, "server_apps", "id", $conn))->findBySql("SELECT * FROM :db:.:tbl: WHERE `server` = '{$conn->escapeValue($server_name)}' AND `name` = '{$conn->escapeValue($app_name)}' LIMIT 1 ")) {
      $app = new API\DevApp ($conn, $db_name, "apps");
      $app->load($found[0]->name, $found[0]->pu_key);
      return !empty($app->name) ? $app : null;
    }
  }
  return null;
}
function get_navgroup (string $group_name):array {
  global $session;
  $return_navs = [];
  $nav_file = get_constant("PRJ_ROOT") . "/.system/.navigation";
  if (\file_exists($nav_file)) {
    $navs = \file_get_contents($nav_file);
    if ($navs && $navs = \json_decode($navs)) {
      if (!empty($navs->$group_name)) {
        foreach ($navs->$group_name->links as $nav) {
          if (
            ((bool)$nav->strict_access && $nav->access_rank == $session->access_rank())
            || (!(bool)$nav->strict_access && $nav->access_rank <= $session->access_rank())
          ) {
            // $nav->path = $path;
            unset($nav->strict_access);
            unset($nav->access_rank);
            $return_navs[] = $nav;
          }
        }
      }
    }
  }
  return $return_navs;
}

function email_temp (string $message, string $unsubscribe = '', string $template = ""){
  $tmp_file = !empty($template) ? $template : get_constant("PRJ_LIBRARY") . '/Email-Temp.html';
  if (!\file_exists($tmp_file) || !\is_readable($tmp_file)) {
    throw new \Exception ("Template file: {$tmp_file} not found/readable.",1);
  }
  $template = \file_get_contents($tmp_file);
  $template = \str_replace("%{message}", $message, $template);
  $replace_regex = [
    "subject" => "%{subject}",
    "logo" => "%{logo}",
    "email_icon" => "%{email_icon}",
    "primary_color" => "%{primary_color}",
    "secondary_color" => "%{secondary_color}",
    "website" => "%{website}",
    "project_name" => "%{project_name}",
    "project_title" => "%{project_title}",
    "unsubscribe" => "%{unsubscribe}"
  ];

  $replace = [
    "subject" => "",
    "logo" => WHOST . "/assets/img/icon-192x192.png",
    "email_icon" => WHOST . "/assets/img/email-icon.png",
    "primary_color" => get_constant("PRJ_PRIMARY_COLOUR"),
    "secondary_color" => get_constant("PRJ_SECONDARY_COLOUR"),
    "website" => "https://" . get_constant("PRJ_DOMAIN"),
    "project_name" => get_constant("PRJ_NAME"),
    "project_title" => get_constant("PRJ_TITLE"),
    "unsubscribe" => empty($unsubscribe) ? "" : "<div style=\"border-top: solid 1px silver; padding:12px; font-size:0.85em; text-align: center;\">{$unsubscribe}</div>",
  ];
  foreach ($replace as $prop =>$val) {
    if (\array_key_exists($prop, $replace_regex)) $template = \str_replace($replace_regex[$prop], $val, $template);
  }
  return $template;
}

function round (float $num, string $currency = "NGN") {
  global $cryptos;
  if (!empty($cryptos) && \is_array($cryptos)) {
    return \round($num, (\array_key_exists($currency, $cryptos) ? 8 : 2));
  }
  return false;
}
function currency_decimals ($currency) {
  global $cryptos;
  return \array_key_exists($currency, $cryptos) ? 8 : 2;
}

function coupon_summary (string $type, float $value, string|null $ext_type, int $ext_count, float $max_value = 0.00, string $currency = "USD") {
  global $cryptos;
  $return  = "";
  if ($value > 0) {
    $surfix = $type == "FLUID" ? "%" : "";
    $prefix = $type == "FIXED" ? currency_symbol($currency) : "";
    $value = $value <= 0 ? 0.00 : \number_format($value, (\in_array($currency, $cryptos) ? 8 : 2), ".", ",");
    $max_value = $max_value <= 0 ? 0.00 : \number_format($max_value, (\in_array($currency, $cryptos) ? 8 : 2), ".", ",");
    $return .= $prefix . $value . $surfix;

    if ($max_value > 0) {
  
      $return .= (" (up to ". currency_symbol($currency). "{$max_value})");
    }
    $return .= " OFF";
  } if (!empty($ext_type) && $ext_count > 0) {
    $return .= " + additional {$ext_count} " .\ucwords(\strtolower($ext_type));
    $return .= $ext_count > 1 ? "s" : "";
  }
  return $return;
}
function currency_symbol (string $currency):string {
  global $currency_symbols;
  return \array_key_exists($currency, $currency_symbols) ? $currency_symbols[$currency] : $currency;
}
// Data inputation score validation
function data_get_score (string $table) {
  global $database;
  $table = $database->escapeValue($table);
  if (!$score = (new MultiForm(get_database("BASE", "base"), "data_scores", "id"))->findBySql("SELECT * FROM :db:.:tbl: WHERE tbl = '{$table}' LIMIT 1")) {
    throw new \Exception("No record found for given table ({$table}).", 1);
  }
  $score = $score[0];
  if (empty($score->regex)) throw new \Exception("Invalid [regex] for score record.", 1);
  $return = [
    "table" => $score->tbl,
    "pkey" => $score->pkey,
    "search_key" => $score->search_key,
    "btn_text" => $score->btn_text,
    "callback" => $score->callback,
    "set_path" => $score->set_path,
    "is_popup" => (bool)$score->is_popup,
    "title" => $score->title,
    "score" => 0,
    "description" => $score->description,
    "fields" => [],
  ];
  // process fields
  $flds = \preg_split('/\r\n|\r|\n/', $score->regex);
  if (!\is_array($flds) || (\is_array($flds) && \count($flds) < 1)) throw new \Exception("Invalid [regex] pattern.", 1);
  foreach ($flds as $fld) {
    $fld_split = \explode("-;", $fld);
    @ list($fld_name, $fld_type, $fld_count, $vldn_opt) = $fld_split;
    if (
      empty($fld_name)
      || empty($fld_type)
      || empty($fld_count)
    ) throw new \Exception("Invalid [regex] pattern: array index [0,1,2] cannot be empty.", 1);
    $fld_prop = [
      "type" => $fld_type,
      "score" => (int)$fld_count
    ];
    $return["score"] += $fld_count;
    if (!empty($vldn_opt)) {
      @ $fld_prop[\explode("-:",$vldn_opt)[0]] = \explode("-,",\explode("-:",$vldn_opt)[1]);
    }
    $return["fields"][$fld_name] = $fld_prop;
  }
  return $return;
}
function data_user_score (string $table, string $user, string $search_key = "") {
  global $database;
  if (\count(\explode(".",$table)) < 2) throw new \Exception("Invalid table. Value should be [database].[table].[primary_key](optional)", 1);
  @ list($db_name, $table_name, $pkey) = \explode(".",$table);
  $db_name =  $database->escapeValue($db_name);
  $table_name =  $database->escapeValue($table_name);
  $user = $database->escapeValue($user);
  $score = data_get_score("{$db_name}.{$table_name}");
  $search_key = !empty($search_key) ? $search_key : (
    !empty($score["search_key"]) ? $score["search_key"] : ""
  );
  $search_key =  $database->escapeValue($search_key);
  $pkey = !empty($pkey) ? $pkey : (
    !empty($score["pkey"]) ? $score["pkey"] : ""
  );
  $pkey =  $database->escapeValue($pkey);
  $ret_score = 0;
  if ($uscore = (new MultiForm($db_name, $table_name, $pkey, $database))->findBySql("SELECT * FROM :db:.:tbl: WHERE `{$search_key}` = '{$user}' LIMIT 1")) {
    $valid = new \TymFrontiers\Validator();
    foreach($uscore[0] as $prop => $val) {
      if (\array_key_exists($prop, $score["fields"])) {
        $fld = $score["fields"][$prop];
        $opt = [
          $prop,
          $fld["type"]
        ];
        if (!empty($fld["value_length"]) && \is_array($fld["value_length"]) && \count($fld["value_length"]) > 1) {
          $opt[] = $fld["value_length"][0];
          $opt[] = $fld["value_length"][1];
        }
        if ($fld["type"] == "option" && \is_array($fld["values"]) ) {
          $opt[] = $fld["values"];
        }
        if ( @ $valid->validate($val, $opt)) {
          $ret_score += (int)$fld["score"];
        }
      }
    }
  }
  return [
    "table" => $score,
    "user" => $ret_score
  ];
}
function data_check_score (string $table, string $user, string $search_key = "user") {
  $check = data_user_score($table, $user, $search_key);
  return $check["table"]["score"] == $check["user"];
}
function data_require_score (array $tables, string $user, string $search_key = "user", bool $dump = true) {
  $errors = [];
  global $database;
  foreach ($tables as $table) {
    try {
      $check = data_user_score($table, $user, $search_key);
      if ($check["table"]["score"] !== $check["user"]) {
        $msg = $check["table"]["description"];
          $msg .= "\r\n <a class='bold'";
          $msg .= (bool)$check["table"]["is_popup"]
            ? " href=\"#\" onclick=\"sos.faderBox.url('{$check["table"]["set_path"]}',{callback:'{$check["table"]["callback"]}', '{$check["table"]["search_key"]}':'{$user}'}, {exitBtn : false})\""
            : " href=\"{$check["table"]["set_path"]}\"";
          $msg .= "> <i class=\"fas fa-edit\"></i> {$check["table"]["btn_text"]}</a>";
        $errors[$table] = [
          "subject" => $check["table"]["title"],
          "message" => $msg
        ];
      }
    } catch (\Exception $e) {
      $errors[$table] = [
        "subject" => "Failed to get score task/data score.",
        "message" => $e->getMessage()
      ];
    }
  }
  if (empty($errors)) {
    return true;
  } else if(!$dump) {
    return false;
  } else {
    $ret = "<h3>Mandatory task(s) must be completed before you can proceed with current request.</h3> ";
    $ret .= "<ol type='I'>";
    foreach ($errors as $tbl=>$detail) {
      $ret .= "<li class=\"paddn -pall -p10\"> <span class=\"bold\">{$detail["subject"]}</span> <br> ". \nl2br($detail["message"])."</li>";
    }
    $ret .= "</ol>";
    throw new \Exception($ret, 1);
  }
}
function session_check_rank (int $rank, bool $strict = false) {
  global $session;
  if ($session->isLoggedIn() && (($strict && $session->access_rank == $rank) || (!$strict && $session->access_rank >= $rank))) {
    return true;
  }
  return false;
}

function generate_code (string $prefix, string $type, int $len, $class = false, string $field = "", bool $log = false) {
  global $database;
  $code = $prefix . Data::uniqueRand("", ($len - \strlen($prefix)), $type, false);
  if ($class && \is_object($class) && \method_exists($class, "findBySql")) {
    if ($database instanceof MySQLDatabase) $field = $database->escapeValue($field);
    $used = $class->findBySql("SELECT `{$field}` FROM :db:.:tbl: WHERE `{$field}` = '{$code}' LIMIT 1");
    if ($log && $used) {
      $log_dir = get_constant("PRJ_ROOT") . "/.system/logs/ucode-duplicate";
      if (!\file_exists($log_dir)) {
        \mkdir($log_dir, 0777, true);
      }
      $wr_log_file = $log_dir . "/" . \date("Y-m") ."-requests.log";
      $wr_write = \date(BetaTym::MYSQL_DATETIME_STRING, \time());
      $wr_write .= " | {$code}";
      \file_put_contents($wr_log_file, $wr_write . PHP_EOL, FILE_APPEND);
      return false;
    }
  }
  return $used
    ? generate_code($prefix, $type, $len, $class, $field, $log)
    : $code;
}

function code_split (string $code, string $sep = "-") {
  if ($prfx = \substr($code, 0, 3)) {
    return "{$prfx}{$sep}" . Data::charSplit(\str_replace($prfx,"",$code), 4, $sep);
  }
  return null;
}

function setting_variant (string $pattern){
  $output = [
    "optiontype" => "", // "radio", "checkbox"
    "minval" => 0,
    "maxval" => 0,
    "minlen" => 0,
    "maxlen" => 0,
    "options" => [],
    "pattern" => ""
  ];
  $regex = \explode("-;", $pattern);
  if ( @ \preg_match($pattern, "") !== false ) {
    $output['pattern'] = $pattern;
  } else if (!empty($regex)) {
    foreach ($regex as $var) {
      $key_val = \explode("-:", $var);
      if (\count($key_val) !== 2) return false;
      list($key, $val) = $key_val;
      if ($key == 'options') {
        foreach (\explode("-,",$val) as $opt) {
          $output["options"][] = $opt;
        }
      } else {
        if (\array_key_exists($key, $output)) $output[$key] = $val;
      }
    }
  } else {
    return false;
  }
  return $output;
}
function setting_option (string $name, $conn = false): array | null {
  global $database;
  if (!$conn || !$conn instanceof MySQLDatabase || $conn->getServer() !== get_dbserver(get_constant("PRJ_SERVER_NAME"))) $conn = query_conn(get_constant("PRJ_SERVER_NAME"), $database);

  // $conn = ($database && $database instanceof MySQLDatabase && $database->getServer() == get_dbserver(get_constant("PRJ_SERVER_NAME"))) ? $database : query_conn(get_constant("PRJ_SERVER_NAME"));
  if ($found = (new MultiForm(get_database(get_constant("PRJ_SERVER_NAME"), "data"), "setting_options", "id", $conn))->findBySql("SELECT * FROM :db:.:tbl: WHERE `name` = '{$conn->escapeValue($name)}' AND `enabled` = TRUE LIMIT 1")) {
      $type_arr = (new \TymFrontiers\Validator)->validate_type;
    return [
      "encrypt" => (bool)$found[0]->encrypt,
      "name" => $found[0]->name,
      "multi_val" => (bool)$found[0]->multi_val,
      "type" => $found[0]->type,
      "type_title" => $type_arr[$found[0]->type],
      "variant" => $found[0]->variant,
      "title" => $found[0]->title,
      "description" => $found[0]->description
    ];
  }
  return null;
}
function setting_get_value (string $user, string $key, string $domain = "", $conn = false) {
  if (empty($domain)) $domain = get_constant("PRJ_BASE_DOMAIN");
  if (!$server_name = domain_server($domain)) throw new \Exception("Domain: [{$domain}] is not associated with any known server.", 1);
  if (!$db_name = get_database($server_name, "base")) throw new \Exception("Database not found for domain [{$domain}] settings.", 1);

  if (!$conn || !$conn instanceof MySQLDatabase || $conn->getServer() !== get_dbserver($server_name)) $conn = query_conn($server_name);
  $user = $conn->escapeValue("{$domain}.{$user}");

  $found = (new MultiForm($db_name, "settings", "id", $conn))
    ->findBySql("SELECT sval FROM :db:.:tbl: WHERE user='{$user}' AND skey='{$conn->escapeValue($key)}' LIMIT 1");
return $found ? $found[0]->sval : null;
}
function setting_set_value (string $user, string $key, $value, string $domain = "", $conn = false) {
  if (empty($domain)) $domain = get_constant("PRJ_BASE_DOMAIN");
  if (!$server_name = domain_server($domain)) throw new \Exception("Domain: [{$domain}] is not associated with any known server.", 1);
  if (!$db_name = get_database($server_name, "base")) throw new \Exception("Database not found for domain [{$domain}] settings.", 1);
  if (!$conn || !$conn instanceof MySQLDatabase || $conn->getServer() !== get_dbserver($server_name)) $conn = query_conn($server_name);
  $option = setting_option($key);
  if (!$option) throw new \Exception("Setting property not found \r\n", 1);
  
  $key = $conn->escapeValue($key);
  $is_new = true;
  $find_user = "{$domain}.{$user}";
  if (!(bool)$option["multi_val"] && $set = (new MultiForm($db_name, "settings", "id", $conn))->findBySql("SELECT * FROM :db:.:tbl: WHERE `user` = '{$find_user}' AND skey='{$key}' LIMIT 1")) {
    $set = $set[0];
    $is_new = false;
  } else {
    $set = new MultiForm($db_name, "settings", "id", $conn);
  }
  // validate [value] presented
  // get expexted value
  $rqp = [];
  $variant = empty($option['variant']) ? false : setting_variant($option['variant']);
  $filt_arr = ["value", $option['type']];
  if ($option["type"] == "boolean") {
    $value = (bool)$value ? 1 : 0;
  } else {
    if ($option['type'] == "pattern") {
      if (empty($variant["pattern"])) {
        throw new \Exception("No pre-set [pattern], contact Developer", 1);
      }
      $filt_arr[2] = $variant["pattern"];
      $rqp["value"] = $filt_arr;
    } else if (\in_array($option['type'], ["username","text","html","markdown","mixed","script","date","time","datetime","int","float"])) {
      $filt_arr[2] = !empty($variant["minval"]) ? (int)$variant["minval"] : (!empty($variant["minlen"]) ? (int)$variant["minlen"] : 0);
      $filt_arr[3] = !empty($variant["maxval"]) ? (int)$variant["maxval"] : (!empty($variant["maxlen"]) ? (int)$variant["maxlen"] : 0);
    } else if ($option['type'] == "option" && !empty($variant["optiontype"]) && $variant["optiontype"] == "checkbox") {
      $filt_arr[1] = "text";
      $filt_arr[2] = 3;
      $filt_arr[3] = 256;
    } else if ($option['type'] == "option" && !empty($variant["optiontype"]) && $variant["optiontype"] == "radio") {
      if (empty($variant["options"])) {
        throw new \Exception("No pre-set options, contact Developer", 1);
      }
      $filt_arr[2] = $variant["options"];
    } else {
      throw new \Exception("option [type] unknown", 1);
    }
    $rqp["value"] = $filt_arr;  
    $gen = new \TymFrontiers\Generic;
    $params = $gen->requestParam($rqp,["value" => $value], ["value"]);
    if (!$params || !empty($gen->errors)) {
      $errors = (new InstanceError($gen,true))->get("requestParam",true);
      $errors = \implode("\r\n",$errors)."GGG";
      throw new \Exception($errors, 1);
    }
  }
  if ((bool)$option['encrypt']) {
    $data_obj = new \TymFrontiers\Data;
    $enckey = encKey($server_name);
    $value = $data_obj->encodeEncrypt($value, $enckey);
  }
  $value = $conn->escapeValue($value);
  if (!$is_new) {
    // run update
    $set->sval = $value;
  } else {
    $set->user = "{$domain}.{$user}";
    $set->skey = $key;
    $set->sval = $value;
  }
  if ($set->save()) {
    return true;
  }
  $set->mergeErrors();
  $errors = (new InstanceError($set,true))->get("",true);
  $errors = \implode("\r\n",$errors);

  throw new \Exception($errors, 1);
}
function setting_set_file_default(string $user, string $set_key, int $file_id, bool $set_multiple = false, $domain = "", $conn = false) {
  global $database;
  if (empty($domain)) $domain = get_constant("PRJ_BASE_DOMAIN");
  if (!$server_name = domain_server($domain)) throw new \Exception("Domain: [{$domain}] is not associated with any known server.", 1);
  if (!$db_name = get_database($server_name, "file")) throw new \Exception("Database not found for domain [{$domain}] settings.", 1);
  if (!$conn || !$conn instanceof MySQLDatabase || $conn->getServer() !== get_dbserver($server_name)) $conn = query_conn($server_name, $database);

  $user = $conn->escapeValue($user);
  $set_key = $conn->escapeValue($set_key);
  if ((new MultiForm($db_name, "file_default","id", $conn))->findBySql("SELECT * FROM :db:.:tbl: WHERE `user`='{$user}' AND `set_key` = '{$set_key}' AND `file_id` = {$file_id} LIMIT 1")) {
    // already set
    return true;
  }
  if($exists = (new MultiForm($db_name, "file_default", "id", $conn))->findBySql("SELECT * FROM :db:.:tbl: WHERE `user`='{$user}' AND `set_key`='{$set_key}'")) {
    // $exists = $exists[0];
  }
  if (!$set_multiple && \count($exists) > 1) { // delete if previously set
    $file_db = $db_name;
    $conn->query("DELETE FROM `{$file_db}`.file_default WHERE `user`='{$user}' AND `set_key`='{$set_key}'");
    // create new one
    $exists = false;
  }
  $file_db = $db_name;
  if ($exists) {
    // run update
    if (!$conn->query("UPDATE `{$file_db}`.`file_default` SET `file_id` = $file_id WHERE `user` = '{$user}' AND `set_key`='{$set_key}' LIMIT 1")) {
      throw new \Exception("Failed to update file-default setting.", 1);
    }
  } else {
    // create new record
    if (!$conn->query("INSERT INTO `{$file_db}`.`file_default` (`user`, `set_key`, `file_id`) VALUES ('{$user}', '{$set_key}', {$file_id})")) {
      throw new \Exception("Failed to create file-default setting.", 1);
    }
  }
  return true;
}
function setting_unset_file_default (int $fid, $domain = "", $conn = false) {
  global $database;
  if (empty($domain)) $domain = get_constant("PRJ_BASE_DOMAIN");
  if (!$server_name = domain_server($domain)) throw new \Exception("Domain: [{$domain}] is not associated with any known server.", 1);
  if (!$db_name = get_database($server_name, "file")) throw new \Exception("Database not found for domain [{$domain}] settings.", 1);
  if (!$conn || !$conn instanceof MySQLDatabase || $conn->getServer() !== get_dbserver($server_name)) $conn = query_conn($server_name, $database);

  if ((new MultiForm($db_name, "file_default", "id", $conn))->findBySql("SELECT * FROM :db:.:tbl: WHERE `file_id` = {$fid} LIMIT 1") && !$conn->query("DELETE FROM `{$db_name}`.`file_default` WHERE `file_id` = {$fid}")) {
    throw new \Exception("Failed to delete default settings", 1);
  }
  return true;
}
function setting_get_file_default(string $user, string $set_key, $domain = "", $file_server = "", $conn = false) {
  global $database;
  if (empty($domain)) $domain = get_constant("PRJ_BASE_DOMAIN");
  if (!$server_name = domain_server($domain)) throw new \Exception("Domain: [{$domain}] is not associated with any known server.", 1);
  if (!$db_name = get_database($server_name, "file")) throw new \Exception("Database not found for domain [{$domain}] settings.", 1);
  if (!$conn || !$conn instanceof MySQLDatabase || $conn->getServer() !== get_dbserver($server_name)) $conn = query_conn($server_name, $database);

  $file_server = !empty($file_server) ? $file_server : get_constant("PRJ_FILE_SERVER");
  $file_server = $conn->escapeValue($file_server);
  $user = $conn->escapeValue($user);
  $set_key = $conn->escapeValue($set_key);
  $query = "SELECT fd.id, fd.user, fd.set_key,
                   fi.id AS file_id, fi.type_group AS file_type, fi._type AS file_mime, fi.caption AS file_caption, fi._size AS file_size,
                   CONCAT('{$file_server}/file/', fi._name) AS url
            FROM :db:.:tbl: AS fd
            LEFT JOIN `{$db_name}`.`file_meta` AS fi ON fi.id = fd.file_id
            WHERE fd.user = '{$user}'
            AND fd.set_key = '{$set_key}'";
  $return = (new MultiForm($db_name, "file_default", "id", $conn))->findBySql($query);

  return $return;
}
function setting_check_file_default(int $file_id, $domain = "", $conn = false) {
  global $database;
  if (empty($domain)) $domain = get_constant("PRJ_BASE_DOMAIN");
  if (!$server_name = domain_server($domain)) throw new \Exception("Domain: [{$domain}] is not associated with any known server.", 1);
  if (!$db_name = get_database($server_name, "file")) throw new \Exception("Database not found for domain [{$domain}] settings.", 1);
  if (!$conn || !$conn instanceof MySQLDatabase || $conn->getServer() !== get_dbserver($server_name)) $conn = query_conn($server_name, $database);

  if ($set = (new MultiForm($db_name, "file_default", "id", $conn))->findBySql("SELECT * FROM :db:.:tbl: WHERE `file_id` = {$file_id}")) {
    $set_r = [];
    foreach ($set as $st) {
      $set_r[] = [
        "id" => $st->id,
        "user" => $st->user,
        "key" => $st->set_key,
      ];
    }
    return $set_r;
  }
  
  return [];
}
function destroy_cookie (string $cname) {
  global $_COOKIE;
  if (isset($_COOKIE[$cname])) {
    unset($_COOKIE[$cname]);
    \setcookie($cname, 0, -1, '/');
    return true;
  }
  return false;
}

function email_mask ( string $email, string $mask_char="*", int $percent=50 ){
  list( $user, $domain ) = \preg_split("/@/", $email );
  $len = \strlen( $user );
  $mask_count = \floor( $len * $percent /100 );
  $offset = \floor( ( $len - $mask_count ) / 2 );
  $masked = \substr( $user, 0, $offset )
    . \str_repeat( $mask_char, $mask_count )
    . \substr( $user, $mask_count+$offset );

  return( $masked.'@'.$domain );
}

function phone_mask (string $number){
  $mask_number =  \str_repeat("*", \strlen($number)-4) . \substr($number, -4);
  return $mask_number;
}
function file_set(string $mime){
  global $file_upload_groups;
  $return = "unknown";
  foreach($file_upload_groups as $type=>$arr){
    if( \in_array($mime,$arr) ){
      $return = $type;
      break;
    }
  }
  return $return;
}
function auth_errors (API\Authentication $auth, string $message, string $errname, bool $override=true) {
  $auth_errors = (new InstanceError ($auth,$override))->get($errname,true);
  $out_errors = [
  "Message" => $message
  ];
  $i=0;
  if (!empty($auth_errors)) {
    foreach ($auth_errors as $err) {
      $out_errors["Error-{$i}"] = $err;
      $i++;
    }
  }
  $out_errors["Status"] = "1" . (\count($out_errors) - 1);
  return $out_errors;
}
function setup_page(string $page_name, string $page_group = "base", bool $show_dnav = true, int $dnav_ini_top_pos=0, string $dnav_stick_on='#page-head', bool $cartbot = false, string $cartbotCb = "", string $dnav_clear_elem = '#main-content', string $dnav_pos = "affix"){
  $set = "<input ";
  $set .=   "type='hidden' ";
  $set .=   "data-setup='page' ";
  $set .=   ("data-show-nav = '" . ($show_dnav ? 1 : 0) ."' ");
  $set .=   "data-group = '{$page_group}' ";
  $set .=   "data-name = '{$page_name}' ";
  $set .= "> ";
  $set .= "<input ";
  $set .=   "type='hidden' ";
  $set .=   "data-setup='dnav' ";
  $set .=   "data-clear-elem='{$dnav_clear_elem}' ";
  $set .=   "data-ini-top-pos={$dnav_ini_top_pos} ";
  $set .=   "data-pos='{$dnav_pos}' ";
  $set .=   "data-cart-bot='". ($cartbot ? 1 : 0)."' ";
  $set .=   "data-cart-bot-click='{$cartbotCb}' ";
  $set .=   "data-stick-on='{$dnav_stick_on}' ";
  $set .= ">";
  echo $set;
}
function file_size_unit($bytes) {
  if ($bytes >= 1073741824) {
    $bytes = number_format($bytes / 1073741824, 2) . ' GB';
  } elseif ($bytes >= 1048576) {
    $bytes = number_format($bytes / 1048576, 2) . ' MB';
  } elseif ($bytes >= 1024) {
    $bytes = number_format($bytes / 1024, 2) . ' KB';
  } elseif ($bytes > 1) {
    $bytes = $bytes . ' bytes';
  } elseif ($bytes == 1) {
    $bytes = $bytes . ' byte';
  } else {
    $bytes = '0 bytes';
  }
  return $bytes;
}
function require_login (bool $redirect = true, string $rd_path = "/user/login") {
  global $session;
  if (!$session->isLoggedIn() ) {
    if ($redirect) {
      HTTP\Header::redirect(Generic::setGet($rd_path,['rdt'=>THIS_PAGE]));
    } else {
      HTTP\Header::unauthorized(false,'',["Message"=>"Login is required for requested resource!"]);
    }
  }
}
