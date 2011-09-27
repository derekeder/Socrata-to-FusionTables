<?php
  //see README for instructions
  error_reporting(E_ALL);
  ini_set("memory_limit","200M"); //datasets we are dealing with can be quite large, need enough space in memory
  set_time_limit(0);
  date_default_timezone_set('America/Chicago');
  
  //pulling from Socrata with https://github.com/socrata/socrata-php
  require("source/socrata.php");
  
  //inserting in to Fusion Tables with http://code.google.com/p/fusion-tables-client-php/
  require('source/clientlogin.php');
  require('source/sql.php');
  require('source/file.php'); //not being used, but could be useful to someone automating CSV import in to FT
  
  //my custom libraries
  require('source/connectioninfo.php');
  
  header('Content-type: text/plain');

  //keep track of script execution time
  $bgtime=time();

  $view_uid = ConnectionInfo::$view_uid;
  $data_site = ConnectionInfo::$data_site;
  $app_token = ConnectionInfo::$app_tokenn;
  $fusionTableId = ConnectionInfo::$fusionTableId;
  
  echo "Socrata -> Fusion Tables import by Derek Eder\n\n";
  echo "app token: $app_token \n";
  
  //Fetch data from Socrata
  $response = NULL;
  if($view_uid != NULL && $data_site != NULL) {
    // Create a new unauthenticated client
    $socrata = new Socrata("http://$data_site/api", $app_token);

    $params = array();
    //$params["max_rows"] = 1; //max number of rows to fetch

    // Request rows from Socrata
    $response = $socrata->get("/views/$view_uid/rows.json", $params);
    
    echo "----Fetching data from Socrata----\n";
    echo "Dataset name: " . $response["meta"]["view"]["name"] . "\n";
    
    echo "\n----Columns----\n";
    $colCount = 0;
    foreach($response["meta"]["view"]["columns"] as $column) {
      echo $colCount . ": " . $column["name"] . "\n";
      $colCount++;
    }
    
    //Fetch info from Fusion Tables and do inserts & data manipulation
    echo "\n----Inserting in to Fusion Tables----\n";
    //get token
	$token = ClientLogin::getAuthToken(ConnectionInfo::$google_username, ConnectionInfo::$google_password);
	$ftclient = new FTClientLogin($token);
	
	//for clearing out table
	//$ftclient->query("DELETE FROM $fusionTableId");
	
	//check how many are in Fusion Tables already
	$ftResponse = $ftclient->query("SELECT Count() FROM $fusionTableId");
	echo "$ftResponse \n";
	
	//this part is very custom to this particular dataset. If you are using this, here's where the bulk of your work would be: data mapping!
	$ftResponse = $ftclient->query(SQLBuilder::select($fusionTableId, "'DATE RECEIVED'", "", "'DATE RECEIVED' DESC", "1"));
	$ftResponse = trim(str_replace("DATE RECEIVED", "", $ftResponse)); //totally a hack. there's a better way to do this
	
	//big assumption: socrata will return the data ordered by date. this may not always be the case
	if ($ftResponse != "")
		$latestInsert = new DateTime(str_replace("DATE RECEIVED", "", $ftResponse));   
	else
		$latestInsert = new DateTime("1/1/2001"); //if there are no rows, set it to an early date so we import everything
	  
	echo "\nLatest FT insert: " . $latestInsert->format('m/d/Y') . "\n";

	$insertCount = 0;
    foreach($response["data"] as $row) {
    	if ($row[9] != "SERVICE REQUEST #") { //first row in this dataset is a duplicate of the column names
    		
    		//convert received date in to DateTime format
    		$receivedDate = new DateTime($row[10]);
    		
    		//creating full address column for geocoding
    		$fullAddress = $row[18] . " " . $row[19] . " " . $row[20] . " " . $row[21] . " chicago IL " . $row[22];
    		
    		//todo add flag columns and do conversion using SQLBuilder::convertToFlag()
    		
    		if ($receivedDate > $latestInsert) {
		    	$insertArray = array(
		    	"SERVICE REQUEST #" => $row[9],
		    	"DATE RECEIVED" => $receivedDate->format('m/d/Y'),
		    	"LOT LOCATION" => $row[11],
		    	"DANGEROUS OR HAZARDOUS?" => $row[12], //this column appears to be empty
		    	"Dangerous flag" => SQLBuilder::convertToFlag($row[12], "dangerous"),
		    	"OPEN OR BOARDED?" => $row[13],
		    	"Open flag" => SQLBuilder::convertToFlag($row[13], "open"),
		    	"ENTRY POINT" => $row[14],
		    	"VACANT OR OCCUPIED?" => $row[15],
		    	"Vacant flag" => SQLBuilder::convertToFlag($row[15], "vacant"),
		    	"VACANT DUE TO FIRE?" => $row[16],
		    	"Fire flag" => SQLBuilder::setEmptyToZero($row[16]), //stored as an int in Socrata
		    	"ANY PEOPLE USING PROPERTY?" => $row[17],
		    	"In use flag" => SQLBuilder::setEmptyToZero($row[17]), //stored as an int in Socrata
		    	"ADDRESS STREET NUMBER" => $row[18],
		    	"ADDRESS STREET DIRECTION" => $row[19],
		    	"ADDRESS STREET NAME" => $row[20],
		    	"ADDRESS STREET SUFFIX" => $row[21],
		    	"ZIP CODE" => $row[22],
		    	"Full Address" => $fullAddress,
		    	"X COORDINATE" => $row[23],
		    	"Y COORDINATE" => $row[24],
		    	"LATITUDE" => $row[25],
		    	"LONGITUDE" => $row[26],
		    	"Location" => "(" . implode(",", $row[27]) . ")"
		    	);
		    
		    	$ftclient->query(SQLBuilder::insert($fusionTableId, $insertArray));
		    	$insertCount++;
		    	echo "inserted $insertCount so far\n";
		    }
    	}
    }
  }
  echo "\ninserted $insertCount rows\n";
  echo "This script ran in " . (time()-$bgtime) . " seconds\n";
  echo "\nDone.\n";
?>