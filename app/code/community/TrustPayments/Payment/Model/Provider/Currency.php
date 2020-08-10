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
 * Provider of currency information from the gateway.
 */
class TrustPayments_Payment_Model_Provider_Currency extends TrustPayments_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('trustpayments_payment_currencies');
    }

    /**
     * Returns the currency by the given code.
     *
     * @param string $code
     * @return \TrustPayments\Sdk\Model\RestCurrency
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns a list of currencies.
     *
     * @return \TrustPayments\Sdk\Model\RestCurrency[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $currencyService = new \TrustPayments\Sdk\Service\CurrencyService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $currencyService->all();
    }

    protected function getId($entry)
    {
        /* @var \TrustPayments\Sdk\Model\RestCurrency $entry */
        return $entry->getCurrencyCode();
    }
}