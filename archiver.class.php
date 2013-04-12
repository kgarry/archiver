<?php

// TODO -- add check for this day !== a day in archiver, rotate db method

/***
* 
***/
class Archiver {
  function __construct($table, $templateTable=null) {
    $host   = "localhost";
    $user   = "pcm_adminuser";
    $pass   = "killallbadip$";
    $db     = "pcm_ipdb";
    
    $this->oldestCapNum = 2;
    $this->oldestCapUnit = 'MONTH';

    $this->debug = false; //true;
    $this->log = array();

    $this->conn = new mysqli($host, $user, $pass, $db);
    $this->table = $table;
    $this->verifyTable($templateTable);
    $this->computeOldestDay();
  }

/***
* @todo   consider arg for hard false return on error
***/
  private function safeQ($sql, $msg) {
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
  private function logger($msg) {
    $msgStamp = date("Y-m-d h:i:s");
    $this->log[] = "[" . $msgStamp . "]: " . $msg;
  }

/***
* @return   true, if verified
            true, if created and verified
            false, if error
***/
  private function verifyTable($templateTable) {
    $q = "
SHOW TABLES LIKE '" . $this->table . "'
    ";

    $r = $this->safeQ($q, 
      "Table existence could not be verified in ({$this->table}).");  // hard false return?

    // EXIT early (true)?
    if ($r->num_rows > 0) { 
      return true; 
    }
    
    // EXIT early (false)?
    if (empty($templateTable)) { 
      return false; 
    }
    else {
      $createQ = $this->getCreateStatement($templateTable);  

      $this->safeQ($createQ,
        "Table creation could not be performed in ({$this->table})."); // hard false return?
    }
  }

/***
* @param    table name from which to template
* @return   mysql create statement
***/
  private function getCreateStatement($templateTable) {
    $q = "SHOW CREATE TABLE " . $templateTable;

    $r = $this->safeQ($q,
      "Table create statement could not be fetched for ({$templateTable}).");

    $ret = $r->fetch_object(); // ???????
    // replace with new name
    return str_replace($templateTable, $this->table, $ret->{'Create Table'});
  }

/***
* @desc   determine the oldest day in this table, foramtted as YYYY-MM-DD
	  ignore beyond historical cap (oldestCapUnit, oldestCapNum)
***/
  private function computeOldestDay() { 
    $q = "
SELECT SUBSTR(MIN(date_created), 1, 10) as oldest 
FROM " . $this->table . " 
  WHERE date_created < DATE_SUB(CURDATE(), INTERVAL " . $this->oldestCapNum . " " . $this->oldestCapUnit . ")
    ";
//echo $q."\n\r";
    $r = $this->safeQ($q,
      "Oldest date in ({$this->table}) could not be determined.");
//print_r($r);
    if ($r->num_rows == 0) { 
      $this->oldestDay = '1977-03-01';
//echo "No rows found, so no oldest.. set to 3/11/77";
    } 
    else {
      $ret = $r->fetch_object();
      $this->oldestDay = $ret->oldest;
    }
//echo "table name: ".$this->table." oldest: ".$this->oldestDay;
  }

/***
* @param    $table (str) table to count
* @return   (int) number of rows
***/
  public function countDay($day=null) {
    if (empty($day)) { 
      $day = $this->oldestDay; 
    }
    $q = "
SELECT count(*) as qty
FROM " . $this->table . " 
WHERE date_created >= '" . $day .  "' 
  AND date_created < DATE_ADD('" . $day . "', INTERVAL 1 DAY)
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
SELECT bad_ip_num, IFNULL(referring_url, ''), IFNULL(network, ''), IFNULL(subid, ''), date_created, IFNULL(referring_domain , '') 
FROM " . $orig->table . "
WHERE date_created >= '" . $orig->oldestDay .  "' 
  AND date_created < DATE_ADD('" . $orig->oldestDay . "', INTERVAL 1 DAY)
    ";

    $i = "
INSERT INTO " . $this->table . "
  (bad_ip_num, referring_url, network, subid, date_created, referring_domain) " . 
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
WHERE date_created >= '" . $day .  "' 
  AND date_created < DATE_ADD('" . $day . "', INTERVAL 1 DAY)
  AND date_created < DATE_SUB(CURDATE(), INTERVAL " . $this->oldestCapNum . " " . $this->oldestCapUnit . ") 
    ";
//echo $d . "\n\n"; return;
  
    $this->safeQ($d,
      "Rows of oldest day in ({$this->table}) could not be deleted.");
  } 
}
