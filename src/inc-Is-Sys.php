<?php
namespace Catali;
use \TymFrontiers\MultiForm;

$is_sys = !empty($http_auth) && (bool)$http_auth && $auth instanceof \TymFrontiers\API\Authentication
          && (new MultiForm(get_database(get_constant("PRJ_SERVER_NAME"), "developer"), "users", "code", $conn))
            ->findBySql("SELECT `code` 
                        FROM :db:.:tbl: 
                        WHERE is_system = TRUE 
                        AND `code` = (
                          SELECT `user`
                          FROM :db:.apps
                          WHERE `name` = '{$auth->appName()}'
                          LIMIT 1
                        ) 
                        LIMIT 1");
