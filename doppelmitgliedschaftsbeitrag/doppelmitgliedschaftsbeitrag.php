<?php

/*
 * Getting all Member fees in a specific timeframe from gnucash files.
 * With this, you can easily create a report for CCC Office
 *
 * Written by Claudius for CCCMZ <ccc@amenthes.de>
 * Released under MIT License (2013)
 */
error_reporting(E_ALL);
mb_internal_encoding('utf-8');
date_default_timezone_set('Europe/Berlin');

/** CONFIGURE THESE!!! */
$MIN_TRANSACTION_DATE = DateTime::createFromFormat('Y-m-d H:i:s P', '2013-01-01 00:00:00 +0100');
$MAX_TRANSACTION_DATE = DateTime::createFromFormat('Y-m-d H:i:s P', '2013-06-30 23:59:59 +0100');
$xml = simplexml_load_file("../../finanz_gnucash/konto.gnucash");

// getting the root ID for all member accounts
$memberFeesRootAccountId = getAccountId('Mitgliedsbeiträge');
// all member account IDs and Names
$memberFeeAccountIds = getSubAccounts($memberFeesRootAccountId);

// User Interaction
asort($memberFeeAccountIds);
foreach ($memberFeeAccountIds as $id => $name) {
	global $MIN_TRANSACTION_DATE, $MAX_TRANSACTION_DATE;

	$trns = getTransactionsFromAccount($id);
	$trns = filterTransactionsByDate($trns, $MIN_TRANSACTION_DATE, $MAX_TRANSACTION_DATE);
	$trns = filterTransactionsByDescription($trns, '/\\(CCC.{0,6}\\)/i', true); // ccc-buchungen finden

	$CNR = '?';
	$text = '';
	$total = 0.0;
	foreach($trns as $t) {
		if ($CNR === '?' && preg_match('/\\(CCC\s*(\d{1,6})\\)/i', $t['description'], $matches)) {
			$CNR = $matches[1];
		}
		$text .= $t['description'] . " (" . sprintf('%01.2f', $t['amount'] / 100) . ")\n";
		$total += $t['amount'] / 100;
	}
	echo "\"$name\";\"" . $text . "\";" . $CNR . ";" . $total . "\n";
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

function findExistingNumber($searchPrefix, $sanitizedName) {
	global $targetFolder, $filenameParseRegex;
	$handle = opendir($targetFolder);
	while (false !== ($file = readdir($handle))) {
		preg_match($filenameParseRegex, $file, $match);
		if ($match) {
			@$prefix = $match[1];
			@$number = (int) $match[2];
			@$name = $match[3];
			if ($prefix == $searchPrefix && $name == $sanitizedName) {
				echo "Es wurde bereits eine Spendenquittung angelegt: ".$file."\n";
				return $number;
			}
		}
	}

	return false;
}

function getNextFreeNumber() {
	global $targetFolder, $filenameParseRegex;
	$highest = 0;
	$handle = opendir($targetFolder);
	while (false !== ($file = readdir($handle))) {
		preg_match($filenameParseRegex, $file, $match);
		@$current = (int) $match[2];
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