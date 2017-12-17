<?php
/**
 * ---------------------------------------------------------------------------------
 *
 * 2018 Nils NICOLAS
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to ecommerce@quadra-informatique.fr so we can send you a copy immediately.
 *
 * @author    Nils NICOLAS <nils@oneshot.fr>
 * @copyright 2018 Oneshot
 * @version Release: $Revision: 1.0.1 $
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * ---------------------------------------------------------------------------------
 */

class odeisstock extends Module
{

	public function __construct()
	{
		$this->name = 'odeisstock';
		$this->tab = 'administration';
		$this->version = '1.0.1';
		$this->author = 'Nils NICOLAS';
 		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_); 


		$this->displayName = $this->l('Odeis - Combination equalize stock');
		$this->description = $this->l('This module will equalize stock for combination article');
		parent::__construct();
	}

	public function install()
	{
		return parent::install() && $this->registerHook('actionValidateOrder');
	}


	public function uninstall()
	{
        
        // Uninstall Module
        if (!parent::uninstall())
            return false;

		return parent::uninstall();
	}

    /**
    * hookActionValidateOrder
    *
    * New orders
    * 
    **/
    public function hookActionValidateOrder($params)
    {
		
 		$address 	= new Address($params['cart']->id_address_delivery);
 		$customer 	= new Customer($params['customer']->id);
 		$carrier 	= new Carrier($params['cart']->id_carrier);
 		$order 		= new Order($params['order']->id_carrier);
 
	

		foreach ($params['order']->product_list as $row)
		{
		
			if(isset($row['attributes']))
				$attributes = $row['attributes'];
			else
				$attributes = '';
		
			$product = new Product( (int) $row["id_product"] );
			$allCombinations = $product->getAttributeCombinations(1, false);
			//Si le produit a un attribut
			if(isset($row['id_product_attribute'])) {
				foreach ($allCombinations as $key => $value) {
					
					//Si le produit n'a pas déjà été decrementé
					if($row['id_product_attribute'] != $value['id_product_attribute'])
						StockAvailable::updateQuantity($value['id_product'], $value['id_product_attribute'], -(int) $row['cart_quantity'], $params['order']->id_shop);
				}
			}

		}

    }

}
