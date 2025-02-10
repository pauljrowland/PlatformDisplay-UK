<?php
    function refresh( $time ){
        $current_url = $_SERVER[ 'REQUEST_URI' ];
        return header( "Refresh: " . $time . "; URL=$current_url" );
    }

    // call the function in the appropriate place
    refresh( 60 ); 
    
?>
<html>
<head>
    <title>Paul's Train Board</title>
    <link rel="stylesheet" href="/assets/style.css"/>
</head>
<body>



<a href="?station=ncl">NCL</a> - <a href="?station=kgx">KGX</a> - <a href="?station=asl">ASL</a> - <a href="?station=nwh">NWH</a> - <a href="?station=car">CAR</a> - <a href="?station=sun">SUN</a> - <a href="?station=nlw">NLW</a> - <a href="?station=zzz">ZZZ</a></br /><br />

<?php

    $debug_next = null; //!!!!!------Used for listing additional stations in the debug log

    $station = $_GET['station']; // Get station code from address bar

    // API Query
    $apiUrl = "https://api.rtt.io/api/v1/json/search/$station";

    // Request options
    include("creds/creds.php");
    $opts = [
        "http" => [
            'method'  => 'GET',
            'header'  => [
                'Content-Type: application/x-www-form-urlencoded', 
                'header' => "Authorization: Basic " . base64_encode("$username:$password")
                ]
                ]
            ];

    $timestamp = date(DATE_RFC2822);
    $context = stream_context_create($opts); //Get result
    $json = file_get_contents($apiUrl, false, $context); //Convert to PHP JSON
    $obj = json_decode($json); //Store PHP JSON as object

    $s_name = $obj->location->name ?? false; //Station Name
    $s_code = $obj->location->crs ?? false; //Station Code
    $s_services = $obj->services ?? false; //Services


    if ($s_code == false) { //Station code specified hasn't returned a valid reply from RTT

        echo "You have entered an invalid station code, please try again...";
        $invalid_s_code = true;

    }

    else {

        $invalid_s_code = false;

        if ($s_services == true) { //There are services of some sort

            $index = 0; //Set train index to 0 so first train sets to 1
        
            foreach ($s_services as $s_service) { //Foreach service, get the information
            
                $index++;
                $trainIdentity = $s_service->trainIdentity ?? "Identity Unknown"; //Train identity (head code)
                $destinations = $s_service->locationDetail->destination ?? "Destination Unknown"; //All train calling destinations
                $destination  = $destinations[0]->description ?? "Description Unknown"; //First destination returned is the last the train will stop at
                $platform = $s_service->locationDetail->platform ?? "-"; //Platform number. Set to "-" if unknown or a single platform station
                $operatorCode = $s_service->atocCode ?? "Operator Code Unknown"; //Operator code, i.e. LD
                $operator = $s_service->atocName ?? "Operator Name Unknown"; //Operator friendly name
                $sched = $s_service->locationDetail->gbttBookedDeparture ?? "Booked Departure Unknown"; //When should it leave?
                $expect = $s_service->locationDetail->realtimeDeparture ?? "-"; //When is it likely to leave?
                $arrived = $s_service->locationDetail->realtimeArrivalActual ?? false; //Has it arived?
                $origins = $s_service->locationDetail->origin ?? "Origin Unknown"; //All origins
                $origin = $origins[0]->description ?? "Origin unkown"; //Where is it from?
                
                if ($operatorCode == "LD") {$operator = "Lumo";} //Lumo shows on RTT as unknown, fix this.

                if ($index == 1 AND $s_service->serviceType != "train") { $index = 0; } //Next train is say a bus, don't display it - so reset the index to 0, meaning the next real train shows
                
                elseif ($index == 1 AND $s_service->serviceType == "train") { //We only care about trains, not rail replacement buses or ferries. Get the next train.
                    

                    $schedDisplay = substr_replace($sched, ":", 2, 0);

                    $schedDate = date("Y-m-d"); //Get today's date
                    $schedDate = "$schedDate-$sched"; //Add on the scheduled time
                    $schedDateObj = DateTime::createFromFormat('Y-m-d-Hi', $schedDate); //Convert to object

                    $expDate = date("Y-m-d"); //Get today's date
                    $expDate = "$expDate-$expect"; //Add on the expected time
                    $expDateObj = DateTime::createFromFormat('Y-m-d-Hi', $expDate); //Convert to object

                    $nowDate = date("Y-m-d-Hi"); //Get date and time now;
                    $nowDateObj = DateTime::createFromFormat('Y-m-d-Hi', $nowDate); //Convert to object

                    $deviation = date_diff($schedDateObj,$expDateObj); //Work out the difference
                    $delay = $deviation->format('%i');

                    if ($delay > 0) {$delayed = true;}
                    else {$delayed = false;}

                    $interval = date_diff($expDateObj,$nowDateObj); //Work out the difference
                    $due = $interval->format('%i');

                    if ($due == 0) {$due = "Due";}
                    elseif ($arrived == true) {$due = "At Platform";}
                    else {$due = "$due min";}

                    ?>
                    <div id='displayBoardWrapper'>
                        <div class='board-top'>
                            <p>
                                <table>
                                    <tr>
                                        <td id='nextTrainTime'><?php echo $schedDisplay;?></td>
                                        <td id='nextTrainPlatform'><strong id='platformLabel'>P</strong><?php echo "$platform";?></td>
                                        <td id='nextTrainDest'><?php echo $destination;?></td>
                                        <td id='nextTrainExpected'<?php if ($delayed == true) {echo " style='color:red;' class='blink_me'>";} else {echo ">";}; echo $due;?></td>
                                    </tr>
                                </table>
                            </p>
                        </div>
                        <div class='board-middle'>
                            <p>
                                <table>
                                    <tr>
                                        <td id='callingAtLabel'>Info:</td>
                                        <td id='callingAtText'>
                                            <div class="scrolling-text-container">
                                                <div class="scrolling-text-inner" style="--marquee-speed: 20s; --direction:scroll-left" role="marquee">
                                                    <div class="scrolling-text">
                                                        <div class="scrolling-text-item" style="width:1000px;"></div> <!--Start the scroll with blank space -->
                                                        <div class="scrolling-text-item">This is the <?php echo $operator;?> service from <?php echo $origin;?>.</div>
                                                        <div class="scrolling-text-item">Calling at xxxxxx (xx:xx), xxxxxx (xx:xx) and xxxxx (xx:xx).</div>
                                                        <div class="scrolling-text-item">Some class of seating is available.</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </p>
                        </div>

                    <?php
                
                }

                elseif  ($s_service->serviceType == "train") { //We still only care about trains, not rail replacement buses or ferries. Get the rest of the trains.
                    
                    $debug_next .= "Identity: $trainIdentity<br />
                        Destination: $destination<br />
                        Operator: $operator<br />
                        Scheduled: $sched<br />
                        Expected: $sched<br />
                        Platform: $platform<br />
                        <br /><br />";
                
                }

                else {
                    
                    $s_services = false; //Services was previously true, however none of them are trains, so reset to false
                    
                }
            
            }
            ?>

        <?php
        
        }

        else { //($s_services == false) { //There are no services, or there are only non-train services.

            ?>
            <div class='board-top'>
                <p>
                    <table>
                        <tr>
                            <td id="noNextTrainTitle">
                                <?php echo $s_name; ?>
                            </td>
                        </tr>
                    </table>
                </p>
            </div>
            <div class='board-middle'>
                            <p>
                                <table>
                                    <tr>
                                        <td id='callingAtText'><div class='scroll-left-text'>There are no services stopping at this station</div></td>
                                    </tr>
                                </table>
                            </p>
                        </div>
            <?php
        }


    }

    if ($invalid_s_code == false) {


?>

    <script type="text/javascript" charset="utf-8">

    function sleep(miliseconds) {
                    var currentTime = new Date().getTime();
                    while (currentTime + miliseconds >= new Date().getTime()) {
                    }
                }
        
        timeout = 5; //Seconds to display each message
        m1 = 0;
        m2 = 0;
        m3 = 0;
        let a;
        let time;
        var timeDisplay = setInterval(() => {
            if (++m1 < timeout){
                display = "<?php echo $s_name; ?>";
                document.getElementById('bottomRow').innerHTML = display;
            }
            else if (++m2 < timeout) {
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
                document.getElementById('bottomRow').innerHTML = display;
            }
            else if (++m3 < timeout) {
                a = new Date();
                var time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second: '2-digit'});   
                document.getElementById('bottomRow').innerHTML = time;
            }
    
            else {
                m1 = 0;
                m2 = 0;
                m3 =0;
            }
    
        }, 1000);
    
    
    
    </script>

<div class='board-bottom'>

<p>
    <table>
            <tr>
                <td id="boardBottomTextRunning">
                    <span id="bottomRow"></span>
                </td>
            </tr>
    </table>
</p>
</div>

    </div>

<?php

    }

    //Dump debug crap and full JSON

    print "<br /><br /><br /><br /><br /><br /><br />-----DEBUG-----<br /><br />";
    print "Data retrieved from <a href='https://www.realtimetrains.co.uk/' target='_blank'>Realtime Trains</a> (<a href='https://api.rtt.io' target='_blank'>https://api.rtt.io</a>) at $timestamp<br /><br />";

    echo "Identity: $trainIdentity<br />";
    echo "Destination: $destination<br />";
    echo "Operator: $operator<br />";
    echo "Scheduled: $sched<br />";
    echo "Expected: $expect<br />";
    echo "Platform: $platform<br />";
    echo "<br /><br />";

    echo "Delay maybe of $delay mins<br /><br /><br />";


    echo $expDate;
    echo "&nbsp;&nbsp;&nbsp;";
    echo $nowDate;
    echo "&nbsp;&nbsp;&nbsp;";
    echo $due;

    echo $debug_next;

    echo "<br /><br />";
    echo json_encode($json, JSON_PRETTY_PRINT);
 
?>

</body>
</html>
