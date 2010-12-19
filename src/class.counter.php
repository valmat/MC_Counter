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
    *
    * ���� ����� ������ � ���������� ��������� �� ������������, �� � ����� ����� delim() ������ ���������� 0
    * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    * ������ �������������:
    *
    *  $cnt = new Counter('AnySlot');
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
    
    /**
      * ����������� ��� ���������� ���������� �������� � ����������
      */
    private $upd_delim;
    private $Key;
    private $Val;
    private $SlotName;
    
    /**
      * ���� ������������� ����������
      * ����� ��������� ���� ���� ���������� � 1
      * � ������ set ����������� ������ ����, � ������ ���� �� ����������, ����� ��������� ���������� [self::$memcache->delete(self::LOCK_PREF . $CacheKey)]
      * ����� ���� ���������� ������ ���� ����: $this->is_locked = false;
      */
    private        $is_locked = false;
    
    function __construct($SlotName) {
        self::$memcache = Mcache::init();
        $this->SlotName = 'Counter_Slot_' . $SlotName;
        $this->Key      = self::NAME_SPACE . call_user_func($this->SlotName .'::key');;
        $this->upd_delim= call_user_func($this->SlotName .'::delim');
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
               # �������� ������ �� ����������� ���������, ����������� �� 1 � ��������� � ��������� ���������
               $this->Val = call_user_func($this->SlotName .'::get');
               self::$memcache->add($this->Key, $this->Val, false, call_user_func($this->SlotName .'::expire') );
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
        
        # ��������� ������ ����������� ��������� �� ������ ���������� ���������
        if($this->upd_delim > 0 && 0 == $this->Val%$this->upd_delim){
            call_user_func($this->SlotName .'::set', $this->Val);
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
        self::$memcache->set($this->Key, $this->Val=$newVal, false, call_user_func($this->SlotName .'::expire') );
        call_user_func($this->SlotName .'::set', $this->Val);
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



/*******************************************************************************
 * ��������� ��� ����� ��������.
 * 
 */

interface Counter_Slot_Interface
 {
    /*
     * Set counter value at hard storage
     * function set
     * @param $val integer
     * @return void
     */
    static function set($val);
    
    /*
     * Get counter value from hard storage
     * function name
     * @param void
     * @return integer
     */
    static function get();
    
    /*
     * Return integer no negative delimiter for save in hard storage
     * function dilim
     * @param void
     * @return integer
     */
    static function delim();
    
    /*
     * Return slot key. Use in memcache, and posible hard, storage
     * function name
     * @param void
     * @return string
     */
    static function key();
    
    /*
     * Return expire slot in sec. Default 0.
     * function name
     * @param void
     * @return string
     */
    static function expire();
 }

/*******************************************************************************
 * requere slots
 * 
 */

 require CONFIG_Counter::SLOT_PATH;

?>