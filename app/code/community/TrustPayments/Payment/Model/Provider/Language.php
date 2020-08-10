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
 * Provider of language information from the gateway.
 */
class TrustPayments_Payment_Model_Provider_Language extends TrustPayments_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('trustpayments_payment_languages');
    }

    /**
     * Returns the language by the given code.
     *
     * @param string $code
     * @return \TrustPayments\Sdk\Model\RestLanguage
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns the primary language in the given group.
     *
     * @param string $code
     * @return \TrustPayments\Sdk\Model\RestLanguage
     */
    public function findPrimary($code)
    {
        $code = substr($code, 0, 2);
        foreach ($this->getAll() as $language) {
            if ($language->getIso2Code() == $code && $language->getPrimaryOfGroup()) {
                return $language;
            }
        }

        return false;
    }

    /**
     * Returns a list of language.
     *
     * @return \TrustPayments\Sdk\Model\RestLanguage[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $languageService = new \TrustPayments\Sdk\Service\LanguageService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $languageService->all();
    }

    protected function getId($entry)
    {
        /* @var \TrustPayments\Sdk\Model\RestLanguage $entry */
        return $entry->getIetfCode();
    }
}