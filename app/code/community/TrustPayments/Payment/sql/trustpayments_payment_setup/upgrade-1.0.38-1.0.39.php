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
$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

/**
 * Add a new column to the sales/order table that stores whether the invoice has been derecognized.
 */
$installer->getConnection()->addColumn(
    $installer->getTable('sales/order'), 'trustpayments_derecognized', array(
    'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'default' => '0',
    'comment' => 'Trust Payments Payment Derecognized'
    )
);

$installer->endSetup();