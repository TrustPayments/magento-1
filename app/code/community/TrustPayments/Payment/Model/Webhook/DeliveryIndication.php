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
 * Webhook processor to handle delivery indication state transitions.
 */
class TrustPayments_Payment_Model_Webhook_DeliveryIndication extends TrustPayments_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see TrustPayments_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \TrustPayments\Sdk\Model\DeliveryIndication
     */
    protected function loadEntity(TrustPayments_Payment_Model_Webhook_Request $request)
    {
        $deliveryIndicationService = new \TrustPayments\Sdk\Service\DeliveryIndicationService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $deliveryIndicationService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getTransactionId($deliveryIndication)
    {
        /* @var \TrustPayments\Sdk\Model\DeliveryIndication $deliveryIndication */
        return $deliveryIndication->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $deliveryIndication)
    {
        /* @var \TrustPayments\Sdk\Model\DeliveryIndication $deliveryIndication */
        switch ($deliveryIndication->getState()) {
            case \TrustPayments\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED:
                $this->review($order);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function review(Mage_Sales_Model_Order $order)
    {
        if ($order->getState() != Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true,
                Mage::helper('trustpayments_payment')->__(
                    'A manual decision about whether to accept the payment is required.'));
            $order->save();
        }
    }
}