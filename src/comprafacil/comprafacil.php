<?php
/*
 * Created on 2017
 * Author: David Soares
 *
 * Copyright (c) 2017 Mindshaker
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Comprafacil extends PaymentModule
{

    protected $_html = '';
    protected $_postErrors = array();

    public $sandbox;
    public $entity;
    public $username;
    public $password;
    public $wsURL;

    public function __construct()
    {
        $this->name = 'comprafacil';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.8.0.0', 'max' => _PS_VERSION_);
        $this->author = 'Mindshaker';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
		
        $config = Configuration::getMultiple(array('CF_SANDBOX', 'CF_ENTITY', 'CF_USERNAME', 'CF_PASSWORD'));
        if (!empty($config['CF_SANDBOX'])) {
            $this->sandbox = $config['CF_SANDBOX'];
        }
        if (!empty($config['CF_ENTITY'])) {
            $this->entity = $config['CF_ENTITY'];
        }
        if (!empty($config['CF_USERNAME'])) {
            $this->username = $config['CF_USERNAME'];
        }
        if (!empty($config['CF_PASSWORD'])) {
            $this->password = $config['CF_PASSWORD'];
        }

        $ws10241 = "https://hm.comprafacil.pt/SIBSClick/webservice/ComprafacilWS.asmx?WSDL";
        $ws11249 = "https://hm.comprafacil.pt/SIBSClick2/webservice/ComprafacilWS.asmx?WSDL";
        $ws10241_sandbox = "https://hm.comprafacil.pt/SIBSClickTeste/webservice/ComprafacilWS.asmx?WSDL";
        $ws11249_sandbox = "https://hm.comprafacil.pt/SIBSClick2Teste/webservice/ComprafacilWS.asmx?WSDL";

        if ($config['CF_SANDBOX'] == "1") {
            $this->wsURL = ($config['CF_ENTITY'] == "10241") ? $ws10241_sandbox : $ws11249_sandbox;
        } else {
            $this->wsURL = ($config['CF_ENTITY'] == "10241") ? $ws10241 : $ws11249;
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = 'Multibanco';
        $this->description = 'Aceita pagamentos por referência multibanco.';
        $this->confirmUninstall = 'Tem a certeza que quer remover?';
        if (!isset($this->username) || !isset($this->password)) {
            $this->warning = 'Tem de introduzir o user e a password do webservice comprafácil.';
        }
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = 'Este modulo de pagamento ainda não tem nenhuma moeda definida.';
        }
    }

    public function install()
    {
        $db = Db::getInstance(); 
        $query = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."comprafacil` ("
            ."`id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY ,"
            ."`id_order` INT(11) NOT NULL ,"
            ."`reference` VARCHAR(15) NOT NULL,"
            ."`entity` VARCHAR(10) NOT NULL,"
            ."`value` FLOAT NOT NULL,"
            ."`created_date` DATETIME NOT NULL,"
            ."`payment_date` DATETIME NOT NULL,"
            ."`payed` SMALLINT(1) NOT NULL DEFAULT '0'"
            .") ENGINE = MYISAM ";
        $db->execute($query);
		
		if (!Configuration::get('COMPRAFACIL_STATE_WAITING'))
		{
			$os = new OrderState();
			$os->name = array();
			foreach (Language::getLanguages(false) as $language) {
				$os->name[(int)$language['id_lang']] = 'Aguardar pagamento Multibanco';
			}
			$os->color = '#dca814';
			$os->unremovable = 1;
			if ($os->add()) {
				Configuration::updateValue('COMPRAFACIL_STATE_WAITING', $os->id);
                copy(dirname(__FILE__).'/small_logo.gif', dirname(__FILE__).'/../../img/os/'.(int)$os->id.'.gif');
			}
		}
		if (!Configuration::get('COMPRAFACIL_STATE_PAID'))
		{
			$os = new OrderState();
			$os->name = array();
			foreach (Language::getLanguages(false) as $language) {
				$os->name[(int)$language['id_lang']] = 'Confirmado pagamento por Multibanco';
			}
			$os->color = '#72ff69';
			$os->unremovable = 1;
			$os->paid = 1;
			if ($os->add()) {
				Configuration::updateValue('COMPRAFACIL_STATE_PAID', $os->id);
                copy(dirname(__FILE__).'/small_logo.gif', dirname(__FILE__).'/../../img/os/'.(int)$os->id.'.gif');
			}
		}
		
		if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('CF_SANDBOX')
                || !Configuration::deleteByName('CF_ENTITY')
                || !Configuration::deleteByName('CF_USERNAME')
                || !Configuration::deleteByName('CF_PASSWORD')
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('CF_USERNAME')) {
                $this->_postErrors[] = 'Tem de introduzir o user do webservice comprafácil.';
            } elseif (!Tools::getValue('CF_PASSWORD')) {
                $this->_postErrors[] = 'Tem de introduzir a password do webservice comprafácil.';
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('CF_SANDBOX', Tools::getValue('CF_SANDBOX'));
            Configuration::updateValue('CF_ENTITY', Tools::getValue('CF_ENTITY'));
            Configuration::updateValue('CF_USERNAME', Tools::getValue('CF_USERNAME'));        
            Configuration::updateValue('CF_PASSWORD', Tools::getValue('CF_PASSWORD'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    protected function _displayComprafacil()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayComprafacil();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->trans('Multibanco', array(), 'Modules.Comprafacil.Shop'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAdditionalInformation($this->fetch('module:comprafacil/views/templates/hook/comprafacil_intro.tpl'));
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState(); 
        $validStates = array( Configuration::get('COMPRAFACIL_STATE_WAITING'), Configuration::get('COMPRAFACIL_STATE_PAID'), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('PS_OS_OUTOFSTOCK_UNPAID') );
        if ( in_array($state, $validStates ) ) {
            $multibanco = $this->getReferenceMB($params['order']->id);
            $this->smarty->assign(array(
                'shop_name' => $this->context->shop->name,
                'total' => Tools::displayPrice($params['order']->getOrdersTotalPaid(), new Currency($params['order']->id_currency), false),
                'status' => 'ok',
                'reference' => $params['order']->reference,
                'cf_entity' => $multibanco['entity'],
                'cf_reference' => $multibanco['reference'],
                'cf_value' => $multibanco['value']
            ));
        } else {
            $this->smarty->assign(
                array(
                    'status' => 'failed'
                )
            );
        }

        return $this->fetch('module:comprafacil/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => 'Configurações',
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => 'Sandbox',
                        'name' => 'CF_SANDBOX',
                        'is_bool' => true,
                        'desc' => 'Se estiver activo irá ser usado o webservice de testes.',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => 'Sim',
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => 'Não',
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Entidade',
                        'name' => 'CF_ENTITY',
                        'options' => array(
                            'id' => 'value',
                            'name' => 'name',
                            'query' => array(
                                array('value' => 11249, 'name' => '11249'), array('value' => 10241, 'name' => '10241')
                            )
                        ),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'User',
                        'name' => 'CF_USERNAME',
                        'desc' => 'User do webservice comprafacil.',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Password',
                        'name' => 'CF_PASSWORD',
                        'desc' => 'Password do webservice comprafacil.',
                        'required' => true
                    )
                ),
                'submit' => array(
                    'title' => 'Guardar'
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }
	
	public function getConfigFieldsValues()
    {
        return array(
            'CF_SANDBOX' => Tools::getValue('CF_SANDBOX', Configuration::get('CF_SANDBOX')),
            'CF_ENTITY' => Tools::getValue('CF_ENTITY', Configuration::get('CF_ENTITY')),
            'CF_USERNAME' => Tools::getValue('CF_USERNAME', Configuration::get('CF_USERNAME')),
            'CF_PASSWORD' => Tools::getValue('CF_PASSWORD', Configuration::get('CF_PASSWORD'))
        );
    }

    public function getOrderReference($id_order)
	{
        $db = Db::getInstance();
        $result = $db->ExecuteS('SELECT reference FROM `'._DB_PREFIX_.'orders` WHERE `id_order` = '.$id_order);
        return $result[0]['reference'];
    }

    public function generateReferenceMB($cart, $customer)
	{
		$id_order = Order::getOrderByCartId($cart->id);
        //$callbackURL = Tools::getHttpHost(true).__PS_BASE_URI__."modules/comprafacil/callback.php?id_order=".$id_order;
        $callbackURL = $this->context->link->getModuleLink($this->name, 'confirmation', array("id_order" => $id_order), true);

        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        try {
            $parameters = array(
                "origin" => $callbackURL,
                "username" => $this->username, // required
                "password" => $this->password, // required
                "amount" => $total, // required
                "additionalInfo" => 'Encomenda '.$id_order,
                "name" => $customer->firstname.' '.$customer->lastname,
                "address" => '',
                "postCode" => '',
                "city" => '',
                "NIC" => '',
                "externalReference" => $id_order,
                "contactPhone" => '',
                "email" => $customer->email, // required
                "IDUserBackoffice" => -1, // required
                "timeLimitDays" => 3, // required
                "sendEmailBuyer" => false // required
            );
            $client = new SoapClient($this->wsURL);
            $multibanco = $client->getReferenceMB($parameters);
        } catch (Exception $e) {
            $multibanco = false;
        }

        if ($multibanco && $multibanco->getReferenceMBResult) {
            $db = Db::getInstance();
            $query = 'INSERT INTO `'._DB_PREFIX_.'comprafacil` (id_order, reference, entity, value, created_date) ';
            $query.= 'VALUES ('.$id_order.',"'.$multibanco->reference.'","'.$multibanco->entity.'", '.$multibanco->amountOut.', "'.date("Y-m-d H:i:s").'")';
            $db->execute($query);
            return $multibanco;
        } else {
            return false;
        }
    }

    public function getReferenceMB($id_order)
	{
        $db = Db::getInstance();
        $result = $db->ExecuteS('SELECT reference, entity, value FROM `'._DB_PREFIX_.'comprafacil` WHERE `id_order` = '.$id_order);
        return $result[0];
    }

    public function confirmPayment($reference)
	{
        try {
            $parameters = array(
                "username" => $this->username, // required
                "password" => $this->password, // required
                "reference" => $reference // required
            );
            $client = new SoapClient($this->wsURL);
            $result = $client->getInfoReference($parameters);
            if ($result->getInfoReferenceResult) {
                return $result->paid;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

}
