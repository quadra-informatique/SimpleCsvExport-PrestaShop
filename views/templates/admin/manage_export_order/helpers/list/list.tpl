{*
*  1997-2013 QUADRA INFORMATIQUE
*
*  @author QUADRA INFORMATIQUE <ecommerce@quadra-informatique.fr>
*  @copyright 1997-2012 QUADRA INFORMATIQUE
*  @version  Release: 1.5 $Revision: 1.2 $
*  @license  http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*}


<form action="{$url_post}" method="post" >
    <fieldset id="date_part">
        <legend><img src="../img/admin/choose.gif" />{l s='Export des commandes' mod='ccas_exportorder'}</legend>
        <label>{l s='Date de debut:' mod='ccas_exportorder'}</label>
        <div class="margin-form">
            <input type="text" class="datepicker" name="date_begin" data-hex="true" value="{$dateBegin}" />
        </div>
        <label>{l s='Date de fin:' mod='ccas_exportorder'}</label>
        <div class="margin-form">
            <input type="text" class="datepicker" name="date_endin" data-hex="true" value="{$dateEndin}" />
        </div>
        <div class="margin-form">
            <input type="submit" class="button" name="export_order" value="Valider" />
        </div>
    </fieldset><br />


</form>
<div id="loader_container">
    <div id="loader"></div>
</div>
<script type="text/javascript">
    {literal}

		$(document).ready(function() {
			if ($(".datepicker").length > 0)
				$(".datepicker").datepicker({
					prevText: '',
					nextText: '',
					dateFormat: 'yy-mm-dd'
				});
		});
    {/literal}
</script>




