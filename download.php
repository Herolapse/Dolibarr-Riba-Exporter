<?php
/* Copyright (C) 2024 Herolapse s.r.l.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    download.php
 * \ingroup ribaexporter
 * \brief   Download handler for RIBA export files
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Check if user has permission
if (!$user->hasRight('facture', 'lire')) {
	accessforbidden();
}

// Get download ID
$download_id = GETPOST('id', 'alphanohtml');

if (empty($download_id)) {
	http_response_code(400);
	die('Missing download ID');
}

// Check if download data exists in session
if (!isset($_SESSION["riba_downloads"][$download_id])) {
	http_response_code(404);
	die('Download not found or expired');
}

// Get download data
$download_data = $_SESSION["riba_downloads"][$download_id];

// Clean up session data
unset($_SESSION["riba_downloads"][$download_id]);

// Validate download data
if (empty($download_data["filename"]) || empty($download_data["content"]) || empty($download_data["content_type"])) {
	http_response_code(500);
	die('Invalid download data');
}

// Set headers for file download
header("Content-Type: " . $download_data["content_type"]);
header('Content-Disposition: attachment; filename="' . $download_data["filename"] . '"');
header("Content-Length: " . strlen($download_data["content"]));
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Output file content
echo $download_data["content"];
exit();
