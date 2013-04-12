<?php

$begin = microtime(true);
$out = "\nBEGIN ARCHIVER run at " . date("Y-m-d h:i:s") . "\n";

/***
*
***/
require_once("/var/www/thor/archiver/archiver.class.php");

$orig_table = 'bad_ip';
$orig = new Archiver($orig_table);
$derivedTableName = $orig_table . '_archive_' . substr(str_replace('-', '', $orig->oldestDay), 0, 6);

$arch = new Archiver($derivedTableName, $orig_table . '_archive_template');

// rinse cycle
$origCount = $orig->countDay();
$out .= "orig day/qty: ".$orig->oldestDay." / " . $origCount."\n\r";
$out .= "archive qty: ".$arch->countDay($orig->oldestDay)."\n\r";

if ($origCount == $arch->countDay($orig->oldestDay)) {
	purgeOrig($orig, $orig_table);
}

if ($arch->countDay($orig->oldestDay) == 0) {
  $arch->copy($orig);
}
else { // e-mail an admin??
  $out .= "WARNING: This day " . $orig->oldestDay . " has already been processed in " . $arch->table . 
		"(at least partially " . $arch->countDay($orig->oldestDay) . " of " . $orig->countDay() . ")";
}

$out .= "\nEND ARCHIVER run after (" . round(microtime(true)-$begin, 5) . ")\n";

echo $out;



/***
* @desc		invoke purge method and return new $orig object
* @param	$orig (Archiver) the original "original"
* @return	$orig (Archiver) the new "original" :)
* @todo		check if $orig is of class Archiver and throw
***/
function purgeOrig($orig, $table) {
	$orig->purge();
	$out .= "\tPURGED " . $origCount . " records dated " . $orig->oldestDay . " from " . $orig->table . "\n";
	$out .= "\nEND ARCHIVER run after PURGE (" . round(microtime(true)-$begin, 5) . ")\n";

	return new Archiver($table);
}
