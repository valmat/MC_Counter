<?php
 define('COUNTER_SLOT_REQUIRED',TRUE);

/**
 * Интерфейс для счетчика.
 * 
 */

interface Counter_Slot_Interface
 {
    static function set($id, $val);
    static function get($id);
    static function create($id, $val);
 }

################################################################################
 /*
  * для тестирования
  * class AnySlot
  */
 
 class AnySlot implements Counter_Slot_Interface {
    

    
    private function __construct() {}
    
    static function set($id, $val){
        echo "<hr>_update($id,$val):<pre>";
        var_export($val);
        echo '</pre><hr>';
        file_put_contents('/tmp/anycntr_'.$id.'.txt',$val);
    }
    static function get($id){
        echo '<hr><h1>';
        var_export($id);
        echo '</h1><hr>';
        
      return (int) file_get_contents('/tmp/anycntr_'.$id.'.txt');
    }
    static function create($id, $val){
        
    }
 }
?>
