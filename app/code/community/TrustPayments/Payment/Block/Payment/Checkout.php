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
 * This block extends the checkout to be able to process Trust Payments payments.
 */
class TrustPayments_Payment_Block_Payment_Checkout extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('trustpayments/payment/checkout.phtml');
    }

    /**
     * Returns the URL to Trust Payments's JavaScript library that is necessary to display the payment form.
     *
     * @return string
     */
    public function getJavaScriptUrl()
    {
        /* @var TrustPayments_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('trustpayments_payment/service_transaction');
        /* @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        try {
            return $transactionService->getJavaScriptUrl($checkoutSession->getQuote());
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Returns the URL to Trust Payments's payment page.
     *
     * @return string
     */
    public function getPaymentPageUrl()
    {
        /* @var TrustPayments_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('trustpayments_payment/service_transaction');
        /* @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        try {
            return $transactionService->getPaymentPageUrl($checkoutSession->getQuote());
        } catch (Exception $e) {
            return false;
        }
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
    
    /**
     * Returns the URL to fetch the current information to collect the payment data.
     * 
     * @return string
     */
    public function getInformationUrl()
    {
        return $this->getUrl('trustpayments/transaction/information');
    }
}