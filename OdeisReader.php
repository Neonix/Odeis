<?php
/*

Le connecteur doit à réception du fichier articles.txt (dernier fichier envoyé) :
- soit mettre à jour les informations correspondantes à chaque article présent dans articles.txt
et mettre sa quantité à jour à partir du fichier dispo.txt
- soit supprimer (ou désactiver) dans le cas des articles marqués "S".



Fichier des disponibilités en stock
Nom :dispo.txt
1iere ligne d’entête : famille four reffour refweb qte taille Dernière ligne : [FIN]
Si les paramétrages « Gérer les stocks disponibles » et « Par tailles » sont cochés Et que la famille de l’article ne gère
pas de tailles :
Le champ 6 contient le mot réservé « unique »
et la quantité est calculée sur la référence fournisseur.
Si les paramétrages « Gérer les stocks disponibles » et « Par tailles » sont cochés Et que la famille de l’article gère
des tailles :
Le champ 6 contient la taille
et la quantité est calculée sur la référence fournisseur à taille.
Si les paramétrages « Gérer les stocks disponibles » est coché et « Par tailles » non coché le champ 6 n’est pas envoyé et
la quantité est calculée sur la référence fournisseur

*/

set_time_limit(0);

define('FIN', '[FIN]');


$i 				= 0;
$time_start 	= microtime(true);


header('Content-Type: text/html; charset=iso-8859-1');

require_once('./class/PSWebServiceLibrary.php');
require_once('./class/CsvImporter.php');
require_once('./config/config.php');




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


// VERIFICATION DES FICHIERS
foreach ($files as $Akey => $value)
{
	if (!file_exists(PATH . $value)) {
	    echo "Le fichier $value n'existe pas.";
	      die();
	}
}


//	FICHIER DE CORRESPONDANCE ID ODEIS ET PRESTASHOP
if($recoveredData = file_get_contents(PATH . "articles/rapprochement.txt"))
$correspondance = json_decode($recoveredData, true);

//	CREATION DU TABLEAU VIDE SI FIRST TIME
if(!isset($correspondance))
	$correspondance = array();
file_put_contents(PATH . "articles/rapprochement.txt", json_encode($correspondance));


// CHARGEMENT DE TOUS LES FICHIERS
foreach ($files as $key => $value)
{
	try
	{
		$file = new CsvImporter(PATH . $value,false);
		$files[$key] = $file->get();
	}
	catch (Exception $e) {
            var_dump($e->getMessage());
        }
}



// ASSOCIATION ARTICLES ET DISPOS
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





// INSERTION EN MASSE DES CATEGORIES
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
				$id_parent	= 2; //Accueil
				
			}
			else 
			{

				//11	101.Solitaires	T  -> 101 : categorie 1
				//Marche que jusqu'a 9 :s
				$first_chiffre = explode('.', $famweb[1], 2)[0][0];
				$id_parent = (int) $correspondance['categories'][$first_chiffre][0];
			}


			$category_id = make_categorie($famweb, $id_parent);
			$correspondance['categories'][$famweb[0]] = $category_id;
			file_put_contents(PATH . "articles/rapprochement.txt", json_encode($correspondance));

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
			echo "/! PAS DE TRAITEMENT DATTRIBUS ATM\n\r";
			$read2 = true;
		}
	}
}

if(LEVEL)
{
	if(isset($category_exist))
		echo $category_exist . " CATEGORIES NON INJECTE CAR PRESENTE\n\r";
	else
		echo "Toutes les categories ont etaient injectees\n\r";
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
			$correspondance['articles'][$articles[14]] = $product->id; //Association ID presta 
			file_put_contents(PATH . "articles/rapprochement.txt", json_encode($correspondance)); //On enregistre

			add_image($product->id, $articles); //On ajoute l'image associé

			if( isset($files['articles'][$Akey]['dispo']) ) //On regarde si on à une dispo 
			{		
				//On ajout les stocks
				$stock = set_product_quantity( 
					(int) $files['articles'][$Akey]['dispo'][4],
				 	(int) $product->id, 
				 	(int) $product->associations->stock_availables->stock_available->id, 
				 	(int) $product->associations->stock_availables->stock_available->id_product_attribute
				 );

				$correspondance['stock'][$articles[14]] = (int) $stock->id;
				file_put_contents(PATH . "articles/rapprochement.txt", json_encode($correspondance));

			}
			if(LEVEL)
			{	
				echo $files['articles'][$Akey][3] . " 0 dispo \n\r";
			}


			
		}
		else
		{
			if($articles[15] == 'S')
			{
				if(isset($correspondance['articles'][$articles[14]][0]))
				{

					del_product($correspondance['articles'][$articles[14]][0]);
					
					unset($correspondance['articles'][$articles[14]]);
					unset($correspondance['stock'][$articles[14]]);

					file_put_contents(PATH . "articles/rapprochement.txt", json_encode($correspondance)); //On enregistre

				}
				else
					echo "/! ARTICLE A DELETE MAIS 0 CORRESPONDANCE \n\r";
			}

			if(LEVEL)
			{
				if(!isset($article_exist))
					$article_exist=0;
				$article_exist++;
			}
		}
		
	}



}
if(LEVEL)
	echo " ".$article_exist." ARTICLES NON INJECTE CAR PRESENTE \n\r";







$time_end = microtime(true);
$time = $time_end - $time_start;
if(LEVEL)
	echo "Temps de traitement $time secondes\n\r";






















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

	try{

	$xml 							= $webService->get(array('url' => PS_SHOP_PATH.'/api/products'));
	$product                        = $xml->children()->children();
	$opt['resource'] 				= 'products';
	//$opt['filter']                  = array('reference' => $data[14]);

	$opt['id'] 						= $id;

	$xml = $webService->delete($opt);


	} catch (PrestaShopWebserviceException $e)	{
		// Here we are dealing with errors
		$trace = $e->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$e->getMessage();
		return;
	}

	
	return $xml;

}


function make_product($data){
	global $webService, $correspondance;
	
	try{
		$xml                                                     	= $webService->get(array('url' => PS_SHOP_PATH.'/api/products?schema=blank'));
		$product                                                 	= $xml->children()->children();
		
		$product->price                                          	= (int) $data[9]; //Prix TTC
		$product->wholesale_price                                	= (int) $data[10]; //Prix d'achat
		$product->unit_price_ratio								 	= (int) $data[9];

		$product->active                                         	= '1';
		$product->on_sale                                        	= 1; //on ne veux pas de bandeau promo
		$product->show_price                                     	= 1;
		$product->available_for_order                            	= 1;
		$product->state 										 	= 1;
		$product->depends_on_stock 								 	= 1;
		
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
			->addChild('id', $correspondance['categories'][$data[0]][0]);

		$product->id_category_default								= $correspondance['categories'][$data[0]][0];
		
		
		$product->unit_price_ratio 									= (int) $data[9];

		
		$opt                                                        = array('resource' => 'products');
		$opt['postXml']                                             = $xml->asXML();
		sleep(1);
		$xml                                                        = $webService->add($opt); 
		
		$product                                                    = $xml->product;

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

	
	return $product;
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
	global $webService, $config;
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

		return $xml;
	}
	catch (PrestaShopWebserviceException $ex) {
		// Here we are dealing with errors
		$trace = $ex->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$ex->getMessage();
	}
}



function add_combination($data){
	global $webService, $config;
	try{
		$xml                                               = $webService->get(array('url' => PS_SHOP_PATH.'/api/combinations?schema=blank'));
		
		$combination                                                                    = $xml->children()->children();
		$combination->associations->product_option_values->product_option_values[0]->id = $data["option_id"];
		$combination->reference                                                         = $data["code"];
		$combination->id_product                                                        = $data["id_product"];
		$combination->price                                                             = $data["price"]; //Prix TTC
		$combination->show_price                                                        = 1;
		$combination->quantity                                                          = $data["quantity"]; //Prix TTC
		$combination->minimal_quantity                                                  = 1;
		//$product_option_value->id                                                     = 1;    
		
		
		$opt                                                                            = array('resource' => 'combinations');
		$opt['postXml']                                                                 = $xml->asXML();
		sleep(1);
		$xml                                                                            = $webService->add($opt); 
		$combination                                                                    = $xml->combination;
	}
	catch (PrestaShopWebserviceException $ex) {
		// Here we are dealing with errors
		$trace = $ex->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$ex->getMessage();
	}
	//insert stock
	return $combination;
}




function add_product_options($data){
	global $webService;
	try{
		$xml                                              = $webService->get(array('url' => PS_SHOP_PATH.'/api/product_options?schema=blank'));
		
		$product_options                                                                    = $xml->children()->children();
		$product_options->is_color_group													= 0;
		$product_options->group_type                                                        = 'select';
		$product_options->position                                                        	= 1;

		$product_options->name->language[0][0]                           					= 'Size';
		$product_options->name->language[0][0]['id']                     					= 1;
		$product_options->name->language[0][0]['xlink:href'] 								= PS_SHOP_PATH . '/api/languages/' . 1;

		$product_options->name->public_name[0][0]                           				= 'Taille';
		$product_options->name->public_name[0][0]['id']                     				= 1;
		$product_options->name->public_name[0][0]['xlink:href'] 			 				= PS_SHOP_PATH . '/api/languages/' . 1;

		$product_options->associations->product_option_values 								= 1;

		$opt                                                                            = array('resource' => 'product_options');
		$opt['postXml']                                                                 = $xml->asXML();
		sleep(1);
		$xml                                                                            = $webService->add($opt); 
		$product_options                                                                    = $xml->product_options;
	}
	catch (PrestaShopWebserviceException $ex) {
		// Here we are dealing with errors
		$trace = $ex->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$ex->getMessage();
	}
	//insert stock
	return $combination;
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
function add_product_option_values($data) {
global $webService;
	try{
		$xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/product_option_values?schema=blank'));
		
		$product_option_values                                                              = $xml->children()->children();
		$product_option_values->id_attribute_group											= 0;


		$product_option_values->color                                                       = 'select';
		$product_option_values->position                                                    = 1;

		$product_option_values->name->language[0][0]                           				= 'Size';
		$product_option_values->name->language[0][0]['id']                     				= 1;
		$product_option_values->name->language[0][0]['xlink:href'] 							= PS_SHOP_PATH . '/api/languages/' . 1;


		$opt                                                                            	= array('resource' => 'product_option_values');
		$opt['postXml']                                                                 	= $xml->asXML();
		sleep(1);
		$xml                                                                            	= $webService->add($opt); 
		$product_options                                                                    = $xml->product_options;
	}
	catch (PrestaShopWebserviceException $ex) {
		// Here we are dealing with errors
		$trace = $ex->getTrace();
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo 'Other error<br />'.$ex->getMessage();
	}
	//insert stock
	return $combination;

}


?>
