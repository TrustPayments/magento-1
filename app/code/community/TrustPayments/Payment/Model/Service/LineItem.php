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
 * This service provides functions to convert Magento quote and order items into Trust Payments API line items.
 */
class TrustPayments_Payment_Model_Service_LineItem extends TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * Returns the line items for the given invoice, with reduced amounts to match the expected sum.
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param float $amount
     * @return \TrustPayments\Sdk\Model\LineItemCreate[]
     */
    public function collectInvoiceLineItems(Mage_Sales_Model_Order_Invoice $invoice, $amount)
    {
        $lineItems = array();

        foreach ($invoice->getAllItems() as $item) {
            /* @var Mage_Sales_Model_Order_Invoice_Item $item */
            $orderItem = $item->getOrderItem();
            if ($orderItem->getParentItemId() != null &&
                $orderItem->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                continue;
            }

            if ($orderItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE &&
                $orderItem->getParentItemId() == null) {
                continue;
            }

            $lineItems[] = $this->getProductLineItem($item, $invoice->getOrderCurrencyCode());
        }

        $shippingItem = $this->getInvoiceShippingLineItem($invoice);
        if ($shippingItem) {
            $lineItems[] = $shippingItem;
        }

        $surchargeItem = $this->getFoomanSurchargeLineItem($invoice->getOrder());
        if ($surchargeItem) {
            $lineItems[] = $surchargeItem;
        }

        $mxgGiftCard = $this->getMX2GiftCardLineItem($invoice->getOrder());
        if ($mxgGiftCard) {
            $lineItems[] = $mxgGiftCard;
        }

        $awGiftCards = $this->getAWGiftCardLineItems($invoice);
        if (! empty($awGiftCards)) {
            $lineItems = array_merge($lineItems, $awGiftCards);
        }

        $result = new StdClass();
        $result->items = $lineItems;
        Mage::dispatchEvent('trustpayments_payment_convert_invoice_line_items',
            array(
                'result' => $result,
                'invoice' => $invoice
            ));
        return $this->getLineItemHelper()->getItemsByReductionAmount($result->items, $amount,
            $invoice->getOrderCurrencyCode());
    }

    /**
     * Returns the line item for the invoice's shipping.
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return \TrustPayments\Sdk\Model\LineItemCreate
     */
    protected function getInvoiceShippingLineItem(Mage_Sales_Model_Order_Invoice $invoice)
    {
        if ($invoice->getShippingAmount() > 0) {
            $lineItem = new \TrustPayments\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax(
                $this->roundAmount($invoice->getShippingInclTax(), $invoice->getOrderCurrencyCode()));
            if (Mage::getStoreConfig('trustpayments_payment/line_item/overwrite_shipping_description',
                $invoice->getStore())) {
                $lineItem->setName(
                    Mage::getStoreConfig('trustpayments_payment/line_item/custom_shipping_description',
                        $invoice->getStore()));
            } else {
                $lineItem->setName($invoice->getOrder()
                    ->getShippingDescription());
            }

            $lineItem->setQuantity(1);
            $lineItem->setSku('shipping');
            $tax = $this->getShippingTax($invoice->getOrder());
            if ($tax != null && $tax->getRate() > 0) {
                $lineItem->setTaxes(array(
                    $tax
                ));
            }

            $lineItem->setType(\TrustPayments\Sdk\Model\LineItemType::SHIPPING);
            $lineItem->setUniqueId('shipping');
            return $this->cleanLineItem($lineItem);
        }
    }

    /**
     * Returns the line items for the given order or quote.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \TrustPayments\Sdk\Model\LineItemCreate[]
     */
    public function collectLineItems($entity)
    {
        $lineItems = $this->getProductLineItems($entity->getItemsCollection(), $this->getCurrencyCode($entity));

        $shippingItem = $this->getShippingLineItem($entity);
        if ($shippingItem) {
            $lineItems[] = $shippingItem;
        }

        $surchargeItem = $this->getFoomanSurchargeLineItem($entity);
        if ($surchargeItem) {
            $lineItems[] = $surchargeItem;
        }

        $mxgGiftCard = $this->getMX2GiftCardLineItem($entity);
        if ($mxgGiftCard) {
            $lineItems[] = $mxgGiftCard;
        }

        $awGiftCards = $this->getAWGiftCardLineItems($entity);
        if (! empty($awGiftCards)) {
            $lineItems = array_merge($lineItems, $awGiftCards);
        }

        $result = new StdClass();
        $result->items = $lineItems;
        Mage::dispatchEvent('trustpayments_payment_convert_line_items',
            array(
                'result' => $result,
                'entity' => $entity
            ));
        return $this->getLineItemHelper()->cleanupLineItems($result->items, $entity->getGrandTotal(),
            $this->getCurrencyCode($entity),
            Mage::getStoreConfig('trustpayments_payment/line_item/enforce_consistency', $entity->getStore()),
            $entity instanceof Mage_Sales_Model_Order ? $entity->getFullTaxInfo() : array());
    }

    /**
     * Returns the line items for the given products.
     *
     * @param Mage_Sales_Model_Order_Item[]|Mage_Sales_Model_Quote_Item[] $items
     * @param string $currency
     * @return \TrustPayments\Sdk\Model\LineItemCreate[]
     */
    protected function getProductLineItems($items, $currency)
    {
        $lineItems = array();

        foreach ($items as $item) {
            /* @var Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item $item */
            if ($item->getParentItemId() != null &&
                $item->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                continue;
            }

            if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE &&
                $item->getParentItemId() == null) {
                /* @var Mage_Catalog_Model_Product $product */
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                if ($product->getPriceType() != Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED) {
                    continue;
                }
            }

            $lineItems[] = $this->getProductLineItem($item, $currency);
        }

        return $lineItems;
    }

    /**
     * Returns the line item for the given product.
     *
     * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item|Mage_Sales_Model_Order_Invoice_Item $productItem
     * @param string $currency
     * @return \TrustPayments\Sdk\Model\LineItemCreate
     */
    protected function getProductLineItem($productItem, $currency)
    {
        $lineItem = new \TrustPayments\Sdk\Model\LineItemCreate();
        $lineItem->setAmountIncludingTax($this->roundAmount($productItem->getRowTotalInclTax(), $currency));
        $lineItem->setName($productItem->getName());
        $lineItem->setQuantity($productItem->getQty() ? $productItem->getQty() : $productItem->getQtyOrdered());
        $lineItem->setShippingRequired(! $productItem->getIsVirtual());
        $lineItem->setSku($productItem->getSku());

        $orderItem = ($productItem instanceof Mage_Sales_Model_Order_Invoice_Item) ? $productItem->getOrderItem() : $productItem;
        if ($orderItem->getTaxPercent() > 0) {
            $lineItem->setTaxes(array(
                $this->getTax($orderItem)
            ));
        }

        $lineItem->setType(\TrustPayments\Sdk\Model\LineItemType::PRODUCT);
        $uniqueId = $productItem->getId();
        if ($productItem instanceof Mage_Sales_Model_Order_Item) {
            $uniqueId = $productItem->getQuoteItemId();
        } elseif ($productItem instanceof Mage_Sales_Model_Order_Invoice_Item) {
            $uniqueId = $productItem->getOrderItem()->getQuoteItemId();
        }

        $lineItem->setUniqueId($uniqueId);

        $attributes = array();
        foreach ($this->getProductOptions($productItem) as $option) {
            $value = $option['value'];
            if (is_array($value)) {
                $value = current($value);
            }

            $attribute = new \TrustPayments\Sdk\Model\LineItemAttributeCreate();
            $attribute->setLabel($this->fixLength($this->getFirstLine($option['label']), 512));
            $attribute->setValue($this->fixLength($this->getFirstLine($value), 512));
            $attributes[$this->getAttributeKey($option)] = $attribute;
        }

        if (! empty($attributes)) {
            $lineItem->setAttributes($attributes);
        }

        if ($productItem->getDiscountAmount() != 0) {
            /* @var Mage_Tax_Helper_Data $taxHelper */
            $taxHelper = Mage::helper('tax');
            if ($taxHelper->priceIncludesTax($productItem->getStoreId()) ||
                ! $taxHelper->applyTaxAfterDiscount($productItem->getStoreId())) {
                $lineItem->setDiscountIncludingTax($this->roundAmount($productItem->getDiscountAmount(), $currency));
                $lineItem->setAmountIncludingTax(
                    $this->roundAmount($productItem->getRowTotalInclTax() - $productItem->getDiscountAmount(), $currency));
            } else {
                $lineItem->setDiscountIncludingTax(
                    $this->roundAmount($productItem->getDiscountAmount() * ($productItem->getTaxPercent() / 100 + 1),
                        $currency));
                $lineItem->setAmountIncludingTax(
                    $this->roundAmount(
                        $productItem->getRowTotal() - $productItem->getDiscountAmount() + $productItem->getTaxAmount(),
                        $currency));
            }
        }

        $result = new StdClass();
        $result->item = $lineItem;
        Mage::dispatchEvent('trustpayments_payment_convert_product_line_item',
            array(
                'result' => $result,
                'entityItem' => $productItem,
                'currency' => $currency
            ));
        return $this->cleanLineItem($result->item);
    }

	/**
	 * Get attribute array key value
	 *
	 * @param array $option
	 *
	 * @return string
	 */
	protected function getAttributeKey(array $option)
	{
		$attributeKey = 'random_' . rand(10, 10000);
		$label        = empty($option['label']) ? null : $option['label'];

		if (!empty($option['option_id'])) {
			$attributeKey = 'option_' . $option['option_id'];
		} elseif (!empty($label)) {

			$attributeKeyTmp = preg_replace('/[^a-z0-9]/', '', strtolower($label));
			if (empty($attributeKeyTmp)) {
				$attributeKey = 'hash_' . md5($label);
			} else {
				$attributeKey = is_numeric($attributeKeyTmp[0]) ? 'o_' . $attributeKeyTmp : $attributeKeyTmp;
			}
		}

		$attributeKey = $this->fixLength($attributeKey, 40);

		return $attributeKey;

	}

    /**
     *
     * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item|Mage_Sales_Model_Order_Invoice_Item $productItem
     */
    protected function getProductOptions($productItem)
    {
        if ($productItem instanceof Mage_Sales_Model_Order_Item ||
            $productItem instanceof Mage_Sales_Model_Order_Invoice_Item) {
            $result = array();
            if ($options = $productItem->getProductOptions()) {
                if (isset($options['options'])) {
                    $result = array_merge($result, $options['options']);
                }

                if (isset($options['additional_options'])) {
                    $result = array_merge($result, $options['additional_options']);
                }

                if (isset($options['attributes_info'])) {
                    $result = array_merge($result, $options['attributes_info']);
                }
            }

            return $result;
        } elseif ($productItem instanceof Mage_Sales_Model_Quote_Item) {
            /* @var $helper Mage_Catalog_Helper_Product_Configuration */
            $helper = Mage::helper('catalog/product_configuration');
            return $helper->getCustomOptions($productItem);
        } else {
            return array();
        }
    }

    /**
     * Returns the line item for the shipping.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \TrustPayments\Sdk\Model\LineItemCreate
     */
    protected function getShippingLineItem($entity)
    {
        $shippingInfo = $entity;
        if ($entity instanceof Mage_Sales_Model_Quote) {
            $shippingInfo = $entity->getShippingAddress();
        }

        if ($shippingInfo->getShippingAmount() > 0) {
            $lineItem = new \TrustPayments\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax(
                $this->roundAmount($shippingInfo->getShippingInclTax(), $this->getCurrencyCode($entity)));
            if (Mage::getStoreConfig('trustpayments_payment/line_item/overwrite_shipping_description',
                $entity->getStore())) {
                $lineItem->setName(
                    Mage::getStoreConfig('trustpayments_payment/line_item/custom_shipping_description',
                        $entity->getStore()));
            } else {
                $lineItem->setName($shippingInfo->getShippingDescription());
            }

            $lineItem->setQuantity(1);
            $lineItem->setSku('shipping');
            $tax = $this->getShippingTax($entity);
            if ($tax != null && $tax->getRate() > 0) {
                $lineItem->setTaxes(array(
                    $tax
                ));
            }

            $lineItem->setType(\TrustPayments\Sdk\Model\LineItemType::SHIPPING);
            $lineItem->setUniqueId('shipping');

            $result = new StdClass();
            $result->item = $lineItem;
            Mage::dispatchEvent('trustpayments_payment_convert_shipping_line_item',
                array(
                    'result' => $result,
                    'entity' => $entity
                ));
            return $this->cleanLineItem($result->item);
        }
    }

    /**
     * Returns the tax for the shipping.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \TrustPayments\Sdk\Model\TaxCreate
     */
    protected function getShippingTax($entity)
    {
        /* @var Mage_Tax_Model_Calculation $taxCalculation */
        $taxCalculation = Mage::getSingleton('tax/calculation');

        /* @var Mage_Customer_Model_Group $customerGroup */
        $customerGroup = Mage::getModel('customer/group');

        $classId = $customerGroup->getTaxClassId($entity->getCustomerGroupId());
        $request = $taxCalculation->getRateRequest($entity->getShippingAddress(), $entity->getBillingAddress(), $classId,
            $entity->getStore());
        $shippingTaxClass = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS,
            $entity->getStore());
        if (! $shippingTaxClass) {
            return null;
        }

        /* @var Mage_Tax_Model_Class $taxClass */
        $taxClass = Mage::getModel('tax/class')->load($shippingTaxClass);

        $tax = new \TrustPayments\Sdk\Model\TaxCreate();
        $tax->setRate($taxCalculation->getRate($request->setProductClassId($shippingTaxClass)));
        $tax->setTitle($this->fixLength($taxClass->getClassName(), 40));
        return $tax;
    }

    /**
     * Returns the line item for the Fooman surcharge.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \TrustPayments\Sdk\Model\LineItemCreate
     */
    protected function getFoomanSurchargeLineItem($entity)
    {
        if (Mage::helper('core')->isModuleEnabled('Fooman_Surcharge') && $entity->getFoomanSurchargeAmount() != 0) {
            $lineItem = new \TrustPayments\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax(
                $this->roundAmount($entity->getFoomanSurchargeAmount(), $this->getCurrencyCode($entity)));
            $lineItem->setName($entity->getFoomanSurchargeDescription());
            $lineItem->setQuantity(1);
            $lineItem->setSku('surcharge');
            $tax = $this->getSurchargeTax($entity);
            if ($tax != null && $tax->getRate() > 0) {
                $lineItem->setTaxes(array(
                    $tax
                ));
            }

            $lineItem->setType(\TrustPayments\Sdk\Model\LineItemType::FEE);
            $lineItem->setUniqueId('surcharge');
            return $this->cleanLineItem($lineItem);
        }
    }

    /**
     * Returns the tax for the Fooman surcharge.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \TrustPayments\Sdk\Model\TaxCreate
     */
    protected function getSurchargeTax($entity)
    {
        $surchargeTaxClass = Mage::getStoreConfig('tax/classes/surcharge_tax_class', $entity->getStoreId());
        if ($surchargeTaxClass) {
            /* @var Mage_Tax_Model_Calculation $taxCalculation */
            $taxCalculation = Mage::getSingleton('tax/calculation');

            /* @var Mage_Customer_Model_Group $customerGroup */
            $customerGroup = Mage::getModel('customer/group');

            $classId = $customerGroup->getTaxClassId($entity->getCustomerGroupId());
            $request = $taxCalculation->getRateRequest($entity->getShippingAddress(), $entity->getBillingAddress(),
                $classId, $entity->getStore());
            if ($surchargeTaxRate = $taxCalculation->getRate($request->setProductClassId($surchargeTaxClass))) {
                /* @var Mage_Tax_Model_Class $taxClass */
                $taxClass = Mage::getModel('tax/class')->load($surchargeTaxClass);

                $tax = new \TrustPayments\Sdk\Model\TaxCreate();
                $tax->setRate($surchargeTaxRate);
                $tax->setTitle($this->fixLength($taxClass->getClassName(), 40));
                return $tax;
            }
        }
    }

    /**
     * Returns the line item for the MX2 giftcard.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \TrustPayments\Sdk\Model\LineItemCreate
     */
    protected function getMX2GiftCardLineItem($entity)
    {
        if (Mage::helper('core')->isModuleEnabled('MX2_Giftcard') && $entity->getGiftCardsAmount() != 0) {
            $lineItem = new \TrustPayments\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax(
                $this->roundAmount(- 1 * $entity->getGiftCardsAmount(), $this->getCurrencyCode($entity)));
            $lineItem->setName($this->getHelper()
                ->__('Giftcard'));
            $lineItem->setQuantity(1);
            $lineItem->setSku('giftcard');
            $lineItem->setType(\TrustPayments\Sdk\Model\LineItemType::DISCOUNT);
            $lineItem->setUniqueId('giftcard');
            return $this->cleanLineItem($lineItem);
        }
    }

    /**
     * Returns the line item for the AW giftcards.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote|Mage_Sales_Model_Order_Invoice $entity
     * @return \TrustPayments\Sdk\Model\LineItemCreate[]
     */
    protected function getAWGiftCardLineItems($entity)
    {
        if (Mage::helper('core')->isModuleEnabled('AW_Giftcard')) {
            $giftcards = array();
            if ($entity instanceof Mage_Sales_Model_Order) {
                $giftcards = Mage::helper('aw_giftcard/totals')->getInvoicedGiftCardsByOrderId($entity->getId());
            } elseif ($entity instanceof Mage_Sales_Model_Quote) {
                $giftcards = Mage::helper('aw_giftcard/totals')->getQuoteGiftCards($entity->getId());
            } elseif ($entity instanceof Mage_Sales_Model_Order_Invoice) {
                $giftcards = Mage::helper('aw_giftcard/totals')->getInvoiceGiftCards($entity->getId());
            }
            $lineItems = array();
            foreach ($giftcards as $giftcard) {
                $giftcardModel = Mage::getModel('aw_giftcard/giftcard')->load($giftcard->getGiftcardId());
                $lineItem = new \TrustPayments\Sdk\Model\LineItemCreate();
                $lineItem->setAmountIncludingTax(
                    $this->roundAmount(- 1 * $giftcard->getGiftcardAmount(), $this->getCurrencyCode($entity)));
                $lineItem->setName($this->getHelper()
                    ->__('Giftcard (%s)', $giftcardModel->getCode()));
                $lineItem->setQuantity(1);
                $lineItem->setSku('giftcard_' . $giftcard->getGiftcardId());
                $lineItem->setType(\TrustPayments\Sdk\Model\LineItemType::DISCOUNT);
                $lineItem->setUniqueId('aw_giftcard_' . $giftcard->getGiftcardId());
                $lineItems[] = $this->cleanLineItem($lineItem);
            }
            return $lineItems;
        }
    }

    /**
     * Returns the tax for the given item.
     *
     * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item $item
     * @return \TrustPayments\Sdk\Model\TaxCreate
     */
    protected function getTax($item)
    {
        /* @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($item->getProductId());

        /* @var Mage_Tax_Model_Class $taxClass */
        $taxClass = Mage::getModel('tax/class')->load($product->getTaxClassId());

        $tax = new \TrustPayments\Sdk\Model\TaxCreate();
        $tax->setRate($item->getTaxPercent());
        $tax->setTitle($this->fixLength($taxClass->getClassName(), 40));
        return $tax;
    }

    /**
     * Cleans the given line item for it to meet the API's requirements.
     *
     * @param \TrustPayments\Sdk\Model\LineItemCreate $lineItem
     * @return \TrustPayments\Sdk\Model\LineItemCreate
     */
    protected function cleanLineItem(\TrustPayments\Sdk\Model\LineItemCreate $lineItem)
    {
        $lineItem->setSku($this->fixLength($this->removeLinebreaks($lineItem->getSku()), 200));
        $lineItem->setName($this->fixLength($this->removeLinebreaks($lineItem->getName()), 150));
        return $lineItem;
    }

    /**
     * Returns the currency code to use.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return string
     */
    protected function getCurrencyCode($entity)
    {
        if ($entity instanceof Mage_Sales_Model_Quote) {
            return $entity->getQuoteCurrencyCode();
        } else {
            return $entity->getOrderCurrencyCode();
        }
    }

    /**
     * Returns the line item helper.
     *
     * @return TrustPayments_Payment_Helper_LineItem
     */
    protected function getLineItemHelper()
    {
        return Mage::helper('trustpayments_payment/lineItem');
    }
}