<?php
  /**
    * class Counter
    * ��� ������� ���������� �������� �� memcache
    * ����� ��������� ������ ���������� �� ����� ����������
    * ���������� ����������� ���������� �������� �������� �������������� �� ��������� �����.
    * ����� ����������� ���������� �� ��������� ��������� �������
    *
    * ����������� ��������� ��� ���������: ����, ��� �����, � ������������� ��� ������������� �����.
    * ��� ���� ��� �������: �������� �������� ������ ���� ����� ������� ���������.
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
    
    /**
      *  Memcache parametrs 
      */
    const MC_HOST    = 'unix:///tmp/memcached.socket';
    const MC_PORT    = 0;
    
    private static $memcache=null;
    
    /**
     *  NameSpase prefix for counter key
     *  var set as default should be redefined
     *  @var string
     */
    const  NAME_SPACE = COUNTER_NAME_SPACE;
    const  LAST_DUMP_PREF = 'ld_';
    /**
      * ������� ��� ������������ ����� ����������
      */
    const LOCK_PREF = COUNTER_LOCK_PREF;
    /**
      * ����� ����� ����� ����������. ���� �� ����� ������������ ���� ������� �������� ����������,
      * �� ���������� ��������� ���������� � ������ �������� ����� ���������� �������� ��������� ��� LOCK_TIME ������.
      * � ������ ������� ���� ���� ���������� ������� �� ����, ��� ��� ����� ����������, �� ��������� ��������� ����� � ������������� �������� ���������� ��������.
      * �.�. LOCK_TIME ����� ������������� �����, ��� �� ��� ����� ����� ���� ��������, � �� ������� ������, ��� �� ���������� ���� ���� ������� � ������ �������
      */
    const LOCK_TIME = COUNTER_LOCK_TIME;
    
    const SLOT_PATH = COUNTER_SLOT_PATH;
    
    /**
      * ����������� ��� ���������� ���������� �������� � ����������
      */
    private $upd_delim = COUNTER_UPD_DELUM;
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
        if(null===self::$memcache){
           self::$memcache = new Memcache;
           self::$memcache->connect(self::MC_HOST, self::MC_PORT);
        }
        $this->Key      = self::NAME_SPACE . $Key;
        $this->ld_Key   = self::NAME_SPACE . self::LAST_DUMP_PREF . $Key;
        $this->SlotName = $SlotName;
        $this->SlotArg  = $SlotArg;
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
               # ����� �������� ����� $this->Key, ������ �������� ��� �� ����� ������ � (self::LOCK_PREF . $this->Key) � �����
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
        if(0 == $this->Val%$this->upd_delim){
            if(!defined('COUNTER_SLOT_REQUIRED'))
               require self::SLOT_PATH;
               
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
        if($var>0)
            $this->upd_delim = $var;
    }
    
}



?>
