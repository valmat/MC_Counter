<?php
    echo '<hr>memory_get_usage: '.(memory_get_usage()/1024) .'��<br>';
   
    require './config/Counter.php';
    require './src/class.counter.php';
    require './src/class.memstore.php';
    require './src/class.redis.php';
    
 
    function microtime_float(){
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
    }

    ################################################################################
    
    $time_start = microtime_float();
    
    $cnt = new Counter('AnySlot',22);
    //$cnt->set_updelim(0);
    
    echo '<h2>$cnt->get() = '.$cnt->get().'</h2>';
    //echo '<h2>$cnt->del() = '.(int)$cnt->del().'</h2>';
    echo '<h2>$cnt->increment() = '.$cnt->increment().'</h2>';
    //echo '<h2>'.$cnt->get().'</h2>';
    //echo $cnt->set(11);
    
    
    
    echo '<hr>memory get: '.(memory_get_usage()/1024) .'��<br>';
    echo '<hr>memory peak: '.(memory_get_peak_usage()/1024) .'��<br>';
    echo '<hr>time: '.( (microtime_float() - $time_start)*1000 ).' ms<br>';
