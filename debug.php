<?php

//Dump debug crap and full JSON

include('index.php');

print "<br><br><br>-----DEBUG-----<br><br>";



echo "JSON Dump<br><br>";


echo json_encode($json, JSON_PRETTY_PRINT);


phpinfo();
 
?>