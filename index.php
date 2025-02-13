<?php

function refresh( $time ){
    $current_url = $_SERVER[ 'REQUEST_URI' ];
    return header( "Refresh: " . $time . "; URL=$current_url" );
}
refresh( 30 );

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
    $station = "KGX";
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
    $showDelayInfo = false;
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

// API Query
$apiUrl = "https://api.rtt.io/api/v1/json/search/$station"; //RTT API URL

// Request options
include("creds/creds.php"); //External password file
$opts = [
    "http" => [
        'method'  => 'GET',
        'header'  => [
            'Content-Type: application/x-www-form-urlencoded',
            'header' => "Authorization: Basic " . base64_encode("$username:$password")
            ]
        ]
    ];

//$timestamp = date(DATE_RFC2822); //Timestamp the script run

$timestamp = date("Y-m-d-Hi"); // Timestamp now
$context = stream_context_create($opts); //Get result
$json = file_get_contents($apiUrl, false, $context); //Convert to PHP JSON
$obj = json_decode($json); //Store PHP JSON as object

$json_name = $obj->location->name ?? false; //Station Name
$json_crs = $obj->location->crs ?? false; //Station Code
$json_services = $obj->services ?? false; //Services

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

        $json_trainIdentity = $s_service->trainIdentity ?? "Identity Unknown"; //Train identity (head code)
        $json_destinations = $s_service->locationDetail->destination ?? "Destination Unknown"; //All train calling destinations
        $json_destination  = $json_destinations[0]->description ?? "Description Unknown"; //First destination returned is the last the train will stop at
        $json_platform = $s_service->locationDetail->platform ?? "-"; //Platform number. Set to "-" if unknown or a single platform station
        $json_atocCode = $s_service->atocCode ?? "Operator Code Unknown"; //Operator code, i.e. LD
        $json_atocName = $s_service->atocName ?? "Operator Name Unknown"; //Operator friendly name
        $json_gbttBookedDeparture = $s_service->locationDetail->gbttBookedDeparture ?? "Booked Departure Unknown"; //When should it leave?
        $json_gbttBookedDepartureDisplay = substr_replace($json_gbttBookedDeparture, ":", 2, 0);
        $json_gbttBookedDepartureNextDay = $s_service->locationDetail->gbttBookedDepartureNextDay ?? false; //Next day departure
        $json_realtimeDeparture = $s_service->locationDetail->realtimeDeparture ?? "-"; //When is it likely to leave?
        $json_realtimeDepartureDisplay = substr_replace($json_realtimeDeparture, ":", 2, 0);
        $json_realtimeArrivalActual = $s_service->locationDetail->realtimeArrivalActual ?? false; //Has it arived?
        $json_origins = $s_service->locationDetail->origin ?? "Origin Unknown"; //All origins
        $json_origin = $json_origins[0]->description ?? "Origin unkown"; //Where is it from?

        $schedDate = date("Y-m-d"); //Get today's date
        $schedDate = "$schedDate-$json_gbttBookedDeparture"; //Add on the scheduled time to the end of the date
        $schedDateObj = date("Y-m-d Hi", strtotime($schedDate)); //Convert to date string

        $expDate = date("Y-m-d"); //Get today's date
        $expDate = "$expDate $json_realtimeDeparture"; //Add on the expected time to the end of the date
        $expDateObj = date("Y-m-d Hi", strtotime($expDate)); //Convert to date string

        $nowDateObj = date("Y-m-d Hi", strtotime($timestamp)); //Convert the now timestamp to a date string too
        
        if ($expDateObj == true) { //There is an expected time - let's work out if it's delayed ot not.
            
            $diff_SchedActual = abs(strtotime($schedDateObj) - strtotime($expDateObj)); 
            $diff_SchedActual= round($diff_SchedActual /60,2);//->format('%i');

            $diff_NowAndExpected = abs(strtotime($expDateObj) - strtotime($nowDateObj));
            $diff_NowAndExpected = round($diff_NowAndExpected /60,2);//->format('%i');

            if ($diff_SchedActual > 0) { //The difference between scheduled and expected is greater than 0 - the train is late.
                $delayed = true; //Set delayed to true
                if ($showDelayInfo == "true") { //The user has chosen to see this (as opposed to just flashing text). Add the delay onto the destination text.
                    $json_destination = "$json_destination    (Exp: $json_realtimeDepartureDisplay)";
                }
            }
            else { //The train is on time
                $delayed = false;
                if ($showDelayInfo == "true") { //The user has chosen to see this (as opposed to just green). Add that it is on time onto the destination text.
                    $json_destination = "$json_destination    (On Time)";
                }
            }

        }
        
        //if ($diff_NowAndExpected <= 0 AND $json_realtimeArrivalActual == false) { //Train is due, but isn't here yet - mark as late
        //    $diff_NowAndExpected = "Due";
        //    $delayed = true;
        //}
        if ($diff_NowAndExpected == 0 AND $json_realtimeArrivalActual == false) { //The train is within 1 minute away, so may as well say it's Due
            $diff_NowAndExpected = "Due";
        }
        elseif ($json_realtimeArrivalActual == true) { //RTT has confirmed the train is in the platform
            $diff_NowAndExpected = "At Platform";
        }
        else {
            $diff_NowAndExpected = "$diff_NowAndExpected min"; //None of the above are correct - so show how many minutes there are remaining
        }
        
        if ($json_atocCode == "LD") {
            $json_atocName = "Lumo";
        } //Lumo shows on RTT as unknown, fix this.
        
        if ($index > 0 AND $s_service->serviceType != "train") { //Next train is say a bus, don't display it - so reset the index to 0, meaning the next real train shows
            $index--;
        } 
        elseif ($pltatformSet == true AND $platform != $json_platform) { //Is the train showing on the platform we selected (if not all)? If not, reset the index to 0 and try again
            $index--;
        }
        elseif (($index == 1 AND $s_service->serviceType == "train")) { //We only care about trains, not rail replacement buses or ferries. Get the next train.
            $servicesShown++;

?>

        <script> //Switch the train identity and headcode
            var texts = new Array();
            texts.push("<?php echo $json_trainIdentity; ?>");
            texts.push("<?php echo $json_gbttBookedDepartureDisplay; ?>");
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
                    <td id='nextTrainTime'><?php echo $json_gbttBookedDepartureDisplay; ?></td>
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
                            <div class="scrolling-text-inner" style="--marquee-speed: 30s; --direction:scroll-left" role="marquee">
                                <div class="scrolling-text">
                                    <div class="scrolling-text-item">This is the <?php echo $json_atocName;?> service from <?php echo $json_origin;?>.</div>
                                    <div class="scrolling-text-item">Calling at xxxxxx (xx:xx), xxxxxx (xx:xx) and xxxxx (xx:xx).</div>
                                    <div class="scrolling-text-item">Some class of seating is available.</div>
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
                $nextTrainTextStrings[] = "<table class='nextTrainsTable'><tr><td>$nextTrainNumbers[$index]&#9;$json_gbttBookedDepartureDisplay&#9;P$json_platform&#9;$json_destination</td><td id='nextTrainsTableDueLate'>$diff_NowAndExpected</td></tr></table>";
                $nextTrainStringDelayed = true;
            }
            else {
                $nextTrainTextStrings[] = "<table class='nextTrainsTable'><tr><td>$nextTrainNumbers[$index]&#9;$json_gbttBookedDepartureDisplay&#9;P$json_platform&#9;$json_destination</td><td id='nextTrainsTableDueOnTime'>$diff_NowAndExpected</td></tr></table>";
                $nextTrainStringDelayed = false;

            }

        }
        else {
            $services = false; //Services was previously true, however none of them are trains, so reset to false
            }
        
        }
        
    } //End Foreach service

    if ($json_services == false || $servicesShown < 1) { //There are no services, or there are only non-train services.
        $expDate = false;
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

                        if ($json_name AND ($json_services == true || $servicesShown > 0)) { //If the station is valid

                            $wordlist[] = "\"<table class='nextTrainsTableStaticText'><tr><td>$json_name</td></tr></table>\",";
                            $wordlist[] = "\"<table class='nextTrainsTableStaticText'><tr><td>$dateDisplay</td></tr></table>\",";

                            $changeTrainTextStringIndex = 0;

                            foreach ($nextTrainTextStrings as $nextTrainTextString) {
                                    $changeTrainTextStringIndex++;
                                    if ($changeTrainTextStringIndex < count($nextTrainTextStrings)) {
                                        $wordlist[] = "\"$nextTrainTextString\",";
                                    }
                                    else {
                                        $wordlist[] =  "\"$nextTrainTextString\"";
                                    }
                                    
                            }
                        }
                        else {

                            $wordlist[] = "\"<table class='nextTrainsTableStaticText'><tr><td>Invalid Station Code: '$station'</td></tr></table>\",";
                            $wordlist[] = "\"<table class='nextTrainsTableStaticText'><tr><td>$dateDisplay</td></tr></table>\",";

                        }

?>

                    <script> 
                    
                        const words = [<?php foreach ($wordlist as $word) {
                            echo $word;
                        }?>];
                            
                        let changeTrainTextStringindex = 0; 

                        function changeTrainTextString() { 
                            changeTrainTextStringindex = (changeTrainTextStringindex + 1) % words.length; // Cycle through the words 
                            document.getElementById("wordContainer").innerHTML = words[changeTrainTextStringindex]; 
                        } 

                        setInterval(changeTrainTextString, 3000); // Change word every 2 seconds 
                        
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
        PlatformDisplay.UK <?php echo date("Y"); ?> | PaulJRowland | <a href='https://github.com/pauljrowland/PlatformDisplay-UK' target='_blank'>Github</a> | <a href='https://www.realtimetrains.co.uk' target='_blank'>Realtime Trains</a> <a href='https://api.rtt.io' target='_blank'>API</a>
    </p>
</div>

</body>
</html>
