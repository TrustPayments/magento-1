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
 * This helper provides functions to handle line items.
 */
class TrustPayments_Payment_Helper_LineItem extends Mage_Core_Helper_Abstract
{

    /**
     * Returns the amount of the line item's reductions.
     *
     * @param \TrustPayments\Sdk\Model\LineItem[] $lineItems
     * @param \TrustPayments\Sdk\Model\LineItemReduction[] $reductions
     * @param string $currencyCode
     * @return float
     */
    public function getReductionAmount(array $lineItems, array $reductions, $currencyCode)
    {
        $lineItemMap = array();
        foreach ($lineItems as $lineItem) {
            $lineItemMap[$lineItem->getUniqueId()] = $lineItem;
        }

        $amount = 0;
        foreach ($reductions as $reduction) {
            if (! isset($lineItemMap[$reduction->getLineItemUniqueId()])) {
                Mage::throwException(
                    'The refund cannot be executed as the transaction\'s line items do not match the order\'s.');
            }

            $lineItem = $lineItemMap[$reduction->getLineItemUniqueId()];
            $unitPrice = $lineItem->getAmountIncludingTax() / $lineItem->getQuantity();
            $amount += $unitPrice * $reduction->getQuantityReduction();
            $amount += $reduction->getUnitPriceReduction() *
                ($lineItem->getQuantity() - $reduction->getQuantityReduction());
        }

        return $this->roundAmount($amount, $currencyCode);
    }

    /**
     * Returns the total amount including tax of the given line items.
     *
     * @param \TrustPayments\Sdk\Model\LineItem[] $lineItems
     * @return float
     */
    public function getTotalAmountIncludingTax(array $lineItems)
    {
        $sum = 0;
        foreach ($lineItems as $lineItem) {
            $sum += $lineItem->getAmountIncludingTax();
        }

        return $sum;
    }

    /**
     * Returns the total tax amount of the given line items.
     *
     * @param \TrustPayments\Sdk\Model\LineItem[] $lineItems
     * @param string $currency
     * @return float
     */
    public function getTotalTaxAmount(array $lineItems, $currency)
    {
        $sum = 0;
        foreach ($lineItems as $lineItem) {
            $aggregatedTaxRate = 0;
            if (is_array($lineItem->getTaxes())) {
                foreach ($lineItem->getTaxes() as $tax) {
                    $aggregatedTaxRate += $tax->getRate();
                }
            }
            $amountExcludingTax = $this->roundAmount(
                $lineItem->getAmountIncludingTax() / (1 + $aggregatedTaxRate / 100), $currency);
            $sum += $lineItem->getAmountIncludingTax() - $amountExcludingTax;
        }

        return $sum;
    }

    /**
     * Reduces the amounts of the given line items proportionally to match the given expected sum.
     *
     * @param \TrustPayments\Sdk\Model\LineItemCreate[] $originalLineItems
     * @param float $expectedSum
     * @param string $currency
     * @return \TrustPayments\Sdk\Model\LineItemCreate[]
     */
    public function getItemsByReductionAmount(array $lineItems, $expectedSum, $currency)
    {
        if (empty($lineItems)) {
            Mage::throwException("No line items provided.");
        }

        $total = $this->getTotalAmountIncludingTax($lineItems);
        $factor = $expectedSum / $total;

        $appliedTotal = 0;
        foreach ($lineItems as $lineItem) {
            /* @var \TrustPayments\Sdk\Model\LineItem $lineItem */
            $lineItem->setAmountIncludingTax(
                $this->roundAmount($lineItem->getAmountIncludingTax() * $factor, $currency));
            $appliedTotal += $lineItem->getAmountIncludingTax() * $factor;
        }

        // Fix rounding error
        $roundingDifference = $expectedSum - $appliedTotal;
        $lineItems[0]->setAmountIncludingTax(
            $this->roundAmount($lineItems[0]->getAmountIncludingTax() + $roundingDifference, $currency));
        return $this->ensureUniqueIds($lineItems);
    }

    /**
     * Cleans the given line items by ensuring uniqueness and introducing adjustment line items if necessary.
     *
     * @param \TrustPayments\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $expectedSum
     * @param string $currency
     * @return \TrustPayments\Sdk\Model\LineItemCreate[]
     */
    public function cleanupLineItems(array $lineItems, $expectedSum, $currency, $ensureConsistency = true,
        array $taxInfo = array())
    {
        $diff = $this->getDifference($lineItems, $expectedSum, $currency);
        if ($diff != 0) {
            $currencyFractionDigits = Mage::helper('trustpayments_payment')->getCurrencyFractionDigits(
                $currency);
            if (abs($diff) < count($lineItems) * pow(10, - $currencyFractionDigits)) {
                $this->fixDiscountLineItem($lineItems, $diff, $currency);
            }

            if ($ensureConsistency) {
                $this->checkAmount($lineItems, $expectedSum, $currency);
            } else {
                $this->adjustLineItems($lineItems, $expectedSum, $currency, $taxInfo);
            }
        }

        return $this->ensureUniqueIds($lineItems);
    }

    /**
     *
     * @param \TrustPayments\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $amount
     * @param string $currency
     */
    protected function adjustLineItems(array &$lineItems, $expectedSum, $currency, array $taxInfo)
    {
        $expectedSum = $this->roundAmount($expectedSum, $currency);
        $effectiveSum = $this->roundAmount($this->getTotalAmountIncludingTax($lineItems), $currency);
        $diff = $expectedSum - $effectiveSum;

        $adjustmentLineItem = new \TrustPayments\Sdk\Model\LineItemCreate();
        $adjustmentLineItem->setAmountIncludingTax($this->roundAmount($diff, $currency));
        $adjustmentLineItem->setName($this->__('Adjustment'));
        $adjustmentLineItem->setQuantity(1);
        $adjustmentLineItem->setSku('adjustment');
        $adjustmentLineItem->setUniqueId('adjustment');
        $adjustmentLineItem->setShippingRequired(false);
        $adjustmentLineItem->setType(
            $diff > 0 ? \TrustPayments\Sdk\Model\LineItemType::FEE : \TrustPayments\Sdk\Model\LineItemType::DISCOUNT);

        if (! empty($taxInfo) && count($taxInfo) == 1) {
            $taxAmount = $this->getTotalTaxAmount($lineItems, $currency);
            $taxDiff = $this->roundAmount($taxInfo[0]['amount'] - $taxAmount, $currency);
            if ($taxDiff != 0) {
                $rate = $taxInfo[0]['percent'];
                $adjustmentTaxAmount = $this->roundAmount($diff - $diff / (1 + $rate / 100), $currency);
                if ($adjustmentTaxAmount == $taxDiff) {
                    $taxes = array();
                    foreach ($taxInfo[0]['rates'] as $rate) {
                        $tax = new \TrustPayments\Sdk\Model\TaxCreate();
                        $tax->setRate($rate['percent']);
                        $tax->setTitle($this->fixLength($rate['title'], 40));
                        $taxes[] = $tax;
                    }
                    $adjustmentLineItem->setTaxes($taxes);
                }
            }
        }

        $lineItems[] = $adjustmentLineItem;
    }

    /**
     *
     * @param \TrustPayments\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $amount
     * @param string $currency
     */
    protected function getDifference(array $lineItems, $expectedSum, $currency)
    {
        $expectedSum = $this->roundAmount($expectedSum, $currency);
        $effectiveSum = $this->roundAmount($this->getTotalAmountIncludingTax($lineItems), $currency);
        return $expectedSum - $effectiveSum;
    }

    /**
     *
     * @param \TrustPayments\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $amount
     * @param string $currency
     */
    protected function checkAmount(array $lineItems, $expectedSum, $currency)
    {
        $expectedSum = $this->roundAmount($expectedSum, $currency);
        $effectiveSum = $this->roundAmount($this->getTotalAmountIncludingTax($lineItems), $currency);
        $diff = $expectedSum - $effectiveSum;
        if ($diff != 0) {
            Mage::throwException(
                'The line item total amount of ' . $effectiveSum . ' does not match the order\'s invoice amount of ' .
                $expectedSum . '.');
        }
    }

    /**
     *
     * @param \TrustPayments\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $amount
     * @param string $currency
     */
    protected function fixDiscountLineItem(array &$lineItems, $amount, $currency)
    {
        foreach (array_reverse($lineItems, true) as $index => $lineItem) {
            if (preg_match('/^(\d+)-discount$/', $lineItem->getUniqueId())) {
                $updatedLineItem = new \TrustPayments\Sdk\Model\LineItemCreate();
                $updatedLineItem->setAmountIncludingTax(
                    $this->roundAmount($lineItem->getAmountIncludingTax() + $amount, $currency));
                $updatedLineItem->setName($lineItem->getName());
                $updatedLineItem->setQuantity($lineItem->getQuantity());
                $updatedLineItem->setSku($lineItem->getSku());
                $updatedLineItem->setUniqueId($lineItem->getUniqueId());
                $updatedLineItem->setShippingRequired($lineItem->getShippingRequired());
                $updatedLineItem->setTaxes($lineItem->getTaxes());
                $updatedLineItem->setType($lineItem->getType());
                $updatedLineItem->setAttributes($lineItem->getAttributes());
                $lineItems[$index] = $updatedLineItem;
                return;
            }
        }
    }

    /**
     * Ensures uniqueness of the line items.
     *
     * @param \TrustPayments\Sdk\Model\LineItemCreate[] $lineItems
     * @return \TrustPayments\Sdk\Model\LineItemCreate[]
     */
    public function ensureUniqueIds(array $lineItems)
    {
        $uniqueIds = array();
        foreach ($lineItems as $lineItem) {
            $uniqueId = $lineItem->getUniqueId();
            if (empty($uniqueId)) {
                $uniqueId = preg_replace("/[^a-z0-9]/", '', strtolower($lineItem->getSku()));
            }

            if (empty($uniqueId)) {
                Mage::throwException("There is an invoice item without unique id.");
            }

            if (isset($uniqueIds[$uniqueId])) {
                $backup = $uniqueId;
                $uniqueId = $uniqueId . '_' . $uniqueIds[$uniqueId];
                $uniqueIds[$backup] ++;
            } else {
                $uniqueIds[$uniqueId] = 1;
            }

            $lineItem->setUniqueId($uniqueId);
        }

        return $lineItems;
    }

    protected function roundAmount($amount, $currencyCode)
    {
        /* @var TrustPayments_Payment_Helper_Data $helper */
        $helper = Mage::helper('trustpayments_payment');
        return round($amount, $helper->getCurrencyFractionDigits($currencyCode));
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
}