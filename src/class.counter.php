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
    
    private $memstore = NULL;
    
    /**
     *  NameSpase prefix for counter key
     *  var set as default should be redefined
     */
    const  NAME_SPACE     = CONFIG_Counter::NAME_SPACE;
    
    /**
      * ������� ��� ������������ ����� ����������
      */
    const  LOCK_PREF       = CONFIG_Counter::LOCK_PREF;
    
    /**
      * ����� ����� ����� ����������. ���� �� ����� ������������ ���� ������� �������� ����������,
      * �� ���������� ��������� ���������� � ������ �������� ����� ���������� �������� ��������� ��� LOCK_TIME ������.
      * � ������ ������� ���� ���� ���������� ������� �� ����, ��� ��� ����� ����������, �� ��������� ��������� ����� � ������������� �������� ���������� ��������.
      * �.�. LOCK_TIME ����� ������������� �����, ��� �� ��� ����� ����� ���� ��������, � �� ������� ������, ��� �� ���������� ���� ���� ������� � ������ �������
      */
    const  LOCK_TIME       = CONFIG_Counter::LOCK_TIME;
    
    /**
      * ����������� ��� ���������� ���������� �������� � ����������
      */
    private $upd_delim;
    
    /**
      *  ���� �������� 
      */
    private $Key;
    
    /**
      *  ���� ����� (��� �������� � ����) 
      */
    private $Val;
    
    private $Slot;
    
    /**
      * ���� ������������� ����������
      * ����� ��������� ���� ���� ���������� � 1
      * � ������ set ����������� ������ ����, � ������ ���� �� ����������, ����� ��������� ���������� [$this->memstore->delete(self::LOCK_PREF . $CacheKey)]
      * ����� ���� ���������� ������ ���� ����: $this->is_locked = false;
      */
    private        $is_locked = false;
    
    function __construct($SlotName, $id = NULL) {
        $this->Key = (crc32(self::NAME_SPACE . $SlotName)+0x100000000) . '#' . $id;
        
        $SlotName = 'Counter_Slot_' . $SlotName;
        $Slot = new $SlotName($id);
        
        $this->memstore  = $Slot->memstore();
        $this->upd_delim = $Slot->delim();
        $this->Slot      = $Slot;
        
    }
    
    /*
     * ��������� �� ��������� �� ��� ���� ����������
     * ���� ���������� �� �����������, �������� ������� �� ������� add, ��� �� ������������� ��������� �����
     * function set_lock
     * @param $arg void
     */
    private function set_lock() {
        if( !($this->is_locked) && !($this->memstore->get(self::LOCK_PREF . $this->Key)) )
            $this->is_locked = $this->memstore->add(self::LOCK_PREF . $this->Key,1,self::LOCK_TIME);
        return $this->is_locked;
    }
        
     /*
     * ����������� �������� �������� � ��������� ��������
     * function increment
     * @param $Key   string
     */
    function increment(){
        $this->Val = $this->memstore->increment($this->Key);
        
        if(false===$this->Val){
            # ��������� ��������� �� ������� ������� ���������� �� ������������ ��������� ������
            if( $this->set_lock() ){
               # �������� ������ �� ����������� ���������, ����������� �� 1 � ��������� � ��������� ���������
               $this->Val = $this->Slot->get();
               $this->memstore->add($this->Key, $this->Val, $this->Slot->expire() );
               # ����� �������� ����� $this->Key, ������ �������� ��� ����� ������ � (self::LOCK_PREF . $this->Key) � �����
               # �� ��������� ��������� ����� �� ����� �����
               $difVal = (int) $this->memstore->get(self::LOCK_PREF . $this->Key);
               $this->memstore->del(self::LOCK_PREF . $this->Key);
               # ��������� � �������� ������� ��� ��� ���������� �� ��������� ���������, ���� �� �������� ������ �� ����������� ���������
               $this->memstore->increment($this->Key, $difVal);
               
            }else{
                # ���� ���������� ��������� ������ �������, �������������� �� ��������� ��������� ��������
                $this->memstore->increment(self::LOCK_PREF . $this->Key);
            }
            
            return $this->Val;
        }
        
        # ��������� ������ ����������� ��������� �� ������ ���������� ���������
        if($this->upd_delim && 0 == $this->Val%$this->upd_delim){
            $this->Slot->set($this->Val);
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
        $this->memstore->set($this->Key, $this->Val=$newVal, $this->Slot->expire() );
        $this->Slot->set($this->Val);
    }
    
    /*
     * �������� �������� ���� ���� ����, ��� false, ���� �����������.
     * function get
     * @param  $CacheKey string  ���� ����
     * @return           int     counter value
     */
    function get(){
        if(false===( $this->Val = $this->memstore->get($this->Key) )) {
            $this->Val = $this->Slot->get();
            $this->memstore->add($this->Key, $this->Val, $this->Slot->expire() );
        }
        return $this->Val;
    }
    
    /*
     * �������� �������� ���� ���� ����, ��� false, ���� �����������.
     * function get
     * @param  $keys array
     * @param  $fillZero bool fill by zero, if not exist
     * @return array counter values
     */
    static function mget($SlotName, $keys, $fillZero = false) {
        $pf =  (crc32(self::NAME_SPACE . $SlotName)+0x100000000) . '#';
        
        $rez = $fillZero ? array_fill_keys($keys, 0) : array();
        
        $keys = array_combine($keys, array_map(
            function($id) use($pf) {
                return $pf . $id;
            }, $keys));
        $reKeys = array_flip($keys);
        
        $SlotClName = 'Counter_Slot_' . $SlotName;
        foreach($SlotClName::memstore()->get($keys) as $k => $v) {
            $rez[$reKeys[$k]] = $v;
        }
        
        if(!$fillZero)
        foreach(array_diff_key($keys,$rez) as $k => $v) {
	    $cnt = new Counter($SlotName, $k);
	    $rez[$k] = $cnt->get();
        }
        
        return $rez;
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
     * @param $key string
     * @param $val integer
     * @return void
     */
    public function set($val);
    
    /*
     * Get counter value from hard storage
     * function get
     * @param $key string
     * @return integer
     */
    public function get();
    
    /*
     * Return integer no negative delimiter for save in hard storage
     * function dilim
     * @param void
     * @return integer
     */
    public function delim();
    
    /*
     * Return expire slot in sec. Default 0.
     * function expire
     * @param void
     * @return string
     */
    public function expire();
    
    /*
     * Return memstore
     * function memstore
     * @param void
     * @return Memstore_incremented_Interface
     */
    public function memstore();
    
 }

/*******************************************************************************
 * requere slots
 * 
 */

 require CONFIG_Counter::SLOT_PATH;


