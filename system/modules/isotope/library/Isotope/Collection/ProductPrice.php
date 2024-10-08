<?php

/*
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009 - 2019 terminal42 gmbh & Isotope eCommerce Workgroup
 *
 * @link       https://isotopeecommerce.org
 * @license    https://opensource.org/licenses/lgpl-3.0.html
 */

namespace Isotope\Collection;

use Isotope\Interfaces\IsotopePrice;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Isotope;
use Contao\Model\Collection;

/**
 * @method \Isotope\Model\ProductPrice current()
 */
class ProductPrice extends Collection implements IsotopePrice
{
    /**
     * Remove duplicate models with the same column value (first model will be kept)
     *
     * @param string $strColumn
     *
     * @return $this
     */
    public function filterDuplicatesBy($strColumn)
    {
        $this->reset();

        $arrFound = array();

        $this->arrModels = array_filter(
            $this->arrModels,
            function($objModel) use (&$arrFound, $strColumn) {
                if (isset($arrFound[$objModel->$strColumn])) {
                    return false;
                }

                $arrFound[$objModel->$strColumn] = $objModel->id;

                return true;
            }
        );

        return $this;
    }

    /**
     * Return true if more than one price is available
     *
     * @return bool
     */
    public function hasTiers()
    {
        return $this->current()->hasTiers();
    }

    /**
     * Return lowest tier (= minimum quantity)
     *
     * @return int
     */
    public function getLowestTier()
    {
        return $this->current()->getLowestTier();
    }

    /**
     * Return price
     *
     * @param int   $intQuantity
     *
     * @return float
     */
    public function getAmount($intQuantity = 1, array $arrOptions = array())
    {
        return $this->current()->getAmount($intQuantity, $arrOptions);
    }

    /**
     * Return original price
     *
     * @param int   $intQuantity
     *
     * @return  float
     */
    public function getOriginalAmount($intQuantity = 1, array $arrOptions = array())
    {
        return $this->current()->getOriginalAmount($intQuantity, $arrOptions);
    }

    /**
     * Return net price (without taxes)
     *
     * @param int   $intQuantity
     *
     * @return float
     */
    public function getNetAmount($intQuantity = 1, array $arrOptions = array())
    {
        return $this->current()->getNetAmount($intQuantity, $arrOptions);
    }

    /**
     * Return gross price (with all taxes)
     *
     * @param int   $intQuantity
     *
     * @return float
     */
    public function getGrossAmount($intQuantity = 1, array $arrOptions = array())
    {
        return $this->current()->getGrossAmount($intQuantity, $arrOptions);
    }

    /**
     * Generate price for HTML rendering
     *
     * @param bool  $blnShowTiers
     * @param int   $intQuantity
     *
     * @return string
     */
    public function generate($blnShowTiers = false, $intQuantity = 1, array $arrOptions = array())
    {
        if (\count($this->arrModels) > 1) {

            $fltPrice           = null;
            $fltOriginalPrice   = null;
            $arrPrices          = array();

            /** @var \Isotope\Model\ProductPrice $objPrice */
            foreach ($this->arrModels as $objPrice) {
                $fltNew       = $blnShowTiers ? $objPrice->getLowestAmount($arrOptions) : $objPrice->getAmount($intQuantity, $arrOptions);
                $arrPrices[]  = $fltNew;

                if (null === $fltPrice || $fltNew < $fltPrice) {
                    $fltPrice         = $fltNew;
                    $fltOriginalPrice = $objPrice->getOriginalAmount($intQuantity, $arrOptions);
                }
            }

            $arrPrices = array_unique($arrPrices);
            $blnShowFrom = \count($arrPrices) > 1;

            if ($blnShowFrom) {
                return sprintf($GLOBALS['TL_LANG']['MSC']['priceRangeLabel'], Isotope::formatPriceWithCurrency($fltPrice));
            } elseif ($fltPrice < $fltOriginalPrice) {
                $strPrice         = Isotope::formatPriceWithCurrency($fltPrice);
                $strOriginalPrice = Isotope::formatPriceWithCurrency($fltOriginalPrice);

                return '<div class="original_price"><strike>' . $strOriginalPrice . '</strike></div><div class="price">' . $strPrice . '</div>';
            } else {
                return Isotope::formatPriceWithCurrency($fltPrice);
            }

        } else {
            return $this->current()->generate($blnShowTiers, $intQuantity, $arrOptions);
        }
    }

    public function setProduct(IsotopeProduct $product)
    {
        foreach ($this->arrModels as $model) {
            if ($model instanceof \Isotope\Model\ProductPrice) {
                $model->setProduct($product);
            }
        }
    }
}
