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
 * This service provides functions to deal with Trust Payments delivery indications.
 */
class TrustPayments_Payment_Model_Service_DeliveryIndication extends TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * The delivery indication API service.
     *
     * @var \TrustPayments\Sdk\Service\DeliveryIndicationService
     */
    protected $_deliveryIndicationService;

    /**
     * Marks the delivery indication belonging to the given payment as suitable.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \TrustPayments\Sdk\Model\DeliveryIndication
     */
    public function markAsSuitable(Mage_Sales_Model_Order_Payment $payment)
    {
        $deliveryIndication = $this->getDeliveryIndicationForTransaction(
            $payment->getOrder()
                ->getTrustpaymentsSpaceId(), $payment->getOrder()
                ->getTrustpaymentsTransactionId());
        return $this->getDeliveryIndicationService()->markAsSuitable($deliveryIndication->getLinkedSpaceId(),
            $deliveryIndication->getId());
    }

    /**
     * Marks the delivery indication belonging to the given payment as not suitable.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \TrustPayments\Sdk\Model\DeliveryIndication
     */
    public function markAsNotSuitable(Mage_Sales_Model_Order_Payment $payment)
    {
        $deliveryIndication = $this->getDeliveryIndicationForTransaction(
            $payment->getOrder()
                ->getTrustpaymentsSpaceId(), $payment->getOrder()
                ->getTrustpaymentsTransactionId());
        return $this->getDeliveryIndicationService()->markAsNotSuitable($deliveryIndication->getLinkedSpaceId(),
            $deliveryIndication->getId());
    }

    /**
     * Returns the delivery indication API service..
     *
     * @return \TrustPayments\Sdk\Service\DeliveryIndicationService
     */
    protected function getDeliveryIndicationService()
    {
        if ($this->_deliveryIndicationService == null) {
            $this->_deliveryIndicationService = new \TrustPayments\Sdk\Service\DeliveryIndicationService(
                $this->getHelper()->getApiClient());
        }

        return $this->_deliveryIndicationService;
    }

    /**
     * Returns the delivery indication for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \TrustPayments\Sdk\Model\DeliveryIndication
     */
    protected function getDeliveryIndicationForTransaction($spaceId, $transactionId)
    {
        $query = new \TrustPayments\Sdk\Model\EntityQuery();
        $query->setFilter($this->createEntityFilter('transaction.id', $transactionId));
        $query->setNumberOfEntities(1);
        return current($this->getDeliveryIndicationService()->search($spaceId, $query));
    }
}