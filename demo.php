<?php
 echo '<hr>memory_get_usage: '.(memory_get_usage()/1024) .'Κα<br>';

 require './config/base.php';
 require './config/Counter.php';
 
 
function microtime_float(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
   }

################################################################################
/**
  *   __autoload
  */
   function __autoload($ClassName){
       require './src/class.'.strtolower($ClassName).'.php';
    }
################################################################################


 $time_start = microtime_float();
 
 $cnt = new Counter('anykey', 'AnySlot',15);
 $cnt->set_updelim(10);
 echo '<h2>'.$cnt->increment().'</h2>';
 //echo $cnt->set(11);




echo '<hr>memory_get_usage: '.(memory_get_usage()/1024) .'Κα<br>';

echo '<hr>time: '.( (microtime_float() - $time_start)*1000 ).' ms<br>';

?>
