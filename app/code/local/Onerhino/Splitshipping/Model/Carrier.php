<?php

/**
 * 
 * @author geiser
 *
 */
class Onerhino_Splitshipping_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {
	
	/**
	 * Carrier's code, as defined in parent class
	 *
	 * @var string
	 */
	const CODE = 'onerhino_splitshipping';
	
	/**
	 * Collect carriers to use for shipping.
	 *
	 * @param Mage_Shipping_Model_Rate_Request $request        	
	 * @return array
	 */
	protected function _collectCarriersToShip(Mage_Shipping_Model_Rate_Request $request) {
		$carriersToShip = array ();
		$hasSpecific = false;
		
		// if the default carrier is not present, ignore
		if (! ($defaultCarrier = Mage::helper ( 'onerhino_splitshipping' )->getDefaultCarrier ()) && ! $this->_isActiveShippingMethod ( $defaultCarrier )) {
			return false;
		}
		
		foreach ( $request->getAllItems () as $item ) {
			
			/** @var \Mage_Catalog_Model_Product $product */
			$product = Mage::getModel ( 'catalog/product' )->load ( $item->getProductId () );
			
			if (! $product->isConfigurable ()) {
				
				$carrierToShip = $product->getData ( 'carrier_to_ship' );
				if ($carrierToShip && $this->_isActiveShippingMethod ( $carrierToShip )) {
					$hasSpecific = true;
				} else {
					$carrierToShip = $defaultCarrier;
				}
				
				if (! isset ( $carriersToShip [$carrierToShip] )) {
					$carriersToShip [$carrierToShip] = array ();
				}
				$carriersToShip [$carrierToShip] [] = $product;
			}
		}
		
		if ($hasSpecific) {
			return $carriersToShip;
		}
		
		return false;
	}
	
	/**
	 * Returns available shipping rates for Inchoo Shipping carrier
	 *
	 * @param Mage_Shipping_Model_Rate_Request $request        	
	 * @return Mage_Shipping_Model_Rate_Result
	 */
	public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
		
		/** @var Mage_Shipping_Model_Rate_Result $result */
		$result = Mage::getModel ( 'shipping/rate_result' );
		
		$carriersToShip = $this->_collectCarriersToShip ( $request );
		if ($carriersToShip) {
			$carrierRates = array ();
			foreach ( $carriersToShip as $carrier => $products ) {
				/** @var \Mage_Shipping_Model_Rate_Result_Method $rate */
				$rates = $this->_getAvailableCarrierRate ( $carrier, $request, $products );
				if ($rates) {
					$carrierRates [] = $rates;
				}
			}
			
			list ( $nothing, $nothing2, $options ) = $this->_combineArrays ( $carrierRates );
			
			foreach ( $options as $option ) {
				
				$price = 0;
				$cost = 0;
				$methods = array ();
				$methodTitles = array ();
				
				foreach ( $option as $rate ) {
					
					$price += $rate->getPrice ();
					$cost += $rate->getCost ();
					$methods [] = "{$rate->getCarrier()}_{$rate->getMethod()}";
					
					//$formattedPrice = Mage::helper('core')->currency($rate->getPrice(), true, false);
					$formattedPrice = '';
					$formattedTitle = $this->_translateCarrier($rate->getCarrierTitle());
					
					$productTypes = '';
					if ($rate->getProductTypes ()) {
						$productTypes .= '(';
						$productTypes .= strtolower ( implode ( ",", $rate->getProductTypes () ) );
						$productTypes .= ')';
					}
					
					$methodTitles [] = trim("$formattedTitle-{$rate->getMethodTitle()} $productTypes");
				}
				
				/** @var Mage_Shipping_Model_Rate_Result_Method $rate */
				$rate = Mage::getModel ( 'shipping/rate_result_method' );
				
				$rate->setCarrier ( self::CODE );
				$rate->setCarrierTitle ( $this->getConfigData ( 'title' ) );
				$rate->setMethod ( implode ( '|', $methods ) );
				$rate->setMethodTitle ( implode ( ' + ', $methodTitles ) );
				$rate->setPrice ( $price );
				$rate->setCost ( $cost );
				
				if ($price) {
					$result->append ( $rate );
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * Returns Allowed shipping methods
	 *
	 * @return array
	 */
	public function getAllowedMethods() {
		return array ();
	}
	
	/**
	 * Get best matching rate given a carrier code.
	 *
	 * @param string $carrier        	
	 * @param Mage_Shipping_Model_Rate_Request $request        	
	 * @return array of Mage_Shipping_Model_Rate_Result_Method
	 */
	protected function _getAvailableCarrierRate($carrier, $request, $products) {
		
		/** @var \Mage_Shipping_Model_Rate_Request $newRequest */
		$requestToMake = $this->_cloneRequest ( $request );
		
		$orderTotalQty = 0;
		$orderSubtotal = 0;
		$orderSubtotalWithDiscount = 0;
		$allitems = array ();
		$types = array();
		/** @var \Mage_Sales_Model_Quote_Item $item */
		foreach ( $request->getAllItems () as $item ) {
			/** @var \Mage_Catalog_Model_Product $carrierProduct */
			foreach ( $products as $carrierProduct ) {
				if ($item->getProductId () == $carrierProduct->getId ()) {
					$types [] = Mage::getModel ( 'eav/entity_attribute_set' )->load ( $item->getProduct()->getAttributeSetId () )->getAttributeSetName ();
					$allitems [] = $item;
					$orderSubtotal += $item->getBaseRowTotal ();
					$orderTotalQty += $item->getQty ();
					$orderSubtotalWithDiscount += $item->getBaseRowTotal () - $item->getBaseDiscountAmount ();
				}
			}
		}
		$requestToMake->setAllItems ( $allitems );
		
		$requestToMake->setOrderTotalQty ( $orderTotalQty );
		$requestToMake->setOrderSubtotal ( $orderSubtotal );
		
		$requestToMake->setPackageValue ( $orderSubtotal );
		$requestToMake->setPackageValueWithDiscount ( $orderSubtotalWithDiscount );
		$requestToMake->setPackagePhysicalValue ( $orderSubtotal );
		$requestToMake->setPackageQty ( $orderTotalQty );
		
		$carrierMethod = explode ( '_', $carrier );
		
		/** @var \Mage_Shipping_Model_Shipping $shipping */
		$shipping = Mage::getModel ( 'shipping/shipping' );
		$shipping->collectCarrierRates ( $carrierMethod [0], $requestToMake );
		
		$allRates = $shipping->getResult ()->getAllRates ();
		$newAllRates = array();
		$types = array_unique($types);
		/** @var \Mage_Shipping_Model_Rate_Result_Abstract $rate */
		foreach($allRates as $rate) {
			$rate->setProductTypes($types);
			$newAllRates[] = $rate;
		}
		return $newAllRates;
		
	}
	
	/**
	 * Clones a request.
	 *
	 * @param Mage_Shipping_Model_Rate_Request $request        	
	 */
	protected function _cloneRequest($request) {
		/** @var \Mage_Shipping_Model_Rate_Request $newRequest */
		$requestToMake = Mage::getModel ( 'shipping/rate_request' );
		
		$requestToMake->setStoreId ( $request->getStoreId () );
		$requestToMake->setWebsiteId ( $request->getWebsiteId () );
		$requestToMake->setBaseCurrency ( $request->getBaseCurrency () );
		
		$requestToMake->setAllItems ( $request->getAllItems () );
		
		$requestToMake->setOrderTotalQty ( $request->getOrderTotalQty () );
		$requestToMake->setOrderSubtotal ( $request->getOrderSubtotal () );
		
		$requestToMake->setPackageValue ( $request->getPackageValue () );
		$requestToMake->setPackageValueWithDiscount ( $request->getPackageValueWithDiscount () );
		$requestToMake->setPackagePhysicalValue ( $request->getPackagePhysicalValue () );
		$requestToMake->setPackageQty ( $request->getPackageQty () );
		$requestToMake->setPackageWeight ( $request->getPackageWeight () );
		$requestToMake->setPackageHeight ( $request->getPackageHeight () );
		$requestToMake->setPackageWidth ( $request->getPackageWidth () );
		$requestToMake->setPackageDepth ( $request->getPackageDepth () );
		$requestToMake->setPackageCurrency ( $request->getPackageCurrency () );
		
		$requestToMake->setOrigCountryId ( $request->getOrigCountryId () );
		$requestToMake->setOrigRegionId ( $request->getOrigRegionId () );
		$requestToMake->setOrigPostcode ( $request->getOrigPostcode () );
		$requestToMake->setOrigCity ( $request->getOrigCity () );
		
		$requestToMake->setDestCountryId ( $request->getDestCountryId () );
		$requestToMake->setDestRegionId ( $request->getDestRegionId () );
		$requestToMake->setDestRegionCode ( $request->getDestRegionCode () );
		$requestToMake->setDestPostcode ( $request->getDestPostcode () );
		$requestToMake->setDestCity ( $request->getDestCity () );
		$requestToMake->setDestStreet ( $request->getDestStreet () );
		
		$requestToMake->setFreeShipping ( $request->getFreeShipping () );
		$requestToMake->setFreeMethodWeight ( $request->getFreeMethodWeight () );
		
		$requestToMake->setOptionInsurance ( $request->getOptionInsurance () );
		$requestToMake->setOptionHandling ( $request->getOptionHandling () );
		
		$requestToMake->setConditionName ( $request->getConditionName () );
		
		$requestToMake->setLimitCarrier ( $request->getLimitCarrier () );
		$requestToMake->setLimitMethod ( $request->getLimitMethod () );
		
		return $requestToMake;
	}
	
	/**
	 * Checks if a shipping method is active.
	 *
	 * @param string $carrier        	
	 */
	protected function _isActiveShippingMethod($carrier) {
		$activeCarriers = Mage::getSingleton ( 'shipping/config' )->getActiveCarriers ();
		foreach ( $activeCarriers as $carrierCode => $carrierModel ) {
			if ($carrier == $carrierCode) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Combines one array into all possibilites.
	 *
	 * @param arary $arr        	
	 * @param array $codes        	
	 * @param number $pos        	
	 * @param array $globalCodes        	
	 */
	protected function _combineArrays($arr, $codes = array(), $pos = 0, $globalCodes = array()) {
		if (count ( $arr )) {
			for($i = 0; $i < count ( $arr [0] ); $i ++) {
				$tmp = $arr;
				$codes [$pos] = $arr [0] [$i];
				$tarr = array_shift ( $tmp );
				$pos ++;
				list ( $codes, $pos, $globalCodes ) = $this->_combineArrays ( $tmp, $codes, $pos, $globalCodes );
			}
		} else {
			$globalCodes [] = $codes;
		}
		
		$pos --;
		
		return array (
				$codes,
				$pos,
				$globalCodes 
		);
	}
	
	/**
	 * Translates a carrier to compressed one.
	 * 
	 * @param string $title        	
	 */
	protected function _translateCarrier($title) {
		
		$title = str_ireplace('united parcel service', 'UPS', $title);
		$title = str_ireplace('federal express', 'FedEx', $title);
		$title = str_ireplace('united states postal service', 'USPS', $title);
		
		return $title;
	}
}