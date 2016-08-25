<?php
class Onerhino_Splitshipping_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {
	
	/**
	 * Default carrier code, to use when no specific carrier is defined on a product.
	 *
	 * @var string
	 */
	protected $_default = 'ups';
	
	/**
	 * Carrier's code, as defined in parent class
	 *
	 * @var string
	 */
	protected $_code = 'onerhino_splitshipping';
	
	/**
	 * Collect carriers to use for shipping.
	 *
	 * @param Mage_Shipping_Model_Rate_Request $request        	
	 * @return array
	 */
	protected function _collectCarriersToShip(Mage_Shipping_Model_Rate_Request $request) {
		$carriersToShip = array ();
		$hasSpecific = false;
		
		foreach ( $request->getAllItems () as $item ) {
			
			/** @var \Mage_Catalog_Model_Product $product */
			$product = Mage::getModel ( 'catalog/product' )->load ( $item->getProductId () );
			
			$carrierToShip = $product->getData ( 'carrier_to_ship' );
			if ($carrierToShip && $this->_isActiveShippingMethod ( $carrierToShip )) {
				$hasSpecific = true;
			} else {
				$carrierToShip = $this->_default;
			}
			
			if (! isset ( $carriersToShip [$carrierToShip] )) {
				$carriersToShip [$carrierToShip] = array ();
			}
			$carriersToShip [$carrierToShip] [] = $product;
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
			
			$price = 0;
			$cost = 0;
			$methodTitles = array ();
			$methods = array ();
			
			foreach ( $carriersToShip as $carrier => $products ) {
				
				/** @var \Mage_Shipping_Model_Rate_Result_Method $rate */
				$rate = $this->_getAvailableCarrierRate ( $carrier, $request, $products );
				
				if ($rate) {
					$price += $rate->getPrice ();
					$cost += $rate->getCost ();
					$methodTitles [] = "{$rate->getCarrierTitle()}-{$rate->getMethodTitle ()}";
					$methods [] = "{$rate->getCarrier()}_{$rate->getMethod ()}";
				}
			}
			
			/** @var Mage_Shipping_Model_Rate_Result_Method $rate */
			$rate = Mage::getModel ( 'shipping/rate_result_method' );
			
			$rate->setCarrier ( $this->_code );
			$rate->setCarrierTitle ( $this->getConfigData ( 'title' ) );
			$rate->setMethod ( implode ( '|', $methods ) );
			$rate->setMethodTitle ( implode ( ' + ', $methodTitles ) );
			$rate->setPrice ( $price );
			$rate->setCost ( $cost );
			
			$result->append ( $rate );
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
	 * @return Mage_Shipping_Model_Rate_Result_Method
	 */
	protected function _getAvailableCarrierRate($carrier, $request, $products) {
		
		/** @var \Mage_Shipping_Model_Rate_Request $newRequest */
		$requestToMake = $this->_cloneRequest ( $request );
		
		$orderTotalQty = 0;
		$orderSubtotal = 0;
		$orderSubtotalWithDiscount = 0;
		$allitems = array ();
		/** @var \Mage_Sales_Model_Quote_Item $item */
		foreach ( $request->getAllItems () as $item ) {
			/** @var \Mage_Catalog_Model_Product $carrierProduct */
			foreach ( $products as $carrierProduct ) {
				if ($item->getProductId () == $carrierProduct->getId ()) {
					$allitems [] = $item;
					$orderSubtotal += $item->getBaseRowTotal ();
					$orderTotalQty += $item->getQty ();
					$orderSubtotalWithDiscount += $item->getBaseRowTotal () - $item->getBaseDiscountAmount ();
				}
			}
		}
		$requestToMake->setAllItems ( $allItems );
		
		$requestToMake->setOrderTotalQty ( $orderTotalQty );
		$requestToMake->setOrderSubtotal ( $orderSubtotal );
		
		$requestToMake->setPackageValue ( $orderSubtotal );
		$requestToMake->setPackageValueWithDiscount ( $orderSubtotalWithDiscount );
		$requestToMake->setPackagePhysicalValue ( $orderSubtotal );
		$requestToMake->setPackageQty ( $orderTotalQty );
		
		/** @var \Mage_Shipping_Model_Shipping $shipping */
		$shipping = Mage::getModel ( 'shipping/shipping' );
		$shipping->collectCarrierRates ( $carrier, $requestToMake );
		
		$rate = $shipping->getResult ()->getCheapestRate ();
		
		return $rate;
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
}