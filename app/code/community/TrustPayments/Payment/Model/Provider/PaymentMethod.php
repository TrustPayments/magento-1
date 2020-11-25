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
 * Provider of payment method information from the gateway.
 */
class TrustPayments_Payment_Model_Provider_PaymentMethod extends TrustPayments_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('trustpayments_payment_methods');
    }

    /**
     * Returns the payment method by the given id.
     *
     * @param int $id
     * @return \TrustPayments\Sdk\Model\PaymentMethod
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment methods.
     *
     * @return \TrustPayments\Sdk\Model\PaymentMethod[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $methodService = new \TrustPayments\Sdk\Service\PaymentMethodService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $methodService->all();
    }

    protected function getId($entry)
    {
        /* @var \TrustPayments\Sdk\Model\PaymentMethod $entry */
        return $entry->getId();
    }
}