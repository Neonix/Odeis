<?php

set_time_limit(0);

define('FIN', '[FIN]');


$i 				= 0;
$time_start 	= microtime(true);


header('Content-Type: text/html; charset=iso-8859-1');

require_once(__DIR__ . '/class/PSWebServiceLibrary.php');
require_once(__DIR__ . '/class/CsvImporter.php');
require_once(__DIR__ . '/config/config.php');




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

/*
	N° colonne          Champ                           Ex
	1                   Code Famille                    B
	2                   Rang de l’article dans famille  1
	3                   Nom du fichier photo            12456.jpg
	4                   Libellé article                 Bague Or et saphir
	5                   Descriptif ligne 2              Poids : 3,4 g
	6                   Descriptif ligne 3              Saphirs ovales
	7                   Descriptif ligne 4
	8   Descriptif ligne 5
	9   Descriptif ligne 6
	10  Prix de vente public € TTC135.00
	11  Prix public € HT  112.65
	12  Montant TVA 22.12
	13  Montant TPH 0.23
	14  Valeur remise € TTC     Remise à appliquer sur le prix de vente public € TTC
	15  Référence WEB unique      54321REBC
	16  Etat "S" si article/réf-Web à supprimer (sinon vide)
	17  Code fournisseur    4 caractères maxi
	18  Réf fournisseur    15 caractères maxi

  	[262] => Array
        (
            [0] => 51
            [1] => 59
            [2] => gba-400-4aer.jpg
            [3] => CASIOGBA-400-4AER
            [4] => MONTRE CASIO G-SHOCK
            [5] => 
            [6] => 
            [7] => 
            [8] => 
            [9] =>   32522.00
            [10] =>   32200.00
            [11] =>     322.00
            [12] =>       0.00
            [13] =>       0.00
            [14] => REA4-004-ABG   4
            [15] => 
            [16] => 4
            [17] => GBA-400-4AER
        )
*/
class article {

}



/*

	N° colonne 		Champ 							Ex
	1 				Code Famille 					BG
	2 				Code fournisseur 				CBER
	3 				Référence fournisseur 			12345
	4 				Référence WEB unique 			54321REBC
	5 				Quantité disponible 			2
	6 				Taille (suivant paramétrage) 	54.5
*/
class dispo {

}

// TELECHARGEMENT DES FICHIERS
//$content = file_get_contents('ftp://'.$ftp["username"].':'.$ftp["password"].'@'.$ftp["serveur"].':'.$ftp["port"]. '/' .$files["articles"]);


//1. Mise à jour des articles
//2. Mise à jour des disponibilités stock
//3. Mise à jour de « Réparations »
//4. Mise à jour du fichier clients



// FICHIER DE PROTECTION POUR EVITER LES MULTIPLES EXECUTIONS
if(is_file(FILE_LOCK) )
{

	$lock = file_get_contents(FILE_LOCK);
	if((int) $lock + EXEC_DIFF_TIME > time())
	{
		$time_stay = ((int) $lock + EXEC_DIFF_TIME) - time();
		die(FILE_LOCK . "Le script est en cours d'execution wait $time_stay secondes \n\r");
	}
	else
		unlink(FILE_LOCK);

}
else
{
	$lock = time();
	file_put_contents(FILE_LOCK, json_encode($lock));
}


// VERIFICATION DES FICHIERS
foreach ($files as $Akey => $value)
{
	if (!file_exists(PATH . 'articles/' .$Akey .'.txt')) {
	    echo "Le fichier $Akey.txt n'existe pas.\n\r";
	      //die('ERROR: files');
	}
}


// VERIFICATION DE LURL API
if(! @get_headers(PS_SHOP_PATH) || @get_headers(PS_SHOP_PATH)[0] == 'HTTP/1.1 404 Not Found') {
    echo PS_SHOP_PATH . " API URL not found.\n\r";
	die();
}


//	FICHIER DE CORRESPONDANCE ID ODEIS ET PRESTASHOP
if(is_file(FILE_RAPPROCHEMENT) )
{
	if($recoveredData = file_get_contents(FILE_RAPPROCHEMENT))
	$correspondance = json_decode($recoveredData, true);
}

//	CREATION DU TABLEAU VIDE SI FIRST TIME
if(!isset($correspondance))
	$correspondance = array();
file_put_contents(FILE_RAPPROCHEMENT, json_encode($correspondance));


// CHARGEMENT DE TOUS LES FICHIERS
foreach ($files as $key => $value)
{

	if (file_exists(PATH . 'articles/' .$key . '.txt')) {
		$file = new CsvImporter(PATH . 'articles/' .$key . '.txt',false);
		$files[$key] = $file->get();
	}

}



// ASSOCIATION ARTICLES ET DISPOS
if($files['articles'])
foreach ($files['articles'] as $Akey => $article)
{
	
	if($article[0] == FIN) // MARQUEUR DE FIN
		break;	

	foreach ($files['dispo'] as $Dkey => $dispo)
	{
		
		if($dispo[0] == FIN) // MARQUEUR DE FIN
			break;

		if($dispo[3] == $article[14]) //Si la Ref web Article à une Dispo
		{
			$files['articles'][$Akey]['dispo'] = $dispo; //Assosciation de la dispo à l'article
			$i++;

			if($files['articles'][$Akey]['dispo'][5] == 'unique')
			{
				//TODO TRAINTEMENT UNIQUE !!
				//var_dump($files['articles'][$Akey]);
				//die('unique');

				if(LEVEL && !isset($read1)) {
					echo "/! PAS DE TRAITEMENT POUR ARTICLE UNIQUE ATM\n\r";
					$read1 = true;
				}
			}
			else
			{


			}


		}
		
	}

}
if(LEVEL)
	echo "".$i." articles associés\n\r";





// CONNECTION RECUPERATION DES SCHEMAS 
try
{
	$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
}
catch (PrestaShopWebserviceException $e)
{
	// Here we are dealing with errors
	$trace = $e->getTrace();
	if ($trace[0]['args'][0] == 404) echo 'Bad ID';
	else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
	else echo 'Other error<br />'.$e->getMessage();
	die();
}





// INSERTION DES ATTRIBUTS
foreach ($files['code_attribut'] as $Ckey => $code_attribut)
{
	if($code_attribut[0] == FIN) // MARQUEUR DE FIN
		break;

	if($code_attribut[0] != "") //On ignore premiere ligne vide ou les lignes vides
	{


		if( empty($correspondance['code_attribut'][$code_attribut[0]]) )
		{
			$xml = add_product_options($code_attribut);
			(int) $id_category_attribut = $xml->id;
			
			$correspondance['code_attribut'][$code_attribut[0]] = $id_category_attribut;
			file_put_contents(FILE_RAPPROCHEMENT, json_encode($correspondance));
		

			foreach ($files['attributs'] as $Akey => $attributs)
			{
				if($attributs[0] == $code_attribut[0])
				{
					
					$xml = add_product_option_values($attributs, $id_category_attribut);
					$correspondance['attributs'][$attributs[2]] = (int) $xml->id;
					file_put_contents(FILE_RAPPROCHEMENT, json_encode($correspondance));
				}
			}
		}

		

	}

}

// @TODO  VERIFICATION DES ATTRIBUTS AJOUTER OU MODIFIER




// INSERTION EN MASSE DES CATEGORIES
if($files['famweb'])
foreach ($files['famweb'] as $Akey => $famweb)
{
	if($famweb[0] == FIN) // MARQUEUR DE FIN
		break;

	if($famweb[0] != "") //On ignore premiere ligne vide ou les lignes vides
	{

		if( empty($correspondance['categories'][$famweb[0]]) )
		{


			//Si le chiffre avant le . du libelle de la Famweb.txt est modulo 100 alors c'est une catégorie -_-"
			//1	100.BAGUES	T -> 100 est100
			if(( (int) (explode('.', $famweb[1], 2)[0]) % 100) == 0) 
			{
				//Si c'est une catégorie
				if(LEVEL > 2)
					echo $famweb[1] . "Famille injectees \n\r";
				
				$id_parent	= 2; //Accueil
				
			}
			else 
			{
				//11	101.Solitaires	T  -> 101 : categorie 1
				//Marche que jusqu'a 9 :s
				$first_chiffre = explode('.', $famweb[1], 2)[0][0];
				$id_parent = (int) $correspondance['categories'][$first_chiffre];

			}

			$category_id = make_categorie($famweb, $id_parent);
			$correspondance['categories'][$famweb[0]] = (int) $category_id;
			file_put_contents(FILE_RAPPROCHEMENT, json_encode($correspondance));

		}
		else
			if(LEVEL)
			{
				if(!isset($category_exist))
					$category_exist=0;
				$category_exist++;
			}
	}
	


	if($famweb[2] != null )
	{
		if(LEVEL && !isset($read2))
		{
			//echo "/! PAS DE TRAITEMENT DATTRIBUS ATM ".$famweb[2]."\n\r";
			$read2 = true;
		}
	}
}

if(LEVEL)
{
	if(isset($category_exist))
		echo $category_exist . " CATEGORIES NON INJECTE CAR PRESENTE\n\r";
	else
		echo "Toutes les categories ont déjà etaient injectees\n\r";
}



$i = 0;
// INSERTION EN MASSE DES ARTICLES ET DISPO ARTICLES
foreach ($files['articles'] as $Akey => $articles)
{

	if($articles[0] == FIN) // MARQUEUR DE FIN
		break;

	if($articles[0] != "") //On ignore premiere ligne vide ou les lignes vides
	{
		if( empty($correspondance['articles'][$articles[14]]) && $articles[15] != 'S' ) //S = DELETE ARTICLE
		{
			$i++;
			

			$product = make_product($articles);
			if(LEVEL > 2) 
				echo  $product->description->language[0][0]  . " add article\n\r";

			$correspondance['articles'][$articles[14]] = (int) $product->id; //Association ID presta 
			file_put_contents(FILE_RAPPROCHEMENT, json_encode($correspondance)); //On enregistre

			add_image($product->id, $articles); //On ajoute l'image associé
			if(LEVEL > 2) 
				echo  $product->reference  . " add  image\n\r";



			if( isset($files['articles'][$Akey]['dispo']) ) 
			{	
				//Famille 1	100.BAGUES	T
				if($files['articles'][$Akey]['dispo'][0][0] == '1')
				{
					foreach ($files['attributs'] as $ATkey => $ATvalue) 
					{
						$articlescomb = $articles;
						$newArticle = array (
							"code" 			=> $product->reference,
							"id_product" 	=> $product->id,
							"price"			=> $product->price,
							"quantity" 		=> $files['articles'][$Akey]['dispo'][4],
							"option_id" 	=> $correspondance['attributs'][$ATvalue[2]],
						);
						$rtnNewArticle = add_combination($newArticle);


						//print_r($ATvalue);
						if(LEVEL > 2) 
							echo  $product->reference  . " add  combination\n\r";
					}


					/* 	Ajout des stocks au article avec attribut */
					if($combination = get_product((int) $product->id))
					{
						//var_dump($combination->associations);

						foreach ($combination->associations->stock_availables->stock_available as $key => $stock_available) {
							//print_r($stock_available->id);

							$stock = set_product_quantity( 
							(int) $files['articles'][$Akey]['dispo'][4],
						 	(int) $product->id, 
						 	(int) $stock_available->id, 
						 	(int) $stock_available->id_product_attribute
						 );
						}
					}

					

				}

			
			}




			if( isset($files['articles'][$Akey]['dispo']) ) //On regarde si on à une dispo 
			{		
				//On ajout les stocks
				$stock = set_product_quantity( 
					(int) $files['articles'][$Akey]['dispo'][4],
				 	(int) $product->id, 
				 	(int) $product->associations->stock_availables->stock_available->id, 
				 	(int) $product->associations->stock_availables->stock_available->id_product_attribute
				 );

				$correspondance['stock'][$articles[14]] = (int) $files['articles'][$Akey]['dispo'][4];
				file_put_contents(FILE_RAPPROCHEMENT, json_encode($correspondance));

			}
			if(LEVEL)
			{	
				echo $files['articles'][$Akey][3] . " 0 dispo \n\r";
			}
			//die();


			
		}
		else
		{	//DELETE D'UN ARTICLE
			if($articles[15] == 'S')
			{
				if(isset($correspondance['articles'][$articles[14]]))
				{

					del_product($correspondance['articles'][$articles[14]]);
					
					unset($correspondance['articles'][$articles[14]]);//Delete dans notre fichier de correspondance
					unset($correspondance['stock'][$articles[14]]);//Delete dans notre fichier de correspondance

					file_put_contents(FILE_RAPPROCHEMENT, json_encode($correspondance)); //On enregistre

				}
				else
					echo "/! ARTICLE A DELETE MAIS 0 CORRESPONDANCE \n\r";
			}

			if(LEVEL)
			{
				if(!isset($article_exist))//Compteur de log
					$article_exist=0;
				$article_exist++;
			}
		}

		//si l'article exist on Verifie les stocks;
	}
}
if(LEVEL)
{
	if(isset($article_exist))
		echo " ".$article_exist." ARTICLES NON INJECTE CAR PRESENTE \n\r";
	else
		echo "Toutes les articles ont déjà etaient injectees\n\r";
}



// Aucun article ajouter on regarde si il ya des dispo
/*
* TODO: Code trop long: voir optimisation
* TOTO: Association correct des noms de variable dispo[?] = ?
*/
if(LEVEL && ($i == 0))
{
		echo "UPDATE DISPO STOCK DETECTE \n\r";
}


$ctn_updatestock = 0;
if($i == 0)
{
//var_dump($files);
	foreach ($files['dispo'] as $Dkey => $dispo)
	{
		
		if($dispo[0] == FIN) // MARQUEUR DE FIN
			break;


		//ON regarde si l'article exist déjà 
		if(!empty($correspondance['articles'][$dispo[3]]))
		{

			$id_product = $correspondance['articles'][$dispo[3]];

			if(!empty($correspondance['stock'][$dispo[3]]))
			{
				if($correspondance['stock'][$dispo[3]] !=  $dispo[4])
				{
					$ctn_updatestock++;
				
					if($product = get_product((int) $id_product))
					{


						//Famille 1	100.BAGUES	T
						if($dispo[0][0] == '1')
						{
							foreach ($files['attributs'] as $ATkey => $ATvalue) 
							{
								$articlescomb = $articles;
								$newArticle = array (
									"code" 			=> $product->reference,
									"id_product" 	=> $product->id,
									"price"			=> $product->price,
									"quantity" 		=> $dispo[4],
									"option_id" 	=> $correspondance['attributs'][$ATvalue[2]],
								);
								$rtnNewArticle = add_combination($newArticle);


								//print_r($ATvalue);
								if(LEVEL > 2) 
									echo  $product->reference  . " add  combination\n\r";
							}


							/* 	Ajout des stocks au article avec attribut */
							if($combination = get_product((int) $product->id))
							{

								foreach ($combination->associations->stock_availables->stock_available as $key => $stock_available) {
									//print_r($stock_available->id);

									$stock = set_product_quantity( 
									(int) $dispo[4],
								 	(int) $product->id, 
								 	(int) $stock_available->id, 
								 	(int) $stock_available->id_product_attribute
								 );
								}

							}
						}



						//TODO get product combinaison et update

						$stock = set_product_quantity( 
							(int) $dispo[4],
						 	(int) $product->id, 
						 	(int) $product->associations->stock_availables->stock_available->id, 
						 	(int) $product->associations->stock_availables->stock_available->id_product_attribute
						 );

						//Association du stock[ref article] = quantity
						$correspondance['stock'][$dispo[3]] =  $dispo[4];
						file_put_contents(FILE_RAPPROCHEMENT, json_encode($correspondance));

						if(LEVEL)
							echo  $product->reference  . " update stock ". $dispo[4] ."\n\r";




					}
					
				}
				else
					if(LEVEL)
						echo  $dispo[3]  . " no need update \n\r";

			}
			else
				if(LEVEL)
				{
					if(!isset($stockNoNeedUpdate))//Compteur de log
						$stockNoNeedUpdate=0;
					$stockNoNeedUpdate++;
				}

		}
		else
		{
			if(LEVEL)
			{
				if(!isset($articleNotFound))//Compteur de log
					$articleNotFound=0;
				else	
					$articleNotFound++;
			}

			if(LEVEL > 2) {

				echo  $dispo[3] ." no exist or found - can't update stock ". $dispo[4] ."\n\r";
			}
		}
		
	}
}
if(LEVEL)
{
	if(isset($articleNotFound))
		echo $articleNotFound ." article(s) not found - update stock impossible  \n\r";
	else
		echo "Toutes les articles ont etaient injectees \n\r";

	echo "$ctn_updatestock : update de stock \n\r";
}





// SI Archivage est activé
if(ARCHIVE)
{
	if(LEVEL)
		echo  "Archivage des fichiers \n\r";
	// ON VERIFIE QUE LE DOSSIER EXIST
	if(!@opendir(PATH_ARCHIVE))
		if (!mkdir(PATH_ARCHIVE, 0777, true)) { // ON CREER LE DOSSIER
    		die('Echec lors de la création des répertoires...');
		}

	$name='myfile_'.date('m-d-Y_hia');
	$return = archive_ftp($name);
	

	//ON DELETE LES FICHIERS DEDANTS
	$files = glob(PATH . 'articles/*'); // get all file names
	foreach($files as $file){ // iterate files
		if(is_file($file))
			unlink($file); // delete file

		if(LEVEL > 2)
			echo  "Delete $file \n\r";
	}

	$files = glob(PATH . 'photos/*'); // get all file names
	foreach($files as $file){ // iterate files
		if(is_file($file))
			unlink($file); // delete file
		
		if(LEVEL > 2)
			echo  "Delete $file \n\r";
	}
}


if( is_file(FILE_LOCK) )
	unlink(FILE_LOCK);





$time_end = microtime(true);
$time = $time_end - $time_start;
if(LEVEL)
	echo "Temps de traitement $time secondes\n\r";









function archive_ftp($output_file)
{
	// ARCHIVE FTP
	// Get real path for our folder
	$rootPath = realpath(PATH);


	// Initialize archive object
	$zip = new ZipArchive();
	$zip->open(PATH_ARCHIVE  . $output_file . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

	// Create recursive directory iterator
	/** @var SplFileInfo[] $files */
	$files = new RecursiveIteratorIterator(
	    new RecursiveDirectoryIterator($rootPath),
	    RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ($files as $name => $file)
	{
	    // Skip directories (they would be added automatically)
	    if (!$file->isDir())
	    {
	        // Get real and relative path for current file
	        $filePath = $file->getRealPath();
	        $relativePath = substr($filePath, strlen($rootPath) + 1);

	        // Add current file to archive
	        $zip->addFile($filePath, $relativePath);
	    }
	}

	// Zip archive will be created only after closing object
	@$zip->close();
}




function make_categorie($data, $parent = 2) {
	global $webService;

	$url =  rawurlencode(explode('.', $data[1], 2)[1]) ;
	$url = strtr($url, array (
				 '%20' => '-',

				 '%C8' => 'e',
				 '%C9' => 'e',

				 '%E0' => 'a',
				 '%E1' => 'a',
				 '%E2' => 'a',

				 '%E8' => 'e',
				 '%E9' => 'e',
				 '%EA' => 'e',

				 '%C0' => 'a',
				 '%C1' => 'a',

				 '%27' => '',

				));

	try {
		$xml 													= $webService->get(array('url' => PS_SHOP_PATH.'/api/categories?schema=blank'));
		$categories												= $xml->children()->children();
	
		$categories->name->language[0][0] 						=  utf8_encode(explode('.', $data[1], 2)[1]);
		$categories->name->language[0][0]['id'] = 1;
		$categories->name->language[0][0]['xlink:href'] 		= PS_SHOP_PATH . '/api/languages/' . 1;

		$categories->link_rewrite->language[0][0] 				= $url;
		$categories->link_rewrite->language[0][0]['id'] 		= 1;
		$categories->link_rewrite->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 1;

		$categories->description->language[0][0] 				= utf8_encode(explode('.', $data[1], 2)[1]);
		$categories->description->language[0][0]['id'] 			= 1;
		$categories->description->language[0][0]['xlink:href'] 	= PS_SHOP_PATH . '/api/languages/' . 1;

		$categories->id_parent 									= $parent; //Accueil
		$categories->active 									= 1;

		$opt													= array('resource' => 'categories');
		$opt['postXml'] 										= $xml->asXML();
		$xml 													= $webService->add($opt);

	}
	catch (PrestaShopWebserviceException $e)	{
		// Here we are dealing with errors
		$trace = $e->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$e->getMessage();
		return;
	}

	return  $xml->category->id;
}



function del_product($id){

	global $webService;
	try
	{
		$xml = $webService->get(array('resource' => 'products', 'id' => $id));
		foreach ($xml->children()->children() as $attName => $attValue)
	  		echo $attName.' = '.$attValue.'<br />';
	  	}
	  	catch (PrestaShopWebserviceException $ex)
	  	{
	  		echo 'Error : '.$ex->getMessage();
	  	}
//var_dump($xml);
//die();
	
	return $xml;

}


function make_product($data){
	global $webService, $correspondance, $_taxe;
	

	//Taxe a définir 
	$taxe = 100 - (100 * (float) $data[10]) /  (float) $data[9] ;
	$taxe = round($taxe, 1, PHP_ROUND_HALF_UP);

	if(isset($_taxe[$taxe])){
		$id_tax_rules_group = (int) $_taxe[$taxe];
	}
	else
	{
		if(LEVEL)
			echo  "/! No taxe $taxe find taxe par default $_taxe[0] \n\r";
		$id_tax_rules_group = (int) $_taxe[0];
	}






	try{
		$xml                                                     	= $webService->get(array('url' => PS_SHOP_PATH.'/api/products?schema=blank'));
		$product                                                 	= $xml->children()->children();
		
		$product->price                                          	= (float) $data[10]; //Prix TTC
		$product->wholesale_price                                	= (float) $data[10]; //Prix d'achat
		$product->unit_price_ratio								 	= (float) $data[10];

		$product->active                                         	= '1';
		$product->on_sale                                        	= 0; //on ne veux pas de bandeau promo
		$product->show_price                                     	= 1;
		$product->available_for_order                            	= 1;
		$product->state 										 	= 1;
		$product->depends_on_stock 								 	= 1;
		
		$product->id_tax_rules_group								= $id_tax_rules_group;

		$product->name->language[0][0]                           	= utf8_encode($data[4]);
		$product->name->language[0][0]['id']                     	= 1;
		$product->name->language[0][0]['xlink:href'] 			 	= PS_SHOP_PATH . '/api/languages/' . 1;
		
		$product->description->language[0][0]                    	= utf8_encode($data[4]);
		$product->description->language[0][0]['id']              	= 1;
		$product->description->language[0][0]['xlink:href'] 	 	= PS_SHOP_PATH . '/api/languages/' . 1;
		
		$product->description_short->language[0][0]              	= utf8_encode($data[5]);
		$product->description_short->language[0][0]['id']        	= 1;
		$product->description_short->language[0][0]['xlink:href']	= PS_SHOP_PATH . '/api/languages/' . 1;


		$product->reference                                         = $data[14];
		$product->supplier_reference 								= $data[17];
		
		$product->associations->categories
			->addChild('category')
			->addChild('id', $correspondance['categories'][$data[0]]);

		$product->id_category_default								= $correspondance['categories'][$data[0]];
		

		
		$opt                                                        = array('resource' => 'products');
		$opt['postXml']                                             = $xml->asXML();
		//sleep(1);
		$xml                                                        = $webService->add($opt); 

		return $xml->product;
/*

	//Association avec notre catégorie créée auparavant
	if(!empty($correspondance['categories'][$value[0]]))
	{
		$resources->associations->categories->category->id = $correspondance['categories'][$value[0]][0];
		$resources->id_category_default = $correspondance['categories'][$value[0]][0];
		$resources->associations->categories->addChild('category')->addChild('id', $correspondance['categories'][$value[0]][0]);
		$resources->id_category_default = $correspondance['categories'][$value[0]][0];
	}
	$resources->product_bundle->product->quantity = 1;

*/




	} catch (PrestaShopWebserviceException $e)	{
		// Here we are dealing with errors
		$trace = $e->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$e->getMessage();
		return;
	}

	

}



function get_product($id){
	global $webService, $correspondance;

		$xml                            = $webService->get(array('url' => PS_SHOP_PATH.'/api/products/'.$id));
		$resources                      = $xml -> children() -> children();
		return $resources;
}


function add_image($id, $data)
{

	if(!file_exists(PATH . 'photos/' . $data[2]))
		echo PATH . "photos/" . $data[2] . "not found \n\r";

	if(@isset($id)) {
		$cfile = new CURLFile(PATH . 'photos/' . $data[2], 'image/jpg', $data[2]);


		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, PS_SHOP_PATH . '/api/images/products/'.$id.'?ps_method=POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
		//curl_setopt($ch, CURLOPT_PUT, true); Pour modifier une image
		curl_setopt($ch, CURLOPT_USERPWD, PS_WS_AUTH_KEY.':');
		curl_setopt($ch, CURLOPT_POSTFIELDS, array( "image" => $cfile ));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		curl_close($ch);
	}
}



function set_product_quantity($quantity, $ProductId, $StokId, $AttributeId){
	global $webService;
	try {
		$opt                             = array();
		$opt['resource']                 = "stock_availables";
		$opt['filter']                   = array('id_product' => $ProductId, "id_product_attribute" => $AttributeId);
		$xml                             = $webService->get($opt);
		$resources                       = $xml->children()->children()[0];
		$StokId                          = (int) $resources['id'][0];
		
		$xml                             = $webService->get(array('url' => PS_SHOP_PATH.'/api/stock_availables?schema=blank'));
		$resources                       = $xml -> children() -> children();
		
		$resources->id                   = $StokId;
		$resources->id_product           = $ProductId;
		$resources->quantity             = $quantity;
		$resources->id_shop              = 1;
		$resources->out_of_stock         = 0;
		$resources->depends_on_stock     = 0;
		$resources->id_product_attribute = $AttributeId;
		
		$opt                             = array('resource' => 'stock_availables');
		$opt['putXml']                   = $xml->asXML();
		$opt['id']                       = $StokId;
		$xml                             = $webService->edit($opt);
		//sleep(1);
		return $xml->stock_available;
	}
	catch (PrestaShopWebserviceException $ex) {
		// Here we are dealing with errors
		$trace = $ex->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$ex->getMessage();
		return;
	}
}



function add_combination($data){
	global $webService;
	try{
		$xml                                               = $webService->get(array('url' => PS_SHOP_PATH.'/api/combinations?schema=blank'));
		
		$combination                                                                    = $xml->children()->children();
		$combination->associations->product_option_values->product_option_values[0]->id = $data["option_id"];
		$combination->reference                                                         = $data["code"];
		$combination->id_product                                                        = $data["id_product"];
		$combination->price                                                             = 0; //Prix TTC
		$combination->show_price                                                        = 1;
		$combination->quantity                                                          = $data["quantity"]; //Prix TTC
		$combination->minimal_quantity                                                  = 1;
		//$product_option_value->id                                                     = 1;    
		
		
		$opt                                                                            = array('resource' => 'combinations');
		$opt['postXml']                                                                 = $xml->asXML();
		//sleep(1);
		$combination																	= $webService->add($opt); 
		return $combination;
	}
	catch (PrestaShopWebserviceException $ex) {
		// Here we are dealing with errors
		$trace = $ex->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$ex->getMessage();
	}
}




function add_product_options($data){
	global $webService;
	try{
		$xml 									= $webService->get(array('url' => PS_SHOP_PATH.'/api/product_options?schema=blank'));
		
		$product_options                                                            = $xml->children()->children();
		$product_options->is_color_group											= 0;
		$product_options->group_type                                                = 'select';
		$product_options->position                                                  = 1;

		$product_options->name->language[0][0]                           			= $data[1];
		$product_options->name->language[0][0]['id']                     			= 1;
		$product_options->name->language[0][0]['xlink:href'] 						= PS_SHOP_PATH . '/api/languages/' . 1;

		$product_options->public_name->language[0][0]                           	= $data[1];
		$product_options->public_name->language[0][0]['id']                     	= 1;
		$product_options->public_name->language[0][0]['xlink:href'] 			 	= PS_SHOP_PATH . '/api/languages/' . 1;

		//$product_options->associations->product_option_values 					= 1;

		$opt                                                                        = array('resource' => 'product_options');
		$opt['postXml']                                                             = $xml->asXML();
		//sleep(1);
		$xml                                                                        = $webService->add($opt); 
		return $xml->product_option;
	}
	catch (PrestaShopWebserviceException $ex) {
		// Here we are dealing with errors
		$trace = $ex->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$ex->getMessage();
		return;
	}
	//insert stock

}

/*
< <combinations nodeType="combination" api="combinations"/>
< <product_option_values nodeType="product_option_value" api="product_option_values"/>
---
> <combinations nodeType="combination" api="combinations">
> 	<combination xlink:href="http://localhost/api/combinations/880">
> 	<id><![CDATA[880]]></id>
> 	</combination>
> 	<combination xlink:href="http://localhost/api/combinations/881">
> 	<id><![CDATA[881]]></id>
> 	</combination>
> </combinations>
> <product_option_values nodeType="product_option_value" api="product_option_values">
> 	<product_option_value xlink:href="http://localhost/api/product_option_values/18">
> 	<id><![CDATA[18]]></id>
> 	</product_option_value>
> 	<product_option_value xlink:href="http://localhost/api/product_option_values/19">
> 	<id><![CDATA[19]]></id>
> 	</product_option_value>
> </product_option_values>
*/
function add_product_option_values($data, $parent) {
global $webService;
	try{
		$xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/product_option_values?schema=blank'));
		
		$product_option_values                                          = $xml->children()->children();
		$product_option_values->id_attribute_group						= (int) $parent;


		$product_option_values->color                                   = 'select';
		$product_option_values->position                                = 1;

		$product_option_values->name->language[0][0]                    = $data[2];
		$product_option_values->name->language[0][0]['id']              = 1;
		$product_option_values->name->language[0][0]['xlink:href'] 		= PS_SHOP_PATH . '/api/languages/' . 1;
		$opt 															= array('resource' => 'product_option_values');
		$opt['postXml']                                                 = $xml->asXML();
		//sleep(1);
		$xml                                                            = $webService->add($opt); 
		$product_option_value                                           = $xml->product_option_value;
		return $product_option_value;
	}
	catch (PrestaShopWebserviceException $ex) {
		// Here we are dealing with errors
		$trace = $ex->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$ex->getMessage();
		return;
	}
	//insert stock
}


/**
 * Generate an MD5 hash string from the contents of a directory.
 *
 * @param string $directory
 * @return boolean|string
 */
function hashDirectory($directory)
{
    if (! is_dir($directory))
    {
        return false;
    }
 
    $files = array();
    $dir = dir($directory);
 
    while (false !== ($file = $dir->read()))
    {
        if ($file != '.' and $file != '..')
        {
            if (is_dir($directory . '/' . $file))
            {
                $files[] = hashDirectory($directory . '/' . $file);
            }
            else
            {
                $files[] = md5_file($directory . '/' . $file);
            }
        }
    }
 
    $dir->close();
 
    return md5(implode('', $files));
}


?>
