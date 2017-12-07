<?php

define('DEBUG', false);
define('LEVEL', 3);
define('ARCHIVE', true);

define('PS_SHOP_PATH', 'http://localhost');
define('PATH', __DIR__ . '/../FTP/');

define('FILE_LOCK', __DIR__ . '/../lock'); // NUL = No lock
define('EXEC_DIFF_TIME', 3600);
define('FILE_RAPPROCHEMENT', __DIR__ . '/../relation.db');
define('PATH_ARCHIVE', __DIR__ . '/../archives/');


define('PS_WS_AUTH_KEY', 'TMAY3ILMIEYPE3TSF6L8Z6UVMBZ1T5PU');

/* FTP */
$ftp = array (
	"serveur" 		=> 'diams.dev.oneshot.nc',
	"port"			=> 10021,
	"username"		=> "",
	"password" 		=> ""
);


