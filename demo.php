<?php
echo '<hr>memory_get_usage: '.(memory_get_usage()/1024) .'Κα<br>';

 //define('DFLT_CACHEBKND','Memcache');
 
 
function microtime_float(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
   }

################################################################################
/**
  *   __autoload
  */
   function __autoload($ClassName){
       //require_once PATH_CLASS.'/class.'.strtolower($ClassName).'.php';
       require './class.'.strtolower($ClassName).'.php';
    }
    
 //echo  Cacher::name('test');

 
 //echo  SimplTempl::Plug('test',0,5);
 //echo  SimplTempl::Plug('test1');

################################################################################


$time_start = microtime_float();
 $cnt = new Counter('anykey', 'AnySlot',15);
 echo $cnt->increment();
 //echo $cnt->set(11);




echo '<hr>memory_get_usage: '.(memory_get_usage()/1024) .'Κα<br>';
$time_end = microtime_float();
echo '<hr>time: '.( ($time_end - $time_start)*1000 ).' ms<br>';

?>
