<?php

/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * Handles the dynamic payment method configs.
 */
class TrustPayments_Payment_Model_Core_Config extends Mage_Core_Model_Config
{
    
    public function loadDb()
    {
        parent::loadDb();
        
        Mage::getModel('trustpayments_payment/system_config')->initConfigValues();
    }
    
}