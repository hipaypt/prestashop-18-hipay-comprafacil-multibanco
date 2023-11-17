<?php
/*
 * Created on 2017
 * Author: David Soares
 *
 * Copyright (c) 2017 Mindshaker
 */

class ComprafacilPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function initContent()
	{
		parent::initContent();

		$cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart))
			Tools::redirect('index.php?controller=order');

		$total = sprintf(
			$this->getTranslator()->trans('%1$s (tax incl.)', array(), 'Modules.Comprafacil.Shop'),
			Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH))
		);

		$this->context->smarty->assign(array(
			'back_url' => $this->context->link->getPageLink('order', true, NULL, "step=3"),
			'confirm_url' => $this->context->link->getModuleLink('comprafacil', 'validation', [], true),
			'image_url' => $this->module->getPathUri() . 'multibanco.png',
			'cust_currency' => $cart->id_currency,
			'currencies' => $this->module->getCurrency((int)$cart->id_currency),
			'total' => $total,
			'this_path' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		$this->setTemplate('payment_execution.tpl');
	}
}
