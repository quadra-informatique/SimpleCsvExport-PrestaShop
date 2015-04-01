<?php
/**
 * ---------------------------------------------------------------------------------
 *
 * 1997-2015 Quadra Informatique
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to ecommerce@quadra-informatique.fr so we can send you a copy immediately.
 *
 * @author    Quadra Informatique <ecommerce@quadra-informatique.fr>
 * @copyright 1997-2015 Quadra Informatique
 * @version Release: $Revision: 1.3.0 $
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * ---------------------------------------------------------------------------------
 */

class Simplecsvexport extends Module
{

	public function __construct()
	{
		$this->name = 'simplecsvexport';
		$this->tab = 'administration';
		$this->version = '1.3.0';
		$this->author = 'Quadra Informatique';
		$this->displayName = $this->l('Simple csv export order');
		$this->description = $this->l('This module will generate csv export order files');
		parent::__construct();
	}

	public function install()
	{
		if (!parent::install() || !$this->registerHook('adminOrder') || !$this->registerHook('postUpdateOrderStatus') || !$this->installBackOffice())
			return false;
		return true;
	}

	public function getContent()
	{
		$errors = array();
		$this->_html = '<h2>'.$this->displayName.'</h2>';

		/* Update values in DB */
		if (Tools::isSubmit('submitExportCSV'))
		{
			$send_copy = (int)Tools::getValue('send_copy');
			$email = (string)Tools::getValue('email');

			if (!Validate::isInt($send_copy) || !Validate::isString($email))
				$errors[] = $this->l('Invalid data');
			else
			{
				Configuration::updateValue('PS_SCE_SEND_COPY', $send_copy);
				Configuration::updateValue('PS_SCE_EMAIL', $email);
			}

			if (isset($errors) && count($errors))
				$this->_html .= $this->displayError(implode('<br />', $errors));
			else
				$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
		}

		$this->displayForm();
		return $this->_html;
	}

	private function displayForm()
	{
		$this->_html .= '
            <form action="'.$_SERVER['REQUEST_URI'].'" method="post">
                <fieldset class="width3">
                    <legend><img src="'.$this->_path.'logo.gif" />'.$this->l('Automatic export parameters').'</legend>
                    <label>'.$this->l('Send a copy by email').'</label>
                    <div class="margin-form">
                        <input type="radio" name="send_copy" id="send_copy_on" 
						value="1" '.(Configuration::get('PS_SCE_SEND_COPY') ? 'checked="checked" ' : '').'/>
                        <label class="t" for="send_copy_on">
                            <img src="../img/admin/enabled.gif" alt="'.$this->l('Yes').'" title="'.$this->l('Yes').'" />
                        </label>
                        <input type="radio" name="send_copy" id="send_copy_off" 
						value="0"  '.(!Configuration::get('PS_SCE_SEND_COPY') ? 'checked="checked" ' : '').'/>
                        <label class="t" for="send_copy_off">
                            <img src="../img/admin/disabled.gif" alt="'.$this->l('No').'" title="'.$this->l('No').'" />
                        </label>
                    </div>
                    <label>'.$this->l('Email').'</label>
                    <div class="margin-form">
                        <input type="text" name="email" id="email" 
						value="'.(Configuration::get('PS_SCE_EMAIL') ? Configuration::get('PS_SCE_EMAIL') : '' ).'" style="width: 335px" />
                    </div>
                    <div class="margin-form">
                        <input type="submit" name="submitExportCSV" value="'.$this->l('Save').'" class="button" />
                    </div>
                </fieldset>
            </form>
        ';
	}

	public function uninstall()
	{
		$this->uninstallModuleTab('AdminManageExportOrder');
		return parent::uninstall();
	}

	private function installBackOffice()
	{
		$id_lang_en = LanguageCore::getIdByIso('en');
		$id_lang_fr = LanguageCore::getIdByIso('fr');
		$this->installModuleTab('AdminManageexportorder', array($id_lang_fr => 'Export des commandes', $id_lang_en => 'Order export'), 10);
		return true;
	}

	private function uninstallModuleTab($tab_class)
	{
		$id_tab = Tab::getIdFromClassName($tab_class);
		if ($id_tab != 0)
		{
			$tab = new Tab($id_tab);
			$tab->delete();
			return true;
		}
		return false;
	}

	private function installModuleTab($tab_class, $tab_name, $id_tab_parent)
	{
		$tab = new Tab();
		$tab->name = $tab_name;
		$tab->class_name = $tab_class;
		$tab->module = $this->name;
		$tab->id_parent = (int)$id_tab_parent;
		if (!$tab->save())
			return false;
		return true;
	}

	public function hookAdminOrder($params)
	{
		$link = new Link();

		$html = '<div class="panel"><div class="clear">&nbsp;</div>
                <form action="'.$link->getAdminLink('AdminOrders').'&vieworder&id_order='.$params['id_order'].'" method="post">
                    <input type="hidden" value="1" name="export_csv">
                    <input class="btn btn-primary" type="submit" value="'.$this->l('Export this order by email').'" name="submitExportCSV" />
                </form></div>';

		if (Tools::isSubmit('submitExportCSV'))
		{
			if ((int)Tools::getValue('export_csv'))
			{
				$this->sendExportByEmail($params);
				$html .= '<div class="panel"><div class="clear">&nbsp;</div>
                        <div class="conf">
                            <img alt="" src="../img/admin/ok2.png">
                            '.$this->l('Order was exported with success.').'
                        </div></div>';
			}
		}

		return $html;
	}

	public function hookPostUpdateOrderStatus($params)
	{
		if ($params['newOrderStatus']->id == Configuration::get('PS_OS_PAYMENT'))
			$this->sendExportByEmail($params);
	}

	protected function sendExportByEmail($params)
	{
		if (Configuration::get('PS_SCE_SEND_COPY'))
		{
			$csv_header_sent = false;
			$orders = Db::getInstance()->ExecuteS('
                SELECT
                    o.id_order AS order_id,
                    od.product_reference AS product_reference,
                    od.product_name AS product_name,
                    od.product_price AS product_price,
                    od.product_weight AS product_weight,
                    od.product_quantity AS product_quantity,
                    od.product_quantity_refunded AS product_quantity_refunded,
                    od.product_quantity_return AS product_quantity_return,
                    od.tax_rate AS product_tax_rate,
                    od.ecotax AS product_ecotax,
                    od.discount_quantity_applied AS product_discount_quantity_applied,
                    o.id_customer AS customer_id,
                    ainv.firstname AS invoice_firstname,
                    ainv.lastname AS invoice_lastname,
                    ainv.company AS invoice_company,
                    ainv.address1 AS invoice_address1,
                    ainv.address2 AS invoice_address2,
                    ainv.postcode AS invoice_postcode,
                    ainv.city AS invoice_city,
                    ainv.phone AS invoice_phone,
                    ainv.phone_mobile AS invoice_phone_mobile,
                    adel.firstname AS delivery_firstname,
                    adel.lastname AS delivery_lastname,
                    adel.company AS delivery_company,
                    adel.address1 AS delivery_address1,
                    adel.address2 AS delivery_address2,
                    adel.postcode AS delivery_postcode,
                    adel.city AS delivery_city,
                    adel.phone AS delivery_phone,
                    adel.phone_mobile AS delivery_phone_mobile,
                    DATE(o.invoice_date) AS invoice_date,
                    o.payment AS payment,
                    DATE(o.delivery_date) AS delivery_date,
                    o.shipping_number AS shipping_number,
                    (SELECT osl.name
                        FROM
                            '._DB_PREFIX_.'order_history oh,
                            '._DB_PREFIX_.'order_state_lang osl
                        WHERE o.id_order=oh.id_order
                        AND oh.id_order_state = osl.id_order_state
                        ORDER BY id_order_history DESC LIMIT 1) AS status,
                    o.total_discounts AS total_discounts,
                    o.total_paid AS total_paid,
                    o.total_paid_real AS total_paid_real,
                    o.total_products AS total_products,
                    o.total_products_wt AS total_products_wt,
                    o.total_shipping AS total_shipping,
                    o.total_wrapping AS total_wrapping,
                    cur.name AS currency
                FROM '._DB_PREFIX_.'orders o
                LEFT JOIN '._DB_PREFIX_.'order_detail od ON o.id_order=od.id_order
                LEFT JOIN '._DB_PREFIX_.'address ainv ON o.id_address_invoice=ainv.id_address
                LEFT JOIN '._DB_PREFIX_.'address adel ON o.id_address_delivery=adel.id_address
                LEFT JOIN '._DB_PREFIX_.'currency cur ON o.id_currency=cur.id_currency
                WHERE o.valid=1 AND o.id_order='.pSQL($params['id_order']).'
                ORDER BY o.id_order, od.id_order_detail ASC');

			$time = date('Y-m-d_H-i-s');
			$filename = 'export_orders_'.$time.'.csv';

			$stdout = fopen(_PS_MODULE_DIR_.'simplecsvexport/'.$filename, 'w');

			// BOM utf8
			fwrite($stdout, chr(239).chr(187).chr(191));
			// while ($row = Db::getInstance()->nextRow($orders))
			foreach ($orders as $row)
			{
				//number format
				foreach (array('product_price', 'product_weight', 'product_tax_rate', 'product_ecotax',
			'product_discount_quantity_applied', 'total_discounts', 'total_paid', 'total_paid_real',
			'total_products', 'total_products_wt', 'total_shipping', 'total_wrapping') as $field)
					$row[$field] = str_replace('.', ',', $row[$field]);

				//phone format
				foreach (array('invoice_phone', 'invoice_phone_mobile', 'delivery_phone', 'delivery_phone_mobile') as $field)
					$row[$field] = preg_replace('[^0-9]', '', $row[$field]).' ';

				//csv header
				if (!$csv_header_sent)
					$csv_header_sent = fputcsv($stdout, array_keys($row), ';', '"');
				//write line
				fputcsv($stdout, $row, ';', '"');
			}

			fclose($stdout);

			Mail::Send(
					(int)Configuration::get('PS_LANG_DEFAULT'), 'export_order', $this->l('CSV Export: Order #').(int)$params['id_order'], array(
				'{order_id}' => (int)$params['id_order'],
				'{time}' => str_replace('_', ' ', $time),
				'{filename}' => $filename
					), Configuration::get('PS_SCE_EMAIL'), null, Configuration::get('PS_SHOP_EMAIL'), Configuration::get('PS_SHOP_NAME'), array(
				'content' => Tools::file_get_contents(_PS_MODULE_DIR_.'simplecsvexport/'.$filename),
				'name' => $filename,
				'mime' => 'text/csv'
					), null, dirname(__FILE__).'/mails/'
			);

			if (file_exists(_PS_MODULE_DIR_.'simplecsvexport/'.$filename))
				unlink(_PS_MODULE_DIR_.'simplecsvexport/'.$filename);
		}
	}

}
