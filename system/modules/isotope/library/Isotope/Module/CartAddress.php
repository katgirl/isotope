<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2014 terminal42 gmbh & Isotope eCommerce Workgroup
 *
 * @package    Isotope
 * @link       http://isotopeecommerce.org
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

namespace Isotope\Module;

use Haste\Form\Form;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\ProductCollection;


class CartAddress extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'member_default';

    /**
     * Address fields
     * @var array
     */
    protected $arrAddressFields = array();

    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE') {
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ISOTOPE ECOMMERCE: CART ADDRESS ###';

            $objTemplate->title = $this->headline;
            $objTemplate->id    = $this->id;
            $objTemplate->link  = $this->name;
            $objTemplate->href  = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        $this->iso_address = deserialize($this->iso_address, true);
        $this->arrAddressFields = deserialize($this->iso_addressFields, true);

        if (empty($this->iso_address) || empty($this->arrAddressFields)) {
            return '';
        }

        // Set the custom member template
        if ($this->memberTpl != '') {
            $this->strTemplate = $this->memberTpl;
        }

        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        $this->Template->hasError  = false;
        $this->Template->slabel    = specialchars($GLOBALS['TL_LANG']['MSC']['saveAddressButton']);

        $table = Address::getTable();

        \System::loadLanguageFile($table);
        \Controller::loadDataContainer($table);

        // Call onload_callback (e.g. to check permissions)
        if (is_array($GLOBALS['TL_DCA'][$table]['config']['onload_callback'])) {
            foreach ($GLOBALS['TL_DCA'][$table]['config']['onload_callback'] as $callback) {
                $objCallback = \System::importStatic($callback[0]);
                $objCallback->$callback[1]();
            }
        }

        $objAddress = $this->getDefaultAddress();

        $objForm = new Form('iso_cart_address_' . $this->id, 'POST', function($objHaste) {
            return \Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        }, (boolean) $this->tableless);

        $objForm->bindModel($objAddress);
        $arrFields = $this->arrAddressFields;

        // Add form fields
        $objForm->addFieldsFromDca($table, function ($strName, &$arrDca) use ($arrFields) {

            if (!in_array($strName, $arrFields) || !$arrDca['eval']['feEditable']) {
                return false;
            }

            // Map checkboxWizard to regular checkbox widget
            if ($arrDca['inputType'] == 'checkboxWizard') {
                $arrDca['inputType'] = 'checkbox';
            }

            // Special field "country"
            if ($strName == 'country') {
                $arrCountries = array_merge(Isotope::getConfig()->getBillingCountries(), Isotope::getConfig()->getShippingCountries());
                $arrDca['reference'] = $arrDca['options'];
                $arrDca['options'] = array_values(array_intersect(array_keys($arrDca['options']), $arrCountries));
                $arrDca['default'] = Isotope::getConfig()->billing_country;
            }

            return true;
        });

        $objCart = Isotope::getCart();

        // Save the data
        if ($objForm->validate()) {

            if (!$objCart->id) {
                $objCart->save();
            }

            $objAddress->tstamp = time();
            $objAddress->pid = $objCart->id;
            $objAddress->save();

            // Call onsubmit_callback
            if (is_array($GLOBALS['TL_DCA'][$table]['config']['onsubmit_callback'])) {
                foreach ($GLOBALS['TL_DCA'][$table]['config']['onsubmit_callback'] as $callback) {
                    $objCallback = \System::importStatic($callback[0]);
                    $objCallback->$callback[1]($objAddress);
                }
            }

            // Set the billing address
            if (in_array('billing', $this->iso_address)) {
                $objCart->setBillingAddress($objAddress);
            }

            // Set the shipping address
            if (in_array('shipping', $this->iso_address)) {
                $objCart->setShippingAddress($objAddress);
            }

            $this->jumpToOrReload($this->jumpTo);
        }

        $objForm->addToTemplate($this->Template);
        $arrGroups = array();

        // Add groups
        foreach ($objForm->getFormFields() as $strName => $arrConfig) {
            if ($arrConfig['feGroup'] != '') {
                $arrGroups[$arrConfig['feGroup']][$strName] = $objForm->getWidget($strName)->parse();
            }
        }

        foreach ($arrGroups as $k => $v) {
            $this->Template->$k = $v;
        }

        $this->Template->addressDetails = $GLOBALS['TL_LANG'][$table]['addressDetails'];
        $this->Template->contactDetails = $GLOBALS['TL_LANG'][$table]['contactDetails'];
        $this->Template->personalData   = $GLOBALS['TL_LANG'][$table]['personalData'];
        $this->Template->loginDetails   = $GLOBALS['TL_LANG'][$table]['loginDetails'];
    }

    /**
     * Get default address for this collection and address type
     * @return  \Isotope\Model\Address
     */
    protected function getDefaultAddress()
    {
        $objAddress = null;
        $intCart = Isotope::getCart()->id;
        $strDefault = in_array('billing', $this->iso_address) ? 'isDefaultBilling' : 'isDefaultShipping';

        if ($intCart > 0) {
            $objAddress = Address::findOneBy(
                array(
                    "ptable='tl_iso_product_collection'",
                    "pid=?",
                    "$strDefault='1'"
                ),
                array(
                    $intCart
                )
            );
        }

        if ($objAddress === null) {
            $objAddress = new AddressModel();
            $objAddress->ptable = 'tl_iso_product_collection';
        }

        $objAddress->$strDefault = '1';

        return $objAddress;
    }
}
