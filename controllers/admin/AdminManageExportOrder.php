<?php

/**
 * ---------------------------------------------------------------------------------
 *
 * 1997-2013 Quadra Informatique
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
 * @author Quadra Informatique <ecommerce@quadra-informatique.fr>
 * @copyright 1997-2013 Quadra Informatique
 * @version Release: $Revision: 1.0 $
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * ---------------------------------------------------------------------------------
 */
class AdminManageExportOrderController extends ModuleAdminController
{

    public function __construct()
    {
        $this->module = 'simplecsvexport';
        $this->lang = false;
        $this->requiredDatabase = false;
        $this->className = 'Configuration';
        $this->context = Context::getContext();

        parent :: __construct();
    }

    public function postProcess()
    {

        parent::postProcess();
        if (Tools::isSubmit('export_order') && !($this->tabAccess['add'] === '1'))
            $this->errors[] = Tools::displayError('You do not have the required permission to export stock.');

        if (count($this->errors))
            return;
        if (Tools::isSubmit('export_order'))
        {
            // get source warehouse id
            $dateBegin = Tools::getValue('date_begin', '');
            $dateEndin = Tools::getValue('date_endin', '');

            if (!$dateBegin)
                $this->errors[] = Tools::displayError('Vous devez choisir une date de début.');
            else
            {
                $dateBegin = explode('-', $dateBegin);
                $dateBegin = mktime(0, 0, 0, $dateBegin[1], $dateBegin[2], $dateBegin[0]);
                $dateBegin = date('Y-m-d', $dateBegin);
            }
            if (!$dateEndin)
                $this->errors[] = Tools::displayError('Vous devez choisir une date de fin.');
            else
            {
                $dateEndin = explode('-', $dateEndin);
                $dateEndin = mktime(0, 0, 0, $dateEndin[1], $dateEndin[2], $dateEndin[0]);
                $dateEndin = date('Y-m-d', $dateEndin);
            }

            if ($dateEndin < $dateBegin)
                $this->errors[] = Tools::displayError('La date de fin ne peut pas être inferieure à la date de début.');
            if (count($this->errors))
                return;
            else
                $this->processExportOrder($dateBegin, $dateEndin);
        }
    }

    public function processExportOrder($dateBegin, $dateEndin)
    {

        // clean buffer
        if (ob_get_level() && ob_get_length() > 0)
            ob_clean();

        $dateDebut = explode('-', $dateBegin);
        $dateDebut = mktime(0, 0, 0, $dateDebut[1], $dateDebut[2], $dateDebut[0]);
        $dateDebut = date('d/m/Y', $dateDebut);
        $dateFin = explode('-', $dateEndin);
        $dateFin = mktime(0, 0, 0, $dateFin[1], $dateFin[2], $dateFin[0]);
        $dateFin = date('d/m/Y', $dateFin);

        $bankList = array();

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
WHERE o.valid=1 AND o.date_add>="'.pSQL($dateBegin).' 00:00:00" AND o.date_add<="'.pSQL($dateEndin).' 23:59:59" 
        ORDER BY o.id_order, od.id_order_detail ASC';

        $result = Db::getInstance()->ExecuteS($sql);

        foreach ($result as $row)
        {
            foreach (array('product_price', 'product_weight', 'product_tax_rate', 'product_ecotax',
        'product_discount_quantity_applied', 'total_discounts', 'total_paid', 'total_paid_real',
        'total_products', 'total_products_wt', 'total_shipping', 'total_wrapping') as $field)
            {
                $row[$field] = str_replace(".", ",", $row[$field]);
            }

            //phone format
            foreach (array('invoice_phone', 'invoice_phone_mobile', 'delivery_phone', 'delivery_phone_mobile') as $field)
            {
                $row[$field] = @ereg_replace('[^0-9]', '', $row[$field]).' ';
			}
                array_push($bankList, array($row['order_id'], $row['product_reference'], utf8_decode($row['product_name']), $row['product_price'],
                    $row['product_weight'], $row['product_quantity'], $row['product_quantity_refunded'], $row['product_quantity_return'],
                    $row['product_tax_rate'], $row['product_ecotax'], $row['product_discount_quantity_applied'], $row['customer_id'], $row['invoice_firstname'], $row['invoice_lastname'],
                    $row['invoice_company'], $row['invoice_address1'], $row['invoice_address2'], $row['invoice_postcode'], $row['invoice_city'], $row['invoice_phone'],
                    $row['invoice_phone_mobile'], $row['delivery_firstname'], $row['delivery_lastname'], $row['delivery_company'], $row['delivery_address1'],
                    $row['delivery_address2'], $row['delivery_postcode'], $row['delivery_city'], $row['delivery_phone'], $row['delivery_phone_mobile'],
                    $row['invoice_date'], $row['payment'], $row['delivery_date'], $row['shipping_number'], $row['status'], $row['total_discounts'],
                    $row['total_paid'], $row['total_paid_real'], $row['total_products'], $row['total_products_wt'], $row['total_shipping'],
                    $row['total_wrapping'], $row['currency']));
            
        }

        /* if (!count($bankList))
          return; */

        header('Content-type: text/csv');
        header('Content-Type: application/force-download; charset=UTF-8');
        header('Cache-Control: no-store, no-cache');
        header('Content-disposition: attachment; filename="export_stock_mvt_'.date('Y-m-d_H-m-s').'.csv"');

        $headers = array();
        $titles = array('order_id', 'product_reference', 'product_name', 'product_price', 'product_weight', 'product_quantity', 'product_quantity_refunded', 'product_quantity_return',
            'product_tax_rate', 'product_ecotax', 'product_discount_quantity_applied', 'customer_id', 'invoice_firstname', 'invoice_lastname', 'invoice_company'
            , 'invoice_address1', 'invoice_address2', 'invoice_postcode', 'invoice_city', 'invoice_phone', 'invoice_phone_mobile', 'delivery_firstname', 'delivery_lastname'
            , 'delivery_company', 'delivery_address1', 'delivery_address2', 'delivery_postcode', 'delivery_city', 'delivery_phone', 'delivery_phone_mobile',
            'invoice_date', 'payment', 'delivery_date', 'shipping_number', 'status', 'total_discounts', 'total_paid', 'total_paid_real', 'total_products',
            'total_products_wt', 'total_shipping', 'total_wrapping', 'currency');
        foreach ($titles as $datas)
            $headers[] = Tools::htmlentitiesDecodeUTF8($datas);

        $content = array();
        foreach ($bankList as $i => $row)
        {
            $content[$i] = array();
            foreach ($row as $key => $value)
            {
                if (isset($row[$key]))
                {
                    $content[$i][] = Tools::htmlentitiesDecodeUTF8($row[$key]);
                }
            }
        }

        $this->context->smarty->assign(array(
            'export_precontent' => "\xEF\xBB\xBF",
            'export_headers' => $headers,
            'export_content' => $content
                )
        );

        $this->layout = 'layout-export.tpl';
    }

    public function setMedia()
    {
        parent::setMedia();
        $this->addJqueryUI('ui.datepicker');
    }

    public function renderList()
    {

        $dateBegin = Tools::getValue('date_begin', ((int)date('Y') - 1).date('-m-d'));
        $dateEndin = Tools::getValue('date_endin', date('Y-m-d'));
        $this->context->smarty->assign(array(
            'url_post' => self::$currentIndex.'&token='.$this->token,
            'dateBegin' => $dateBegin,
            'dateEndin' => $dateEndin,
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_.'simplecsvexport/views/templates/admin/manage_export_order/helpers/list/list.tpl');
    }

}
