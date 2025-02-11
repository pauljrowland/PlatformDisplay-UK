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

?>

<a href="?station=ncl">NCL</a> - <a href="?station=kgx">KGX</a> - <a href="?station=asl">ASL</a> - <a href="?station=nwh">NWH</a> - <a href="?station=car">CAR</a> - <a href="?station=sun">SUN</a> - <a href="?station=nlw">NLW</a> - <a href="?station=zzz">ZZZ</a><br><br>
<form action=".">
    Manual Station Code: <input name="station" id="station" type="text" required value="<?php echo $station;?>"><br>
    Platform Number (leave blank for all): <input name="platform" id="platform" type="text" value="<?php echo $platform;?>">
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

$timestamp = date(DATE_RFC2822); //Timestamp the script run
$context = stream_context_create($opts); //Get result
$json = file_get_contents($apiUrl, false, $context); //Convert to PHP JSON
$obj = json_decode($json); //Store PHP JSON as object

$s_name = $obj->location->name ?? false; //Station Name
$s_code = $obj->location->crs ?? false; //Station Code
$s_services = $obj->services ?? false; //Services

?>

<div id='displayBoardWrapper'>
    <div id='displayBoardInnerWrapper'>

<?php

$servicesShown = 0; //None shown yet

if ($s_services == true) { //There are services of some sort
    
    $index = 0; //Set train index to 0 so first train sets to 1
    
    foreach ($s_services as $s_service) { //Foreach service, get the information
        
        $index++;
        $trainIdentity = $s_service->trainIdentity ?? "Identity Unknown"; //Train identity (head code)
        $destinations = $s_service->locationDetail->destination ?? "Destination Unknown"; //All train calling destinations
        $destination  = $destinations[0]->description ?? "Description Unknown"; //First destination returned is the last the train will stop at
        $pltfm = $s_service->locationDetail->platform ?? "-"; //Platform number. Set to "-" if unknown or a single platform station
        $operatorCode = $s_service->atocCode ?? "Operator Code Unknown"; //Operator code, i.e. LD
        $operator = $s_service->atocName ?? "Operator Name Unknown"; //Operator friendly name
        $sched = $s_service->locationDetail->gbttBookedDeparture ?? "Booked Departure Unknown"; //When should it leave?
        $schedDisplay = substr_replace($sched, ":", 2, 0);
        $expect = $s_service->locationDetail->realtimeDeparture ?? "-"; //When is it likely to leave?
        $arrived = $s_service->locationDetail->realtimeArrivalActual ?? false; //Has it arived?
        $origins = $s_service->locationDetail->origin ?? "Origin Unknown"; //All origins
        $origin = $origins[0]->description ?? "Origin unkown"; //Where is it from?

        $schedDate = date("Y-m-d"); //Get today's date
        $schedDate = "$schedDate-$sched"; //Add on the scheduled time
        $schedDateObj = DateTime::createFromFormat('Y-m-d-Hi', $schedDate); //Convert to object

        $expDate = date("Y-m-d"); //Get today's date
        $expDate = "$expDate-$expect"; //Add on the expected time
        $expDateObj = DateTime::createFromFormat('Y-m-d-Hi', $expDate); //Convert to object

        $nowDate = date("Y-m-d-Hi"); //Get date and time now;
        $nowDateObj = DateTime::createFromFormat('Y-m-d-Hi', $nowDate); //Convert to object
        
        if ($expDateObj == true) { //There is an expected time - let's work out if it's delayed ot not.
            $deviation = date_diff($schedDateObj,$expDateObj); //Work out the difference
            $delay = $deviation->format('%i');
            $interval = date_diff($expDateObj,$nowDateObj); //Work out the difference
            $due = $interval->format('%i');
            if ($delay > 0) {
                $delayed = true;
            }
            else {
                $delayed = false;
            }
        }
        
        if ($due == 0) {
            $due = "Due";
        }
        elseif ($arrived == true) {
            $due = "At Platform";
        }
        else {
            $due = "$due min";
        }
        
        if ($operatorCode == "LD") {
            $operator = "Lumo";
        } //Lumo shows on RTT as unknown, fix this.
        
        if ($index > 0 AND $s_service->serviceType != "train") { //Next train is say a bus, don't display it - so reset the index to 0, meaning the next real train shows
            $index--;
        } 
        elseif ($pltatformSet == true AND $platform != $pltfm) { //Is the train showing on the platform we selected (if not all)? If not, reset the index to 0 and try again
            $index--;
        }
        elseif (($index == 1 AND $s_service->serviceType == "train")) { //We only care about trains, not rail replacement buses or ferries. Get the next train.
            $servicesShown++;

?>

        <script> //Switch the train identity and headcode
            var texts = new Array();
            texts.push("<?php echo $trainIdentity; ?>");
            texts.push("<?php echo $schedDisplay; ?>");
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
                    <td id='nextTrainTime'><?php echo $schedDisplay; ?></td>
                    <td id='nextTrainPlatform'>P<?php echo $pltfm;?></td>
                    <td id='nextTrainDest'><?php echo $destination;?></td>
                    <td id='nextTrainExpected'<?php if ($delayed == true) {echo " style='color:red;' class='blink_me'>";} else {echo ">";}; echo $due;?></td>
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
                                    <div class="scrolling-text-item">This is the <?php echo $operator;?> service from <?php echo $origin;?>.</div>
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
                $nextTrainStringText = "<table class=\"nextTrainsTable\"><tr><td>$nextTrainNumbers[$index]&#9;$schedDisplay&#9;P$pltfm&#9;$destination</td><td id=\"nextTrainsTableDueLate\">$due</td></tr></table>";
            }
            else {
                $nextTrainStringText = "<table class=\"nextTrainsTable\"><tr><td>$nextTrainNumbers[$index]&#9;$schedDisplay&#9;P$pltfm&#9;$destination</td><td id=\"nextTrainsTableDueOnTime\">$due</td></tr></table>";
            }

            if ($nextTrainString = false) {
                $nextTrainStrings = array($nextTrainStringText);
            }       
            else {
                $nextTrainStrings[] = $nextTrainStringText;
            }
        }
        else {
            $services = false; //Services was previously true, however none of them are trains, so reset to false
            }
        
        }
        
    } //End Foreach service
    
    if ($s_services == false || $servicesShown < 1) { //There are no services, or there are only non-train services.
        $expDate = false;
        $nowDate = false;
        $due = false;
        $delay = false;
?>

        <div class='board-top'>
            <table>
                <tr>
                    <td id="noNextTrainTitle"><?php echo $s_name;?></td>
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

    if ($s_code != false) {
        if ($pltatformSet == true) {
            echo "There are currenly no services stopping at this station on platform $platform.";
        }
        else {
            echo "There are currenly no services stopping at this station.";
        }
    }
    else {
        echo "ERROR: Invalid Station Code '$station'";
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

<script>
    function sleep(miliseconds) {
        var currentTime = new Date().getTime();while (currentTime + miliseconds >= new Date().getTime()) {
        }
    }
    timeout = 1000; //1000 Is about 5 seconds
    message1 = 0;
    message2 = 0;
    message3 = 0;
    message4 = 0;
    message5 = 0;
    message6 = 0;
    message7 = 0;
    message8 = 0;
    let a;
    let time;
    var timeDisplay = setInterval(() => {
        if (++message1 < timeout){
            display = "<?php echo $s_name; ?>";
            document.getElementById('boardBottomText').style.textAlign = 'center'; 
            document.getElementById('bottomRow').innerHTML = display;
        }
        else if (++message2 < timeout) {
            a = new Date();
            day = a.getDate();
            dayName = new Date(a).toLocaleString('en-us', {weekday:'long'})
            if (day == 1 || day == 21 || day == 31 ) {suffix = "st";}
            else if (day == 2 || day == 22) {suffix = "nd";}
            else if (day == 3 || day == 23) {suffix = "rd";}
            else {suffix = "th";}
            monthName = new Date(a).toLocaleString('en-us', {month:'long'})
            year = a.getFullYear()
            display = dayName + ' ' + day + suffix + ' ' + monthName + ' ' + year;
            document.getElementById('boardBottomText').style.textAlign = 'center'; 
            document.getElementById('bottomRow').innerHTML = display;
        }
        else if (++message3 < timeout) {
            a = new Date();
            var time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second: '2-digit'});
            document.getElementById('boardBottomText').style.textAlign = 'center'; 
            document.getElementById('bottomRow').innerHTML = time;
            }

<?php

$additionalTrains = 0;
foreach ($nextTrainStrings as $nextTrainString) {
    $additionalTrains++;
    $message = $additionalTrains + 3;
    echo "else if (++message$message < timeout) {
        document.getElementById('boardBottomText').style.textAlign = 'left';
        document.getElementById('bottomRow').innerHTML = '$nextTrainString';
    }";
}
?>

        else {
            message1 = 0;
            message2 = 0;
            message3 = 0;
            message4 = 0;
            message5 = 0;
            message6 = 0;
            message7 = 0;
            message8 = 0;
        }
    }, 1);
</script>
        
        <div class='board-bottom'>
            <table>
                <tr>
                    <td id="boardBottomText">
                        <span id="bottomRow"></span>
                    </td>
                </tr>
            </table>
        </div>
    </div> <!-- End displayBoardInnerWrapper DIV -->
    
    <div class='bottomOfScreenText'>
        <?php echo $s_name; ?> - PlatformDisplay UK
    </div>

</div> <!-- End displayBoardWrapper DIV -->

<div id="footer">
    <p>PlatformDisplay.UK <?php echo date("Y"); ?> | PaulJRowland | <a href='https://github.com/pauljrowland/PlatformDisplay-UK' target='_blank'>Github</a></p>
    <p>With thanks to the <a href='https://www.realtimetrains.co.uk' target='_blank'>Realtime Trains</a> <a href='https://api.rtt.io' target='_blank'>API</a>
</div>

</body>
</html>
