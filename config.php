<?php
set_time_limit(600000);
ini_set("memory_limit", "256M");

define('TEMP_DIR', dirname(__FILE__) . '/xml/');
define('RESULT_DIR', dirname(__FILE__) . '/result/');
define('RULES_DIRECTORY', __DIR__ . '/rules/');
define('DB_PATH', __DIR__ . '/db/links.sqlite');
