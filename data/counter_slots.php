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
 }

################################################################################
 /*
  * для тестирования
  * class AnySlot
  */
 
 class AnySlot implements Counter_Slot_Interface {
    

    const CNTR_FILE_PREF = '/tmp/anycntr_';
    
    private function __construct() {}
    
    static function set($id, $val){
        echo "<hr>_update($id,$val):<pre>";
        var_export($val);
        echo '</pre><hr>';
        file_put_contents(self::CNTR_FILE_PREF.$id.'.txt',$val);
    }

    static function get($id){
        echo '<hr><h1>';
        var_export($id);
        echo '</h1><hr>';
        
      if(!is_file(self::CNTR_FILE_PREF.$id.'.txt')){
         file_put_contents(self::CNTR_FILE_PREF.$id.'.txt',0);
         return 0;
      }
      return (int) file_get_contents(self::CNTR_FILE_PREF.$id.'.txt');
    }
 }
?>
