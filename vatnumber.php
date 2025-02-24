<?php
/*
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2017 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class VatNumber extends TaxManagerModule
{
	public function __construct()
	{
		$this->name = 'vatnumber';
		$this->tab = 'billing_invoicing';
		$this->version = '2.1.0';
		$this->author = 'Onlineshop-Module';
		$this->need_instance = 0;

		$this->tax_manager_class = 'VATNumberTaxManager';

		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('European VAT number');
		$this->description = $this->l('Enables you to enter the intra-community VAT number when creating the address. You must fill in the company field to allow entering the VAT number.');
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
	}

	public function install()
	{
		return
		    parent::install()
		    && Configuration::updateValue('VATNUMBER_MANAGEMENT', 1)
		    && $this->registerHook('actionValidateCustomerAddressForm');
	}

	public function uninstall()
	{
		return (parent::uninstall() && Configuration::updateValue('VATNUMBER_MANAGEMENT', 0));
	}

	public function enable($force_all = false)
	{
		parent::enable($force_all);
		Configuration::updateValue('VATNUMBER_MANAGEMENT', 1);
	}

	public function disable($force_all = false)
	{
		parent::disable($force_all);
		Configuration::updateValue('VATNUMBER_MANAGEMENT', 0);
	}

	public static function getPrefixIntracomVAT()
	{
		$intracom_array = array(
			'AT' => 'AT',
			//Austria
			'BE' => 'BE',
			//Belgium
			'DK' => 'DK',
			//Denmark
			'FI' => 'FI',
			//Finland
			'FR' => 'FR',
			//France
			'FX' => 'FR',
			//France métropolitaine
			'DE' => 'DE',
			//Germany
			'GR' => 'EL',
			//Greece
			'IE' => 'IE',
			//Irland
			'IT' => 'IT',
			//Italy
			'LU' => 'LU',
			//Luxembourg
			'NL' => 'NL',
			//Netherlands
			'PT' => 'PT',
			//Portugal
			'ES' => 'ES',
			//Spain
			'SE' => 'SE',
			//Sweden
			'GB' => 'GB',
			//United Kingdom
			'CY' => 'CY',
			//Cyprus
			'EE' => 'EE',
			//Estonia
			'HU' => 'HU',
			//Hungary
			'LV' => 'LV',
			//Latvia
			'LT' => 'LT',
			//Lithuania
			'MT' => 'MT',
			//Malta
			'PL' => 'PL',
			//Poland
			'SK' => 'SK',
			//Slovakia
			'CZ' => 'CZ',
			//Czech Republic
			'SI' => 'SI',
			//Slovenia
			'RO' => 'RO',
			//Romania
			'BG' => 'BG',
			//Bulgaria
			'HR' => 'HR',
			//Croatia
		);

		return $intracom_array;
	}

	public static function isApplicable($id_country)
	{
		return (((int)$id_country && array_key_exists(Country::getIsoById($id_country), self::getPrefixIntracomVAT())) ? 1 : 0);
	}

	public static function WebServiceCheck($vat_number)
	{
		if (empty($vat_number))
			return array();
		$vat_number = str_replace(' ', '', $vat_number);
		$prefix = Tools::substr($vat_number, 0, 2);
		if (array_search($prefix, self::getPrefixIntracomVAT()) === false)
			return array(Tools::displayError('Invalid VAT number'));
		$vat = Tools::substr($vat_number, 2);
		$url = 'http://ec.europa.eu/taxation_customs/vies/viesquer.do?ms='.urlencode($prefix).'&iso='.urlencode($prefix).'&vat='.urlencode($vat);
		@ini_set('default_socket_timeout', 2);
		for ($i = 0; $i < 2; $i++)
		{
			if ($page_res = Tools::file_get_contents($url, false, null, 10))
			{
				if (preg_match('/invalid VAT number/i', $page_res))
				{
					@ini_restore('default_socket_timeout');

					return array(Tools::displayError('VAT number not found'));
				}
				else if (preg_match('/valid VAT number/i', $page_res))
				{
					@ini_restore('default_socket_timeout');

					return array();
				}
				else
					++$i;
			}
			else
				sleep(1);
		}
		@ini_restore('default_socket_timeout');

		return array(Tools::displayError('VAT number validation service unavailable'));
	}

	public function getContent()
	{
		$echo = '';

		if (Tools::isSubmit('submitVatNumber'))
		{
			if (Configuration::updateValue('VATNUMBER_ZONE_1', (int)Tools::getValue('VATNUMBER_ZONE_1')))
				$echo .= $this->displayConfirmation($this->l('The zone for your home country has been updated.'));

			if (Configuration::updateValue('VATNUMBER_ZONE_2', (int)Tools::getValue('VATNUMBER_ZONE_2')))
				$echo .= $this->displayConfirmation($this->l('The zone for your EU countries has been updated.'));

			if (Configuration::updateValue('VATNUMBER_CHECKING', (int)Tools::getValue('VATNUMBER_CHECKING')))
				$echo .= ((bool)Tools::getValue('VATNUMBER_CHECKING') ? $this->displayConfirmation($this->l('The check of the VAT number with the WebService is now enabled.')) : $this->displayConfirmation($this->l('The check of the VAT number with the WebService is now disabled.')));
		}
		$echo .= $this->renderForm();

		return $echo;
	}

	public function renderForm()
	{
		$zones = Zone::getZones($this->context->language->id);

		$zones_fmt = array(
			0 => array(
				'id' => 0,
				'name' => $this->l('-- Choose a zone --')
			)
		);

		foreach ($zones as $zone)
			$zones_fmt[] = array(
				'id' => $zone['id_zone'],
				'name' => $zone['name']
			);

		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'select',
						'label' => $this->l('Zone home country'),
						'desc' => $this->l('Select a zone here that corresponds to your home country'),
						'name' => 'VATNUMBER_ZONE_1',
						'required' => true,
						'default_value' => (int)$this->context->country->id_zone,
						'options' => array(
							'query' => $zones_fmt,
							'id' => 'id',
							'name' => 'name',
						)
					),
					array(
						'type' => 'select',
						'label' => $this->l('Zone home country'),
						'desc' => $this->l('Select a zone here that corresponds to the countries of the EU'),
						'name' => 'VATNUMBER_ZONE_2',
						'required' => true,
						'default_value' => (int)$this->context->country->id_zone,
						'options' => array(
							'query' => $zones_fmt,
							'id' => 'id',
							'name' => 'name',
						)
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Enable checking of the VAT number with the web service'),
						'name' => 'VATNUMBER_CHECKING',
						'is_bool' => true,
						'desc' => $this->l('The verification by the web service is slow. Enabling this option can slow down your shop.'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						),
					)
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);

		$helper = new HelperForm();
		$helper->module = $this;
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();

		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitVatNumber';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
		return array(
			'VATNUMBER_ZONE_1' => Tools::getValue('VATNUMBER_ZONE_1', Configuration::get('VATNUMBER_ZONE_1')),
			'VATNUMBER_ZONE_2' => Tools::getValue('VATNUMBER_ZONE_2', Configuration::get('VATNUMBER_ZONE_2')),
			'VATNUMBER_CHECKING' => Tools::getValue('VATNUMBER_CHECKING', Configuration::get('VATNUMBER_CHECKING')),
		);
	}

	public function hookActionValidateCustomerAddressForm(&$params)
	{
		$form = $params['form'];
		$is_valid = true;

		if (($vatNumber = $form->getField('vat_number')) && Configuration::get('VATNUMBER_MANAGEMENT') && Configuration::get('VATNUMBER_CHECKING')) {
			$isAVatNumber = VatNumber::WebServiceCheck($vatNumber->getValue());
			if (is_array($isAVatNumber) && count($isAVatNumber) > 0) {
				$vatNumber->addError($isAVatNumber[0]);
				$is_valid = false;
			}
		}

		return $is_valid;
	}
}
