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
 * Webhook processor to handle refund state transitions.
 */
class TrustPayments_Payment_Model_Webhook_Refund extends TrustPayments_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see TrustPayments_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \TrustPayments\Sdk\Model\Refund
     */
    protected function loadEntity(TrustPayments_Payment_Model_Webhook_Request $request)
    {
        $refundService = new \TrustPayments\Sdk\Service\RefundService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $refundService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getTransactionId($refund)
    {
        /* @var \TrustPayments\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getId();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $refund)
    {
        /* @var \TrustPayments\Sdk\Model\Refund $refund */
        switch ($refund->getState()) {
            case \TrustPayments\Sdk\Model\RefundState::FAILED:
                $this->deleteRefundJob($refund);
                break;
            case \TrustPayments\Sdk\Model\RefundState::SUCCESSFUL:
                $this->refunded($refund, $order);
                $this->deleteRefundJob($refund);
            default:
                // Nothing to do.
                break;
        }
    }

    protected function refunded(\TrustPayments\Sdk\Model\Refund $refund, Mage_Sales_Model_Order $order)
    {
        if ($this->isDerecognizedInvoice($refund, $order)) {
            $invoice = $this->getInvoiceForTransaction($refund->getLinkedSpaceId(), $refund->getTransaction()
                ->getId(), $order);
            if ($invoice == null || $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
                if ($invoice == null) {
                    $order->setTrustpaymentsPaymentInvoiceAllowManipulation(true);
                }
                
                if ($invoice == null || $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
                    $order->getPayment()->registerCaptureNotification($refund->getAmount());
                    if ($invoice != null) {
                        $invoice->setTrustpaymentsCapturePending(false);
                        $order->addRelatedObject($invoice);
                    }
                }
                $order->save();
            }
        }

        if ($order->getTrustpaymentsCanceled()) {
            return;
        }

        /* @var Mage_Sales_Model_Order_Creditmemo $existingCreditmemo */
        $existingCreditmemo = Mage::getModel('sales/order_creditmemo')->load($refund->getExternalId(),
            'trustpayments_external_id');
        if ($existingCreditmemo->getId() > 0) {
            return;
        }

        /* @var TrustPayments_Payment_Model_Service_Refund $refundService */
        $refundService = Mage::getSingleton('trustpayments_payment/service_refund');
        $refundService->registerRefundNotification($refund, $order);
    }

    protected function deleteRefundJob(\TrustPayments\Sdk\Model\Refund $refund)
    {
        /* @var TrustPayments_Payment_Model_Entity_RefundJob $refundJob */
        $refundJob = Mage::getModel('trustpayments_payment/entity_refundJob');
        $refundJob->loadByExternalId($refund->getExternalId());
        if ($refundJob->getId() > 0) {
            $refundJob->delete();
        }
    }

    protected function isDerecognizedInvoice(\TrustPayments\Sdk\Model\Refund $refund,
        Mage_Sales_Model_Order $order)
    {
        /* @var TrustPayments_Payment_Model_Service_TransactionInvoice $invoiceService */
        $invoiceService = Mage::getSingleton('trustpayments_payment/service_transactionInvoice');
        $transactionInvoice = $invoiceService->getTransactionInvoiceByTransaction(
            $order->getTrustpaymentsSpaceId(), $order->getTrustpaymentsTransactionId());
        if ($transactionInvoice->getState() == \TrustPayments\Sdk\Model\TransactionInvoiceState::DERECOGNIZED) {
            return true;
        } else {
            return false;
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