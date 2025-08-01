<?php
require_once __DIR__ . "/../vendor/autoload.php";

use DevCode\CBI\RiBa\RiBa;
use DevCode\CBI\RiBa\Intestazione;
use DevCode\CBI\RiBa\Ricevuta;

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

		// Check if the action is export to RIBA
		if ($_REQUEST["massaction"] != "export_to_riba") {
			return 0; // Not the right action
		}

		// Retrive company information
		$company = new Societe($db);
		$company->fetch($conf->entity);
		$vat_number = preg_replace("/^[A-Z]{2}/", "", $company->tva_intra);

		// Group invoice by bank
		$bank_list = [];

		foreach ($parameters["toselect"] as $obj) {
			// Load the invoice object
			$invoice = new Facture($db);
			$invoice->fetch($obj);

			$bank_list[$invoice->fk_account][] = $invoice;
		}

		// List of generated RIBA files
		$ribas = [];

		foreach ($bank_list as $bank_id => $invoices) {
			// Get bank information
			$bank = new Account($db);
			$bank->fetch($invoice->fk_account);
			$bank->fetch_optionals();

			// SIA Code
			$sia_code = $bank->array_options["options_ribaexporter_codice_sia"];

			// Check if SIA code is set
			if (empty($sia_code)) {
				// If SIA code is not set, skip this bank
				continue;
			}

			// Retrive bank state information
			$sql = "SELECT code_departement FROM " . MAIN_DB_PREFIX . "c_departements WHERE rowid = " . ((int) $bank->state_id);
			$resql = $db->query($sql);
			if ($resql && $db->num_rows($resql) > 0) {
				$obj = $db->fetch_object($resql);
				$province_code = $obj->code_departement;
			} else {
				// Error retrieving province code
				setEventMessages($langs->trans("Error while retrieving province code for ") . $bank->label, null, "errors");
			}

			// Prepare the RIBA export information
			$riba_info = [
				"nome_supporto" => "",
				"data_creazione" => date("Y-m-d"),

				"creditore" => [
					"ragione_sociale" => $bank->proprio,
					"partita_iva" => $vat_number,
					"codice_fiscale" => "",
					"cap" => $bank->owner_zip,
					"citta" => $bank->owner_town,
					"provincia" => $province_code,
					"indirizzo" => $bank->owner_address,

					"banca" => [
						"codice_sia" => $sia_code,
						"conto" => $bank->number,
						"abi" => $bank->code_banque,
						"cab" => $bank->code_guichet,
					],
				],
			];

			// Setup RIBA
			// ----------
			// Create the header for the RIBA file
			$intestazione = new Intestazione();
			$creditore = $riba_info["creditore"];
			$banca = $creditore["banca"];

			// Format as dmy
			$intestazione->data_creazione = date("dmy", strtotime($riba_info["data_creazione"]));
			$intestazione->nome_supporto = $riba_info["nome_supporto"];

			// Bank information
			$intestazione->codice_sia = $banca["codice_sia"];
			$intestazione->conto = $banca["conto"];
			$intestazione->abi = $banca["abi"];
			$intestazione->cab = $banca["cab"];

			// Creditor information
			$intestazione->cap_citta_prov_creditore = strtoupper($creditore["cap"] . " " . $creditore["citta"] . " " . $creditore["provincia"]);
			$intestazione->ragione_soc1_creditore = strtoupper($creditore["ragione_sociale"]);
			$intestazione->indirizzo_creditore = strtoupper($creditore["indirizzo"]);
			$intestazione->identificativo_creditore = !empty($creditore["partita_iva"]) ? $creditore["partita_iva"] : $creditore["codice_fiscale"];

			$riba = new RiBa($intestazione);
			$invoices_info = [];

			// Get invoices for this bank
			foreach ($invoices as $invoice) {
				// Load the invoice object
				$creditor = new Societe($db);
				$creditor->fetch($invoice->socid);

				// Retrive bank state information
				$sql = "SELECT code_departement FROM " . MAIN_DB_PREFIX . "c_departements WHERE rowid = " . ((int) $creditor->fk_departement);
				$resql = $db->query($sql);
				if ($resql && $db->num_rows($resql) > 0) {
					$obj = $db->fetch_object($resql);
					$province_code = $obj->code_departement;
				} else {
					// Error retrieving province code
					setEventMessages($langs->trans("Error while retrieving province code for ") . $bank->label, null, "errors");
				}

				$invoices_info[] = [
					"numero" => 1,
					"data_scadenza" => date("dmy", strtotime($invoice->date_lim_reglement)),
					"descrizione" => $invoice->description,
					"importo" => $invoice->total_ttc,

					"debitore" => [
						"codice" => $invoice->ref_client,

						"ragione_sociale" => $creditor->name,
						"partita_iva" => preg_replace("/^[A-Z]{2}/", "", $creditor->tva_intra),
						"codice_fiscale" => "",
						"cap" => $creditor->zip,
						"citta" => $creditor->town,
						"provincia" => $province_code,
						"indirizzo" => $creditor->address,
					],
				];
			}

			// Add the invoices to the RIBA
			foreach ($invoices_info as $invoice) {
				$debitore = $invoice["debitore"];
				$banca = $debitore["banca"];

				$ricevuta = new Ricevuta();
				$ricevuta->numero_ricevuta = $invoice["numero"];
				$ricevuta->scadenza = date("dmy", strtotime($invoice["data_scadenza"]));
				$ricevuta->importo = $invoice["importo"];
				$ricevuta->descrizione_banca = strtoupper($invoice["descrizione"]);

				// Informazioni sul debitore
				$ricevuta->codice_cliente = $debitore["codice"];
				$ricevuta->nome_debitore = strtoupper($debitore["ragione_sociale"]);
				$ricevuta->identificativo_debitore = !empty($debitore["partita_iva"]) ? $debitore["partita_iva"] : $debitore["codice_fiscale"];
				$ricevuta->indirizzo_debitore = strtoupper($debitore["indirizzo"]);
				$ricevuta->cap_debitore = $debitore["cap"];
				$ricevuta->comune_debitore = strtoupper($debitore["citta"]);
				$ricevuta->provincia_debitore = $debitore["provincia"];

				$riba->addRicevuta($ricevuta);
			}

			// Get the CBI content
			$content = $riba->asCBI();

			// Add the RIBA to the list of generated files
			$ribas[] = [
				"filename" => "export_riba_" . date("Y-m-d") . ".txt",
				"content" => $content,
			];
		}

		// If no RIBA files were generated, return an error
		if (empty($ribas)) {
			setEventMessages($langs->trans("No RIBA file generated"), null, "warnings");
			return 0;
		}

		// If one RIBA file was generated, return it directly
		switch (count($ribas)) {
			case 1:
				$filename = $ribas[0]["filename"];
				$content = $ribas[0]["content"];

				// Set the headers for file download
				header("Content-Type: application/octet-stream");
				header("Content-Disposition: attachment; filename=\"{$filename}\"");
				header("Content-Length: " . strlen($content));
				header("Cache-Control: no-cache, no-store, must-revalidate");
				header("Pragma: no-cache");
				header("Expires: 0");

				echo $content;
				break;

			default:
				// If multiple RIBA files were generated, create a zip file
				$zip = new ZipArchive();
				$zip_filename = "export_riba_" . date("Ymd_His") . ".zip";

				if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
					setEventMessages($langs->trans("Error creating ZIP file"), null, "errors");
					return 0;
				}

				foreach ($ribas as $riba) {
					$zip->addFromString($riba["filename"], $riba["content"]);
				}

				$zip->close();
				$content = file_get_contents($zip_filename);

				// Delete the zip file after reading its content
				unlink($zip_filename);

				// Set the headers for file download
				header("Content-Type: application/zip");
				header("Content-Disposition: attachment; filename=\"{$zip_filename}\"");
				header("Content-Length: " . strlen($content));
				header("Cache-Control: no-cache, no-store, must-revalidate");
				header("Pragma: no-cache");
				header("Expires: 0");
				echo $content;
				break;
		}
		exit();
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
