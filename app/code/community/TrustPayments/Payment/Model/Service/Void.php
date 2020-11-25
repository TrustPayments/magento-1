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
 * This service provides functions to deal with Trust Payments transaction voids.
 */
class TrustPayments_Payment_Model_Service_Void extends TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * The transaction void API service.
     *
     * @var \TrustPayments\Sdk\Service\TransactionVoidService
     */
    protected $_transactionVoidService;

    /**
     * Void the transaction of the given payment.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \TrustPayments\Sdk\Model\TransactionVoid
     */
    public function void(Mage_Sales_Model_Order_Payment $payment)
    {
        return $this->getTransactionVoidService()->voidOnline(
            $payment->getOrder()
                ->getTrustpaymentsSpaceId(), $payment->getOrder()
                ->getTrustpaymentsTransactionId());
    }

    /**
     * Returns the transaction void API service.
     *
     * @return \TrustPayments\Sdk\Service\TransactionVoidService
     */
    protected function getTransactionVoidService()
    {
        if ($this->_transactionVoidService == null) {
            $this->_transactionVoidService = new \TrustPayments\Sdk\Service\TransactionVoidService(
                Mage::helper('trustpayments_payment')->getApiClient());
        }

        return $this->_transactionVoidService;
    }
}