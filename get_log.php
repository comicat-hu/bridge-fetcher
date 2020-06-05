<?php

require __DIR__ . './vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::create(__DIR__ . '/scripts')->load();

header("Content-Type:text/html; charset=utf-8");

echo '<html><body>';

$scriptName = basename(__FILE__);

$logDir = __DIR__ . '/scripts/'. getEnv('LOG_DIR');
if (!is_dir($logDir)) {
    die("'{$logDir}' not exists.");
}

if (isset($_GET['f']) && !empty($_GET['f'])) {
    echo "<a href='./{$scriptName}'>Back</a><br><hr>";
    $logfile = $logDir . $_GET['f'];
    if (!file_exists($logfile)) {
        echo 'File not exists.';
    } else {
        $log = file_get_contents($logfile);
        //$log = str_replace("\n", '<br>', $log);
        echo '<pre>';
        echo htmlspecialchars($log);
        echo '</pre>';
    }
} else {
    $dir = scandir($logDir);
    foreach ($dir as $name) {
        if (preg_match('/.log$/', $name)) {
            echo '[' . date("Y-m-d H:i:s", filemtime($logDir . $name)) . '] ';
            echo "<a href='./{$scriptName}?f=$name'>$name</a><br>";
        }
    }
}
echo '</body></html>';
