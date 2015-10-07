<?php
//start timer
$timer = time();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

//Process params passed to application
$argumentsCount = count($argv);

if ($argumentsCount < 2) {
    exit('This app requires at least 2 arguments');
}
$url = $argv[1];

if (filter_var($url, FILTER_VALIDATE_URL) === false) {
    exit("$url is not a valid URL");
}
$parsedUrl = parse_url($url);

if (!isset($parsedUrl['host'])) {
    exit("$url is not a valid URL");
}
$host = trim($parsedUrl['host']);
$resultFileName = '';
$additionalParam = '';
$type = 'a';

if (isset($argv[2]) && $argv[2] == '--img') {
    $additionalParam = '/img';
    $isImageSitemap = true;
} else {
    $additionalParam = '';
    $isImageSitemap = false;
}

if ($host == 'motocms.com' || $host == 'www.motocms.com') {
    $host = 'motocms.com';

    if (isset($parsedUrl['path']) && $parsedUrl['path'] != '/') {
        $path = $parsedUrl['path'];
    } else {
        $path = '';
    }
    $rulePath = RULES_DIRECTORY . $host .  $path . $additionalParam . '/filter.php';
    $resultFileName = str_replace('/', '.', $host . $path . $additionalParam);

    if (!file_exists($rulePath)) {
        exit('Wrong rule file path');
    }
    $customFilter = include $rulePath;
} else {
    exit("Works only with motocms.com domain");
}
$basicFilter = include RULES_DIRECTORY . 'base/filter.php';

//Check if custom settings specified and then merge them
if (isset($customFilter)) {
    $resultFilter = array_merge_recursive($customFilter, $basicFilter);
} else {
    $resultFilter = $basicFilter;
}
$linkProcessor = new \libs\LinkProcessor($url, 'http://www.motocms.com/');
$linkProcessor->setFilter($resultFilter);
$linkProcessor->generateSitemap(3, $isImageSitemap);
$linkProcessor->save(RESULT_DIR . $resultFileName . '.xml');

echo (time() - $timer) . ' seconds';
