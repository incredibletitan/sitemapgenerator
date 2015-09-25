<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

//Process params passed to application
$argumentsCount = count($argv);

if ($argumentsCount < 2) {
    exit('This app required at least 2 arguments');
}
$url = $argv[1];

if (filter_var($url, FILTER_VALIDATE_URL) === false) {
    exit("$url is not a valid URL");
}
$basicFilter = include RULES_DIRECTORY . 'base/filter.php';

if ($argumentsCount > 3 && $argv[2] == '--rule') {
    $rulePath = RULES_DIRECTORY . $argv[3] . '/filter.php';

    if (!file_exists($rulePath)) {
        exit('Wrong rule file path : ' . $rulePath);
    }
    //TODO: make it more secure
    $customFilter = include $rulePath;
}

//Check if custom settings specified and then merge them
if (isset($customFilter)) {
    $resultFilter = array_merge($customFilter, $basicFilter);
} else {
    $resultFilter = $basicFilter;
}

$randName = \libs\RandomHelper::generateString() . '.xml';
$fileName = XML_DIRECTORY_PATH . $randName;
$linkProcessor = new \libs\LinkProcessor($fileName);
$linkProcessor->setFilter($resultFilter);

$linkProcessor->generateSitemap($url, 20);
