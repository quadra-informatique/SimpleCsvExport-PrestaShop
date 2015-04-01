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

class AdminManageexportorderController extends ModuleAdminController
{

	public $module = 'simplecsvexport';
	public $bootstrap = true;

	public function __construct()
	{
		$this->lang = true;
		$this->requiredDatabase = false;
		$this->context = Context::getContext();
		parent::__construct();
	}

	public function postProcess()
	{
		if (Tools::isSubmit('submitAddconfiguration') && !($this->tabAccess['add'] === '1'))
			$this->errors[] = Tools::displayError('You do not have the required permission to export stock.');
		if (count($this->errors))
			return;
		if (Tools::isSubmit('submitAddconfiguration'))
		{
			// get source warehouse id
			$date_begin = Tools::getValue('date_begin', '');
			$date_endin = Tools::getValue('date_endin', '');

			if (!$date_begin)
				$this->errors[] = Tools::displayError($this->l('You must set a beginning date'));
			else
			{
				$date_begin = explode('-', $date_begin);
				$date_begin = mktime(0, 0, 0, $date_begin[1], $date_begin[2], $date_begin[0]);
				$date_begin = date('Y-m-d', $date_begin);
			}
			if (!$date_endin)
				$this->errors[] = Tools::displayError($this->l('You must set an ending date.'));
			else
			{
				$date_endin = explode('-', $date_endin);
				$date_endin = mktime(0, 0, 0, $date_endin[1], $date_endin[2], $date_endin[0]);
				$date_endin = date('Y-m-d', $date_endin);
			}

			if ($date_endin < $date_begin)
				$this->errors[] = Tools::displayError($this->l('Beginning date cannot be inferior to ending date.'));
			if (count($this->errors))
				return;
			else
				$this->processExportOrder($date_begin, $date_endin);
		}
		parent::postProcess();
	}

	public function processExportOrder($date_begin, $date_endin)
	{
		// clean buffer
		if (ob_get_level() && ob_get_length() > 0)
			ob_clean();

		$date_debut = explode('-', $date_begin);
		$date_debut = mktime(0, 0, 0, $date_debut[1], $date_debut[2], $date_debut[0]);
		$date_debut = date('d/m/Y', $date_debut);
		$date_fin = explode('-', $date_endin);
		$date_fin = mktime(0, 0, 0, $date_fin[1], $date_fin[2], $date_fin[0]);
		$date_fin = date('d/m/Y', $date_fin);

		$csv_list = array();

		$sql = 'SELECT
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
LEFT JOIN '._DB_PREFIX_.'product op ON od.product_id = op.id_product 
WHERE o.valid=1 AND o.date_add>="'.pSQL($date_begin).' 00:00:00" AND o.date_add<="'.pSQL($date_endin).' 23:59:59" 
        ORDER BY o.id_order, od.id_order_detail ASC';

		$result = Db::getInstance()->ExecuteS($sql);

		foreach ($result as $row)
		{
			foreach (array('product_price', 'product_weight', 'product_tax_rate', 'product_ecotax',
		'product_discount_quantity_applied', 'total_discounts', 'total_paid', 'total_paid_real',
		'total_products', 'total_products_wt', 'total_shipping', 'total_wrapping') as $field)
				$row[$field] = str_replace('.', ',', $row[$field]);
			//phone format
			foreach (array('invoice_phone', 'invoice_phone_mobile', 'delivery_phone', 'delivery_phone_mobile') as $field)
				$row[$field] = preg_replace('[^0-9]', '', $row[$field]).' ';
			array_push($csv_list, array($row['order_id'], $row['product_reference'], $row['product_name'], $row['product_price'],
				$row['product_weight'], $row['product_quantity'], $row['product_quantity_refunded'], $row['product_quantity_return'],
				$row['product_tax_rate'], $row['product_ecotax'], $row['product_discount_quantity_applied'], $row['customer_id'],
				$row['invoice_firstname'], $row['invoice_lastname'],
				$row['invoice_company'], $row['invoice_address1'], $row['invoice_address2'], $row['invoice_postcode'], $row['invoice_city'],
				$row['invoice_phone'],
				$row['invoice_phone_mobile'], $row['delivery_firstname'], $row['delivery_lastname'], $row['delivery_company'],
				$row['delivery_address1'],
				$row['delivery_address2'], $row['delivery_postcode'], $row['delivery_city'], $row['delivery_phone'], $row['delivery_phone_mobile'],
				$row['invoice_date'], $row['payment'], $row['delivery_date'], $row['shipping_number'], $row['status'], $row['total_discounts'],
				$row['total_paid'], $row['total_paid_real'], $row['total_products'], $row['total_products_wt'], $row['total_shipping'],
				$row['total_wrapping'], $row['currency']));
		}

		header('Content-type: text/csv');
		header('Content-Type: application/force-download; charset=UTF-8');
		header('Cache-Control: no-store, no-cache');
		header('Content-disposition: attachment; filename="export_orders_'.date('Y-m-d_H-m-s').'.csv"');

		$headers = array();
		$titles = array($this->l('order_id'), $this->l('product_reference'), $this->l('product_name'), $this->l('product_price'),
			$this->l('product_weight'), $this->l('product_quantity'), $this->l('product_quantity_refunded'), $this->l('product_quantity_return'),
			$this->l('product_tax_rate'), $this->l('product_ecotax'), $this->l('product_discount_quantity_applied'), $this->l('customer_id'),
			$this->l('invoice_firstname'), $this->l('invoice_lastname'), $this->l('invoice_company'), $this->l('invoice_address1'),
			$this->l('invoice_address2'), $this->l('invoice_postcode'), $this->l('invoice_city'), $this->l('invoice_phone'),
			$this->l('invoice_phone_mobile'), $this->l('delivery_firstname'), $this->l('delivery_lastname'), $this->l('delivery_company'),
			$this->l('delivery_address1'), $this->l('delivery_address2'), $this->l('delivery_postcode'), $this->l('delivery_city'),
			$this->l('delivery_phone'), $this->l('delivery_phone_mobile'), $this->l('invoice_date'), $this->l('payment'), $this->l('delivery_date'),
			$this->l('shipping_number'), $this->l('status'), $this->l('total_discounts'), $this->l('total_paid'), $this->l('total_paid_real'),
			$this->l('total_products'), $this->l('total_products_wt'), $this->l('total_shipping'), $this->l('total_wrapping'), $this->l('currency'));
		foreach ($titles as $datas)
			$headers[] = Tools::htmlentitiesDecodeUTF8($datas);

		$content = array();
		foreach ($csv_list as $i => $row)
		{
			$content[$i] = array();
			foreach ($row as $key => $value)
				if (isset($row[$key]))
					$content[$i][] = Tools::htmlentitiesDecodeUTF8($row[$key]);
		}
		$this->context->smarty->assign(array(
			'export_precontent' => "\xEF\xBB\xBF",
			'export_headers' => $headers,
			'export_content' => $content
				)
		);
		$this->layout = 'layout-export.tpl';
	}

	public function initContent()
	{
		$this->display = 'edit';
		$this->initTabModuleList();
		$this->initToolbar();
		$this->initPageHeaderToolbar();
		$this->content .= $this->initFormByDate();

		$this->context->smarty->assign(array(
			'content' => $this->content,
			'url_post' => self::$currentIndex.'&token='.$this->token,
			'show_page_header_toolbar' => $this->show_page_header_toolbar,
			'page_header_toolbar_title' => $this->page_header_toolbar_title,
			'page_header_toolbar_btn' => $this->page_header_toolbar_btn
		));
	}

	public function initFormByDate()
	{
		$this->fields_form = array(
			'legend' => array(
				'title' => $this->l('Setting date'),
				'icon' => 'icon-calendar'
			),
			'input' => array(
				array(
					'type' => 'date',
					'label' => $this->l('From'),
					'name' => 'date_begin',
					'maxlength' => 10,
					'required' => true,
					'hint' => $this->l('Format: 2011-12-31 (inclusive).')
				),
				array(
					'type' => 'date',
					'label' => $this->l('To'),
					'name' => 'date_endin',
					'maxlength' => 10,
					'required' => true,
					'hint' => $this->l('Format: 2012-12-31 (inclusive).')
				)
			),
			'submit' => array(
				'title' => $this->l('Generate csv file by date'),
				'id' => 'submitPrint',
				'icon' => 'process-icon-download-alt'
			)
		);

		$this->fields_value = array(
			'date_begin' => date('Y-m-d'),
			'date_endin' => date('Y-m-d')
		);

		$this->show_toolbar = false;
		$this->show_form_cancel_button = false;
		$this->toolbar_title = $this->l('Print CSV invoices');
		return parent::renderForm();
	}

}
