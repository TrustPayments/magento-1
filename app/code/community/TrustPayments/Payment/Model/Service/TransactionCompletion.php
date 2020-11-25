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
 * This service provides functions to deal with Trust Payments transaction completions.
 */
class TrustPayments_Payment_Model_Service_TransactionCompletion extends TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * The transaction completion API service.
     *
     * @var \TrustPayments\Sdk\Service\TransactionCompletionService
     */
    protected $_transactionCompletionService;

    /**
     * Completes a transaction completion.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \TrustPayments\Sdk\Model\TransactionCompletion
     */
    public function complete(Mage_Sales_Model_Order_Payment $payment)
    {
        return $this->getTransactionCompletionService()->completeOnline(
            $payment->getOrder()
                ->getTrustpaymentsSpaceId(), $payment->getOrder()
                ->getTrustpaymentsTransactionId());
    }

    /**
     * Returns the transaction completion API service.
     *
     * @return \TrustPayments\Sdk\Service\TransactionCompletionService
     */
    protected function getTransactionCompletionService()
    {
        if ($this->_transactionCompletionService == null) {
            $this->_transactionCompletionService = new \TrustPayments\Sdk\Service\TransactionCompletionService(
                $this->getHelper()->getApiClient());
        }

        return $this->_transactionCompletionService;
    }
}