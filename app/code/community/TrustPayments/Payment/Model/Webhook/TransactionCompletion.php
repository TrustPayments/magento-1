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
 * Webhook processor to handle transaction completion state transitions.
 */
class TrustPayments_Payment_Model_Webhook_TransactionCompletion extends TrustPayments_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see TrustPayments_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \TrustPayments\Sdk\Model\TransactionCompletion
     */
    protected function loadEntity(TrustPayments_Payment_Model_Webhook_Request $request)
    {
        $completionService = new \TrustPayments\Sdk\Service\TransactionCompletionService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $completionService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getTransactionId($completion)
    {
        /* @var \TrustPayments\Sdk\Model\TransactionCompletion $completion */
        return $completion->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $completion)
    {
        /* @var \TrustPayments\Sdk\Model\TransactionCompletion $completion */
        switch ($completion->getState()) {
            case \TrustPayments\Sdk\Model\TransactionCompletionState::FAILED:
                $this->failed($completion->getLineItemVersion()
                    ->getTransaction(), $order);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function failed(\TrustPayments\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        $invoice = $this->getInvoiceForTransaction($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        if ($invoice != null && $invoice->getTrustpaymentsCapturePending() &&
            $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
            $invoice->setTrustpaymentsCapturePending(false);

            $authTransaction = $order->getPayment()->getAuthorizationTransaction();
            $authTransaction->setIsClosed(0);

            Mage::getModel('core/resource_transaction')->addObject($invoice)
                ->addObject($authTransaction)
                ->save();
        }
    }

    /**
     * Returns the invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function getInvoiceForTransaction($spaceId, $transactionId, Mage_Sales_Model_Order $order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if (strpos($invoice->getTransactionId(), $spaceId . '_' . $transactionId) === 0 &&
                $invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_CANCELED) {
                $invoice->load($invoice->getId());
                return $invoice;
            }
        }

        return null;
    }
}