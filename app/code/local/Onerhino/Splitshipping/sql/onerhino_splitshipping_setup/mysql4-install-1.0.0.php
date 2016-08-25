<?php
$installer = $this;
/** @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup ();

$attrCode = 'carrier_to_ship';
$attrGroupName = 'General';
$attrLabel = 'Carrier To Ship';

$objCatalogEavSetup = Mage::getResourceModel ( 'catalog/eav_mysql4_setup', 'core_setup' );
$attrIdTest = $objCatalogEavSetup->getAttributeId ( Mage_Catalog_Model_Product::ENTITY, $attrCode );

if ($attrIdTest === false) {
	
	Mage::log('aqui', null, 'carrier.log');
	
	$objCatalogEavSetup->addAttribute ( Mage_Catalog_Model_Product::ENTITY, $attrCode, array (
			'group' => $attrGroupName,
			'sort_order' => 100,
			'type' => 'varchar',
			'backend' => '',
			'frontend' => '',
			'label' => $attrLabel,
			'note' => '',
			'input' => 'text',
			'class' => '',
			'source' => '',
			'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
			'visible' => true,
			'required' => false,
			'user_defined' => false,
			'default' => '0',
			'visible_on_front' => false,
			'unique' => false,
			'is_configurable' => false,
			'used_for_promo_rules' => true 
	) );
}

Mage::log('acolá', null, 'carrier.log');

$installer->endSetup ();