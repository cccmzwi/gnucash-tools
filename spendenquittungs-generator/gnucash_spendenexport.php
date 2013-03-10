<?php

/*
 * Getting all Member fees in a specific timeframe from gnucash files.
 * This will generate Spendenquittungen
 *
 * Written by Claudius for CCCMZ <ccc@amenthes.de>
 * Released under MIT License (2013)
 */
error_reporting(E_ALL);
date_default_timezone_set('Europe/Berlin');

/** CONFIGURE THESE!!! */
$defaultYear = '2012';
$MIN_TRANSACTION_DATE = DateTime::createFromFormat('Y-m-d H:i:s P', $defaultYear.'-01-01 00:00:00 +0100');
$MAX_TRANSACTION_DATE = DateTime::createFromFormat('Y-m-d H:i:s P', $defaultYear.'-12-31 23:59:59 +0100');
$targetFolder = './spendenquittung/';
$filenamePrefix = $defaultYear;
$filenameTemplate = $targetFolder . $filenamePrefix . "%03d %s.json"; // running number, name
$filenameParseRegex = "/^.{" . strlen($filenamePrefix) . "}(\d{3})\s/";

$xml = simplexml_load_file("../../finanz_gnucash/konto.gnucash");

// getting the root ID for all member accounts
$memberFeesRootAccountId = getAccountId('Mitgliedsbeiträge');
// all member account IDs and Names
$memberFeeAccountIds = getSubAccounts($memberFeesRootAccountId);

// User Interaction
asort($memberFeeAccountIds);
$selectCounter = 0;
foreach ($memberFeeAccountIds as $id => $name) {
	echo ++$selectCounter . ": $name\n";
}
print "Für wen soll die Spendenquittung sein? [1.." . count($memberFeeAccountIds) . "]: ";
$entry = (int) trim(fgets(STDIN));
$selectCounter = 0;
foreach ($memberFeeAccountIds as $id => $name) {
	if ($entry == ++$selectCounter) {
		createDataFor($id, $name);
	}
}



function createDataFor($id, $name) {
	global $filenameTemplate, $MIN_TRANSACTION_DATE, $MAX_TRANSACTION_DATE;
	$runningNumber = getNextFreeNumber();
	$filename = filenameSanitazion(sprintf($filenameTemplate, $runningNumber, $name));
	echo "$filename\n";

	$trns = getTransactionsFromAccount($id);
	$trns = filterTransactionsByDate($trns, $MIN_TRANSACTION_DATE, $MAX_TRANSACTION_DATE);
	$trns = filterTransactionsByDescription($trns, '/\\(Ccc .{1,6}\\)/i'); // ccc-buchungen raus filtern
	$trns = convertForExport($trns);

	$file = fopen($filename, 'x'); // x won't overwrite an existing file.
	if ($file) {
		fwrite($file, json_encode(array(
			"runningNumber" => $runningNumber,
			"name" => $name,
			"address" => "-Straße-",
			"city" => "-Stadt-",
			"date" => date('d.m.Y'),
			"transactions" => $trns
		)));
		fclose($file);
	} else {
		exit(1);
	}
}

// DONE.

/**
 * find an account ID by its name
 * @param $name {String} GnuCash Name
 * @return {String} account Id
 */
function getAccountId($name) {
	global $xml;
	$result = $xml->xpath('/gnc-v2/gnc:book/gnc:account[act:name="' . $name . '"]/act:id');
	if (count($result) === 1) {
		echo "Found " . $name . " node. ID: " . $result[0] . "\n";
		return $result[0];
	} else {
		die("Did not find an account with the name " . $name);
	}
}


/**
 * Account Id to Name Mapping
 * @param {String} $parentId
 * @return {Array} array in ID->Name format, ie {'ae2ac..' => "some name", "bea31..." => "other name"}
 */
function getSubAccounts($parentId) {
	global $xml;
	$accounts = $xml->xpath('/gnc-v2/gnc:book/gnc:account[act:parent="' . $parentId . '"]');

	if (count($accounts) > 0) {
		echo "Found " . count($accounts) . " accounts.\n";
	} else {
		die("Did not find any accounts with the parent-id " . $parentId);
	}

	$ids = array();
	foreach ($accounts as $key => $node) {
		$childNodes = $node->children('act', true);
		$ids[(String) $childNodes->id] = (String) $childNodes->name;
	}
	return $ids;
}


/**
 * All Transactions done by a certain account ID
 * @param $accountId {String}
 * @return {Array} of transactions in the format of array("date" => date-object, "amount" => 1000, "description" => "some text")
 */
function getTransactionsFromAccount($accountId) {
	global $xml;
	$out = array();

	// gets all transaction-splits for this account
	$splits = $xml->xpath('/gnc-v2/gnc:book/gnc:transaction/trn:splits/trn:split[split:account="' . $accountId . '"]');

	foreach($splits as $key => $node) {
		$transaction = $node->xpath('../..'); // goes back to the parent-transaction
		$transaction = $transaction[0]->children('trn',true);
		$date = (String) $transaction->{'date-posted'}->children('ts', true)->date;
		$date = DateTime::createFromFormat('Y-m-d H:i:s P', $date);
		$split = $node->children('split', true);
		$out[] = array(
			"date" => $date,
			"amount" => valueToCents($split->value),
			"description" => (String) $transaction->description
		);
	}
	return $out;
}


/**
 * Filter Transactions by Date-Range
 * @param $transactions {Array} of transactions
 * @param $minDate {Date} (optional) Start date, no transaction before that time will be returned
 * @param $maxDate {Date} (optional) End date, no transaction after that time will be returned
 * @return {Array} of transactions
 */
function filterTransactionsByDate($transactions, $minDate = null, $maxDate = null) {
	$out = array();
	foreach($transactions as $t) {
		if (($minDate === null || $minDate <= $t['date']) && ($t['date'] <= $maxDate || $maxDate === null)) {
			$out[] = $t;
//			echo " - $key $transaction->id " . valueToCents($split->value) . " $transaction->description " . $date->format('Y-m-d') . "\n"; // for debugging
		} else {
//			echo "skip (out of date " . $date->format('Y-m-d') . ")\n"; // for debugging
		}
	}
	return $out;
}


/**
 * Specify a regex that will be filtered from the transactions.
 * @param $transactions {Array} of transactions
 * @param $searchForm {RegEx String} to search for
 * @param $allowed {Boolean} defaults to false - the matched descriptions are not allowed.
 *        specify $allowed=true to include ONLY lines that are matched by $searchFor
 * @return {Array} of transactions
 */
function filterTransactionsByDescription($transactions, $searchFor = null, $allowed = false) {
	$out = array();
	foreach($transactions as $t) {
		$match = preg_match($searchFor, $t['description']);
		if ($match === false) {
			die("Error when filtering for Description");
		}
		if (($match === 0) ^ $allowed) { // xor!
			$out[] = $t;
		} else {
//			echo "skipped this because of description: " . $t['description'] . "\n"; // for debugging
		}
	}
	return $out;
}


function convertForExport($transactions) {
	$out = array();
	foreach($transactions as $t) {
		$t['date'] = $t['date']->format('d.m.Y');
		$t['amount'] = sprintf('%01.2f', $t['amount'] / 100);
		$out[] = $t;
	}
	return $out;
}

/**
 * Transfrom the split:value string into an integer representing cents
 * @param $amount {String} in the form "-4200/100" for 42 Euros
 * @return {integer} in the example that would be 4200 (cents)
 */
function valueToCents($amount)
{
	$parts = explode("/", $amount);
	if ($parts[1] !== '100') {
		die("unexpected number format.");
	}
	$value = (int) $parts[0] * (-1);
	return $value;
}

function getNextFreeNumber() {
	global $targetFolder, $filenameParseRegex;
	$highest = 0;
	$handle = opendir($targetFolder);
	while (false !== ($file = readdir($handle))) {
		preg_match($filenameParseRegex, $file, $match);
		@$current = (int) $match[1];
		$highest = max($current, $highest);
	}

	return $highest + 1;
}

/**
 * PHP 5.x can't handle utf8 strings in filenames.
 */
function filenameSanitazion($in) {
	return str_replace(
		array('ä', 'Ä',  'ö', 'Ö',  'ü', 'Ü',  'ß'),
		array('ae','Ae', 'oe','Oe', 'ue','Ue', 'ss'),
		$in
	);
}


?>