<?php

if (!function_exists('mb_internal_encoding')) {
	echo "please enable mb_internal_encoding in your php.ini. This is needed for utf-8. ";
	echo "on windows, this line looks like this: extension=php_mbstring.dll and extension_dir = \"ext\"";
	exit(1);
}

mb_internal_encoding('UTF-8');
echo mb_internal_encoding();

error_reporting(E_ALL);
date_default_timezone_set('Europe/Berlin');

require "lib/MVBTransaction.php";
require "lib/Ruler.php";
require "lib/Rule.php";

foreach (glob("rules/*.php") as $filename) {
    include $filename;
}

$infilename = empty($argv[1]) ? 'input.csv' : $argv[1];
$outfilename = $infilename.'.qif';
echo 'reading file ' . $infilename . "\n";
echo 'writing to file ' . $outfilename . "\n";

$source = fopen($infilename, 'r');
$start = false;
// $debuglog = fopen('debuglog.log', 'a');

$target = fopen($outfilename, 'w');
fwrite($target, "!Account\nNAktiva:Barvermögen:Girokonto\n^\n");

$unexpectedTransactions = array();

function iconv_array(&$item, $key) {
	$item = iconv('ISO-8859-1', 'UTF-8', $item);
}

while (($data = fgetcsv($source, 1000, ";")) !== FALSE) {
	array_walk($data, 'iconv_array');

	// Start when header was found
	if (!$start) {
		if ($data[0] == "Buchungstag") {
			$start = true;
		}
		continue;
	}

	// Stop at next empty line after start
	if ($start) {
		if (count($data) === 1) {
			break;
		}
	}

	$t = new MVBTransaction();
	$t->parseData($data);
	$knownTransaction = Ruler::applyRules($t);
	if (!$knownTransaction) {
		$unexpectedTransactions[] = '- ' . $t->getDate() . ' (' . $t->getAmount() . ' / ' . $t->getName() . ')' .
			"\n  " . $t->getMemo();
	}

	$buffer = $t->getParsedTransaction();
	fwrite($target, $buffer);
}

fclose($source);
fclose($target);

Ruler::printStats();
echo "\n\nUnerwartete Transaktionen:\n" . join($unexpectedTransactions, "\n");

?>
