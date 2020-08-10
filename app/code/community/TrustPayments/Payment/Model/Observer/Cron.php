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
 * The observer handles cron jobs.
 */
class TrustPayments_Payment_Model_Observer_Cron
{

    /**
     * Tries to send all pending refunds to the gateway.
     */
    public function processRefundJobs()
    {
        /* @var TrustPayments_Payment_Model_Service_Refund $refundService */
        $refundService = Mage::getSingleton('trustpayments_payment/service_refund');

        /* @var TrustPayments_Payment_Model_Resource_RefundJob_Collection $refundJobCollection */
        $refundJobCollection = Mage::getModel('trustpayments_payment/entity_refundJob')->getCollection();
        $refundJobCollection->setPageSize(100);
        foreach ($refundJobCollection->getItems() as $refundJob) {
            /* @var TrustPayments_Payment_Model_Entity_RefundJob $refundJob */
            try {
                $refundService->refund($refundJob->getSpaceId(), $refundJob->getRefund());
            } catch (\TrustPayments\Sdk\ApiException $e) {
                if ($e->getResponseObject() instanceof \TrustPayments\Sdk\Model\ClientError) {
                    $refundJob->delete();
                } else {
                    Mage::logException($e);
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }
}