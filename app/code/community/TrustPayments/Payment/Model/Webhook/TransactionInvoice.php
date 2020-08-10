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
 * Webhook processor to handle transaction inovice transitions.
 */
class TrustPayments_Payment_Model_Webhook_TransactionInvoice extends TrustPayments_Payment_Model_Webhook_Transaction
{

    /**
     *
     * @see TrustPayments_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \TrustPayments\Sdk\Model\TransactionInvoice
     */
    protected function loadEntity(TrustPayments_Payment_Model_Webhook_Request $request)
    {
        $transactionInvoiceService = new \TrustPayments\Sdk\Service\TransactionInvoiceService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $transactionInvoiceService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getTransactionId($transactionInvoice)
    {
        /* @var \TrustPayments\Sdk\Model\TransactionInvoice $transactionInvoice */
        return $transactionInvoice->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $transactionInvoice)
    {
        parent::processOrderRelatedInner($order,
            $transactionInvoice->getCompletion()
                ->getLineItemVersion()
                ->getTransaction());

        /* @var \TrustPayments\Sdk\Model\TransactionInvoice $transactionInvoice */
        $invoice = $this->getInvoiceForTransaction($transactionInvoice->getLinkedSpaceId(),
            $transactionInvoice->getCompletion()
                ->getLineItemVersion()
                ->getTransaction()
                ->getId(), $order);
        if ($invoice == null || $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
            switch ($transactionInvoice->getState()) {
                case \TrustPayments\Sdk\Model\TransactionInvoiceState::NOT_APPLICABLE:
                case \TrustPayments\Sdk\Model\TransactionInvoiceState::PAID:
                    $this->capture($transactionInvoice->getCompletion()
                        ->getLineItemVersion()
                        ->getTransaction(), $order, $transactionInvoice->getAmount(), $invoice);
                    break;
                case \TrustPayments\Sdk\Model\TransactionInvoiceState::DERECOGNIZED:
                default:
                    // Nothing to do.
                    break;
            }
        }
    }

    protected function capture(\TrustPayments\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order,
        $amount, Mage_Sales_Model_Order_Invoice $invoice = null)
    {
        if ($order->getTrustpaymentsCanceled()) {
            return;
        }

        $isOrderInReview = ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);

        if (! $invoice) {
            $order->setTrustpaymentsPaymentInvoiceAllowManipulation(true);
            $invoice = $this->createInvoice($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        }

        if (Mage_Sales_Model_Order_Invoice::STATE_OPEN == $invoice->getState()) {
            $order->getPayment()->registerCaptureNotification($amount);
            $invoice->setTrustpaymentsCapturePending(false)->save();
        }

        if ($transaction->getState() == \TrustPayments\Sdk\Model\TransactionState::COMPLETED) {
            $order->setStatus('processing_trustpayments');
        }

        if ($isOrderInReview) {
            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
        }

        $order->save();
    }

    /**
     * Creates an invoice for the order.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function createInvoice($spaceId, $transactionId, Mage_Sales_Model_Order $order)
    {
        $invoice = $order->prepareInvoice();
        $invoice->setTrustpaymentsAllowCreation(true);
        $invoice->register();
        $invoice->setTransactionId($spaceId . '_' . $transactionId);
        $invoice->save();
        return $invoice;
    }
}