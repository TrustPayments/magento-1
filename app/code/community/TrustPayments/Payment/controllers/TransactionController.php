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
 * This controller redirects the customer to the relevant page in the shop after the payment process and allows
 * customers to download transaction documents.
 */
class TrustPayments_Payment_TransactionController extends Mage_Core_Controller_Front_Action
{

    /**
     * Returns the information to collect the customer's payment data.
     */
    public function informationAction()
    {
        $result = array();

        /* @var TrustPayments_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('trustpayments_payment/service_transaction');
        /* @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');

        $transaction = $transactionService->getTransactionByQuote($checkoutSession->getQuote());
        $result['transactionId'] = $transaction->getId();

        try {
            $result['javascriptUrl'] = $transactionService->getJavaScriptUrl($checkoutSession->getQuote());
        } catch (Exception $e) {
            $result['javascriptUrl'] = false;
        }

        try {
            $result['paymentPageUrl'] = $transactionService->getPaymentPageUrl($checkoutSession->getQuote());
        } catch (Exception $e) {
            $result['paymentPageUrl'] = false;
        }

        $this->_prepareDataJSON($result);
    }

    /**
     * This action is needed for some one step checkouts.
     */
    public function redirectAction()
    {
        $this->getResponse()->setBody('OK');
    }

    /**
     * Redirects the customer to the order confirmation page after the payment process was successful.
     *
     * The quote is inactivated.
     */
    public function successAction()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if (Mage::helper('trustpayments_payment')->hash($orderId) != $this->getRequest()->getParam('secret')) {
            Mage::throwException('Invalid secret.');
        }

        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($orderId);

        /* @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->getQuote()
            ->setIsActive(false)
            ->save();
        $checkoutSession->setLastSuccessQuoteId($order->getQuoteId());

        $checkoutSession->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId());

        /* @var TrustPayments_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('trustpayments_payment/service_transaction');
        $transactionService->waitForTransactionState($order,
            array(
                \TrustPayments\Sdk\Model\TransactionState::CONFIRMED,
                \TrustPayments\Sdk\Model\TransactionState::PENDING,
                \TrustPayments\Sdk\Model\TransactionState::PROCESSING
            ), 3);

        $this->_redirectUrl($this->getSuccessUrl($order));
    }

    protected function getSuccessUrl(Mage_Sales_Model_Order $order)
    {
        $result = new StdClass();
        $result->url = Mage::getUrl('checkout/onepage/success', array(
            '_secure' => true
        ));
        Mage::dispatchEvent('trustpayments_payment_success_url', array(
            'result' => $result,
            'order' => $order
        ));
        return $result->url;
    }

    /**
     * Redirects the customer to the cart after payment process was cancelled.
     */
    public function failureAction()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if (empty($orderId)) {
            $this->_redirectUrl($this->getFailureUrl());
        } else {
            if (Mage::helper('trustpayments_payment')->hash($orderId) != $this->getRequest()->getParam('secret')) {
                Mage::throwException('Invalid secret.');
            }

            /* @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->load($orderId);
            /* @var TrustPayments_Payment_Model_Payment_Method_Abstract $methodInstance */
            $methodInstance = $order->getPayment()->getMethodInstance();
            $methodInstance->fail($order);

            $this->_redirectUrl($this->getFailureUrl($order));
        }
    }

    protected function getFailureUrl(Mage_Sales_Model_Order $order = null)
    {
        $result = new StdClass();
        $result->url = Mage::getUrl('checkout/cart');
        Mage::dispatchEvent('trustpayments_payment_failure_url', array(
            'result' => $result,
            'order' => $order
        ));
        return $result->url;
    }

    /**
     * Downloads the transaction's invoice PDF document.
     */
    public function downloadInvoiceAction()
    {
        $transactionInfo = $this->loadTransactionInfo();
        if ($transactionInfo == false) {
            return false;
        }

        if (! Mage::getStoreConfigFlag('trustpayments_payment/document/customer_download_invoice',
            $transactionInfo->getOrder()->getStore())) {
            $this->_redirect('sales/order/view/order_id/' . $transactionInfo->getOrderId());
            return false;
        }

        try {
            $service = new \TrustPayments\Sdk\Service\TransactionService(
                Mage::helper('trustpayments_payment')->getApiClient());
            $document = $service->getInvoiceDocument($transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId());
            $this->download($document);
        } catch (Exception $e) {
            /* @var Mage_Core_Model_Session $session */
            $session = Mage::getSingleton('core/session');
            $session->addError('The invoice document cannot be downloaded.');
            $this->_redirect('sales/order/view/order_id/' . $transactionInfo->getOrderId());
            return false;
        }
    }

    /**
     * Downloads the transaction's packing slip PDF document.
     */
    public function downloadPackingSlipAction()
    {
        $transactionInfo = $this->loadTransactionInfo();
        if ($transactionInfo == false) {
            return false;
        }

        if (! Mage::getStoreConfigFlag('trustpayments_payment/document/customer_download_packing_slip',
            $transactionInfo->getOrder()->getStore())) {
            $this->_redirect('sales/order/view/order_id/' . $transactionInfo->getOrderId());
            return false;
        }

        try {
            $service = new \TrustPayments\Sdk\Service\TransactionService(
                Mage::helper('trustpayments_payment')->getApiClient());
            $document = $service->getPackingSlip($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            $this->download($document);
        } catch (Exception $e) {
            /* @var Mage_Core_Model_Session $session */
            $session = Mage::getSingleton('core/session');
            $session->addError('The packing slip cannot be downloaded.');
            $this->_redirect('sales/order/view/order_id/' . $transactionInfo->getOrderId());
            return false;
        }
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

    /**
     * Load the transaction info.
     *
     * @return TrustPayments_Payment_Model_Entity_TransactionInfo
     */
    protected function loadTransactionInfo()
    {
        $order = $this->loadOrder();
        if (! $order) {
            return false;
        }

        /* @var TrustPayments_Payment_Model_Entity_TransactionInfo $transactionInfo */
        $transactionInfo = Mage::getModel('trustpayments_payment/entity_transactionInfo')->loadByOrder($order);
        if ($transactionInfo->getId()) {
            return $transactionInfo;
        } else {
            $this->_forward('noRoute');
            return false;
        }
    }

    /**
     * Try to load valid order by order_id and register it
     *
     * @param int $orderId
     * @return Mage_Sales_Model_Order
     */
    protected function loadOrder()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        if (! $orderId) {
            $this->_forward('noRoute');
            return false;
        }

        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($orderId);
        if ($this->canViewOrder($order)) {
            return $order;
        } else {
            $this->_redirect('sales/order/history');
            return false;
        }
    }

    /**
     * Check order view availability
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    protected function canViewOrder(Mage_Sales_Model_Order $order)
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        $availableStates = Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
        if ($order->getId() && $order->getCustomerId() && ($order->getCustomerId() == $customerId) &&
            in_array($order->getState(), $availableStates)) {
            return true;
        }

        return false;
    }

    /**
     * Prepare JSON formatted data for response to client
     *
     * @param
     *            $response
     * @return Zend_Controller_Response_Abstract
     */
    protected function _prepareDataJSON($response)
    {
        $this->getResponse()->setHeader('Content-type', 'application/json', true);
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }
}