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
if (!function_exists('mb_internal_encoding')) {
	echo "please enable mb_internal_encoding in your php.ini. This is needed for utf-8. ";
	echo "on windows, this line looks like this: extension=php_mbstring.dll and extension_dir = \"ext\"";
	exit(1);
}
mb_internal_encoding('UTF-8');

$vorlage = file_get_contents('./vorlage/geldzuwendung.tex');
$workpath = 'spendenquittung/';


$dir = scandir($workpath);

foreach ($dir as $file) {
	if (preg_match('/.json$/i',$file)) {
		$basename = preg_replace('/\\.json$/i', '', $file);
		$texfilename = $workpath . $basename . '.tex';
		if (file_exists($texfilename)) {
			echo "This Texfile already exists. Skipping $texfilename\n";
			continue;
		}

		$data = json_decode(file_get_contents($workpath . $file));
		if ($data == null) {
			die("there's something wrong with the data in $file\n");
		}
		$intermediate = fopen($texfilename, 'w');
		fwrite($intermediate, applyTemplate($vorlage, $data));
		fclose($intermediate);

		chdir('spendenquittung'); // otherwise the assets in the Vorlage will not be available.
		system('pdflatex -output-directory="../' . $workpath . '" "../' . $texfilename . '"');
		chdir('..');
	}
}

function applyTemplate($template, $data) {
	// date
	$out = str_replace("DatumDerBescheinigung", $data->date, $template);

	// number
	$out = str_replace("SpendenQuittungsNummer", $data->runningNumber, $out);

	// amounts
	$trns = '';
	$inWorten = array(
		 '8.00' => 'Acht Euro',
		 '8.33' => 'Acht Euro Dreiunddreißig Cent',
		'10.00' => 'Zehn Euro',
		'11.50' => 'Elf Euro Fünfzig Cent',
		'13.00' => 'Dreizehn Euro',
		'14.00' => 'Vierzehn Euro',
		'15.00' => 'Fünfzehn Euro',
		'20.00' => 'Zwanzig Euro',
		'22.00' => 'Zweiundzwanzig Euro',
		'23.00' => 'Dreiundzwanzig Euro',
		'25.00' => 'Fünfundzwanzig Euro',
		'27.00' => 'Siebenundzwanzig Euro',
		'27.42' => 'Siebenundzwanzig Euro Zweiundvierzig Cent',
		'30.00' => 'Dreißig Euro',
		'30.42' => 'Dreißig Euro Zweiundvierzig Cent',
		'37.00' => 'Siebenunddreißig Euro',
		'39.00' => 'Neununddreißig Euro',
		'40.00' => 'Vierzig Euro',
		'46.00' => 'Sechsundvierzig Euro',
		'90.00' => 'Neunzig Euro',
		'200.00' => 'Zweihundert Euro',
		'264.00' => 'Zweihundertvierundsechzig Euro'
	);
	foreach($data->transactions as $transaction) {
		$trns .= str_replace('.', ',', $transaction->amount) . " Euro & " . $inWorten[$transaction->amount] . " & $transaction->date \\\\\n";
	}
	$out = str_replace("1.000.000.000.000,00 Euro & Eine Fantastillionen Euro & 01.01.1900\n", $trns, $out);

	// name & address
	$out = str_replace("ZuwenderName", $data->name, $out);
	$out = str_replace("ZuwenderStrasse", $data->address, $out);
	$out = str_replace("ZuwenderOrt", $data->city, $out);

	return $out;
}

?>
