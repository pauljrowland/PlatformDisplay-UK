<?php

function refresh( $time ){
    $current_url = $_SERVER[ 'REQUEST_URI' ];
    return header( "Refresh: " . $time . "; URL=$current_url" );
}
refresh( 45 );

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>PlatformDisplay.UK</title>
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="/assets/favicon/site.webmanifest">
</head>
<body>

<?php

if(!isset($_GET['station'] ) || $_GET['station'] == null || $_GET['station'] == '') {
    $station = "NCL";
}
else {
    $station = $_GET['station'];
}

if(!isset($_GET['platform'] ) || $_GET['platform'] == null || $_GET['platform'] == 'all') {
    $platform = "all";
    $pltatformSet = false;
}
else {
    $platform = $_GET['platform'];
    $pltatformSet = true;
}

if((!isset($_GET['delayinfo'] )) || ($_GET['delayinfo'] == null || $_GET['delayinfo'] == '' || $_GET['delayinfo'] == 'false')) {
    $showDelayInfo = false;
}
elseif ($_GET['delayinfo'] == "true" ) {
    $showDelayInfo = $_GET['delayinfo'];
}
else {
    $showDelayInfo = true;
}

?>

<a href="?station=ncl<?php if ($showDelayInfo == true) { echo "&delayinfo=true";} ?>">NCL</a> - <a href="?station=kgx<?php if ($showDelayInfo == true) { echo "&delayinfo=true";} ?>">KGX</a> - <a href="?station=asl<?php if ($showDelayInfo == true) { echo "&delayinfo=true";} ?>">ASL</a> - <a href="?station=nwh<?php if ($showDelayInfo == true) { echo "&delayinfo=true";} ?>">NWH</a> - <a href="?station=car<?php if ($showDelayInfo == true) { echo "&delayinfo=true";} ?>">CAR</a> - <a href="?station=sun<?php if ($showDelayInfo == true) { echo "&delayinfo=true";} ?>">SUN</a> - <a href="?station=nlw<?php if ($showDelayInfo == true) { echo "&delayinfo=true";} ?>">NLW</a> - <a href="?station=zzz<?php if ($showDelayInfo == true) { echo "&delayinfo=true";} ?>">ZZZ</a><br><br>
<form action=".">
    Manual Station Code: <input name="station" id="station" type="text" required value="<?php echo $station;?>"><br>
    Platform Number (leave blank for all): <input name="platform" id="platform" type="text" value="<?php echo $platform;?>"><br>
    Show expected time on destination label: <input name="delayinfo" id="delayinfo" type="checkbox" value="true" <?php if ($showDelayInfo == true) { echo "checked";} ?>>
    <input type="Submit">
</form>

<br>

<?php

$station = strtoupper($station);

// API Query
$apiUrl = "https://api1.raildata.org.uk/1010-live-departure-board-dep1_2/LDBWS/api/20220120/GetDepBoardWithDetails/$station";

include("creds/creds.php"); //External password file
$opts = [
    "http" => [
        'method'  => 'GET',
        'header' => 'x-apikey: '.$apiKey
        ]
    ];

$context = stream_context_create($opts); //Get result
$json = file_get_contents($apiUrl, false, $context); //Convert to PHP JSON
$obj = json_decode($json); //Store PHP JSON as object

$json_name = $obj->locationName ?? false; // Station Name
$json_crs = $obj->crs ?? false; //Station Code
$json_services = $obj->trainServices ?? false; //Services

?>

<div id='displayBoardWrapper'>

    <div class='topOfScreenText'>
        <?php
            if ($json_name) { //If the station is valid
                echo $json_name;
            }
            else {
                echo "Invalid Station Code: '$station'";
            }
        ?> - PlatformDisplay UK
    </div>

    <div id='displayBoardInnerWrapper'>

<?php

$servicesShown = 0; //None shown yet

if ($json_services == true) { //There are services of some sort
    
    $index = 0; //Set train index to 0 so first train sets to 1
    
    foreach ($json_services as $s_service) { //Foreach service, get the information
        
        $index++; //Increment by one so this reflects how many services we've looped through

        $json_serviceID = $s_service->serviceID ?? "Identity Unknown"; //Train identity (head code)
        $json_destination = $s_service->destination[0]->locationName ?? "Destination Unknown"; //All train calling destinations
        $json_isCancelled = $s_service->isCancelled ?? "false";
        $json_platform = $s_service->platform ?? "-"; //Platform number. Set to "-" if unknown or a single platform station
        $json_operatorCode = $s_service->operatorCode ?? "Operator Code Unknown"; //Operator code, i.e. LD
        $json_operator = $s_service->operator ?? "Operator Name Unknown"; //Operator friendly name
        $json_std = $s_service->std ?? "Booked Departure Unknown"; //When should it leave?
        $json_etd = $s_service->etd ?? "-"; //When is it likely to leave?
        if ($json_etd == "On time") {$json_etd = $s_service->std; }
        $json_length = $s_service->length ?? "Unknown length"; //Operator friendly name
        $json_origin = $s_service->origin[0]->locationName  ?? "Origin unkown"; //Where is it from?
        $json_callingPoints = $s_service->subsequentCallingPoints[0]->callingPoint ?? "Where is is going?";

                    $json_gbttBookedDepartureNextDay = $s_service->locationDetail->gbttBookedDepartureNextDay ?? false; //Next day departure
                    //$json_realtimeArrivalActual = $s_service->locationDetail->std ?? false; //Has it arived?

        $stdDate = date("Y-m-d"); //Get today's date
        $stdDate = "$stdDate-$json_std"; //Add on the scheduled time to the end of the date
        $stdDateObj = date("Y-m-d H:i", strtotime($stdDate)); //Convert to date string
        $etdDate = date("Y-m-d"); //Get today's date
        $etdDate = "$etdDate $json_etd"; //Add on the expected time to the end of the date
        $etdDateObj = date("Y-m-d H:i", strtotime($etdDate)); //Convert to date string
        $nowDate = date("Y-m-d-Hi"); // Timestamp now
        $nowDateObj = date("Y-m-d H:i", strtotime($nowDate)); //Convert the now timestamp to a date string too

        if ($etdDateObj == true) { //There is an expected time - let's work out if it's delayed ot not.
            
            $diff_SchedActual = abs(strtotime($stdDateObj) - strtotime($etdDateObj)); 
            $diff_SchedActual= round($diff_SchedActual /60,2);//->format('%i');

            $diff_NowAndExpected = abs(strtotime($etdDateObj) - strtotime($nowDateObj));
            $diff_NowAndExpected = round($diff_NowAndExpected /60,2);//->format('%i');

            if ($diff_SchedActual > 0) { //The difference between scheduled and expected is greater than 0 - the train is late.
                $delayed = true; //Set delayed to true
                if ($showDelayInfo == "true") { //The user has chosen to see this (as opposed to just flashing text). Add the delay onto the destination text.
                    if ($json_etd == "Delayed") {
                        $json_destination = "$json_destination    (Delayed)";
                    }
                    else
                    {
                        $json_destination = "$json_destination    (Exp: $json_etd)";
                    }
                }
            }
            else { //The train is on time
                $delayed = false;
                if ($showDelayInfo == "true") { //The user has chosen to see this (as opposed to just green). Add that it is on time onto the destination text.
                    $json_destination = "$json_destination    (On Time)";
                }
            }

        }
        
        //if ($diff_NowAndExpected == 0 AND $json_realtimeArrivalActual == false) { //The train is likely to be approaching 
        if ($diff_NowAndExpected == 0) { //The train is likely to be approaching 
            $diff_NowAndExpected = "Due";
        }
        //elseif ($diff_NowAndExpected == 1 AND $json_realtimeArrivalActual == false) { //The train is within 1 minute away, so may as well say it's Due
        elseif ($diff_NowAndExpected == 1) { //The train is within 1 minute away, so may as well say it's Due
            $diff_NowAndExpected = "Approaching";
        }
        //elseif ($json_realtimeArrivalActual == true) { //RTT has confirmed the train is in the platform
        elseif ($diff_NowAndExpected == 0 && $json_etd == $json_std) { //RTT has confirmed the train is in the platform

            $diff_NowAndExpected = "At Platform";
        }
        elseif ($json_etd == "Delayed") { // There is a delay - but unknown how long
            $diff_NowAndExpected = "Delayed - No ETA";
        }
        else {
            $diff_NowAndExpected = "$diff_NowAndExpected min"; //None of the above are correct - so show how many minutes there are remaining
        }
        
        //if ($json_operatorCode == "LD") {
        //    $json_operator = "Lumo";
        //} //Lumo shows on RTT as unknown, fix this.
        
        if ($index > 0 AND $s_service->serviceType != "train") { //Next train is say a bus, don't display it - so reset the index to 0, meaning the next real train shows
            $index--;
        } 
        elseif ($pltatformSet == true AND $platform != $json_platform) { //Is the train showing on the platform we selected (if not all)? If not, reset the index to 0 and try again
            $index--;
        }
        elseif ($json_isCancelled == true){
            $index--;
        }
        elseif (($index == 1 AND $s_service->serviceType == "train")) { //We only care about trains, not rail replacement buses or ferries. Get the next train.
            $servicesShown++;

//            // Service API Query
//            $serviceApiUrl = "https://api1.raildata.org.uk/1010-service-details1_2/LDBWS/api/20220120/GetServiceDetails/$json_serviceID";
//
//            include("creds/creds.php"); //External password file
//            $serciceOpts = [
//                "http" => [
//                    'method'  => 'GET',
//                    'header' => 'x-apikey: '.$serviceAPIKey
//                    ]
//                ];
//
//            $serviceContext = stream_context_create($serviceOpts); //Get result
//            $serviceJson = file_get_contents($serviceApiUrl, false, $serviceContext); //Convert to PHP JSON
//            $serviceObj = json_decode($json); //Store PHP JSON as object  
//
//            $serviceJson_

?>

        <script> //Switch the train identity and headcode
            var texts = new Array();
            /*texts.push("<?php echo $json_serviceID; ?>");*/
            texts.push("<?php echo $json_std; ?>");
            point = 0;
            function changeText(){
                if(point < ( texts.length - 1 ) ){
                    point++;
                }else{
                    point = 0;
                }
                document.getElementById('nextTrainTime').innerHTML = texts[point];
            }
            setInterval(changeText, 5000); /*Call it here*/
            changeText();
        </script>
        
        <div class='board-top'>
            <table>
                <tr>
                    <td id='nextTrainTime'><?php echo $json_std; ?></td>
                    <td id='nextTrainPlatform'>P<?php echo $json_platform;?></td>
                    <td id='nextTrainDest'><?php echo $json_destination;?></td>
                    <td id='nextTrainExpected'<?php if ($delayed == true) {echo " style='color:red;' class='blink_me'>";} else {echo ">";}; echo $diff_NowAndExpected;?></td>
                </tr>
            </table>
        </div>
        <div class='board-middle'>
            <table>
                <tr id='board-middle-table-row'>
                    <td id='callingAtLabel'>Info:</td>
                    <td id='callingAtText'>
                        <div class="scrolling-text-container">
                            <div class="scrolling-text-inner" style="--marquee-speed: 45s; --direction:scroll-left" role="marquee">
                                <div class="scrolling-text">
                                    <div class="scrolling-text-item">This is the <?php echo $json_operator;?> service from <?php echo $json_origin;?>.</div>
                                    <div class="scrolling-text-item">
                                        <?php

                                            $callingPointIndex = 1;
                                            $callingAtListStr = "Calling at ";
                                            foreach ($json_callingPoints as $json_calling_point) {

                                                $callingLocationName = $json_calling_point->locationName;
                                                if ($json_calling_point->et == "On time") {
                                                    $callingLocationTime = $json_calling_point->st;
                                                }
                                                else{
                                                    $callingLocationTime = $json_calling_point->et;
                                                }

                                                if (count($json_callingPoints) == 1) {

                                                    $callingAtListStr .= "$callingLocationName ($callingLocationTime) only.";
                                                }
                                                elseif ($callingPointIndex < count($json_callingPoints)) {
                                                    if ($callingPointIndex == 1) {
                                                        $callingAtListStr .= "$callingLocationName ($callingLocationTime)";
                                                        $callingPointIndex++;
                                                    }
                                                    else {
                                                        $callingAtListStr .= ", $callingLocationName ($callingLocationTime)";
                                                        $callingPointIndex++;
                                                    }
                                                }
                                                else {
                                                    $callingAtListStr .= " and $callingLocationName ($callingLocationTime).";
                                                }
                                            }

                                            echo $callingAtListStr;

                                        ?>
                                    </div>
                                    <?php if ($json_length > 0) { echo "<div class='scrolling-text-item'>This train is made up of $json_length carriages.</div>"; }?>
                                    <!--<div class="scrolling-text-item">Some class of seating is available.</div>-->
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

 <?php
                
        }
        elseif  ($s_service->serviceType == "train" && $index <=5 ) { //We still only care about the next 5 trains, not rail replacement buses or ferries. Get the rest of the trains.
            $servicesShown++;
            $nextTrainNumbers = array(null, null, "2nd","3rd","4th","5th","6th","7th","8th","9th","10th"); //Display numbers for next trains (0 and 1 are the first 2 indexes, so null them)
            
            if ($delayed == true) {
                $nextTrainTextStrings[] = "<table class='nextTrainsTable'><tr><td>$nextTrainNumbers[$index]&#9;$json_std&#9;P$json_platform&#9;$json_destination</td><td id='nextTrainsTableDueLate'>$diff_NowAndExpected</td></tr></table>";
                $nextTrainStringDelayed = true;
            }
            else {
                $nextTrainTextStrings[] = "<table class='nextTrainsTable'><tr><td>$nextTrainNumbers[$index]&#9;$json_std&#9;P$json_platform&#9;$json_destination</td><td id='nextTrainsTableDueOnTime'>$diff_NowAndExpected</td></tr></table>";
                $nextTrainStringDelayed = false;

            }

        }
        else {
            $services = false; //Services was previously true, however none of them are trains, so reset to false
            }
        
        }
        
    } //End Foreach service

    if ($json_services == false || $servicesShown < 1) { //There are no services, or there are only non-train services.
        $etdDate = false;
        $nowDate = false;
        $diff_NowAndExpected = false;
        $diff_SchedActual= false;
?>

        <div class='board-top'>
            <table>
                <tr>
                    <td id="noNextTrainTitle"><?php echo $json_name;?></td>
                </tr>
            </table>
        </div>
        
        <div class='board-middle'>
            <table>
                <tr id='board-middle-table-row'>
                    <td id='callingAtText'>
                        <div class="scrolling-text-container">
                            <div class="scrolling-text-inner" style="--marquee-speed: 20s; --direction:scroll-left" role="marquee">
                                <div class="scrolling-text">
                                    <div class="scrolling-text-item" style="width:1000px;"></div> <!--Start the scroll with blank space -->
                                    <div class="scrolling-text-item">
<?php

    if ($json_crs != false) {
        if ($pltatformSet == true) {
            echo "There are currenly no services stopping at this station on platform $platform.";
        }
        else {
            echo "There are currenly no services stopping at this station.";
        }
    }
    else {
        echo "ERROR: Invalid Station Code: '$station'";
    }
?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
                       

<?php

} // End if services

?>

        <div class='board-bottom'>
            <table>
                <tr>
                    <td id="boardBottomText">
                        <!--<span id="bottomRow"></span>-->

                        <div id="wordContainer">
                            <?php
                                if ($json_name) { //If the station is valid
                                    echo "<table class='nextTrainsTableStaticText'><tr><td>$json_name</td></tr></table>";
                                }
                                else {
                                    echo "<table class='nextTrainsTableStaticText'><tr><td>Invalid Station Code: '$station'</td></tr></table>";
                                }
                            ?>
                        </div> 

<?php

                        $dateDisplay = date("l jS F Y");

                        if ($json_isCancelled == true) {
                            $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>$json_name</td></tr></table>\",";
                            $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>Cancelled</td></tr></table>\",";
                        }
                        elseif ($json_name AND ($json_services == true || $servicesShown > 0)) { //If the station is valid

                            $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>$json_name</td></tr></table>\",";
                            $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>$dateDisplay</td></tr></table>\",";

                            $changeTrainTextStringIndex = 0;

                            foreach ($nextTrainTextStrings as $nextTrainTextString) {
                              //      $changeTrainTextStringIndex++;
                              //      if ($changeTrainTextStringIndex < count($nextTrainTextStrings)) {
                                        $btmScrItems[] = "\"$nextTrainTextString\",";
                              //      }
                               //     else {
                               //         $btmScrItems[] =  "\"$nextTrainTextString\"";
                                //    }
                                    
                            }
                        }
                        else {

                            $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>Invalid Station Code: '$station'</td></tr></table>\",";
                            $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>$dateDisplay</td></tr></table>\",";

                        }
                      
                        $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>Please ensure that you do not leave personal</td></tr></table>\",";
                        $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>belongings unattended.</td></tr></table>\",";
                        $btmScrItems[] = "\"<table class='nextTrainsTableStaticText'><tr><td>&nbsp;</td></tr></table>\",";
?>

                    <script> 
                    
                        const words = [<?php foreach ($btmScrItems as $btmScrItem) {
                            echo $btmScrItem;
                        }?>];
                            
                        let changeTrainTextStringindex = 0; 

                        function changeTrainTextString() { 
                            changeTrainTextStringindex = (changeTrainTextStringindex + 1) % words.length; // Cycle through the words 
                            document.getElementById("wordContainer").innerHTML = words[changeTrainTextStringindex]; 
                        } 

                        setInterval(changeTrainTextString, 2000); // Change word every 2 seconds 
                        
                    </script>


                    </td>
                </tr>
            </table>
        </div>
        <div id="clock">
        <script src="/assets/clock.js"></script>
    </div>
    </div> <!-- End displayBoardInnerWrapper DIV -->
</div> <!-- End displayBoardWrapper DIV -->

<div id="footer">
    <p>
        PlatformDisplay.UK <?php echo date("Y"); ?> | PaulJRowland | <a href='https://github.com/pauljrowland/PlatformDisplay-UK' target='_blank'>Github</a>
    </p>
</div>

</body>
</html>
