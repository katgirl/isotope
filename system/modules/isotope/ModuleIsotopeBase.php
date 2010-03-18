<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Winans Creative 2009 
 * @author     Fred Bliss <fred@winanscreative.com>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


abstract class ModuleIsotopeBase extends Module
{

	/**
	 * URL cache array
	 * @var array
	 */
	private static $arrUrlCache = array();
	
	/**
	 * Template
	 * @var string
	 */
	protected $strPriceTemplate = 'stpl_price';
 
	/**
	 * product options array
	 * @var array
	 */
	protected $arrProductOptionsData = array();
	
	/**
	 * for widgets, helps determine the encoding type for a form
	 * @var boolean
	 */
	protected $hasUpload = false;
	
	/**
	 * for widgets, don't submit if certain validation(s) fail
	 * @var boolean;
	 */
	protected $doNotSubmit = false;
	
	
	public function __construct(Database_Result $objModule, $strColumn='main')
	{
		parent::__construct($objModule, $strColumn);
	
		if (TL_MODE == 'FE')
		{	
			$this->import('Isotope');
			$this->import('IsotopeCart', 'Cart');
			
			if (FE_USER_LOGGED_IN)
			{
				$this->import('FrontendUser', 'User');
			}
			
			// Load isotope javascript class
			$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/isotope/html/isotope_frontend.js';
			
			// Make sure field data is available
			if (!is_array($GLOBALS['TL_DCA']['tl_product_data']['fields']))
			{
				$this->loadDataContainer('tl_product_data');
				$this->loadLanguageFile('tl_product_data');
				
			}
		}
	}
	
		
	
	/**
	 * Generate a button for a given function such as add to cart buttons
	 * @param array
	 * @param string
	 * @return array
	 */
	protected function generateButtons($arrButtonData, $pageId, $strReturnUrl = NULL)
	{
		//$arrButtonTypes = array_keys($arrButtonData);
		
		foreach($arrButtonData as $buttonProperties)
		{									
			if(!strlen($buttonProperties['button_template']))
			{
				throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['missingButtonTemplate'], $buttonProperties['button_template']));
			}
			else
			{
				$objTemplate = new FrontendTemplate($buttonProperties['button_template']); //creating one base template object and cloning for specific products
			}
			
			$objTemplate->buttonType = 'link';
			$objTemplate->isAjaxEnabledButton = false;	
			$objTemplate->buttonLabelOrImage = $buttonProperties['button_label'];
			
			//Get list of product ids for the current button
			
			if($buttonProperties['params'])
			{
				$arrProductIds = array_keys($buttonProperties['params']);
				
				foreach($arrProductIds as $productId)
				{
					//Get the current product Id's params & process
					$objTemplate->buttonId = $buttonProperties['button_id'] . $productId;
					$objTemplate->actionTitle = sprintf($buttonProperties['action_string'], $buttonProperties['params'][$productId]['name']);
					$objTemplate->actionLink = $this->generateActionLinkString($buttonProperties['button_type'], $productId, $buttonProperties['params'][$productId], $pageId);
					$arrButtonHTML[$buttonProperties['button_type']][$productId] = $objTemplate->parse();
				}
			}
		}	
			
			/*		
			Button Model Properties Not yet used - BEGIN

			--------------
			$objTemplate->buttonName = '';						//prefix "button_", NOT USED YET
			$objTemplate->buttonTabIndex = 0;						//tab index (optional)
			$objTemplate->buttonClickEvent = '';					//click event to invoke the button's script.  May be to an AJAX handler or just to a form submit.
			--------------

			Button Model Properties Not yet used - END
		*/	

		return $arrButtonHTML;
	}
	
	/**
	 * Generate a link string for various actions such as adding a product to the cart, removing from, or updating.
	 * @param string
	 * @param array
	 * @return string
	 */
	protected function generateActionLinkString($strAction, $intProductId, $arrParams, $pageId)
	{
		$strCacheKeyParams = 'action_' . $strAction . '_';
		$strParams = 'action/' . $strAction . '/';
		
		foreach($arrParams as $k=>$v)
		{
			if(array_key_exists('exclude', $arrParams))
			{
				if(!in_array($k, $arrParams['exclude']))
				{
					$strCacheKeyParams .= $k . '_' . $v . '_';
					$strParams .= $k . '/' . $v . '/';
				}
			}
			else
			{
					$strCacheKeyParams .= $k . '_' . $v . '_';
					$strParams .= $k . '/' . $v . '/';
			}
		}

		$strParams .= 'id/' . $intProductId;
		$strCacheKey = 'id_' . $intProductId . '_' . $strCacheKeyParams . $arrProduct['tstamp'];


		// Load URL from cache
		if (array_key_exists($strCacheKey, self::$arrUrlCache))
		{			
			return self::$arrUrlCache[$strCacheKey];
		}
		
		
		$strUrl = ampersand($this->Environment->request, ENCODE_AMPERSANDS);
		
		$objPage = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")
								  ->limit(1)
								  ->execute($pageId);

		if ($objPage->numRows)
		{
			$strUrl = ampersand($this->generateFrontendUrl($objPage->fetchAssoc(), '/' . $strParams));			
		}
				
		self::$arrUrlCache[$strCacheKey] = $strUrl;

		return self::$arrUrlCache[$strCacheKey];
	}
	
			
	/**
	 * Generate a URL and return it as string
	 * @param object
	 * @param boolean
	 * @return string
	 */
	protected function generateProductUrl($arrProduct, $intJumpTo, $strProductIdKey = 'id', $blnAddArchive=false)
	{
		global $objPage;
		$strCacheKey = $strProductIdKey . '_' . $arrProduct[$strProductIdKey] . '_' . $arrProduct['tstamp'];

		// Load URL from cache
		if (array_key_exists($strCacheKey, self::$arrUrlCache))
		{
			return self::$arrUrlCache[$strCacheKey];
		}

		$strUrl = ampersand($this->Environment->request, ENCODE_AMPERSANDS);

		// Get target page
		$objJump = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")->limit(1)->execute($intJumpTo);
	
		if ($objJump->numRows > 0)
		{
			$strUrl = ampersand($this->generateFrontendUrl($objJump->fetchAssoc(), '/product/' . $arrProduct['alias']));
		}
		else
		{
			$strUrl = ampersand($this->generateFrontendUrl(array('id'=>$objPage->id, 'alias'=>$objPage->alias), '/details/product/' . $arrProduct['alias']));
		}

		self::$arrUrlCache[$strCacheKey] = $strUrl;
			
		return self::$arrUrlCache[$strCacheKey];
	}

	

	/**
	 * Generate a link and return it as string
	 * @param string
	 * @param object
	 * @param boolean
	 * @return string
	 */
	protected function generateProductLink($strLink, $arrProduct, $intJumpTo, $strProductIdKey = 'id', $blnAddArchive=false)
	{
		return 	$this->generateProductUrl($arrProduct, $intJumpTo, $strProductIdKey, $blnAddArchive);
	}
		
	
	protected function generatePrice($fltPrice, $strTemplate='stpl_price')
	{
		$objTemplate = new FrontendTemplate($strTemplate);
		
		$objTemplate->price = $this->Isotope->formatPriceWithCurrency($fltPrice, null, true);
		
		return $objTemplate->parse();
	}
	
	
	/**
	 *	getFilterListData - Grab a list of values and labels for a filter by attribute Id and by a list of eligible values.  If the array is empty, grab all values. 
	 *	@param integer
	 *  @param array
	 *  @return array
	 */
	protected function getFilterListData($intAttributeId, $arrValues = array())
	{
		if(count($arrValues) < 1)
		{
			$blnGrabAll = true;
		}
				
		$objAttributeData = $this->Database->prepare("SELECT name, option_list, use_alternate_source, list_source_table, list_source_field, field_name FROM tl_product_attributes WHERE id=? AND is_filterable='1' AND (type='select' OR type='checkbox')")
									  ->limit(1)
									  ->execute($intAttributeId);
									  
		if(!$objAttributeData->numRows)
		{
			return array();
		}
		
		$arrListData[] = array
		(
			'value'		=> NULL,
			'label'		=> $GLOBALS['TL_LANG']['MSC']['selectItemPrompt']
		);
		
		if($objAttributeData->use_alternate_source==1)
		{

			$objLinkData = $this->Database->prepare("SELECT id, " . $objAttributeData->list_source_field . " FROM " . $objAttributeData->list_source_table)
										  ->execute();
			
			if($objLinkData->numRows < 1)
			{
				return array();
			}
			
			$arrLinkValues = $objLinkData->fetchAllAssoc();
						
			$filter_name = $objAttributeData->list_source_field;
									
			if($blnGrabAll)
			{
				foreach($arrLinkValues as $value)
				{
					$arrListData[] = array
					(
						'value'		=> $value[$objAttributeData->id],
						'label'		=> $value[$objAttributeData->list_source_field]
					);
				}
			}
			else
			{
				foreach($arrLinkValues as $value)
				{
					if(in_array($value['id'], $arrValues))
					{
						$arrListData[] = array
						(
							'value'		=> $value['id'],
							'label'		=> $value[$objAttributeData->list_source_field]
						);
					}			
				}
			}
		}
		else
		{
			$arrLinkValues = deserialize($objAttributeData->option_list);
			
			if($blnGrabAll)
			{
				foreach($arrLinkValues as $value)
				{
					$arrListData[] = array
					(			
						'value'		=> $value['value'],
						'label'		=> $value['label']
					);
				}
			}
			else
			{
				foreach($arrLinkValues as $value)
				{
					if(in_array($value['value'], $arrValues))
					{
						$arrListData[] = array
						(			
							'value'		=> $value['value'],
							'label'		=> $value['label']
						);
					}
				}
			}
		}
		
		usort($arrListData, array($this, "sortArrayAsc"));
		
		return $arrListData;
	}
	
	protected function sortArrayAsc($a, $b)
	{
		return strcasecmp($a['label'], $b['label']);
	}
	
	
	protected function getCookieTimeWindow($intStoreId)
	{
		$objCookieTimeWindow = $this->Database->prepare("SELECT cookie_duration FROM tl_store WHERE id=?")->limit(1)->execute($intStoreId);
		
		if (!$objCookieTimeWindow->numRows)
		{
			return 0;
		}
				
		return $objCookieTimeWindow->cookie_duration;
	}
	
	
	/**
	 * determine the form's action method.
	 * @access protected
	 * @param string $strKey
	 * @return string
	 */
	protected function getRequestData($strKey)
	{
		return strlen($this->Input->post($strKey)) ? $this->Input->post($strKey) : $this->Input->get($strKey);
	}
	
	
	/** 
	 * Return a widget object based on a product attribute's properties
	 * @access public
	 * @param string $strField
	 * @param array $arrData
	 * @param boolean $blnUseTable
	 * @return string
	 */
	public function generateProductOptionWidget($strField, $arrData, $objProduct, $strFormId = '', $arrOptionFields = array(), $blnUseTable = false)
	{
		$hideVariants = false;
		
		$strClass = $GLOBALS['TL_FFL'][$arrData['inputType']];
									
		// Continue if the class is not defined
		if (!$this->classFileExists($strClass))
		{
			return '';
		}

		$arrData['eval']['required'] = $arrData['eval']['mandatory'] ? true : false;
		
		//$GLOBALS['TL_LANG']['MSC']['emptySelectOptionLabel']));
		
		$objWidget = new $strClass($this->prepareForWidget($arrData, $strField));
		
		if (is_array($GLOBALS['ISO_ATTR'][$arrData['attributes']['type']]['callback']) && count($GLOBALS['ISO_ATTR'][$arrData['attributes']['type']]['callback']))
		{
			foreach( $GLOBALS['ISO_ATTR'][$arrData['attributes']['type']]['callback'] as $callback )
			{
				$this->import($callback[0]);
				$arrData = $this->{$callback[0]}->{$callback[1]}($arrData, $arrData['attributes'], $objWidget, $objProduct);
			}
		}
					
		$objWidget->storeValues = true;
		$objWidget->tableless = true;
		$objWidget->name .= "[" . $objProduct->id . "]";
		$objWidget->id .= "_" . $objProduct->id;
		
		// Validate input
		if ($this->Input->post('FORM_SUBMIT') == $strFormId)
		{
			$GLOBALS['TL_LANG']['ERR']['mandatory'] = $GLOBALS['TL_LANG']['ERR']['mandatoryOption'];
			
			$objWidget->validate();
			$varValue = $objWidget->value;
			$objWidget->value = NULL;
			
			// Convert date formats into timestamps
			if (strlen($varValue) && in_array($arrData['eval']['rgxp'], array('date', 'time', 'datim')))
			{
				$objDate = new Date($varValue, $GLOBALS['TL_CONFIG'][$arrData['eval']['rgxp'] . 'Format']);
				$varValue = $objDate->tstamp;
			}

			if ($objWidget->hasErrors())
			{
				$this->doNotSubmit = true;					
			}

			// Store current value
			elseif ($objWidget->submitInput())
			{
				$_SESSION['FORM_DATA'][$strField] = $varValue;
				//Store this options value to the productOptionsData array which is then serialized and stored for the given product that is being added to the cart.
				
				//Has to collect this data differently - product variant data relies upon actual values specified for the given product ID, where as simple options
				//only rely upon predefined option lists and what ones were actually selected.
				switch($strField)
				{					
					case 'product_variants':
						if(count($arrOptionFields))
						{
							$this->arrProductOptionsData = $this->getSubproductValues($varValue, $arrOptionFields);	//field is implied							
						}
						
						if(!count($this->arrProductOptionsData))
						{
							$hideVariants = true;
						}
						break;
									
					default:
						$this->arrProductOptionsData[] = $this->getProductOptionValues($strField, $arrData['inputType'], $varValue); 
						break;
				}			
			}
		}
		
		if ($objWidget instanceof uploadable)
		{
			$this->hasUpload = true;
		}
							
		//$_SESSION['FORM_DATA'][$strField] = $varValue;
		
		//$varSave = is_array($varValue) ? serialize($varValue) : $varValue;
		
		if (!$hideVariants)
		{
			$temp .= $objWidget->parse() . '<br />';
			return $temp;
		}
	}
	
	
	
	protected function getProductOptionValues($strField, $inputType, $varValue)
	{	
		$arrAttributeData = $GLOBALS['TL_DCA']['tl_product_data']['fields'][$strField]['attributes']; //1 will eventually be irrelevant but for now just going with it...
		
		switch($inputType)
		{
			case 'radio':
			case 'checkbox':
			case 'select':
				
				//get the actual labels, not the key reference values.
				$arrOptions = $this->getOptionList($arrAttributeData);
				
				if(is_array($varValue))
				{
					
					foreach($varValue as $value)
					{
						foreach($arrOptions as $option)
						{
							if($option['value']==$value)
							{
								$varOptionValues[] = $option['label'];
								break;
							}
						}
					}	
				}
				else
				{
					foreach($arrOptions as $option)
					{
						if($option['value']==$varValue)
						{
							$varOptionValues[] = $option['label'];
							break;
						}
					}
				}
				break;
				
			default:
				//these values are not by reference - they were directly entered.  
				if(is_array($varValue))
				{
					foreach($varValue as $value)
					{
						$varOptionValues[] = $value;
					}
				}
				else
				{
					$varOptionValues[] = $varValue;
				}
				
				break;
		
		}		
		
		$arrValues = array
		(
			'name'		=> $arrAttributeData['name'],
			'values'	=> $varOptionValues			
		);
		
		return $arrValues;
	}
	
	
	protected function getSubproductOptionValues($intPid, $arrOptionList)
	{
		if (!is_array($arrOptionList) || !count($arrOptionList))
			return array();
			
		$strOptionValues = join(',', $arrOptionList);

		$objData = $this->Database->prepare("SELECT id, " . $strOptionValues . ", price FROM tl_product_data WHERE pid=? AND published='1'")
								  ->execute($intPid);
		
		if($objData->numRows < 1)
		{
			return false;
		}
		
		$arrOptionValues = $objData->fetchAllAssoc();

		//include blank option, manual label override
		$arrOptions[''] = $GLOBALS['TL_LANG']['MSC']['emptySelectOptionLabel'];

		foreach($arrOptionValues as $option)
		{
			$arrValues = array();
			
			foreach($arrOptionList as $optionName)
			{
				$arrValues[] = $option[$optionName];
			}
			
			$strOptionValue = join(',', $arrValues) . ' - ' . $this->Isotope->formatPriceWithCurrency($option['price']);
			
			$arrOptions[$option['id']] = $strOptionValue;
		}
		
		return $arrOptions;
	}
	
	/*
	 * Get the option value data for cart item elaboration
	 * @param variant $varValue
	 * @param array $arrOptionFields
	 * @return array
	 */
	protected function getSubproductValues($varValue, $arrOptionFields)
	{
		$strOptionValues = join(',', $arrOptionFields);
						
		//get the selected variant values;
		$objData = $this->Database->prepare("SELECT " . $strOptionValues . " FROM tl_product_data WHERE id=? AND published='1'")
								  ->execute($varValue);
		
		if($objData->numRows < 1)
		{
			return false;
		}
		
		$arrOptionValues = $objData->fetchAllAssoc();
				
		foreach($arrOptionValues as $row)
		{
			foreach($row as $k=>$v)
			{
				$arrAttributeData = $GLOBALS['TL_DCA']['tl_product_data']['fields'][$k]['attributes'];
					
				$arrOptionData[] = array
				(
					'name'		=> $arrAttributeData['name'],
					'values'	=> array($v)		
				);
			}			
		}
		
		return $arrOptionData;
	}
	
	
	protected function getOptionList($arrAttributeData)
	{
		if($arrAttributeData['use_alternate_source']==1)
		{
			if(strlen($arrAttributeData['list_source_table']) > 0 && strlen($arrAttributeData['list_source_field']) > 0)
			{
				//$strForeignKey = $arrAttributeData['list_source_table'] . '.' . $arrAttributeData['list_source_field'];
				$objOptions = $this->Database->execute("SELECT id, " . $arrAttributeData['list_source_field'] . " FROM " . $arrAttributeData['list_source_table']);
				
				if(!$objOptions->numRows)
				{
					return array();
				}
				
				while($objOptions->next())
				{
					$arrValues[] = array
					(
						'value'		=> $objOptions->id,
						'label'		=> $objOptions->$arrAttributeData['list_source_field']
					);
				}
			}
		}
		else
		{
			$arrValues = deserialize($arrAttributeData['option_list']);
		}
		
		return $arrValues;
	}
	
	
	/**
	 * Shortcut for a single product by ID
	 */
	protected function getProduct($intId)
	{
		$objProductData = $this->Database->prepare("SELECT *, (SELECT class FROM tl_product_types WHERE tl_product_data.type=tl_product_types.id) AS type_class FROM tl_product_data WHERE id=?")
										 ->limit(1)
										 ->executeUncached($intId);
									 
		$strClass = $GLOBALS['ISO_PRODUCT'][$objProductData->type_class]['class'];
		
		if (!$this->classFileExists($strClass))
		{
			return null;
		}
									
		$objProduct = new $strClass($objProductData->row());
		
		$objProduct->reader_jumpTo = $this->iso_reader_jumpTo;
			
		return $objProduct;
	}
	
	
	/**
	 * Shortcut for a single product by alias (from url?)
	 */
	protected function getProductByAlias($strAlias)
	{
		$objProductData = $this->Database->prepare("SELECT *, (SELECT class FROM tl_product_types WHERE tl_product_data.type=tl_product_types.id) AS type_class FROM tl_product_data WHERE alias=?")
										 ->limit(1)
										 ->executeUncached($strAlias);
									 
		$strClass = $GLOBALS['ISO_PRODUCT'][$objProductData->type_class]['class'];
		
		if (!$this->classFileExists($strClass))
		{
			return null;
		}
									
		$objProduct = new $strClass($objProductData->row());
		
		$objProduct->reader_jumpTo = $this->iso_reader_jumpTo;
			
		return $objProduct;
	}
	
	
	/**
	 * Retrieve multiple products by ID.
	 */
	protected function getProducts($arrIds)
	{
		if (!is_array($arrIds) || !count($arrIds))
			return array();
		
		$arrProducts = array();
		
		foreach( $arrIds as $intId )
		{
			$objProduct = $this->getProduct($intId);
		
			if (is_object($objProduct))
				$arrProducts[] = $objProduct;
		}
		
		return $arrProducts;
	}
	
	
	/**
	 * Generate a product template
	 */
	public function generateProduct($objProduct, $strTemplate, $arrData=array(), $strFormId='', $intParentProductId = 0)
	{
		$objTemplate = new FrontendTemplate($strTemplate);

		$objTemplate->setData($arrData);
		
		$arrEnabledOptions = array();
		$arrVariantOptionFields = array();
		$arrProductOptions = array();
		$arrAttributes = $objProduct->getAttributes();
	
		foreach( $arrAttributes as $attribute => $varValue )
		{
			switch( $attribute )
			{
				case 'images':
					if (is_array($varValue) && count($varValue))
					{
						$objTemplate->hasImage = true;
						
						//$objTemplate->mainImage = array_shift($varValue);
						$objTemplate->mainImage = $varValue[0];
						
						//if (count($varValue))
						//{
						$objTemplate->hasGallery = true;
						$objTemplate->gallery = $varValue;
						//}
					}
					break;
					
				default:
									
					if($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_customer_defined'])
					{						
						$objTemplate->hasOptions = true;
						
						if($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['add_to_product_variants'])
						{					
							$blnIsMergedOptionSet = true;
							$arrVariantOptionFields[] = $attribute;	
						}
						else
						{
							$arrAttributeData = $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes'];
							
							$arrEnabledOptions[] = $attribute;	
																					
							$arrProductOptions[$attribute] = $this->generateProductOptionWidget($attribute, $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute], $objProduct, $strFormId);
						}
					}
					else
					{						
						switch($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['type'])
						{
							case 'select':
							case 'radio':
							case 'checkbox':
								
								if($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['use_alternate_source'])
								{																											
									$objData = $this->Database->prepare("SELECT * FROM " . $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['list_source_table'] . " WHERE id=?")						
															  ->limit(1)									 
															  ->execute($varValue);
									
									if(!$objData->numRows)
									{										
										$objTemplate->$attribute = $varValue;
									}
									else
									{
										$objTemplate->$attribute = array
										(
											'id'	=> $varValue,
											'raw'	=> $objData->fetchAssoc(),
										);
									}
								}
								else
								{
								
									//check for a related label to go with the value.
									$arrOptions = deserialize($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['option_list']);
									$varValues = deserialize($varValue);
									$arrLabels = array();
									
									if($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_visible_on_front'])
									{
										foreach($arrOptions as $option)
										{
											if(is_array($varValues))
											{
												if(in_array($option['value'], $varValues))
												{
													$arrLabels[] = $option['label'];
												}
											}
											else
											{	
												if($option['value']===$v)
												{
													$arrLabels[] = $option['label'];
												}
											}
										}
										
										if($arrLabels)
										{									
											$objTemplate->$attribute = join(',', $arrLabels); 
										}
										
									}
								}
								break;
								
							case 'textarea':
								$objTemplate->$attribute = $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['use_rich_text_editor'] ? $varValue : nl2br($varValue);
								break;
																																		
							default:
								if(!isset($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_visible_on_front']) || $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_visible_on_front'])
								{
									//just direct render
									$objTemplate->$attribute = $varValue;
								}
								break;
						}
					}
					break;
			}
		}

		if($blnIsMergedOptionSet && count($arrVariantOptionFields))
		{
 			$objTemplate->hasVariants = true;
 			
			//Create a special widget that combins all option value combos that are enabled.
			$arrData = array
			(
	            'name'      => 'subproducts',
	            'description'  => &$GLOBALS['TL_LANG']['tl_product_data']['product_options'],
	            'inputType'    => 'select',          
	            'options'    => $this->getSubproductOptionValues(($intParentProductId ? $intParentProductId : $objProduct->id), $arrVariantOptionFields),
	            'eval'      => array('mandatory'=>true)
			);
       
			$arrAttributeData = $GLOBALS['TL_DCA']['tl_product_data']['fields'][$k]['attributes'];

			$strHtml = $this->generateProductOptionWidget('product_variants', $arrData, $objProduct, $strFormId, $arrVariantOptionFields);
	
			if(strlen($strHtml) && $arrData['options'])
			{
				$arrVariantWidget = array
				(
					'name'      => $k,
					'description'  => $GLOBALS['TL_LANG']['MSC']['labelProductVariants'],                  
					'html'		=> $strHtml 
				); 
			}
			else
			{
				$objTemplate->hasVariants = false;
			}           
        }
			

		$objTemplate->raw = $objProduct->getData();
		$objTemplate->href_reader = $objProduct->href_reader;
		
		$objTemplate->label_detail = $GLOBALS['TL_LANG']['MSC']['detailLabel'];
		
		$objTemplate->price = $objProduct->formatted_price;
		$objTemplate->low_price = $objProduct->formatted_low_price;
		$objTemplate->high_price = $objProduct->formatted_high_price;
		$objTemplate->priceRangeLabel = $GLOBALS['TL_LANG']['MSC']['priceRangeLabel'];
		$objTemplate->options = $arrProductOptions;	
		$objTemplate->hasOptions = (count($arrProductOptions) || count($arrVariantWidget) ? true : false);
		$objTemplate->variantList = implode(',', $arrVariantOptionFields);
		$objTemplate->variant_widget = $arrVariantWidget;
				
		$objTemplate->optionList = implode(',', $arrEnabledOptions);
		
		return $objTemplate->parse();
	}
}

