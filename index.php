<?php
require_once __DIR__ . '/vendor/autoload.php';

include 'views/sitemap-form.php';

if (isset($_POST['generate_btn'])) {
    if (isset($_POST['url']) && !empty($_POST['url'])) {
        require_once('config.php');

        if (!file_exists(XML_DIRECTORY_PATH) || !is_dir(XML_DIRECTORY_PATH)) {
            mkdir(XML_DIRECTORY_PATH);
        }
        $randName = \libs\RandomHelper::generateString() . '.xml';
        $fileName = XML_DIRECTORY_PATH . $randName;
        $linkProcessor = new \libs\LinkProcessor($fileName);

        if ($linkProcessor->generateSitemap($_POST['url'], 10)) {
            echo "<a href=\"download.php?file_name=$randName\">Download</a>";
        } else {
            echo 'Cannot generate sitemap for ' . $_POST['url'];
        }
    } else {
        echo '<br/>Url is empty<br/>';
    }
}

