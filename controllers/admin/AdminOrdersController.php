<?php
class AdminOrdersController extends AdminOrdersControllerCore
{
    public function renderForm()
    {
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'booking_reservationdetails WHERE id_cart = '.(int)Tools::getValue('id_cart');
        $passengers = Db::getInstance()->executeS($sql);
        
        $this->context->smarty->assign('passengers',$passengers);
        
        parent::renderForm();
    }
}