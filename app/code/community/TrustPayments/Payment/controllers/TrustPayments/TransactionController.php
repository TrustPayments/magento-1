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
 * This controller provides actions regarding the transaction.
 */
class TrustPayments_Payment_TrustPayments_TransactionController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order');
    }

    /**
     * Update the transaction info from the gateway.
     */
    public function updateAction()
    {
        $service = Mage::getSingleton('trustpayments_payment/service_transaction');

        $spaceId = $this->getRequest()->getParam('space_id');
        $transactionId = $this->getRequest()->getParam('transaction_id');
        $transaction = $service->getTransaction($spaceId, $transactionId);

        $order = Mage::getModel('sales/order')->loadByIncrementId($transaction->getMerchantReference());

        $service->updateTransactionInfo($transaction, $order);

        $session = Mage::getSingleton('core/session');
        $session->addSuccess('The transaction has been updated.');

        $this->_redirect('adminhtml/sales_order/view', array(
            'order_id' => $order->getId()
        ));
    }

    /**
     * Sends a refund request to the gateway.
     */
    public function refundAction()
    {
        $orderId = $this->getRequest()->getParam('order_id');

        /* @var Mage_Core_Model_Session $session */
        $session = Mage::getSingleton('core/session');

        /* @var TrustPayments_Payment_Model_Entity_RefundJob $existingRefundJob */
        $existingRefundJob = Mage::getModel('trustpayments_payment/entity_refundJob');
        $existingRefundJob->loadByOrder($orderId);
        if ($existingRefundJob->getId() > 0) {
            try {
                /* @var TrustPayments_Payment_Model_Service_Refund $refundService */
                $refundService = Mage::getSingleton('trustpayments_payment/service_refund');
                $refund = $refundService->refund($existingRefundJob->getSpaceId(), $existingRefundJob->getRefund());

                if ($refund->getState() == \TrustPayments\Sdk\Model\RefundState::FAILED) {
                    $session->addError(
                        Mage::helper('trustpayments_payment')->translate(
                            $refund->getFailureReason()
                                ->getDescription()));
                } elseif ($refund->getState() == \TrustPayments\Sdk\Model\RefundState::PENDING) {
                    $session->addNotice(
                        Mage::helper('trustpayments_payment')->__(
                            'The refund was requested successfully, but is still pending on the gateway.'));
                } else {
                    $session->addSuccess('Successfully refunded.');
                }
            } catch (Exception $e) {
                $session->addError('There has been an error while sending the refund to the gateway.');
            }
        } else {
            $session->addError('For this order no refund request exists.');
        }

        $this->_redirect('adminhtml/sales_order/view', array(
            'order_id' => $orderId
        ));
    }

    /**
     * Downloads the transaction's invoice PDF document.
     */
    public function downloadInvoiceAction()
    {
        $spaceId = $this->getRequest()->getParam('space_id');
        $transactionId = $this->getRequest()->getParam('transaction_id');

        $service = new \TrustPayments\Sdk\Service\TransactionService(
            Mage::helper('trustpayments_payment')->getApiClient());
        $document = $service->getInvoiceDocument($spaceId, $transactionId);
        $this->download($document);
    }

    /**
     * Downloads the transaction's packing slip PDF document.
     */
    public function downloadPackingSlipAction()
    {
        $spaceId = $this->getRequest()->getParam('space_id');
        $transactionId = $this->getRequest()->getParam('transaction_id');

        $service = new \TrustPayments\Sdk\Service\TransactionService(
            Mage::helper('trustpayments_payment')->getApiClient());
        $document = $service->getPackingSlip($spaceId, $transactionId);
        $this->download($document);
    }

    /**
     * Downloads the refund PDF document.
     */
    public function downloadRefundAction()
    {
        $spaceId = $this->getRequest()->getParam('space_id');
        $externalId = $this->getRequest()->getParam('external_id');

        /* @var TrustPayments_Payment_Model_Service_Refund $refundService */
        $refundService = Mage::getSingleton('trustpayments_payment/service_refund');
        $refund = $refundService->getRefundByExternalId($spaceId, $externalId);

        $service = new \TrustPayments\Sdk\Service\RefundService(
            Mage::helper('trustpayments_payment')->getApiClient());
        $document = $service->getRefundDocument($spaceId, $refund->getId());
        $this->download($document);
    }

    /**
     * Sends the data received by calling the given path to the browser.
     *
     * @param string $path
     */
    protected function download(\TrustPayments\Sdk\Model\RenderedDocument $document)
    {
        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', 'application/pdf', true)
            ->setHeader('Content-Disposition', 'attachment; filename=' . $document->getTitle() . '.pdf')
            ->setHeader('Content-Description', $document->getTitle());
        $this->getResponse()->setBody(base64_decode($document->getData()));

        $this->getResponse()->sendHeaders();
        session_write_close();
        $this->getResponse()->outputBody();
    }
}