<?php
class ParentOrderController extends ParentOrderControllerCore
{
 	protected function _assignAddress()
	{
                //if guest checkout disabled and flag is_guest  in cookies is actived
		if (Configuration::get('PS_GUEST_CHECKOUT_ENABLED') == 0 && ((int)$this->context->customer->is_guest != Configuration::get('PS_GUEST_CHECKOUT_ENABLED')))
		{
                    $this->context->customer->logout();
                    Tools::redirect('');
		}
		else if (!Customer::getAddressesTotalById($this->context->customer->id) && !$this->context->cart->isVirtualCart()){
                    die('KOM IK HIER');
                    Tools::redirect('index.php?controller=address&back='.urlencode('order.php?step=1&multi-shipping='.(int)Tools::getValue('multi-shipping')));
                }
                
                $customer = $this->context->customer;
		if (Validate::isLoadedObject($customer))
		{
                        if (!$this->context->cart->isVirtualCart()) {
                            /* Getting customer addresses */
                            $customerAddresses = $customer->getAddresses($this->context->language->id);

                            // Getting a list of formated address fields with associated values
                            $formatedAddressFieldsValuesList = array();

                            foreach ($customerAddresses as $i => $address)
                            {
				if (!Address::isCountryActiveById((int)($address['id_address']))) {
                                    unset($customerAddresses[$i]);										
                                }
                                $tmpAddress = new Address($address['id_address']);
				$formatedAddressFieldsValuesList[$address['id_address']]['ordered_fields'] = AddressFormat::getOrderedAddressFields($address['id_country']);
				$formatedAddressFieldsValuesList[$address['id_address']]['formated_fields_values'] = AddressFormat::getFormattedAddressFieldsValues(
					$tmpAddress,
					$formatedAddressFieldsValuesList[$address['id_address']]['ordered_fields']);

				unset($tmpAddress);
                            }

                            if (key($customerAddresses) != 0) {
				$customerAddresses = array_values($customerAddresses);
                            }
                        
                        
                        
                            if (!count($customerAddresses))
                            {
				$bad_delivery = false;
				if (($bad_delivery = (bool)!Address::isCountryActiveById((int)$this->context->cart->id_address_delivery)) || (!Address::isCountryActiveById((int)$this->context->cart->id_address_invoice)))
				{
					$back_url = $this->context->link->getPageLink('order', true, (int)$this->context->language->id, array('step' => Tools::getValue('step'), 'multi-shipping' => (int)Tools::getValue('multi-shipping')));
					$params = array('multi-shipping' => (int)Tools::getValue('multi-shipping'), 'id_address' => ($bad_delivery ? (int)$this->context->cart->id_address_delivery : (int)$this->context->cart->id_address_invoice), 'back' => $back_url);
					Tools::redirect($this->context->link->getPageLink('address', true, (int)$this->context->language->id, $params));
				}
                            }
                                
			
                            $this->context->smarty->assign(array(
				'addresses' => $customerAddresses,
				'formatedAddressFieldsValuesList' => $formatedAddressFieldsValuesList));

                            /* Setting default addresses for cart */
                            if ((!isset($this->context->cart->id_address_delivery) || empty($this->context->cart->id_address_delivery)) && count($customerAddresses))
                            {
				$this->context->cart->id_address_delivery = (int)($customerAddresses[0]['id_address']);
				$update = 1;
                            }
                            if ((!isset($this->context->cart->id_address_invoice) || empty($this->context->cart->id_address_invoice)) && count($customerAddresses))
                            {
				$this->context->cart->id_address_invoice = (int)($customerAddresses[0]['id_address']);
				$update = 1;
                            }
                            /* Update cart addresses only if needed */
                            if (isset($update) && $update)
                            {
				$this->context->cart->update();
				
				// Address has changed, so we check if the cart rules still apply
				CartRule::autoRemoveFromCart($this->context);
				CartRule::autoAddToCart($this->context);
                            }

                            /* If delivery address is valid in cart, assign it to Smarty */
                            if (isset($this->context->cart->id_address_delivery))
                            {
				$deliveryAddress = new Address((int)($this->context->cart->id_address_delivery));
				if (Validate::isLoadedObject($deliveryAddress) && ($deliveryAddress->id_customer == $customer->id))
					$this->context->smarty->assign('delivery', $deliveryAddress);
                            }

                            /* If invoice address is valid in cart, assign it to Smarty */
                            if (isset($this->context->cart->id_address_invoice))
                            {
				$invoiceAddress = new Address((int)($this->context->cart->id_address_invoice));
				if (Validate::isLoadedObject($invoiceAddress) && ($invoiceAddress->id_customer == $customer->id))
					$this->context->smarty->assign('invoice', $invoiceAddress);
                            }
                        }
                }
		if ($oldMessage = Message::getMessageByCartId((int)($this->context->cart->id)))
			$this->context->smarty->assign('oldMessage', $oldMessage['message']);
	}

	protected function _assignCarrier()
	{	
		$address = new Address($this->context->cart->id_address_delivery);
		$id_zone = Address::getZoneById($address->id);
		$carriers = $this->context->cart->simulateCarriersOutput();
		$checked = $this->context->cart->simulateCarrierSelectedOutput();
		$delivery_option_list = $this->context->cart->getDeliveryOptionList();
		$this->setDefaultCarrierSelection($delivery_option_list);

		$this->context->smarty->assign(array(		
			'address_collection' => $this->context->cart->getAddressCollection(),
			'delivery_option_list' => $delivery_option_list,
			'carriers' => $carriers,
			'checked' => $checked,
			'delivery_option' => $this->context->cart->getDeliveryOption(null, false)
		));

		$vars = array(
			'HOOK_BEFORECARRIER' => Hook::exec('displayBeforeCarrier', array(
				'carriers' => $carriers,
				'checked' => $checked,
				'delivery_option_list' => $delivery_option_list,
				'delivery_option' => $this->context->cart->getDeliveryOption(null, false)
			))
		);
		
		Cart::addExtraCarriers($vars);
		
		$this->context->smarty->assign($vars);
	}

	protected function _assignWrappingAndTOS()
	{
		// Wrapping fees
		$wrapping_fees = $this->context->cart->getGiftWrappingPrice(false);
		$wrapping_fees_tax_inc = $wrapping_fees = $this->context->cart->getGiftWrappingPrice();

		// TOS
		$cms = new CMS(Configuration::get('PS_CONDITIONS_CMS_ID'), $this->context->language->id);
		$this->link_conditions = $this->context->link->getCMSLink($cms, $cms->link_rewrite, (bool)Configuration::get('PS_SSL_ENABLED'));
		if (!strpos($this->link_conditions, '?'))
			$this->link_conditions .= '?content_only=1';
		else
			$this->link_conditions .= '&content_only=1';
		
		$free_shipping = false;
		foreach ($this->context->cart->getCartRules() as $rule)
		{
			if ($rule['free_shipping'] && !$rule['carrier_restriction'])
			{
				$free_shipping = true;
				break;
			}			
		}	
		$this->context->smarty->assign(array(
			'free_shipping' => $free_shipping,
			'checkedTOS' => (int)($this->context->cookie->checkedTOS),
			'recyclablePackAllowed' => (int)(Configuration::get('PS_RECYCLABLE_PACK')),
			'giftAllowed' => (int)(Configuration::get('PS_GIFT_WRAPPING')),
			'cms_id' => (int)(Configuration::get('PS_CONDITIONS_CMS_ID')),
			'conditions' => (int)(Configuration::get('PS_CONDITIONS')),
			'link_conditions' => $this->link_conditions,
			'recyclable' => (int)($this->context->cart->recyclable),
			'delivery_option_list' => $this->context->cart->getDeliveryOptionList(),
			'carriers' => $this->context->cart->simulateCarriersOutput(),
			'checked' => $this->context->cart->simulateCarrierSelectedOutput(),
			'address_collection' => $this->context->cart->getAddressCollection(),
			'delivery_option' => $this->context->cart->getDeliveryOption(null, false),
			'gift_wrapping_price' => (float)$wrapping_fees,
			'total_wrapping_cost' => Tools::convertPrice($wrapping_fees_tax_inc, $this->context->currency),
			'total_wrapping_tax_exc_cost' => Tools::convertPrice($wrapping_fees, $this->context->currency)));
	}

	protected function _assignPayment()
	{
            
		$this->context->smarty->assign(array(
			'HOOK_TOP_PAYMENT' => Hook::exec('displayPaymentTop'),
			'HOOK_PAYMENT' => Hook::exec('displayPayment'),
		));
	}

	/**
	 * Set id_carrier to 0 (no shipping price)
	 */
	protected function setNoCarrier()
	{
		$this->context->cart->setDeliveryOption(null);
		$this->context->cart->update();
	}

	/**
	 * Decides what the default carrier is and update the cart with it
	 *
	 * @todo this function must be modified - id_carrier is now delivery_option
	 * 
	 * @param array $carriers
	 * 
	 * @deprecated since 1.5.0
	 * 
	 * @return number the id of the default carrier
	 */
	protected function setDefaultCarrierSelection($carriers)
	{
		if (!$this->context->cart->getDeliveryOption(null, true))		
			$this->context->cart->setDeliveryOption($this->context->cart->getDeliveryOption());
	}

	/**
	 * Decides what the default carrier is and update the cart with it
	 *
	 * @param array $carriers
	 * 
	 * @deprecated since 1.5.0
	 * 
	 * @return number the id of the default carrier
	 */
	protected function _setDefaultCarrierSelection($carriers)
	{
		$this->context->cart->id_carrier = Carrier::getDefaultCarrierSelection($carriers, (int)$this->context->cart->id_carrier);

		if ($this->context->cart->update())
			return $this->context->cart->id_carrier;
		return 0;
	}

}

