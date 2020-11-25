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
 * Handles the dynamic payment method configs.
 */
class TrustPayments_Payment_Model_System_Config
{

    const SYSTEM_CACHE_ID = 'trustpayments_system_config';

    const VALUES_CACHE_ID = 'trustpayments_config_values';

    /**
     * Initializes the dynamic payment method system config.
     *
     * @param Mage_Core_Model_Config_Base $config
     */
    public function initSystemConfig(Mage_Core_Model_Config_Base $config)
    {
        $websiteCode = Mage::getSingleton('adminhtml/config_data')->getWebsite();

        $parts = array();
        if ($cachedParts = Mage::app()->loadCache(self::SYSTEM_CACHE_ID)) {
            $parts = (array) json_decode($cachedParts);
        }

        $websiteParts = array();
        if (isset($parts[$websiteCode]) && ! empty($parts[$websiteCode])) {
            $websiteParts = $parts[$websiteCode];
        } else {
            $spaceId = Mage::getModel('core/website')->load($websiteCode)->getConfig(
                'trustpayments_payment/general/space_id');
            if ($spaceId) {
                $paymentMethodTemplate = file_get_contents(
                    Mage::getModuleDir('etc', 'TrustPayments_Payment') . DS . 'payment_method.system.xml.tpl');
                foreach (Mage::getModel('trustpayments_payment/entity_paymentMethodConfiguration')->getCollection()
                    ->addSpaceFilter($spaceId)
                    ->addStateFilter() as $paymentMethod) {
                    $websiteParts[] = str_replace(array(
                        '{id}',
                        '{name}'
                    ), array(
                        $paymentMethod->getId(),
                        $paymentMethod->getConfigurationName()
                    ), $paymentMethodTemplate);
                }
            }

            $parts[$websiteCode] = $websiteParts;
            Mage::app()->saveCache(json_encode($parts), self::SYSTEM_CACHE_ID,
                array(
                    Mage_Core_Model_Config::CACHE_TAG
                ));
        }

        $mergeModel = new Mage_Core_Model_Config_Base();
        foreach ($websiteParts as $part) {
            $mergeModel->loadString($part);
            $config->extend($mergeModel, true);
        }
    }

    /**
     * Initializes the dynamic payment method config values.
     */
    public function initConfigValues()
    {
        if (Mage::getConfig() instanceof TrustPayments_Payment_Model_Core_Config) {
            $configLoaded = Mage::getConfig()->getNode('trustpayments/config_loaded');
            if (!$configLoaded) {
                Mage::getModel('wallee_payment/observer_core')->addAutoloader();
                Mage::app()->reinitStores();
                
                $configValues = $this->getConfigValues();
                foreach ($configValues as $path => $value) {
                    $this->setConfigValue($path, $value);
                }
                
                $this->setConfigValue('trustpayments/config_loaded', true);
            }
        } else {
            $configValues = $this->getConfigValues();
            foreach ($configValues as $path => $value) {
                $this->setConfigValue($path, $value);
            }
        }
    }
    
    /**
     * Returns the dynamic payment method config values.
     * 
     * @return array
     */
    protected function getConfigValues()
    {
        $configValues = array();
        if (($cachedValues = Mage::app()->loadCache(self::VALUES_CACHE_ID))) {
            $configValues = json_decode($cachedValues);
        } else {
            if (! $this->isTableExists()) {
                return;
            }
            
            $websiteMap = array();
            foreach (Mage::app()->getWebsites() as $website) {
                $websiteMap[$website->getConfig('trustpayments_payment/general/space_id')][] = $website;
            }
            
            /* @var TrustPayments_Payment_Model_Resource_PaymentMethodConfiguration_Collection $collection */
            $collection = Mage::getModel('trustpayments_payment/entity_paymentMethodConfiguration')->getCollection();
            foreach ($collection as $paymentMethod) {
                /* @var TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod */
                if (isset($websiteMap[$paymentMethod->getSpaceId()])) {
                    $basePath = '/payment/trustpayments_payment_' . $paymentMethod->getId() . '/';
                    $active = $paymentMethod->getState() ==
                    TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration::STATE_ACTIVE ? 1 : 0;
                    $model = 'trustpayments_payment/paymentMethod' . $paymentMethod->getId();
                    $action = Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
                    
                    $configValues['stores/admin' . $basePath . 'active'] = $active;
                    $configValues['stores/admin' . $basePath . 'title'] = $this->getPaymentMethodTitle($paymentMethod,
                        'en-US');
                    $configValues['stores/admin' . $basePath . 'model'] = $model;
                    $configValues['stores/admin' . $basePath . 'payment_action'] = $action;
                    
                    foreach ($websiteMap[$paymentMethod->getSpaceId()] as $website) {
                        $configValues['websites/' . $website->getCode() . $basePath . 'active'] = $active;
                        $configValues['websites/' . $website->getCode() . $basePath . 'title'] = $this->getPaymentMethodTitle(
                            $paymentMethod, $website->getConfig('general/locale/code'));
                        $configValues['websites/' . $website->getCode() . $basePath . 'description'] = $paymentMethod->getDescription(
                            $website->getConfig('general/locale/code'));
                        $configValues['websites/' . $website->getCode() . $basePath . 'sort_order'] = $paymentMethod->getSortOrder();
                        $configValues['websites/' . $website->getCode() . $basePath . 'show_description'] = 1;
                        $configValues['websites/' . $website->getCode() . $basePath . 'show_image'] = 1;
                        $configValues['websites/' . $website->getCode() . $basePath . 'order_email'] = 1;
                        $configValues['websites/' . $website->getCode() . $basePath . 'model'] = $model;
                        $configValues['websites/' . $website->getCode() . $basePath . 'payment_action'] = $action;
                        
                        foreach ($website->getStores() as $store) {
                            /* @var Mage_Core_Model_Store $store */
                            $configValues['stores/' . $store->getCode() . $basePath . 'active'] = $active;
                            $configValues['stores/' . $store->getCode() . $basePath . 'title'] = $this->getPaymentMethodTitle(
                                $paymentMethod, $store->getConfig('general/locale/code'));
                            $configValues['stores/' . $store->getCode() . $basePath . 'description'] = $paymentMethod->getDescription(
                                $store->getConfig('general/locale/code'));
                            $configValues['stores/' . $store->getCode() . $basePath . 'sort_order'] = $paymentMethod->getSortOrder();
                            $configValues['stores/' . $store->getCode() . $basePath . 'show_description'] = 1;
                            $configValues['stores/' . $store->getCode() . $basePath . 'show_image'] = 1;
                            $configValues['stores/' . $store->getCode() . $basePath . 'order_email'] = 1;
                            $configValues['stores/' . $store->getCode() . $basePath . 'model'] = $model;
                            $configValues['stores/' . $store->getCode() . $basePath . 'payment_action'] = $action;
                        }
                    }
                }
            }
            Mage::app()->saveCache(json_encode($configValues), self::VALUES_CACHE_ID,
                array(
                    Mage_Core_Model_Config::CACHE_TAG
                ));
        }
        return $configValues;
    }

    /**
     * Returns the title for the payment method in the given language.
     *
     * @param TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod
     * @param string $locale
     * @return string
     */
    protected function getPaymentMethodTitle(
        TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod, $locale)
    {
        $translatedTitle = $paymentMethod->getTitle($locale);
        if (! empty($translatedTitle)) {
            return $translatedTitle;
        } else {
            return $paymentMethod->getConfigurationName();
        }
    }

    /**
     * Sets the config value if not already set.
     *
     * @param string $path
     * @param mixed $value
     */
    protected function setConfigValue($path, $value)
    {
        if (Mage::getConfig()->getNode($path) === false) {
            Mage::getConfig()->setNode($path, $value);
        }
    }

    /**
     * Returns whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    protected function isTableExists()
    {
        /* @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        if ($connection) {
            return $connection->isTableExists(
                $resource->getTableName('trustpayments_payment/payment_method_configuration'));
        } else {
            return false;
        }
    }
}