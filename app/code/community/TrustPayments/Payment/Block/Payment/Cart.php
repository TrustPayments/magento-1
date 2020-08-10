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
 * This block extends the cart to be able to collect device data.
 */
class TrustPayments_Payment_Block_Payment_Cart extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('trustpayments/payment/cart.phtml');
    }

    /**
     * Returns the URL to Trust Payments's Javascript library to collect customer data.
     *
     * @return string
     */
    public function getDeviceJavascriptUrl()
    {
        /* @var TrustPayments_Payment_Helper_Data $helper */
        $helper = Mage::helper('trustpayments_payment');
        return $helper->getDeviceJavascriptUrl();
    }
}