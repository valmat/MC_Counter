<?php

################################################################################
/*
 * для тестирования
 * class AnySlot
 */

class Counter_Slot_AnySlot implements Counter_Slot_Interface {
    
    const CNTR_FILE_PREF = '/tmp/anycntr_';
    private $id;
    
    public function __construct($id) {
        $this->id = $id;
    }
    
    public function set($val) {
        echo "<pre style='color:red'><hr>set[{$this->id}]=";
        var_export($val);
        echo '<hr></pre>';
        file_put_contents(self::CNTR_FILE_PREF.$this->id.'.txt', $val);
    }
    
    public function get() {
        //$id = self::key();
        echo "<hr><b style='color:green'>get({$this->id})</b><hr>";
        
        if(!is_file(self::CNTR_FILE_PREF.$this->id.'.txt')){
            file_put_contents(self::CNTR_FILE_PREF.$this->id.'.txt',0);
            return 0;
        }
        return (int) file_get_contents(self::CNTR_FILE_PREF.$this->id.'.txt');
    }
    
    public function delim() {
        return 4;
    }
    
    public function expire() {
        return 0;
    }
     
    /*
     * Return memstore
     * function memstore
     * @param void
     * @return Memstore_incremented_Interface
     */
    public function memstore() {
        return new RedisCounter();
        //return new Mcache();
    }
}

