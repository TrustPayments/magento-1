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
 * This block renders the grid tab that lists the customer's tokens.
 */
class TrustPayments_Payment_Block_Adminhtml_Customer_Token extends Mage_Adminhtml_Block_Widget_Grid implements 
    Mage_Adminhtml_Block_Widget_Tab_Interface
{

    protected function _construct()
    {
        parent::_construct();
        $this->setId('trustpayments_payment_adminhtml_customer_token');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
        $this->setSkipGenerateContent(true);
    }

    /**
     * Prepares the token grid collection.
     *
     * @return TrustPayments_Payment_Block_Adminhtml_Customer_Token
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('trustpayments_payment/entity_tokenInfo')->getCollection()->addCustomerFilter(
            Mage::registry('current_customer')->getId());
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepares the token grid's columns.
     *
     * @return TrustPayments_Payment_Block_Adminhtml_Customer_Token
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('trustpayments_payment');

        $this->addColumn('token_id',
            array(
                'header' => $helper->__('Token ID'),
                'width' => '50px',
                'type' => 'number',
                'index' => 'token_id'
            ));

        $this->addColumn('name',
            array(
                'header' => $helper->__('Name'),
                'width' => '250px',
                'type' => 'text',
                'index' => 'name'
            ));

        $this->addColumn('payment_method_id',
            array(
                'header' => $helper->__('Payment Method'),
                'type' => 'text',
                'index' => 'payment_method_id',
                'renderer' => 'TrustPayments_Payment_Block_Adminhtml_Customer_Token_PaymentMethod'
            ));

        $this->addColumn('state',
            array(
                'header' => $helper->__('State'),
                'type' => 'text',
                'index' => 'state',
                'renderer' => 'TrustPayments_Payment_Block_Adminhtml_Customer_Token_State'
            ));

        $this->addColumn('action',
            array(
                'header' => $helper->__('Action'),
                'width' => '50px',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => array(
                    array(
                        'caption' => $helper->__('Delete'),
                        'url' => array(
                            'base' => 'adminhtml/trustpayments_token/delete'
                        ),
                        'field' => 'id'
                    )
                ),
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
                'is_system' => true
            ));

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return;
    }

    public function getGridUrl()
    {
        return $this->getTabUrl();
    }

    public function getTabUrl()
    {
        return $this->getUrl('adminhtml/trustpayments_token/grid',
            array(
                'id' => Mage::registry('current_customer')->getId(),
                '_current' => true
            ));
    }

    public function getTabClass()
    {
        return 'ajax';
    }

    public function getTabLabel()
    {
        return Mage::helper('trustpayments_payment')->__('Trust Payments Tokens');
    }

    public function getTabTitle()
    {
        return Mage::helper('trustpayments_payment')->__('Trust Payments Tokens');
    }

    public function canShowTab()
    {
        return Mage::getSingleton('admin/session')->isAllowed('customer/manage/trustpayments_token');
    }

    public function isHidden()
    {
        return false;
    }
}