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
 * Webhook processor to handle token version state transitions.
 */
class TrustPayments_Payment_Model_Webhook_TokenVersion extends TrustPayments_Payment_Model_Webhook_Abstract
{

    protected function process(TrustPayments_Payment_Model_Webhook_Request $request)
    {
        /* @var TrustPayments_Payment_Model_Service_Token $tokenService */
        $tokenService = Mage::getSingleton('trustpayments_payment/service_token');
        $tokenService->updateTokenVersion($request->getSpaceId(), $request->getEntityId());
    }
}