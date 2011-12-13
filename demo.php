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
    
    /*
    $cnt = new Counter('AnySlot',15);
    //$cnt->set_updelim(0);
    
    echo '<h2>'.$cnt->increment().'</h2>';
    //echo $cnt->set(11);
    */
    
    ###
    
    $r = new RedisCounter;
    /*
    echo "<hr><pre>";
    //var_export($r->set('k2',11));
    var_export($r->get(array('k1','k2','k3')));
    echo '</pre><hr>';
    */
    echo "<hr><pre>";
    var_export($r->increment('k2',5));
    echo '</pre><hr>';
    
    echo "<hr>set<pre>";
    var_export($r->set('k4','fghfghgf'));
    echo '</pre><hr>';
    echo "<hr>get<pre>";
    var_export($r->get('k4'));
    echo '</pre><hr>';
    echo "<hr>del<pre>";
    var_export($r->del('k4'));
    echo '</pre><hr>';
    echo "<hr>get<pre>";
    var_export($r->get('k4'));
    echo '</pre><hr>';

    
    ###
    
    
    echo '<hr>memory get: '.(memory_get_usage()/1024) .'Κα<br>';
    echo '<hr>memory peak: '.(memory_get_peak_usage()/1024) .'Κα<br>';
    echo '<hr>time: '.( (microtime_float() - $time_start)*1000 ).' ms<br>';
