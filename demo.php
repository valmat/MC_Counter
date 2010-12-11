<?php
 echo '<hr>memory_get_usage: '.(memory_get_usage()/1024) .'Κα<br>';

 require './config/base.php';
 require './config/Counter.php';
 require './src/class.counter.php';
 
 
function microtime_float(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
   }

################################################################################


 $time_start = microtime_float();
 
 $cnt = new Counter('AnySlot');
 //$cnt->set_updelim(0);
 echo '<h2>'.$cnt->increment().'</h2>';
 //echo $cnt->set(11);




echo '<hr>memory_get_usage: '.(memory_get_usage()/1024) .'Κα<br>';
echo '<hr>time: '.( (microtime_float() - $time_start)*1000 ).' ms<br>';
?>
