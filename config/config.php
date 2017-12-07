<?php

define('DEBUG', false);
define('LEVEL', 3);
define('ARCHIVE', false);

define('PS_SHOP_PATH', 'http://localhost');
define('PATH', 'FTP/');

define('FILE_LOCK', 'verrou');
define('EXEC_DIFF_TIME', 3600);
define('FILE_RAPPROCHEMENT', 'rapprochement.txt');
define('PATH_ARCHIVE', 'archive/');


define('PS_WS_AUTH_KEY', 'TMAY3ILMIEYPE3TSF6L8Z6UVMBZ1T5PU');



/* FTP */
$ftp 			= array (
					"serveur" 		=> 'diams.dev.oneshot.nc',
					"port"			=> 10021,
					"username"		=> "ftp-dev-diams-odeis",
					"password" 		=> "493QAk*T9EA1"
	);

/* TOUS LES FICHIERS UTILES */
$files 			= array( 
					"articles" 		=> "",
					"attributs" 	=> "",
					"code_attribut" => "",
					"dispo" 		=> "",
					"famweb" 		=> "",
					"markweb" 		=> "",
					"photos" 		=> "",
				);
