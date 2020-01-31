<?php

header("Content-Type:text/html; charset=utf-8");

echo '<html><body>';

$log_dir = "./scripts/logs/"; // set where logs be put

if (isset($_GET['f']) && !empty($_GET['f'])) {
	echo "<a href='./get_log.php'>Back</a><br><hr><br>";
	$logfile = $_GET['f'];
	$log = file_get_contents($log_dir . $logfile);
	//$log = str_replace("\n", '<br>', $log);
	echo '<pre>';
	echo htmlspecialchars($log);
	echo '</pre>';
} else {
	$dir = scandir($log_dir);
	foreach ($dir as $name) {
		if (preg_match('/.log$/', $name)) {
			echo '[' . date("Y-m-d H:i:s", filemtime($log_dir . $name)) . '] ';
			echo "<a href='./get_log.php?f=$name'>$name</a><br>";
		}
	}
}
echo '</body></html>';
