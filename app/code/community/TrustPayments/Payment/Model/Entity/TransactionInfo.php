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
 * This entity holds data about a transaction on the gateway.
 *
 * @method int getTransactionId()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setTransactionId(int transactionId)
 * @method string getState()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setState(string state)
 * @method int getSpaceId()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setSpaceId(int spaceId)
 * @method int getSpaceViewId()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setSpaceViewId(int spaceViewId)
 * @method string getLanguage()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setLanguage(string language)
 * @method string getCurrency()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setCurrency(string currency)
 * @method string getCreatedAt()
 * @method float getAuthorizationAmount()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setAuthorizationAmount(float authorizationAmount)
 * @method string getImage()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setImage(string image)
 * @method array getLabels()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setLabels(array labels)
 * @method int getPaymentMethodId()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setPaymentMethodId(int paymentMethodId)
 * @method int getConnectorId()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setConnectorId(int connectorId)
 * @method int getOrderId()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setOrderId(int orderId)
 * @method int getOrderId()
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setOrderId(int orderId)
 * @method TrustPayments_Payment_Model_Entity_TransactionInfo setFailureReason(string failureReason)
 */
class TrustPayments_Payment_Model_Entity_TransactionInfo extends Mage_Core_Model_Abstract
{

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'trustpayments_payment_transaction_info';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    protected $_eventObject = 'transactionInfo';

    /**
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('trustpayments_payment/transactionInfo');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        if ($this->isObjectNew()) {
            $this->setCreatedAt(Mage::getSingleton('core/date')->date());
        }
    }

    /**
     * Loading transaction info by transaction id.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return TrustPayments_Payment_Model_Entity_TransactionInfo
     */
    public function loadByTransaction($spaceId, $transactionId)
    {
        $this->_getResource()->loadByTransaction($this, $spaceId, $transactionId);
        return $this;
    }

    /**
     * Loading transaction info by order.
     *
     * If none is found, the information are fetched from the gateway.
     *
     * @param int|Mage_Sales_Model_Order $order
     * @return TrustPayments_Payment_Model_Entity_TransactionInfo
     */
    public function loadByOrder($order)
    {
        if ($order instanceof Mage_Sales_Model_Order) {
            $orderId = $order->getId();
        } else {
            $orderId = (int) $order;
        }

        $this->load($orderId, 'order_id');
        if ($this->getId()) {
            return $this;
        }

        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($orderId);

        if ($order->getTrustpaymentsSpaceId() && $order->getTrustpaymentsTransactionId()) {
            /* @var TrustPayments_Payment_Model_Service_Transaction $transactionService */
            $transactionService = Mage::getSingleton('trustpayments_payment/service_transaction');
            $transactionService->updateTransactionInfo(
                $transactionService->getTransaction($order->getTrustpaymentsSpaceId(),
                    $order->getTrustpaymentsTransactionId()), $order);
        }

        return $this->load($orderId, 'order_id');
    }

    /**
     * Returns the order the transaction belongs to.
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (! $this->_order instanceof Mage_Sales_Model_Order) {
            $this->_order = Mage::getModel('sales/order')->load($this->getOrderId());
        }

        return $this->_order;
    }

    /**
     * Returns the translated failure reason.
     *
     * @param string $locale
     * @return string
     */
    public function getFailureReason($language = null)
    {
        $value = $this->getData('failure_reason');
        if (empty($value)) {
            return null;
        }

        if (! is_array($value) && ! is_object($value)) {
            $this->setData('failure_reason', unserialize($value));
        }

        return Mage::helper('trustpayments_payment')->translate($this->getData('failure_reason'), $language);
    }
}