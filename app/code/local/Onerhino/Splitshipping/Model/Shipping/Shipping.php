<?php

/**
 *
 * @author geiser
 *
 */
if (! Mage::helper ( 'core' )->isModuleEnabled ( 'Amasty_Shiprestriction' )) {
	class Onerhino_Splitshipping_Model_Shipping_Shipping extends Mage_Shipping_Model_Shipping {
		public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
			parent::collectRates ( $request );
			
			if ($rates = $this->getResult ()->getRatesByCarrier ( Onerhino_Splitshipping_Model_Carrier::CODE )) {
				
				// clear the result
				$this->getResult ()->reset ();
				
				// append just our rate
				foreach ( $rates as $rate ) {
					$this->getResult ()->append ( $rate );
				}
			}
			
			return $this;
		}
	}
} else {
	class Onerhino_Splitshipping_Model_Shipping_Shipping extends Amasty_Shiprestriction_Model_Shipping_Shipping {
		public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
			parent::collectRates ( $request );
			
			if ($rates = $this->getResult ()->getRatesByCarrier ( Onerhino_Splitshipping_Model_Carrier::CODE )) {
				
				// clear the result
				$this->getResult ()->reset ();
				
				// append just our rate
				foreach ( $rates as $rate ) {
					$this->getResult ()->append ( $rate );
				}
			}
			
			return $this;
		}
	}
}