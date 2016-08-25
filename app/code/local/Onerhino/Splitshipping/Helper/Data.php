<?php

/**
 * 
 * @author geiser
 *
 */
class Onerhino_Splitshipping_Helper_Data extends Mage_Core_Helper_Abstract {
	const XML_DEFAULT_CARRIER = 'carriers/onerhino_splitshipping/default_carrier';
	
	/**
	 * Get the default carrier to ship.
	 *
	 * @return mixed
	 */
	public function getDefaultCarrier() {
		return Mage::getStoreConfig ( self::XML_DEFAULT_CARRIER );
	}
}