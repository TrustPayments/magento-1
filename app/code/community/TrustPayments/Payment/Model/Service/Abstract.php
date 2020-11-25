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
 * Abstract service providing shared methods.
 */
class TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * Returns the data helper.
     *
     * @return TrustPayments_Payment_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('trustpayments_payment');
    }

    /**
     * Returns the fraction digits for the given currency.
     *
     * @param string $currencyCode
     * @return number
     */
    protected function getCurrencyFractionDigits($currencyCode)
    {
        return $this->getHelper()->getCurrencyFractionDigits($currencyCode);
    }

    /**
     * Rounds the given amount to the currency's format.
     *
     * @param float $amount
     * @param string $currencyCode
     * @return number
     */
    protected function roundAmount($amount, $currencyCode)
    {
        return round($amount, $this->getCurrencyFractionDigits($currencyCode));
    }

    /**
     * Compares the given amounts.
     *
     * @param float $amount1
     * @param float $amount2
     * @param string $currencyCode
     * @return number
     */
    protected function compareAmounts($amount1, $amount2, $currencyCode)
    {
        return $this->roundAmount($amount1, $currencyCode) - $this->roundAmount($amount2, $currencyCode);
    }

    /**
     * Creates and returns a new entity filter.
     *
     * @param string $fieldName
     * @param mixed $value
     * @param string $operator
     * @return \TrustPayments\Sdk\Model\EntityQueryFilter
     */
    protected function createEntityFilter($fieldName, $value,
        $operator = \TrustPayments\Sdk\Model\CriteriaOperator::EQUALS)
    {
        $filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
        $filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::LEAF);
        $filter->setOperator($operator);
        $filter->setFieldName($fieldName);
        $filter->setValue($value);
        return $filter;
    }

    /**
     * Creates and returns a new entity order by.
     *
     * @param string $fieldName
     * @param string $sortOrder
     * @return \TrustPayments\Sdk\Model\EntityQueryOrderBy
     */
    protected function createEntityOrderBy($fieldName,
        $sortOrder = \TrustPayments\Sdk\Model\EntityQueryOrderByType::DESC)
    {
        $orderBy = new \TrustPayments\Sdk\Model\EntityQueryOrderBy();
        $orderBy->setFieldName($fieldName);
        $orderBy->setSorting($sortOrder);
        return $orderBy;
    }

    /**
     * Changes the given string to have no more characters as specified.
     *
     * @param string $string
     * @param int $maxLength
     * @return string
     */
    protected function fixLength($string, $maxLength)
    {
        return mb_substr($string, 0, $maxLength, 'UTF-8');
    }

    /**
     * Removes all line breaks in the given string and replaces them with a whitespace character.
     *
     * @param string $string
     * @return string
     */
    protected function removeLinebreaks($string)
    {
        return preg_replace("/\r|\n/", ' ', $string);
    }

    /**
     * Returns the first line of the given string only.
     *
     * @param string $string
     * @return string
     */
    protected function getFirstLine($string)
    {
        return rtrim(strtok($string, "\n"));
    }
}