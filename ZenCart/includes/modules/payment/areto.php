<?php
/**
 * include nessecary classes
 */
 include_once(DIR_WS_MODULES.'payment/areto/CreditCard.php');
 include_once(DIR_WS_MODULES.'payment/areto/FullNameParser.php');
 include_once(DIR_WS_MODULES.'payment/areto/Areto.php');
 
class areto extends base {
  /**
   * $code determines the internal 'code' name used to designate "this" payment module
   *
   * @var string
   */
  var $code;
  /**
   * $title is the displayed name for this payment method
   *
   * @var string
   */
  var $title;
  /**
   * $description is a soft name for this payment method
   *
   * @var string
   */
  var $description;
  /**
   * $enabled determines whether this module shows or not... in catalog.
   *
   * @var boolean
   */
  var $enabled;
  /**
   * log file folder
   *
   * @var string
   */
  var $_logDir = '';
  /**
   * vars
   */

  var $order_status;


  /**
   * @return authorizenet
   */
  function areto() {
    global $order;

    $this->code = 'areto';

    if (defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG == 1) { 
        $this->title = MODULE_PAYMENT_ARETO_TEXT_ADMIN_TITLE;
    } else { 
        $this->title = MODULE_PAYMENT_ARETO_TEXT_CATALOG_TITLE; // Payment module title in Admin
    }
    
    $this->description = MODULE_PAYMENT_ARETO_TEXT_DESCRIPTION;
    $this->enabled = ((MODULE_PAYMENT_ARETO_STATUS == 'True') ? true : false);
    $this->sort_order = MODULE_PAYMENT_ARETO_SORT_ORDER;

    if ((int)MODULE_PAYMENT_ARETO_ORDER_STATUS_ID > 0) {
      $this->order_status = MODULE_PAYMENT_ARETO_ORDER_STATUS_ID;
    }

    if (is_object($order)) $this->update_status();

    $this->_logDir = defined('DIR_FS_LOGS') ? DIR_FS_LOGS : DIR_FS_SQL_CACHE;

  }

  // class methods
  /**
   * Calculate zone matches and flag settings to determine whether this module should display to customers or not
   */
  function update_status() {
    global $order, $db;

    if ($this->enabled && (int)MODULE_PAYMENT_ARETO_ZONE > 0 && isset($order->billing['country']['id'])) {
      $check_flag = false;
      $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_ARETO_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
      while (!$check->EOF) {
        if ($check->fields['zone_id'] < 1) {
          $check_flag = true;
          break;
        } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
          $check_flag = true;
          break;
        }
        $check->MoveNext();
      }

      if ($check_flag == false) {
        $this->enabled = false;
      }
    }
  }
  /**
   * JS validation which does error-checking of data-entry if this module is selected for use
   * (Number, Owner Lengths)
   *
   * @return string
   */
  function javascript_validation() {
    if ($this->gateway_mode == 'offsite') return '';
    $js = '  if (payment_value == "' . $this->code . '") {' . "\n" .
          '    var cc_owner = document.checkout_payment.areto_cc_owner.value;' . "\n" .
          '    var cc_number = document.checkout_payment.areto_cc_number.value;' . "\n";
    if (MODULE_PAYMENT_ARETO_USE_CVV == 'True')  {
      $js .= '    var cc_cvv = document.checkout_payment.areto_cc_cvv.value;' . "\n";
    }
    $js .= '    if (cc_owner == "" || cc_owner.length < 10) {' . "\n" .
          '      error_message = error_message + "Please enter Cardholder full name ";' . "\n" .
          '      error = 1;' . "\n" .
          '    }' . "\n" .
          '    if (cc_number == "" || cc_number.length < 12) {' . "\n" .
          '      error_message = error_message + "Please enter card number ";' . "\n" .
          '      error = 1;' . "\n" .
          '    }' . "\n";
    if (MODULE_PAYMENT_ARETO_USE_CVV == 'True')  {
      $js .= '    if (cc_cvv == "" || cc_cvv.length < "3" || cc_cvv.length > "4") {' . "\n".
      '      error_message = error_message + "Please enter CVV code";' . "\n" .
      '      error = 1;' . "\n" .
      '    }' . "\n" ;
    }
    $js .= '  }' . "\n";
    return $js;
  }
  /**
   * Display Credit Card Information Submission Fields on the Checkout Payment Page
   *
   * @return array
   */
  function selection() {
    global $order;

    for ($i=1; $i<13; $i++) {
      $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B - (%m)',mktime(0,0,0,$i,1,2000)));
    }

    $today = getdate();
    for ($i=$today['year']; $i < $today['year']+10; $i++) {
      $expires_year[] = array('id' => strftime('%y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
    }

    $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

    if ($this->gateway_mode == 'offsite') {
      $selection = array('id' => $this->code,
                         'module' => $this->title);
    } else {
      $selection = array('id' => $this->code,
                         'module' => $this->title,
                         'fields' => array(array('title' => MODULE_PAYMENT_ARETO_TEXT_CREDIT_CARD_OWNER,
                                                 'field' => zen_draw_input_field('areto_cc_owner', $order->billing['firstname'] . ' ' . $order->billing['lastname'], 'id="'.$this->code.'-cc-owner"' . $onFocus . ' autocomplete="off"'),
                                                 'tag' => $this->code.'-cc-owner'),
                                         array('title' => MODULE_PAYMENT_ARETO_TEXT_CREDIT_CARD_NUMBER,
                                               'field' => zen_draw_input_field('areto_cc_number', '', 'id="'.$this->code.'-cc-number"' . $onFocus . ' autocomplete="off"'),
                                               'tag' => $this->code.'-cc-number'),
                                         array('title' => MODULE_PAYMENT_ARETO_TEXT_CREDIT_CARD_EXPIRES,
                                               'field' => zen_draw_pull_down_menu('areto_cc_expires_month', $expires_month, strftime('%m'), 'id="'.$this->code.'-cc-expires-month"' . $onFocus) . '&nbsp;' . zen_draw_pull_down_menu('areto_cc_expires_year', $expires_year, '', 'id="'.$this->code.'-cc-expires-year"' . $onFocus),
                                               'tag' => $this->code.'-cc-expires-month')));
      if (MODULE_PAYMENT_ARETO_USE_CVV == 'True') {
        $selection['fields'][] = array('title' => MODULE_PAYMENT_ARETO_TEXT_CVV,
                                       'field' => zen_draw_input_field('areto_cc_cvv', '', 'size="4" maxlength="4"' . ' id="'.$this->code.'-cc-cvv"' . $onFocus . ' autocomplete="off"') . ' ' . '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_POPUP_CVV_HELP) . '\')">' . MODULE_PAYMENT_ARETO_TEXT_POPUP_CVV_LINK . '</a>',
                                       'tag' => $this->code.'-cc-cvv');
      }
    }
    return $selection;
  }
  /**
   * Evaluates the Credit Card Type for acceptance and the validity of the Credit Card Number & Expiration Date
   *
   */
  function pre_confirmation_check() { 
    global $messageStack;
    
    if (isset($_POST['areto_cc_number'])) {
      include(DIR_WS_CLASSES . 'cc_validation.php');

      $cc_validation = new cc_validation();
      $result = $cc_validation->validate($_POST['areto_cc_number'], $_POST['areto_cc_expires_month'], $_POST['areto_cc_expires_year']);
      $error = '';
      switch ($result) {
        case -1:
        $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
        break;
        case -2:
        case -3:
        case -4:
        $error = TEXT_CCVAL_ERROR_INVALID_DATE;
        break;
        case false:
        $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
        break;
      }

      if ( ($result == false) || ($result < 1) ) {
        $messageStack->add_session('checkout_payment', $error . '<!-- ['.$this->code.'] -->', 'error');
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
      }

      $this->cc_card_type = $cc_validation->cc_type;
      $this->cc_card_number = $cc_validation->cc_number;
      $this->cc_expiry_month = $cc_validation->cc_expiry_month;
      $this->cc_expiry_year = $cc_validation->cc_expiry_year;
      
      $CreditCard = new CreditCard();

        $card = $CreditCard->validCreditCard($_POST['areto_cc_number']);
        if (!$card['valid']) {
            $messageStack->add_session('checkout_payment', 'Credit card number is invalid', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
      
    }

  }
  /**
   * Display Credit Card Information on the Checkout Confirmation Page
   *
   * @return array
   */
  function confirmation() {
    if (isset($_POST['areto_cc_number'])) {
      $confirmation = array('title' => $this->title . ': ' . $this->cc_card_type,
                            'fields' => array(array('title' => MODULE_PAYMENT_ARETO_TEXT_CREDIT_CARD_OWNER,
                                                    'field' => $_POST['areto_cc_owner']),
                                              array('title' => MODULE_PAYMENT_ARETO_TEXT_CREDIT_CARD_NUMBER,
                                                    'field' => substr($this->cc_card_number, 0, 4) . str_repeat('X', (strlen($this->cc_card_number) - 8)) . substr($this->cc_card_number, -4)),
                                              array('title' => MODULE_PAYMENT_ARETO_TEXT_CREDIT_CARD_EXPIRES,
                                                    'field' => strftime('%B, %Y', mktime(0,0,0,$_POST['areto_cc_expires_month'], 1, '20' . $_POST['areto_cc_expires_year'])))));
    } else {
      $confirmation = array(); //array('title' => $this->title);
    }
    return $confirmation;
  }
  /**
   * Build the data and actions to process when the "Submit" button is pressed on the order-confirmation screen.
   * This sends the data to the payment gateway for processing.
   * (These are hidden fields on the checkout confirmation page)
   *
   * @return string
   */
  function process_button() { //echo 'process button'; exit();
    global $order;

    $process_button_string = zen_draw_hidden_field('cc_owner', $_POST['areto_cc_owner']) .
                             zen_draw_hidden_field('cc_expires', $this->cc_expiry_month . substr($this->cc_expiry_year, -2)) .
                             zen_draw_hidden_field('cc_type', $this->cc_card_type) .
                             zen_draw_hidden_field('cc_number', $this->cc_card_number);
    //if (MODULE_PAYMENT_ARETO_USE_CVV == 'True') {
      $process_button_string .= zen_draw_hidden_field('cc_cvv', $_POST['areto_cc_cvv']);
    //}
    $process_button_string .= zen_draw_hidden_field(zen_session_name(), zen_session_id());

    return $process_button_string;

    return false;
  }
  /**
   * Store the CC info to the order and process any results that come back from the payment gateway
   *
   */
  function before_process() { 
    global $messageStack, $order, $db;
    
    if (isset($_GET['iod']) && $_GET['iod'] > 0) {
        
        $areto = new AretoClass();
    
        // Set IDs
        $areto->setEnvironment(MODULE_PAYMENT_ARETO_API_KEY, MODULE_PAYMENT_ARETO_SESSION_KEY);
        
        $internal_order_id = $_COOKIE['internal_order_id'];
        $result = $areto->status_request($internal_order_id);

        // Check request is failed
        if ((int)$result['Result']['Code'] !== 1) {
            //echo sprintf('Status Check is failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']);
            //exit();
            $messageStack->add_session('checkout_payment', sprintf('Status Check is failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']), 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
        
        switch ((int)$result['Body']['OrderStatus']) {
            case 0:
                // Payment has been declined
                //echo 'Payment has been declined';
                $messageStack->add_session('checkout_payment', 'Payment has been declined', 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            break;
            
            case 1;
        	  // Payment is success
                //echo sprintf('Payment success: %s. InternalOrderID: %s', $result['Body']['OrderDescription'], $result['Body']['InternalOrderID']);
                
                $this->InternalOrderID = $result['Body']['InternalOrderID'];
                $this->OrderDescription = $result['Body']['OrderDescription'];
                
                return true;
            
           break;
           
            case 4:
              // pending
            default:
                //echo 'Unknown order status';
                $messageStack->add_session('checkout_payment', 'Unknown order status', 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            break;
            
        }
        
    } else {
    
        $CreditCard = new CreditCard();
    
        $card = $CreditCard->validCreditCard($_POST['cc_number']);
        if (!$card['valid']) {
            $messageStack->add_session('checkout_payment', 'Credit card number is invalid', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
        
        $types = array(
            'visaelectron' => 'VisaElectron',
            'maestro' => 'MAES',
            'visa' => 'VISA',
            'mastercard' => 'MC',
            'amex' => 'AMEX',
            'dinersclub' => 'DINER',
            'discover' => 'DISC',
            'unionpay' => 'CUP',
            'jcb' => 'JCB',
        );
        $type = isset($types[$card['type']]) ? $types[$card['type']] : strtoupper($card['type']);
    
        // Parse name field
        $parser = new FullNameParser();
        $name = $parser->parse_name($_POST['cc_owner']);
    
        $areto = new AretoClass();
    
        // Set IDs
        $areto->setEnvironment(MODULE_PAYMENT_ARETO_API_KEY, MODULE_PAYMENT_ARETO_SESSION_KEY);
    
        $sql = "SELECT MAX(orders_id) as max_id FROM ". TABLE_ORDERS;
        $max_result = $db->execute($sql);
//echo '<pre>'; print_r($order); echo '</pre>'; exit(); 

        $items_array = array();
        for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
            $items_array[] =  urlencode($order->products[$i]['model']).','.urlencode($order->products[$i]['qty']).','.urlencode($order->products[$i]['name']);
        }
   
        $data = array(
            'order_id' => $max_result->fields['max_id'] + 1,
            'items' => implode('|', $items_array),
            'amount' => number_format($order->info['total'], 2),
            'currency_code' => $order->info['currency'],
            'CVC' => $_POST['cc_cvv'],
            'expiry_month' => substr($_POST['cc_expires'], 0, 2),
            'expiry_year' => '20'.substr($_POST['cc_expires'], 2, 2),
            'name' => $name['fname'],
            'surname' => $name['lname'],
            'number' => $_POST['cc_number'],
            'type' => $type,
            'address' => $order->customer['street_address'],
            'client_city' => $order->customer['city'],
            'client_country_code' => $order->customer['country']['iso_code_2'],
            'client_zip' => $order->customer['postcode'],
            'client_state' => $order->customer['state'],
            'client_email' => $order->customer['email_address'],
            'client_external_identifier' => '',
            'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'client_forward_IP' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
            'client_DOB' => '',
            'client_phone' => $order->customer['telephone'],
            'token' => '',
            'create_token' => '0',
            'return_url' => urlencode(zen_href_link(FILENAME_CHECKOUT_PROCESS, 'areto=3ds', 'SSL'))
            //'return_url' => (ENABLE_SSL == 'true' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG . 'areto_redirect.php'
        );

        $result = $areto->sale_request($data);
        
        if (!is_array($result) || !isset($result['Result'])) {
            //throw new Exception('Unable process request: invalid response');
            $messageStack->add_session('checkout_payment', 'Unable process request: invalid response', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
        
        switch ((int)$result['Result']['Code']) {
            case 0:
                // Payment is failed
                //echo sprintf('Payment failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']);
                $messageStack->add_session('checkout_payment', sprintf('Payment failed: %s Code: %s', $result['Result']['Description'], $result['Result']['Code']), 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            break;
            
            case 1:
                // Payment is success
                //echo sprintf('Payment success: %s. Internal OrderID: %s', $result['Result']['Description'], $result['Body']['InternalOrderID']);
                $this->InternalOrderID = $result['Body']['InternalOrderID'];
                $this->transResult = $result['Result']['Description'];
            break;
            
            case 4:
                // Payment require 3D-Secure
                // Save InternalOrderID value
                setcookie('internal_order_id', $result['Body']['InternalOrderID']);
        
                // 3D-Secure with params
                if (count($result['Redirect']) > 0) {
                    $url = urldecode($result['Redirect']['RedirectLink']);
                    $method = !empty($result['Redirect']['Method']) ? $result['Redirect']['Method'] : 'POST';
                    $params = $result['Redirect']['Parameters'];
        
                    echo '<br /><strong>Redirect to payment gateway...</strong>';
                    echo sprintf('<form id="areto_checkout" action="%s" method="%s">', $url, $method);
                    foreach ($params as $key => $value) {
                        echo sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
                    }
                    echo '</form>';
                    echo '<script>document.getElementById(\'areto_checkout\').submit();</script>';
                    exit();
                }
            break;
            
            default:
                //echo sprintf('Error: %s. Code: %s', $result['Result']['Description'], $result['Result']['Code']);
                $messageStack->add_session('checkout_payment', sprintf('Error: %s. Code: %s', $result['Result']['Description'], $result['Result']['Code']), 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            break;
            
        }
    }
    
    //$messageStack->add_session('checkout_payment', MODULE_PAYMENT_ARETO_TEXT_ERROR_MESSAGE, 'error');
    //zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
  }
  /**
   * Post-processing activities
   *
   * @return boolean
   */
  function after_process() {
    global $insert_id, $db;

    $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values (:orderComments, :orderID, :orderStatus, -1, now() )";
    $sql = $db->bindVars($sql, ':orderComments', 'Areto payment info:  InternalOrderID: ' . $this->InternalOrderID . '. Description: ' . $this->OrderDescription . '', 'string');
    $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
    $sql = $db->bindVars($sql, ':orderStatus', $this->order_status, 'integer');
    $db->Execute($sql);
    
    return false;
  }
  /**
   * Check to see whether module is installed
   *
   * @return boolean
   */
  function check() {
    global $db;
    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_ARETO_STATUS'");
      $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }
  /**
   * Install the payment module and its configuration settings
   *
   */
  function install() {
    global $db, $messageStack;
    if (defined('MODULE_PAYMENT_ARETO_STATUS')) {
      $messageStack->add_session('Areto module already installed.', 'error');
      zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=areto', 'NONSSL'));
      return 'failed';
    }
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Areto Module', 'MODULE_PAYMENT_ARETO_STATUS', 'True', 'Do you want to accept Areto payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Key', 'MODULE_PAYMENT_ARETO_API_KEY', 'Enter API Key', 'The API Key used for the Areto service', '6', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('Session Key', 'MODULE_PAYMENT_ARETO_SESSION_KEY', 'Enter Session Key', 'Session Key used the Areto service', '6', '0', now(), 'zen_cfg_password_display')");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Request CVV Number', 'MODULE_PAYMENT_ARETO_USE_CVV', 'False', 'Do you want to ask the customer for the card\'s CVV number', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_ARETO_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_ARETO_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_ARETO_ORDER_STATUS_ID', '1', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
  }
  /**
   * Remove the module and all its settings
   *
   */
  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
  }
  /**
   * Internal list of configuration keys used for configuration of the module
   *
   * @return array
   */
  function keys() {
    return array('MODULE_PAYMENT_ARETO_STATUS', 'MODULE_PAYMENT_ARETO_API_KEY', 'MODULE_PAYMENT_ARETO_SESSION_KEY', 'MODULE_PAYMENT_ARETO_USE_CVV', 'MODULE_PAYMENT_ARETO_ZONE', 'MODULE_PAYMENT_ARETO_ORDER_STATUS_ID', 'MODULE_PAYMENT_ARETO_SORT_ORDER');
  }

}
