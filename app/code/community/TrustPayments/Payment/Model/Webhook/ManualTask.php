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
 * Webhook processor to handle manual task state transitions.
 */
class TrustPayments_Payment_Model_Webhook_ManualTask extends TrustPayments_Payment_Model_Webhook_Abstract
{

    /**
     * Updates the number of open manual tasks.
     *
     * @param TrustPayments_Payment_Model_Webhook_Request $request
     */
    protected function process(TrustPayments_Payment_Model_Webhook_Request $request)
    {
        /* @var TrustPayments_Payment_Model_Service_ManualTask $manualTaskService */
        $manualTaskService = Mage::getSingleton('trustpayments_payment/service_manualTask');
        $manualTaskService->update();
    }
}