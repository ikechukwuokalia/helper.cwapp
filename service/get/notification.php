<?php
namespace Catali;
require_once "../../.appinit.php";
use \TymFrontiers\HTTP,
    \TymFrontiers\Generic,
    \TymFrontiers\Data,
    \TymFrontiers\InstanceError;

\header("Content-Type: application/json");
if (!$session->isLoggedIn()) HTTP\Header::unauthorized(false, "", ["Message"=> "Login required", "Error"=>"1.1"]);
$post = !empty($_POST) ? $_POST : $_GET;
$gen = new Generic;
$params = $gen->requestParam([
  "id" => ["id","int"],
  "ws" => ["ws","pattern", "/^289([0-9]{8,11})$/"],
  "search" => ["search","text",3,25],
  "unread" => ["unread","boolean"],
  "page" => ["page","int"],
  "limit" => ["limit","int"]
], $post, []);
if (!$params || !empty($gen->errors)) {
  $errors = (new InstanceError ($gen, false))->get("requestParam",true);
  echo \json_encode([
    "status" => "3." . \count($errors),
    "errors" => $errors,
    "message" => "Request halted"
  ]);
  exit;
}
$params["user"] = $session->name;
$count = 0;
$notice = new Notice();
$notice->current_page = $page = (int)$params['page'] > 0 ? (int)$params['page'] : 1;

$data = new Data;
$ent_server = !empty($_COOKIE['wssrv']) ? $data->decodeDecrypt($_COOKIE['wssrv']) : false;
if (!$ent_server) $ent_server = get_constant("PRJ_SERVER_NAME");
$usr_code = code_storage($params["user"], get_constant("PRJ_SERVER_NAME"));
$ws_code = empty($params['ws']) ? false : code_storage($params["ws"], $ent_server);
$ws_access = !$ws_code ? false : ws_access($params['ws'], $params['user']);
$cnd = " WHERE ntc.`user` = '{$params['user']}' ";
if ($ws_code) {
  $cnd .= "OR ntc.`user` = '{$params['ws']}'";
}
$query = "SELECT ntc.id, ntc.is_read, ntc.`user`, ntc.`heading`, ntc.message, ntc.`priority`, ntc.`path`, 
                ntc.`action`, ntc._created, ntc._updated,
                (
                  SELECT COUNT(*)
                  FROM :db:.:tbl: AS ntc
                  {$cnd}
                  AND ntc.is_read = FALSE
                ) AS 'unread',
                (
                  SELECT COUNT(*)
                  FROM :db:.:tbl: AS ntc
                  {$cnd}
                ) AS 'records' ";
if ($usr_code) {
  $query .= ", (
    SELECT CONCAT(`name`, ' ', `surname`)
    FROM `{$usr_code[0]}`.`{$usr_code[1]}`
    WHERE `{$usr_code[2]}` = ntc.`user`
    LIMIT 1
  ) AS user_name ";
} if ($ws_code && $ws_access) {
  $query .= ", (
    SELECT `name`
    FROM `{$ws_code[0]}`.`{$ws_code[1]}`
    WHERE `{$ws_code[2]}` = ntc.`user`
    LIMIT 1
  ) AS ws_name ";
}
$query .= "FROM :db:.:tbl: AS ntc ";


$count = $notice->findBySql("SELECT COUNT(*) AS cnt FROM :db:.:tbl: AS ntc {$cnd} ");
// echo $db->last_query;
$count = $notice->total_count = $count ? $count[0]->cnt : 0;

$notice->per_page = $limit = !empty($params['id']) ? 1 : (
    (int)$params['limit'] > 0 ? (int)$params['limit'] : 25
  );
$query .= $cnd;

$query .= " ORDER BY ntc.is_read ASC, ntc.`priority` ASC ";
$query .= " LIMIT {$notice->per_page} ";
$query .= " OFFSET {$notice->offset()}";

$found = $notice->findBySql($query);
if( !$found ){
  die( \json_encode([
    "message" => "No result found.",
    "errors" => [],
    "status" => "0.2"
    ]) );
}
$tym = new \TymFrontiers\BetaTym;
$result = [
  'records' => $found[0]->records,
  'unread' => (int)$found[0]->unread,
  'page'  => $notice->current_page,
  'pages' => $notice->totalPages(),
  'limit' => $limit,
  'previousPage' => $notice->hasPreviousPage() ? $notice->previousPage() : false,
  'nextPage' => $notice->hasNextPage() ? $notice->nextPage() : false
];
$result["message"] = "Request completed.";
$result["errors"] = [];
$result["status"] = "0.0";
foreach ($found as $ntc) {
  $result["data"][] = [
    "id" => (int)$ntc->id,
    "isRead" => (bool)$ntc->is_read,
    "priority" => (int)$ntc->priority,
    "priorityTitle" => @ $priority_titles[$ntc->priority],
    "heading" => $ntc->heading,
    "path" => Generic::setGet($ntc->path, ["id" => $ntc->id]),
    "pathAction" => $ntc->action,
    "user" => $ntc->user,
    "userName" => !empty($ntc->user_name) ? $ntc->user_name : (!empty($ntc->ws_name) ? $ntc->ws_name : ""),
    "created" => "{$tym->monthDay($ntc->created())} {$tym->HMS($ntc->created())}",
    "updated" => "{$tym->monthDay($ntc->updated())} {$tym->HMS($ntc->updated())}"
  ];
}
echo \json_encode($result);
exit;