<?php
 
class ContactController extends ContactControllerCore
{
    public function postProcess()
    {
        if (Tools::isSubmit('submitMessage'))
        {
            $fileAttachment = null;
            if (isset($_FILES['fileUpload']['name']) && !empty($_FILES['fileUpload']['name']) && !empty($_FILES['fileUpload']['tmp_name']))
            {
                $extension = array('.txt', '.rtf', '.doc', '.docx', '.pdf', '.zip', '.png', '.jpeg', '.gif', '.jpg');
                $filename = uniqid().substr($_FILES['fileUpload']['name'], -5);
                $fileAttachment['content'] = file_get_contents($_FILES['fileUpload']['tmp_name']);
                $fileAttachment['name'] = $_FILES['fileUpload']['name'];
                $fileAttachment['mime'] = $_FILES['fileUpload']['type'];
            }
            $message = Tools::getValue('message'); // Html entities is not usefull, iscleanHtml check there is no bad html tags.
            if (!($from = trim(Tools::getValue('from'))) || !Validate::isEmail($from))
                $this->errors[] = Tools::displayError('Invalid email address.');
            else if (!$message)
                $this->errors[] = Tools::displayError('The message cannot be blank.');
            else if (!Validate::isCleanHtml($message))
                $this->errors[] = Tools::displayError('Invalid message');
            else if (!($id_contact = (int)(Tools::getValue('id_contact'))) || !(Validate::isLoadedObject($contact = new Contact($id_contact, $this->context->language->id))))
                $this->errors[] = Tools::displayError('Please select a subject from the list provided. ');
            else if (!empty($_FILES['fileUpload']['name']) && $_FILES['fileUpload']['error'] != 0)
                $this->errors[] = Tools::displayError('An error occurred during the file-upload process.');
            else if (!empty($_FILES['fileUpload']['name']) && !in_array(substr($_FILES['fileUpload']['name'], -4), $extension) && !in_array(substr($_FILES['fileUpload']['name'], -5), $extension))
                $this->errors[] = Tools::displayError('Bad file extension');
            else
            {
                $customer = $this->context->customer;
                if (!$customer->id)
                    $customer->getByEmail($from);
 
                $contact = new Contact($id_contact, $this->context->language->id);
 
                if (!((
                        ($id_customer_thread = (int)Tools::getValue('id_customer_thread'))
                        && (int)Db::getInstance()->getValue('
                        SELECT cm.id_customer_thread FROM '._DB_PREFIX_.'customer_thread cm
                        WHERE cm.id_customer_thread = '.(int)$id_customer_thread.' AND cm.id_shop = '.(int)$this->context->shop->id.' AND token = \''.pSQL(Tools::getValue('token')).'\'')
                    ) || (
                        $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($from, (int)Tools::getValue('id_order'))
                    )))
                {
                    $fields = Db::getInstance()->executeS('
                    SELECT cm.id_customer_thread, cm.id_contact, cm.id_customer, cm.id_order, cm.id_product, cm.email
                    FROM '._DB_PREFIX_.'customer_thread cm
                    WHERE email = \''.pSQL($from).'\' AND cm.id_shop = '.(int)$this->context->shop->id.' AND ('.
                        ($customer->id ? 'id_customer = '.(int)($customer->id).' OR ' : '').'
                        id_order = '.(int)(Tools::getValue('id_order')).')');
                    $score = 0;
                    foreach ($fields as $key => $row)
                    {
                        $tmp = 0;
                        if ((int)$row['id_customer'] && $row['id_customer'] != $customer->id && $row['email'] != $from)
                            continue;
                        if ($row['id_order'] != 0 && Tools::getValue('id_order') != $row['id_order'])
                            continue;
                        if ($row['email'] == $from)
                            $tmp += 4;
                        if ($row['id_contact'] == $id_contact)
                            $tmp++;
                        if (Tools::getValue('id_product') != 0 && $row['id_product'] == Tools::getValue('id_product'))
                            $tmp += 2;
                        if ($tmp >= 5 && $tmp >= $score)
                        {
                            $score = $tmp;
                            $id_customer_thread = $row['id_customer_thread'];
                        }
                    }
                }
                $old_message = Db::getInstance()->getValue('
                    SELECT cm.message FROM '._DB_PREFIX_.'customer_message cm
                    LEFT JOIN '._DB_PREFIX_.'customer_thread cc on (cm.id_customer_thread = cc.id_customer_thread)
                    WHERE cc.id_customer_thread = '.(int)($id_customer_thread).' AND cc.id_shop = '.(int)$this->context->shop->id.'
                    ORDER BY cm.date_add DESC');
                if ($old_message == $message)
                {
                    $this->context->smarty->assign('alreadySent', 1);
                    $contact->email = '';
                    $contact->customer_service = 0;
                }
 
                if ($contact->customer_service)
                {
                    if ((int)$id_customer_thread)
                    {
                        $ct = new CustomerThread($id_customer_thread);
                        $ct->status = 'open';
                        $ct->id_lang = (int)$this->context->language->id;
                        $ct->id_contact = (int)($id_contact);
                        if ($id_order = (int)Tools::getValue('id_order'))
                            $ct->id_order = $id_order;
                        if ($id_product = (int)Tools::getValue('id_product'))
                            $ct->id_product = $id_product;
                         $ct->order_t = Tools::getValue('order_t');
                         $ct->phone= Tools::getValue('phone');
                $ct->update();
                    }
                    else
                    {
                        $ct = new CustomerThread();
                        if (isset($customer->id))
                            $ct->id_customer = (int)($customer->id);
                        $ct->id_shop = (int)$this->context->shop->id;
                        if ($id_order = (int)Tools::getValue('id_order'))
                            $ct->id_order = $id_order;
                        if ($id_product = (int)Tools::getValue('id_product'))
                            $ct->id_product = $id_product;
                        $ct->id_contact = (int)($id_contact);
                        $ct->id_lang = (int)$this->context->language->id;
                        $ct->email = $from;
                        $ct->status = 'open';
                        $ct->token = Tools::passwdGen(12);
                        $ct->order_t = Tools::getValue('order_t');
                        $ct->phone = Tools::getValue('phone');
                        $ct->add();
                    }
 
                    if ($ct->id)
                    {
                        $cm = new CustomerMessage();
                        $cm->id_customer_thread = $ct->id;
                        $cm->message = Tools::htmlentitiesUTF8($message);
                        if (isset($filename) && rename($_FILES['fileUpload']['tmp_name'], _PS_MODULE_DIR_.'../upload/'.$filename))
                            $cm->file_name = $filename;
                        $cm->ip_address = ip2long($_SERVER['REMOTE_ADDR']);
                        $cm->user_agent = $_SERVER['HTTP_USER_AGENT'];
                        if (!$cm->add())
                            $this->errors[] = Tools::displayError('An error occurred while sending the message.');
                    }
                    else
                        $this->errors[] = Tools::displayError('An error occurred while sending the message.');
                }
 
                if (!count($this->errors))
                {
                    $var_list = array(
                                    '{order_name}' => '-',
                                    '{attached_file}' => '-',
                                    '{message}' => Tools::nl2br(stripslashes($message)),
                                    '{email}' =>  $from,
                                    '{order_t}' =>  (isset($ct) && $ct->order_t) ? $ct->order_t : '',
                                    '{phone}' =>  (isset($ct) && $ct->phone) ? $ct->phone : ''

                                );
 
                    if (isset($filename))
                        $var_list['{attached_file}'] = $_FILES['fileUpload']['name'];
 
                    $id_order = (int)Tools::getValue('id_order');
                     
                    if (isset($ct) && Validate::isLoadedObject($ct))
                    {
                        if ($ct->id_order)
                            $id_order = $ct->id_order;
                        $subject = sprintf(Mail::l('Your message has been correctly sent #ct%1$s #tc%2$s'), $ct->id, $ct->token);
                    }
                    else
                        $subject = Mail::l('Your message has been correctly sent');
 
                    if ($id_order)
                    {
                        $order = new Order((int)$id_order);
                        $var_list['{order_name}'] = $order->getUniqReference();
                        $var_list['{id_order}'] = $id_order;
                    }
                     
                    if (empty($contact->email))
                        Mail::Send($this->context->language->id, 'contact_form', $subject, $var_list, $from, null, null, null, $fileAttachment);
                    else
                    {                   
                        if (!Mail::Send($this->context->language->id, 'contact', Mail::l('Message from contact form').' [no_sync]',
                            $var_list, $contact->email, $contact->name, $from, ($customer->id ? $customer->firstname.' '.$customer->lastname : ''),
                                    $fileAttachment) ||
                                !Mail::Send($this->context->language->id, 'contact_form', $subject, $var_list, $from, null, $contact->email, $contact->name, $fileAttachment))
                                    $this->errors[] = Tools::displayError('An error occurred while sending the message.');
                    }
                }
                 
                if (count($this->errors) > 1)
                    array_unique($this->errors);
                else
                    $this->context->smarty->assign('confirmation', 1);
            }
        }
    }
 
}