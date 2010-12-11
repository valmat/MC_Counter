<?php
  /**
    * class Counter
    * ��� ������� ���������� �������� �� memcache
    * ����� ��������� ������ ���������� �� ����� ����������
    * ���������� ����������� ���������� �������� �������� �������������� �� ��������� �����.
    * ����� ����������� ���������� �� ��������� ��������� �������
    *
    * ����������� ��������� ��� ���������: ����, ��� �����, � ������������� ��� ������������� �����.
    * ��� ���� ��� �������: ��������� �������� ������ ���� ����� ������� ���������.
    * �� ������������� ������� ����� � �������� ������� �� �������� �������, ������� � ����� �����������.
    * ������� ���������� ������ ��� ���� �����, ������� ��������� ������ � ������ �������������.
    * � ����� ������� ���������� ����� ������� ����� ��������� � ���������� ���������� �������.
    * ����� ���������, ��� ��� Counter  �� ����� ����� � ������� �������� ������ � ���������� ��������� � ����� ������� � ���.
    * ��� �������������� ��������� �����, ��������� �������� ����������.
    * ��� ������� ����������, �������� �� ���������� ������������ ����� �� ��������� ������ ����� ������ �� ��������� ���������, � �������,
    * ������������ ���������� �� ��������� ����� ������ �������������� ������� ������� �� ���������� ���������
    *
    * ��� ������ ������ � ���������� ��������� �� ������� ���������� ��������� �������� �������� ($this->Val%$this->upd_delim),
    * ���������� �� ���������, �.�. � ���� ������ (��� ���������� ������� �������� $this->upd_delim) � ������� ������ ������� ������ ���� �������
    * �������� � ������������ ������ ������
    * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    * ������ �������������:
    *
    *  $cnt = new Counter('anykey', 'AnySlot',15);
    *  echo $cnt->increment();
    *  echo $cnt->get();
    *  echo $cnt->set(11);
    *  
    * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    */

class Counter {
    
    private static $memcache=null;
    
    /**
     *  NameSpase prefix for counter key
     *  var set as default should be redefined
     */
    const  NAME_SPACE     = CONFIG_Counter::NAME_SPACE;
    const  LAST_DUMP_PREF = 'ld_';
    /**
      * ������� ��� ������������ ����� ����������
      */
    const LOCK_PREF       = CONFIG_Counter::LOCK_PREF;
    /**
      * ����� ����� ����� ����������. ���� �� ����� ������������ ���� ������� �������� ����������,
      * �� ���������� ��������� ���������� � ������ �������� ����� ���������� �������� ��������� ��� LOCK_TIME ������.
      * � ������ ������� ���� ���� ���������� ������� �� ����, ��� ��� ����� ����������, �� ��������� ��������� ����� � ������������� �������� ���������� ��������.
      * �.�. LOCK_TIME ����� ������������� �����, ��� �� ��� ����� ����� ���� ��������, � �� ������� ������, ��� �� ���������� ���� ���� ������� � ������ �������
      */
    const LOCK_TIME       = CONFIG_Counter::LOCK_TIME;
    
    const SLOT_PATH       = CONFIG_Counter::SLOT_PATH;
    
    /**
      * ����������� ��� ���������� ���������� �������� � ����������
      */
    private $upd_delim = CONFIG_Counter::UPD_DELUM;
    private $Key;
    private $ld_Key;
    private $Val;
    private $SlotName;
    private $SlotArg;
    
    /**
      * ���� ������������� ����������
      * ����� ��������� ���� ���� ���������� � 1
      * � ������ set ����������� ������ ����, � ������ ���� �� ����������, ����� ��������� ���������� [self::$memcache->delete(self::LOCK_PREF . $CacheKey)]
      * ����� ���� ���������� ������ ���� ����: $this->is_locked = false;
      */
    private        $is_locked = false;
    

    function __construct($Key, $SlotName, $SlotArg) {
        self::$memcache = Mcache::init();
        $this->Key      = self::NAME_SPACE . $Key;
        $this->ld_Key   = self::NAME_SPACE . self::LAST_DUMP_PREF . $Key;
        $this->SlotName = $SlotName;
        $this->SlotArg  = $SlotArg;
        //$this->upd_delim= call_user_func($this->SlotName .'::delim');
    }
    
    /*
     * ��������� �� ��������� �� ��� ���� ����������
     * ���� ���������� �� �����������, �������� ������� �� ������� add, ��� �� ������������� ��������� �����
     * function set_lock
     * @param $arg void
     */
    private function set_lock() {
        if( !($this->is_locked) && !(self::$memcache->get(self::LOCK_PREF . $this->Key)) )
           $this->is_locked = self::$memcache->add(self::LOCK_PREF . $this->Key,1,false,self::LOCK_TIME);
        return $this->is_locked;
    }
        
     /*
     * ����������� �������� �������� � ��������� ��������
     * function increment
     * @param $Key   string
     */
    function increment(){
        $this->Val = self::$memcache->increment($this->Key);
        
        if(false==$this->Val){
            # ��������� ��������� �� ������� ������� ���������� �� ������������ ��������� ������
            if( $this->set_lock() ){
               if(!defined('COUNTER_SLOT_REQUIRED'))
                  require self::SLOT_PATH;
               
               # �������� ������ �� ����������� ���������, ����������� �� 1 � ��������� � ��������� ���������
               $this->Val = call_user_func($this->SlotName .'::get', $this->SlotArg);
               self::$memcache->add($this->Key, $this->Val, false);
               # ����� �������� ����� $this->Key, ������ �������� ��� ����� ������ � (self::LOCK_PREF . $this->Key) � �����
               # �� ��������� ��������� ����� �� ����� �����
               $difVal = (int) self::$memcache->get(self::LOCK_PREF . $this->Key);
               self::$memcache->delete(self::LOCK_PREF . $this->Key);
               # ��������� � �������� ������� ��� ��� ���������� �� ��������� ���������, ���� �� �������� ������ �� ����������� ���������
               self::$memcache->increment($this->Key, $difVal);
               
            }else{
                # ���� ���������� ��������� ������ �������, �������������� �� ��������� ��������� ��������
                self::$memcache->increment(self::LOCK_PREF . $this->Key);
            }
            
            return $this->Val;
        }
        //echo 'DUMP[' . $this->Val , '][' , $this->upd_delim ,' %: ';//.($this->Val%$this->upd_delim);
        # ��������� ������ ����������� ��������� �� ������ ���������� ���������
        if($this->upd_delim > 0 && 0 == $this->Val%$this->upd_delim){
            if(!defined('COUNTER_SLOT_REQUIRED'))
               require self::SLOT_PATH;
               //echo '<h2>DUMP</h2>';
               
            call_user_func($this->SlotName .'::set', $this->SlotArg, $this->Val);
        }
        return $this->Val;
    }
    
    /*
     * ���������� ������ ��������
     * function set
     * @param $Key string  ���� ��������
     * @param $Val int     ������ ��������
     * @return     int     counter value
     */
    function set($newVal){
        self::$memcache->set($this->Key, $this->Val=$newVal, false);
        if(!defined('COUNTER_SLOT_REQUIRED'))
           require self::SLOT_PATH;
        call_user_func($this->SlotName .'::set', $this->SlotArg, $this->Val);
    }
    
    /*
     * �������� �������� ���� ���� ����, ��� false, ���� �����������.
     * function get
     * @param  $CacheKey string  ���� ����
     * @return           int     counter value
     */
    function get(){
        return ( $this->Val = self::$memcache->get($this->Key) );
    }
    
    /*
     * ������� ��������� ��������� ������ � ���������� ���������
     * function set_updelim
     * @param $var int
     * @return void
     */
    function set_updelim($var) {
        if($var>-1)
            $this->upd_delim = $var;
    }
    
}

//require Counter::SLOT_PATH;

?>