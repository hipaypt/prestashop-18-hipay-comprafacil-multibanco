<?php
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/comprafacil.php');

global $kernel;
if(!$kernel){ 
  require_once _PS_ROOT_DIR_.'/app/AppKernel.php';
  $kernel = new \AppKernel('prod', false);
  $kernel->boot(); 
}

$id_order = (int)stripslashes($_GET["id_order"]);

if (!$id_order) {
    die(Tools::displayError('ID encomenda inválido.'));
}

$db = Db::getInstance();
$result = $db->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'comprafacil` WHERE `id_order` ="'.$id_order.'";');
$multibanco = $result[0];

if (!$multibanco) {
    die(Tools::displayError('ID encomenda inválido.'));
}

if ($multibanco["payed"] == 1) {
    die(Tools::displayError('Pagamento ja efectuado.'));
}

$compraFacil = new CompraFacil();
$payed = $compraFacil->confirmPayment($multibanco["reference"]);
if (!$payed) { 
    die(Tools::displayError("A referência ".$multibanco["reference"]." ainda não foi paga."));
}

$order = new Order($id_order);
$orderHistory = new OrderHistory();
$orderHistory->id_order = $id_order;
$orderHistory->changeIdOrderState(Configuration::get('COMPRAFACIL_STATE_PAID'), $id_order);
$orderHistory->addWithemail();

$db = Db::getInstance();
$db->execute("UPDATE `"._DB_PREFIX_."comprafacil` SET `payed` = 1, `payment_date` = '".date("Y-m-d H:i:s")."' WHERE `id_order` = ".$id_order); 

$template_name = 'wfxcomprafacil_confirm';
$title = 'Pagamento Confirmado';
$from = Configuration::get('PS_SHOP_EMAIL');
$fromName = Configuration::get('PS_SHOP_NAME');
$mailDir = dirname(__FILE__).'/mails/';
$orderReference = $compraFacil->getOrderReference($id_order);
$templateVars = array(
    '{order_name}' => $orderReference
);
Mail::Send($order->id_lang, $template_name, $title, $templateVars, $from, $fromName, $from, $fromName, NULL, NULL, $mailDir);

exit();