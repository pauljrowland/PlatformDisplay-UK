<?php

//Dump debug crap and full JSON

include('index.php');

print "<br><br><br><br><br><br><br>-----DEBUG-----<br><br>";
print "Data retrieved from <a href='https://www.realtimetrains.co.uk/' target='_blank'>Realtime Trains</a> (<a href='https://api.rtt.io' target='_blank'>https://api.rtt.io</a>) at $timestamp<br><br>";

echo "Exp: $expDate<br>";
echo "TimeNow: $nowDate<br>";
echo "DueIn: $due<br>";
echo "Delay maybe of $delay mins";

echo "<br><br><br>";

foreach ($nextTrainStrings as $nextTrainString) {
    echo "$nextTrainString<br>";
}

echo "<br><br><br>";

echo "JSON Dump<br><br>";
echo json_encode($json, JSON_PRETTY_PRINT);

phpinfo();
 
?>