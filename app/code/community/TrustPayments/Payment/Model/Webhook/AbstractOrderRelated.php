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
 * Abstract webhook processor.
 */
abstract class TrustPayments_Payment_Model_Webhook_AbstractOrderRelated extends TrustPayments_Payment_Model_Webhook_Abstract
{

    /**
     * Processes the received order related webhook request.
     *
     * @param TrustPayments_Payment_Model_Webhook_Request $request
     */
    protected function process(TrustPayments_Payment_Model_Webhook_Request $request)
    {
        $entity = $this->loadEntity($request);

        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $this->beginTransaction();
        try {
            $order->load($this->getOrderId($entity));
            if ($order->getId() > 0) {
                if ($order->getTrustpaymentsTransactionId() != $this->getTransactionId($entity)) {
                    return;
                }

                $this->lock($order);
                $order->load($order->getId());
                
                /* @var Mage_Core_Model_App_Emulation $appEmulation */
                $appEmulation = Mage::getSingleton('core/app_emulation');
                $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($order->getStoreId());
                $this->processOrderRelatedInner($order, $entity);
                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
            }

            $order->getResource()->commit();
        } catch (Exception $e) {
            $order->getResource()->rollBack();
            throw $e;
        }
    }

    /**
     * Starts a database transaction with isolation level 'read uncommitted'.
     *
     * In case of two parallel requests linked to the same order, data written to the database by the first will
     * not be up-to-date in the second. This can lead to processing the same data multiple times. By setting the
     * isolation level to 'read uncommitted' this issue can be avoided.
     *
     * An alternative solution to this problem would be to use optimistic locking. However, this could lead to database
     * rollbacks and as for example updating the order status could lead to triggering further processes which may not
     * propertly handle rollbacks, this could result in inconsistencies.
     *
     * @return Varien_Db_Adapter_Interface
     */
    protected function beginTransaction()
    {
        /* @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('sales_write');
        $connection->query("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;");
        $connection->beginTransaction();
        return $connection;
    }

    /**
     * Loads and returns the entity for the webhook request.
     *
     * @param TrustPayments_Payment_Model_Webhook_Request $request
     * @return object
     */
    abstract protected function loadEntity(TrustPayments_Payment_Model_Webhook_Request $request);

    /**
     * Returns the transaction's id linked to the entity.
     *
     * @param object $entity
     * @return int
     */
    abstract protected function getTransactionId($entity);

    /**
     * Actually processes the order related webhook request.
     *
     * This must be implemented
     *
     * @param Mage_Sales_Model_Order $order
     * @param mixed $entity
     */
    abstract protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $entity);

    protected function getOrderId($entity)
    {
        /* @var TrustPayments_Payment_Model_Entity_TransactionInfo $transactionInfo */
        $transactionInfo = Mage::getModel('trustpayments_payment/entity_transactionInfo')->loadByTransaction(
            $entity->getLinkedSpaceId(), $this->getTransactionId($entity));
        return $transactionInfo->getOrderId();
    }

    /**
     * Create a lock to prevent concurrency.
     *
     * @param Mage_Sales_Model_Order $order
     */
    protected function lock(Mage_Sales_Model_Order $order)
    {
        /* @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update($resource->getTableName('sales/order'),
            array(
                'trustpayments_lock' => Mage::getSingleton('core/date')->date()
            ), array(
                'entity_id = ?' => $order->getId()
            ));
    }
}