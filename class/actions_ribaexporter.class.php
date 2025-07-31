<?php

class ActionsRibaExporter
{
	/**
	 * Add an "export riba" option to the mass actions dropdown in the invoice list.
	 */
	function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		// Add Export to RIBA option to invoice list mass actions
		if (in_array("invoicelist", explode(":", $parameters["context"]))) {
			$this->resprints = '<option value="export_to_riba">' . $langs->trans("ExportRiba") . "</option>";
		}
	}

	/**
	 * Handle the export to RIBA action when selected from the mass actions dropdown.
	 */
	function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		if ($_REQUEST["massaction"] == "export_to_riba") {
			foreach ($parameters["toselect"] as $objectId) {
				// Load the invoice object
				$invoice = new Facture($db);
				$invoice->fetch($objectId);

				print_r($invoice);
			}
			// die();
		}
	}

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		echo " 2: " . $action;

		if (in_array("invoicecard", explode(":", $parameters["context"]))) {
			global $conf, $user, $langs, $object, $hookmanager, $db;
			//echo "<pre>".print_r($object->date_validation,1)."</pre>";
			if ($object->date_validation != "") {
				print '<script type="text/javascript">';
				print "$(document).ready(function() {
                            $('.butAction').each(function(){
                            link = $(this).attr('href');

                            if(link.match('modif')){
                                $(this).remove();
                                $('.butActionDelete').addClass('butActionRefused');
                                $(this).addClass('butActionRefused');
                                $(this).attr('href','#');
                                $('.butActionDelete').attr('href','#');
                            } });

                        });";
				print "</script>";
			}
		}
		if (in_array("paiementcard", explode(":", $parameters["context"]))) {
			global $conf, $user, $langs, $object, $hookmanager, $db;
			//echo "<pre>".print_r($object->date_validation,1)."</pre>";
			print '<script type="text/javascript">';
			print "$(document).ready(function() {
                        alert('toto');
                            $('.butAction').each(function(){
                            link = $(this).attr('href');

                            if(link.match('modif')){
                                $(this).remove();
                                $('.butActionDelete').addClass('butActionRefused');
                                $(this).addClass('butActionRefused');
                                $(this).attr('href','#');
                                $('.butActionDelete').attr('href','#');
                            } });

                        });";
			print "</script>";
		}
	}
}
