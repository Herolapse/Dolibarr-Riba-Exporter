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
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		// Load language file
		$langs->loadLangs(["ribaexporter@ribaexporter"]);

		// Add Export to RIBA option to invoice list mass actions
		if (in_array("invoicelist", explode(":", $parameters["context"]))) {
			$this->resprints = '<option value="export_to_riba">' . $langs->trans("ExportRiba") . "</option>";
		}
	}

	/**
	 * Handle the export to RIBA action when selected from the mass actions dropdown.
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		// Check if the action is export to RIBA
		if (!isset($_REQUEST["massaction"]) or $_REQUEST["massaction"] != "export_to_riba") {
			return 0; // Not the right action
		} else {
			// Export RIBA
			$this->exportRibas($parameters, $object, $action, $hookmanager);
		}
	}

	/*
	 * Export invoices to RIBA format.
	 * This function groups invoices by bank account and generates RIBA files for each bank.
	 */
	private function exportRibas($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $db, $user;

		// Load language file
		$langs->loadLangs(["ribaexporter@ribaexporter"]);

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
			$bank->fetch($bank_id);
			$bank->fetch_optionals();

			// SIA Code
			$sia_code = $bank->array_options["options_ribaexporter_codice_sia"];

			// Check if SIA code is set
			if (empty($sia_code)) {
				// If SIA code is not set, skip this bank
				setEventMessages($langs->trans("SIAMissing") . ": " . $bank->label, null, "errors");
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
				setEventMessages($langs->trans("SQLError") . ": " . $bank->label, null, "errors");
				continue;
			}

			// Prepare the RIBA export information
			$riba_info = [
				"nome_supporto" => "",
				"data_creazione" => date("dmy"),

				"creditore" => [
					"ragione_sociale" => $bank->owner_name,
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
			$intestazione->data_creazione = $riba_info["data_creazione"];
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

				// If invoice is not in validation state, skip it
				if ($invoice->statut != Facture::STATUS_VALIDATED) {
					setEventMessages($langs->trans("InvalidInvoice") . ": " . $invoice->ref, null, "warnings");
					continue;
				}

				// Retrive bank state information
				$sql = "SELECT code_departement FROM " . MAIN_DB_PREFIX . "c_departements WHERE rowid = " . ((int) $creditor->state_id);
				$resql = $db->query($sql);
				if ($resql && $db->num_rows($resql) > 0) {
					$obj = $db->fetch_object($resql);
					$province_code = $obj->code_departement;
				} else {
					// Error retrieving province code
					setEventMessages($langs->trans("SQLError") . ": " . $bank->label, null, "errors");
					continue;
				}

				// Set invoice as exported
				$invoice->array_options["options_ribaexporter_riba_exported"] = 1;
				$invoice->update($user);

				$invoices_info[] = [
					"numero" => $invoice->ref,
					"data_scadenza" => gmdate("dmy", $invoice->date_lim_reglement),
					"descrizione" => $invoice->description ?? "",
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
			$c = 0;
			foreach ($invoices_info as $invoice) {
				$debitore = $invoice["debitore"];

				$ricevuta = new Ricevuta();
				$ricevuta->numero_ricevuta = $invoice["numero"];
				$ricevuta->scadenza = $invoice["data_scadenza"];
				$ricevuta->descrizione_banca = strtoupper($invoice["descrizione"]);
				$ricevuta->descrizione = "FATT. " . $invoice["numero"] . " DEL " . date("d/m/Y");

				// Transform in cents
				$ricevuta->importo = round($invoice["importo"] * 100, 0);

				// Informazioni sul debitore
				$ricevuta->codice_cliente = $debitore["codice"];
				$ricevuta->nome_debitore = strtoupper($debitore["ragione_sociale"]);
				$ricevuta->identificativo_debitore = !empty($debitore["partita_iva"]) ? $debitore["partita_iva"] : $debitore["codice_fiscale"];
				$ricevuta->indirizzo_debitore = strtoupper($debitore["indirizzo"]);
				$ricevuta->cap_debitore = $debitore["cap"];
				$ricevuta->comune_debitore = strtoupper($debitore["citta"]);
				$ricevuta->provincia_debitore = $debitore["provincia"];

				$riba->addRicevuta($ricevuta);
				$c++;
			}

			// Get the CBI content (if any ricevute were added)
			if ($c > 0) {
				$content = $riba->asCBI();

				// Add the RIBA to the list of generated files
				$ribas[] = [
					"filename" => "export_riba_" . date("Y-m-d") . ".txt",
					"content" => $content,
				];
			}
		}

		// If no RIBA files were generated, return an error
		if (empty($ribas)) {
			setEventMessages($langs->trans("RibaExportError"), null, "warnings");
			return 0;
		}

		// Set success message
		setEventMessages($langs->trans("RibaExportSuccess"), null, "mesgs");

		// Generate unique identifier for this download
		$download_id = uniqid("riba_", true);

		// Store download data in session
		switch (count($ribas)) {
			case 1:
				// Single file
				$_SESSION["riba_downloads"][$download_id] = [
					"filename" => $ribas[0]["filename"],
					"content" => $ribas[0]["content"],
					"content_type" => "application/octet-stream",
				];
				break;
			default:
				// Multiple files - create zip
				$zip = new ZipArchive();
				$zip_filename = "export_ribas_" . date("Y-m-d") . ".zip";
				$temp_file = tempnam(sys_get_temp_dir(), "riba_zip");

				if ($zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
					setEventMessages($langs->trans("ZipExportError"), null, "errors");
					return 0;
				}

				foreach ($ribas as $riba) {
					$zip->addFromString($riba["filename"], $riba["content"]);
				}

				$zip->close();
				$content = file_get_contents($temp_file);
				unlink($temp_file);

				$_SESSION["riba_downloads"][$download_id] = [
					"filename" => $zip_filename,
					"content" => $content,
					"content_type" => "application/zip",
				];
				break;
		}

		// Use iframe to trigger download without page redirect
		echo '<iframe src="' . DOL_URL_ROOT . "/custom/ribaexporter/download.php?id=" . $download_id . '" style="display:none;"></iframe>';

		return 0;
	}
}
