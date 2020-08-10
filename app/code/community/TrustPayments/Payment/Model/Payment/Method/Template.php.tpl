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

class TrustPayments_Payment_Model_PaymentMethod{id} extends TrustPayments_Payment_Model_Payment_Method_Abstract
{
    protected $_code = 'trustpayments_payment_{id}';
    
    protected $_paymentMethodConfigurationId = {id};
}