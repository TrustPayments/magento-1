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
 * This entity holds data about a Trust Payments payment method.
 *
 * @method string getState()
 * @method TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration setState(string state)
 * @method int getSpaceId()
 * @method TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration setSpaceId(int spaceId)
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
 * @method int getConfigurationId()
 * @method TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration setConfigurationId(int configurationId)
 * @method string getConfigurationName()
 * @method TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration setConfigurationName(string
 *         configurationName)
 * @method TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration setTitle(array title)
 * @method TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration setDescription(array description)
 * @method string getImage()
 * @method TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration setImage(string image)
 * @method int getSortOrder()
 * @method TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration setSortOrder(int sortOrder)
 */
class TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration extends Mage_Core_Model_Abstract
{

    const STATE_ACTIVE = 1;

    const STATE_INACTIVE = 2;

    const STATE_HIDDEN = 3;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'trustpayments_payment_method_configuration';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    protected $_eventObject = 'payment_method_configuration';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('trustpayments_payment/paymentMethodConfiguration');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        if ($this->isObjectNew()) {
            $this->setCreatedAt(Mage::getSingleton('core/date')->date());
        }

        $this->setUpdatedAt(Mage::getSingleton('core/date')->date());
    }

    /**
     * Loading payment method configuration by space and configuration id.
     *
     * @param int $spaceId
     * @param int $configurationId
     * @return TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration
     */
    public function loadByConfigurationId($spaceId, $configurationId)
    {
        $this->_getResource()->loadByConfigurationId($this, $spaceId, $configurationId);
        return $this;
    }

    /**
     * Returns the translated title of the payment method configuration.
     *
     * @param string $locale
     * @return string
     */
    public function getTitle($language = null)
    {
        return Mage::helper('trustpayments_payment')->translate($this->getTitleArray(), $language);
    }

    /**
     * Returns the title as array.
     *
     * @return array
     */
    public function getTitleArray()
    {
        $value = $this->getData('title');
        if (! is_array($value) && ! is_object($value)) {
            $this->setData('title', unserialize($value));
        }

        return $this->getData('title');
    }

    /**
     * Returns the translated description of the payment method configuration.
     *
     * @param string $locale
     * @return string
     */
    public function getDescription($language = null)
    {
        return Mage::helper('trustpayments_payment')->translate($this->getDescriptionArray(), $language);
    }

    /**
     * Returns the description as array.
     *
     * @return array
     */
    public function getDescriptionArray()
    {
        $value = $this->getData('description');
        if (! is_array($value) && ! is_object($value)) {
            $this->setData('description', unserialize($value));
        }

        return $this->getData('description');
    }
}