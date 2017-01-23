<?php
 
class CustomerThread extends CustomerThreadCore
{
    public $phone;
    public $order_t;
 
    public static $definition = array(
        'table' => 'customer_thread',
        'primary' => 'id_customer_thread',
        'fields' => array(
            'id_lang' =>     array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_contact' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_shop' =>     array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_customer' =>array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_order' =>    array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'email' =>       array('type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 254),
            'token' =>       array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true),
            'status' =>  array('type' => self::TYPE_STRING),
            'date_add' =>    array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' =>    array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'order_t' =>  array('type' => self::TYPE_STRING, 'validate' => 'isGenericName'),
            'phone' =>  array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            
        ),
    );
 
 
}