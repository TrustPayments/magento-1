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
 * Abstract webhook processor.
 */
abstract class TrustPayments_Payment_Model_Webhook_Abstract
{

    /**
     * Listens for an event call.
     *
     * @param Varien_Event_Observer $observer
     */
    public function listen(Varien_Event_Observer $observer)
    {
        $this->process($observer->getRequest());
    }

    /**
     * Processes the received webhook request.
     *
     * @param TrustPayments_Payment_Model_Webhook_Request $request
     */
    abstract protected function process(TrustPayments_Payment_Model_Webhook_Request $request);
}