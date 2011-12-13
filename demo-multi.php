<?php
    echo '<hr>memory_get_usage: '.(memory_get_usage()/1024) .'Κα<br>';
   
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
    $CNT = 200;
    $mode = 'mg';
    
    if('s'==$mode)
    for($i=0; $i<$CNT;$i++) {
	if(mt_rand(0,1)) {
	    $cnt = new Counter('AnySlot',$i);
	    echo '<h2>'.$cnt->increment().'</h2>';
	}
    }
    
    
    if('g'==$mode) {
	$rez = array();
	for($i=1; $i<=$CNT;$i++) {
	    $cnt = new Counter('AnySlot',$i);
	    $rez[$i] = $cnt->get();
	}
	echo "<hr><pre>";
	var_export( $rez );
	echo '</pre><hr>';
    }
    
    if('mg'==$mode) {
	$keys = array_keys(array_fill(1,$CNT,1));
	echo "<hr><pre>";
	var_export( Counter::mget('AnySlot',$keys) );
	//var_export( Counter::mget('AnySlot',$keys, TRUE) );
	echo '</pre><hr>';
    }
    
    //;
    
    //$cnt->set_updelim(0);
    //echo '<h2>'.$cnt->get().'</h2>';
    //echo $cnt->set(11);
    
    
    
    echo '<hr>memory get: '.(memory_get_usage()/1024) .'Κα<br>';
    echo '<hr>memory peak: '.(memory_get_peak_usage()/1024) .'Κα<br>';
    echo '<hr>time: '.( (microtime_float() - $time_start)*1000 ).' ms<br>';
