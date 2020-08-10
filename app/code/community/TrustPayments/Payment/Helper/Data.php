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
 * This helper provides general functions.
 */
class TrustPayments_Payment_Helper_Data extends Mage_Core_Helper_Data
{

    protected $_apiClient = null;

    /**
     * Returns the base URL to the gateway.
     *
     * @return string
     */
    public function getBaseGatewayUrl()
    {
        return rtrim(Mage::getStoreConfig('trustpayments_payment/general/base_gateway_url'), '/');
    }

    /**
     * Returns an instance of Trust Payments's API client.
     *
     * @param boolean $gracefully
     * @param boolean $singleton
     * @return \TrustPayments\Sdk\ApiClient
     */
    public function getApiClient($gracefully = false, $singleton = true)
    {
        if ($this->_apiClient == null || ! $singleton) {
            $userId = Mage::getStoreConfig('trustpayments_payment/general/api_user_id');
            $plainApplicationKey = Mage::getStoreConfig('trustpayments_payment/general/api_user_secret');
            $helper = Mage::helper('core');
            /* @var Mage_Core_Helper_Data $helper */
            $applicationKey = $helper->decrypt($plainApplicationKey);
            if ($userId && $applicationKey) {
                $client = new \TrustPayments\Sdk\ApiClient($userId, $applicationKey);
                $client->setBasePath($this->getBaseGatewayUrl() . '/api');
                if (! $singleton) {
                    return $client;
                }

                $this->_apiClient = $client;
            } else if ($gracefully) {
                return false;
            } else {
                Mage::throwException('The Trust Payments API user data are incomplete.');
            }
        }

        return $this->_apiClient;
    }

    /**
     * Returns the URL to a resource on Trust Payments in the given context (space, space view, language).
     *
     * @param string $path
     * @param string $language
     * @param int $spaceId
     * @param int $spaceViewId
     * @return string
     */
    public function getResourceUrl($path, $language = null, $spaceId = null, $spaceViewId = null)
    {
        $url = rtrim($this->getBaseGatewayUrl(), '/');
        if (! empty($language)) {
            $url .= '/' . str_replace('_', '-', $language);
        }

        if (! empty($spaceId)) {
            $url .= '/s/' . $spaceId;
        }

        if (! empty($spaceViewId)) {
            $url .= '/' . $spaceViewId;
        }

        $url .= '/resource/' . $path;
        return $url;
    }

    /**
     * Returns the path to the directory to store generated files.
     *
     * @return string
     */
    public function getGenerationDirectoryPath()
    {
        return Mage::getBaseDir('var') . DS . 'trustpayments';
    }

    /**
     * Returns the translation in the given language.
     *
     * @param array[string,string] $translatedString
     * @param string $language
     * @return string
     */
    public function translate($translatedString, $language = null)
    {
        if ($language == null) {
            if (Mage::app()->getStore()->isAdmin()) {
                $language = Mage::getSingleton('adminhtml/session')->getLocale();
            } else {
                $language = Mage::getStoreConfig('general/locale/code');
            }
        }

        $language = str_replace('_', '-', $language);
        if (isset($translatedString[$language])) {
            return $translatedString[$language];
        }

        try {
            /* @var TrustPayments_Payment_Model_Provider_Language $languageProvider */
            $languageProvider = Mage::getSingleton('trustpayments_payment/provider_language');
            $primaryLanguage = $languageProvider->findPrimary($language);
            if (isset($translatedString[$primaryLanguage->getIetfCode()])) {
                return $translatedString[$primaryLanguage->getIetfCode()];
            }
        } catch (Exception $e) {
            Mage::log('Could not find the primary language: ' . $e->getMessage(), null, 'trustpayments.log');
        }

        if (isset($translatedString['en-US'])) {
            return $translatedString['en-US'];
        }

        return null;
    }

    /**
     * Returns the fraction digits of the given currency.
     *
     * @param string $currencyCode
     * @return number
     */
    public function getCurrencyFractionDigits($currencyCode)
    {
        /* @var TrustPayments_Payment_Model_Provider_Currency $currencyCollection */
        $currencyCollection = Mage::getSingleton('trustpayments_payment/provider_currency');
        $currency = $currencyCollection->find($currencyCode);
        if ($currency) {
            return $currency->getFractionDigits();
        } else {
            return 2;
        }
    }

    /**
     * Returns the security hash of the given data.
     *
     * @param string $data
     * @return string
     */
    public function hash($data)
    {
        $salt = (string) Mage::getConfig()->getNode('global/crypt/key');
        return hash_hmac('sha256', $data, $salt, false);
    }

    /**
     * Returns the URL to Trust Payments's Javascript library to collect customer data.
     *
     * @return string
     */
    public function getDeviceJavascriptUrl()
    {
        /* @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $spaceId = $checkoutSession->getQuote()
            ->getStore()
            ->getConfig('trustpayments_payment/general/space_id');
        if (empty($spaceId)) {
            return null;
        }

        /* @var Mage_Core_Model_Cookie $cookie */
        $cookie = Mage::getSingleton('core/cookie');
        $deviceId = $cookie->get('trustpayments_device_id');
        if (empty($deviceId)) {
            $deviceId = $this->generateUUID();
            $cookie->set('trustpayments_device_id', $deviceId, true);
        }

        return $this->getBaseGatewayUrl() . '/s/' . $spaceId . '/payment/device.js?sessionIdentifier=' . $deviceId;
    }

    /**
     * Generates and returns a unique ID.
     *
     * @return string the unique ID
     */
    public function generateUUID()
    {
        $data = \openssl_random_pseudo_bytes(16);
        $data[6] = \chr(\ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = \chr(\ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return \vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split(\bin2hex($data), 4));
    }
}