<?php

// main function witch is called recursivly
function ftp_sync($dir, $conn_id) {
	if ($dir !== '.') {
		if (ftp_chdir($conn_id, $dir) === FALSE) {
			echo 'Change dir failed: ' . $dir . PHP_EOL;
			return;
		}
		if (!(is_dir($dir))) {
			mkdir($dir);
		}
		chdir($dir);
	}
	$contents = ftp_nlist($conn_id, '.');
	foreach ($contents as $file) {
		if ($file == '.' || $file == '..') {
			continue;
		}
		if (@ftp_chdir($conn_id, $file)) {
			ftp_chdir($conn_id, "..");
			ftp_sync($file, $conn_id);
		} else {
			ftp_get($conn_id, $file, $file, FTP_BINARY);
		}
	}
	ftp_chdir($conn_id, '..');
	chdir('..');
}