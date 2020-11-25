<?php

/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * The block renders the payment form in the checkout.
 */
class TrustPayments_Payment_Block_Payment_Form extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('trustpayments/payment/form.phtml');
    }

    /**
     * Returns the URL to the payment method image.
     *
     * @return string
     */
    public function getImageUrl()
    {
        /* @var TrustPayments_Payment_Model_Payment_Method_Abstract $methodInstance */
        $methodInstance = $this->getMethod();
        $spaceId = $methodInstance->getPaymentMethodConfiguration()->getSpaceId();
        $spaceViewId = Mage::getStoreConfig('trustpayments_payment/general/space_view_id');
        $language = Mage::getStoreConfig('general/locale/code');
        /* @var TrustPayments_Payment_Helper_Data $helper */
        $helper = $this->helper('trustpayments_payment');
        return $helper->getResourceUrl($methodInstance->getPaymentMethodConfiguration()
            ->getImage(), $language, $spaceId, $spaceViewId);
    }

    /**
     * Returns the list of tokens that can be applied.
     *
     * @return TrustPayments_Payment_Model_Entity_TokenInfo[]
     */
    public function getTokens()
    {
        /* @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();

        /* @var TrustPayments_Payment_Model_Payment_Method_Abstract $methodInstance */
        $methodInstance = $this->getMethod();
        $spaceId = $methodInstance->getPaymentMethodConfiguration()->getSpaceId();

        /* @var TrustPayments_Payment_Model_Resource_TokenInfo_Collection $collection */
        $collection = Mage::getModel('trustpayments_payment/entity_tokenInfo')->getCollection();
        $collection->addCustomerFilter($quote->getCustomerId());
        $collection->addSpaceFilter($spaceId);
        $collection->addPaymentMethodConfigurationFilter($methodInstance->getPaymentMethodConfigurationId());
        return $collection->getItems();
    }
}