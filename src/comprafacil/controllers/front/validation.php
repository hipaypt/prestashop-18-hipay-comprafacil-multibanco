<?php
/*
 * Created on 2017
 * Author: David Soares
 *
 * Copyright (c) 2017 Mindshaker
 */

class ComprafacilValidationModuleFrontController extends ModuleFrontController
{

	public function postProcess()
	{
		$cart = $this->context->cart;
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'comprafacil')
			{
				$authorized = true;
				break;
			}
		if (!$authorized)
			die('Este método de pagamento não está disponível.');

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			Tools::redirect('index.php?controller=order&step=1');

		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);

		$this->module->validateOrder($cart->id, Configuration::get('COMPRAFACIL_STATE_WAITING'), $total, $this->module->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);

		$comprafacil = new Comprafacil();
		$multibanco = $comprafacil->generateReferenceMB($cart, $customer);

		if ($multibanco) {
			$orderReference = $comprafacil->getOrderReference($cart->id);
			$templateVars = array(
				'{order_name}' => $orderReference,
				'{firstname}' => $customer->firstname,
				'{lastname}' => $customer->lastname,
				'{cf_entity}' => $multibanco->entity,
				'{cf_reference}' => $multibanco->reference,
				'{cf_value}' => $multibanco->amountOut.' €'
			);
						
			$template_name = 'wfxcomprafacil_data';
			$title = 'Dados para Pagamento Multibanco';
			$from = Configuration::get('PS_SHOP_EMAIL');
			$fromName = Configuration::get('PS_SHOP_NAME');
			$mailDir = dirname(__FILE__).'/mails/';
			$send = Mail::Send($cart->id_lang, $template_name, $title, $templateVars, $customer->email, $customer->firstname.' '.$customer->lastname, $from, $fromName, NULL, NULL, $mailDir);
		}

		Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
	}
}
