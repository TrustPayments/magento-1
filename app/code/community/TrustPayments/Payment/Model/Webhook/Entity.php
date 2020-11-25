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
class TrustPayments_Payment_Model_Webhook_Entity
{

    protected $_id;

    protected $_name;

    protected $_states;

    protected $_notifyEveryChange;

    public function __construct($id, $name, array $states, $notifyEveryChange = false)
    {
        $this->_id = $id;
        $this->_name = $name;
        $this->_states = $states;
        $this->_notifyEveryChange = $notifyEveryChange;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getStates()
    {
        return $this->_states;
    }

    public function isNotifyEveryChange()
    {
        return $this->_notifyEveryChange;
    }
}