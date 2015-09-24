<?php
require_once 'config.php';

if (isset($_GET['file_name']) && !empty($_GET['file_name'])) {
    $file = XML_DIRECTORY_PATH . '/' . $_GET['file_name'];
    // echo $file;
    
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($file));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
?>