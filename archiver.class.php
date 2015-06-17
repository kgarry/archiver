<?php

// TODO -- add check for this day !== a day in archiver, rotate db method

/*** *** *** *** ***
* 
***/
class Archiver {
  function __construct($table, $db, $date_field, $connOrInfo, $oldestCap=null) {
    $this->db     = $db; // pcm_ipdb
    $this->table  = $table;
		$this->date_field = $date_field;

    if (is_array($connOrInfo)) {
      $host   = $connOrInfo['host']; 
      $user   = $connOrInfo['user']; 
      $pass   = $connOrInfo['pass'];  
			try {
	      $this->conn = new mysqli($host, $user, $pass, $this->db);
			}
			catch (Exception $e) {
				exit("Failed to establish connection to (" . $this->db . "): " . $e);
			}
    }
    else {
      $this->conn = $connOrInfo;
    }    
    
		$this->setOldestCap($oldestCap);

    $this->debug = false; //true;
    $this->log = array();
  }

/***
*
***/
	protected function setOldestCap($oldestCap) {
		if (empty($oldestCap['num']) || empty($oldestCap['unit'])) {
    	$this->oldestCapNum = 2;
    	$this->oldestCapUnit = 'MONTH';
		} 
		else {
			$this->oldestCapNum = $oldestCap['num'];
      $this->oldestCapUnit = $oldestCap['unit'];
		}
	}

/***
* @todo   consider arg for hard false return on error
***/
  protected function safeQ($sql, $msg) {
    try {
      $r = $this->conn->query($sql);
    }
    catch (Exception $e) {
    	$this->logger('Caught exception: ' . $e->getMessage() .
        "\n" . $msg .
        "\nsql: {$sql}\n");
    }

    $this->logger($sql);

    return $r;
  }

/***
*
***/
  protected function logger($msg) {
    $msgStamp = date("Y-m-d h:i:s");
    $this->log[] = "[" . $msgStamp . "]: " . $msg;
  }

/***
* @return   true, if verified
            true, if created and verified
            false, if error
* @fixme		misnamed (does more than verify)
***/
  protected function verifyTable() {
    $q = " SHOW TABLES LIKE '" . $this->table . "'";

    $r = $this->safeQ($q, 
      "Table existence could not be verified in ({$this->table}).");  // hard false return?

    // EXIT early (true)?
    if ($r && $r->num_rows > 0) { 
      return true; 
    }
  }  

/***
* @desc   determine the oldest day in this table, foramtted as YYYY-MM-DD
	  ignore beyond historical cap (oldestCapUnit, oldestCapNum)
* @todo	  make this allow for timestamps AND datetime fields
***/
  protected function setOldestDay() { 
    $q = "
SELECT IFNULL(DATE_FORMAT(" . $this->date_field . ", '%Y-%m-%d'), DATE_FORMAT(FROM_UNIXTIME(" . $this->date_field . "), '%Y-%m-%d')) as oldest 
FROM " . $this->table . " 
  WHERE " . $this->date_field . " < UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL " . $this->oldestCapNum . " " . $this->oldestCapUnit . "))
    ";
//echo $q."\n\r";
    $r = $this->safeQ($q,
      "Oldest date in ({$this->table}) could not be determined.");

    if ($r->num_rows == 0) { 
      return false;
    } 
    else {
      $ret = $r->fetch_object();
      $this->oldestDay = $ret->oldest;

      return true;
    }
  }

/***
* @param    $table (str) table to count
* @return   (int) number of rows
***/
  public function countDay($day=null) {
    if (empty($day)) {
			if (empty($this->oldestDay)) { 
				return false;
			} 
      $day = $this->oldestDay; 
    }
    $q = "
SELECT count(*) as qty
FROM " . $this->table . " 
WHERE " . $this->date_field . " >= UNIX_TIMESTAMP('" . $day .  "') 
  AND " . $this->date_field . " < UNIX_TIMESTAMP(DATE_ADD('" . $day . "', INTERVAL 1 DAY))
    ";
//echo $q ."\n\n";   
    $r = $this->safeQ($q,
      "Rows of oldest day in ({$this->table}) could not be counted.");

    $ret = $r->fetch_object();

    return $ret->qty;
  }

/***
* @desc		Copies select rows (date-based) from original into archive table
* @param  	$alt (obj::Archiver) the original table Archiver object from which to fetch
***/
  public function copy($orig) {
    $sub_q = "
SELECT *, UNIX_TIMESTAMP() as archive_" . $this->date_field . "
FROM " . $orig->table . "
WHERE " . $this->date_field . " >= UNIX_TIMESTAMP('" . $orig->oldestDay .  "') 
  AND " . $this->date_field . " < UNIX_TIMESTAMP(DATE_ADD('" . $orig->oldestDay . "', INTERVAL 1 DAY))
    ";

    $i = "
INSERT INTO " . $this->table . "
  () " . 
$sub_q;   

    $this->safeQ($i,
      "Rows dated ({$orig->oldestDay}) in ({$orig->table}) failed copy to ({$this->table}).");
  }

/***
* @param    $dat (str) 'YYYY-MM-DD' matching rows will be purged
***/
  public function purge($day=null) {
    if (empty($day)) { 
      $day = $this->oldestDay; 
    }
    
    $d = "
DELETE FROM " . $this->table . "
WHERE " . $this->date_field . " >= UNIX_TIMESTAMP('" . $day .  "') 
  AND " . $this->date_field . " < UNIX_TIMESTAMP(DATE_ADD('" . $day . "', INTERVAL 1 DAY))
  AND " . $this->date_field . " < UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL " . $this->oldestCapNum . " " . $this->oldestCapUnit . ")) 
    ";
//echo $d . "\n\n"; return;
  
    $this->safeQ($d,
      "Rows of oldest day in ({$this->table}) could not be deleted.");
  } 
}


/*** *** *** *** ***
*
***/
class OrigArchiver extends Archiver {
	function __construct($table, $db, $date_field, $connOrInfo, $oldestCap=null) {
		parent::__construct($table, $db, $date_field, $connOrInfo, $oldestCap);

		// does table exist?  throw errors error
		if (!$this->verifyTable()) {
			throw new Exception("Failed verification of original table (" . $this->table . ")");
		}

		$this->setOldestDay();
	}
}


/*** *** *** *** ***
* @param		$orig is ORIGINAL ARCHIVE takes original Achiver obj
***/
class CopyArchiver extends Archiver {
	function __construct($orig, $connOrInfo) {
		if (get_class($orig) !== 'OrigArchiver') { 
			throw new Exception("CopyArchiver::__construct, first argument must be OrigArchiver object.");
		}

		$this->table = $orig->table . "_archiver_" . substr(str_replace('-', '', $orig->oldestDay), 0, 6);
		$this->db = $orig->db . "_archiver";
		parent::__construct($this->table, $orig->db, $orig->date_field, $connOrInfo);

		$this->assureDatabase($orig); // send $orig in case we don't have rights to create the archive version of db
//print_r($this);		
		if (!$this->verifyTable($this->table)) {
			$this->makeTable($orig);
		}

		unset($orig);
	}

/***
* @desc		create if missing	
* @todo		see if we can connect to it
***/
  private function assureDatabase() {
		$q = "SHOW DATABASES LIKE '" . $this->db . "'";

    $r = $this->safeQ($q,
      "Database (".$this->db.") existence could not be verified.");  // hard false return?

    // EXIT early (true)?
    if ($r->num_rows < 1) {
			try {
				$this->conn("CREATE DATABASE " . $this->db);
			}
			catch (Exception $e) {
				print ("Database (" . $this->db . ") could not be created. \nMYSQL-ERROR: " . $e);
			}
		
			try {
				$this->conn->query("USE " . $this->db);
			}
			catch (Exception $e) {
				print ("Database (" . $this->db . ") could not be created. \nMYSQL-ERROR: " . $e);
			}
		}
	}

/***
* @desc		grab create table statement from orig connection
					create table
					add archive_{$this->date_field} field
***/
	private function makeTable($orig) {
		$createQ = $this->getCreateStatement($orig);  
//echo $createQ."\n\n";
		$this->safeQ($createQ,
			"Table creation could not be performed in ({$this->table})."); // hard false return?

		$a = "ALTER TABLE " . $this->table . " ADD archive_" . $this->date_field . " int(18)";
		$this->safeQ($a,
			"Could not alter table (); attempt to add archive_" . $this->date_field . " int");
  }

/***
* @param    table name from which to template
* @return   mysql create statement
***/
  private function getCreateStatement($orig) {
    $q = "SHOW CREATE TABLE " . $orig->table;

    $r = $orig->safeQ($q,
      "Table create statement could not be fetched for ({$orig->table}) on original.");

    $ret = $r->fetch_object(); // ???????
    // replace with new name
    $out = str_replace('`'.$orig->table.'`', '`'.$this->table.'`', $ret->{'Create Table'});
//		$out = str_replace('AUTO_INCREMENT', '', $out);

		return $out;
  }
}
