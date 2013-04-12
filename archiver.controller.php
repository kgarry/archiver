<?php

// --- START CONFIG -----------------------------------------
# todo read these in from settings/configurator file ??
$table = 'P';
$db = 'ioc';
$date_field = 'dateCreated';
$conn = array('host'=>'localhost', 'user'=>'ioc', 'pass'=>'thecolorpurplehaze1');
$oldestCap = array('num'=>'2', 'unit'=>'HOUR');
// --- END CONFIG -------------------------------------------

require_once("./archiver.class.php");

$begin = microtime(true);
$out = "\nBEGIN ARCHIVER run at " . date("Y-m-d h:i:s") . "\n";

# ----------------------------------------------------------
// establish
$orig = new OrigArchiver($table, $db, $date_field, $conn, $oldestCap);

if (empty($orig->oldestDay)) {
  exit($out . "\nNo oldest date on Original.. LEAVING NOW\n");
}
$copy = new CopyArchiver($orig, $conn);

// rinse cycle
$origCount = $orig->countDay();
$out .= "orig day/qty: ".$orig->oldestDay." / " . $origCount."\n\r";
$out .= "archive qty: ".$copy->countDay($orig->oldestDay)."\n\r";

if ($origCount == $copy->countDay($orig->oldestDay)) {
	purgeOrig($orig, $begin);
}

if ($copy->countDay($orig->oldestDay) == 0) {
  $copy->copy($orig);
}
else { // e-mail an admin?? // delete archive day and continue ??
  $out .= "WARNING: This day " . $orig->oldestDay . " has already been processed in " . $copy->table . 
		"(at least partially " . $copy->countDay($orig->oldestDay) . " of " . $orig->countDay() . ")";
}

$out .= "\nEND ARCHIVER run after (" . round(microtime(true)-$begin, 5) . ")\n";

echo $out . "\n\n\n";
//echo var_export($orig->log, true) . "\n";
//echo var_export($copy->log, true) . "\n";


/***
* @desc		invoke purge method and return new $orig object
* @param	$orig (Archiver) the original "original"
* @return	$orig (Archiver) the new "original" :)
* @todo		check if $orig is of class Archiver and throw
***/
function purgeOrig($orig, $begin) {
	$orig->purge();
	$out = "\tPURGED " . $orig->countDay() . " records dated " . $orig->oldestDay . " from " . 
		$orig->table . "\n" .
		"\nEND ARCHIVER run after PURGE (" . round(microtime(true)-$begin, 5) . ")\n";

	return new OrigArchiver($orig->table, $orig->db, $orig->date_field, $orig->conn, 
		array('num'=>$orig->oldestCapNum, 'unit'=>$orig->oldestCapUnit));
}
