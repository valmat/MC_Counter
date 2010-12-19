<?php
  /**
    * class Counter
    * Это образец реализации счетчика на memcache
    * Можно построить другие реализации на общем интерфейсе
    * Сохранение результатов применения значений счетчика осуществляется по заданному числу.
    * Можно реализовать сохранение по заданному интервалу времени
    *
    * Конструктор принимает три аргумента: ключ, имя слота, и идентификатор для инициализации слота.
    * Для чего это сделано: инкремент счетчика должен быть очень быстрой операцией.
    * Не целесообразно тратить время и сстемные ресурсы на создание объетов, которые е будут использовны.
    * Поэтому передается только имя слот класа, который создается только в случае необходимости.
    * К таким случаям относитсяя обмен данными между локальным и постоянным хранилищем счтчика.
    * Слоты необхоимы, так как Counter  не может знать о способе хранения данных в постоянном хранилище и путях доступа к ним.
    * Для предотвращения состаяния гонки, необходим механизм блокировок.
    * При наличии блокировки, процессы не получившие эксклюзивные права на получение данных будут писать во временное хранилище, а процесс,
    * установивший блокировку по окончанию своей работы инкриментирует счетчик данными из временного хранилища
    *
    * При сбросе данных в постоянное хранилище по условию достежения кратности значения счетчика ($this->Val%$this->upd_delim),
    * блокировка не требуется, т.к. в этом случае (при достаточно большом значении $this->upd_delim) в текущий момент времени только один процесс
    * приходит к неоходимости сброса данных
    *
    * Если сброс данных в постаянное хранилище не предусмотрен, то в слоте метод delim() должен возвращать 0
    * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    * Пример использования:
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
      * Префикс для формирования ключа блокировки
      */
    const LOCK_PREF       = CONFIG_Counter::LOCK_PREF;
    
    /**
      * Время жизни ключа блокировки. Если во время перестроения кеша процесс аварийно завершится,
      * то блокировка останется включенной и другие процессы будут продолжать выдавать протухший кеш LOCK_TIME секунд.
      * С другой стороны если срок блокировки истечет до того, как кеш будет перестроен, то возникнет состояние гонки и блокировочный механизм перестанет работать.
      * Т.е. LOCK_TIME нужно устанавливать таким, что бы кеш точно успел быть построен, и не слишком больши, что бы протухание кеша было заметно в выдаче клиенту
      */
    const LOCK_TIME       = CONFIG_Counter::LOCK_TIME;
    
    /**
      * Разделитель для сохранения локального значения в глобальное
      */
    private $upd_delim;
    private $Key;
    private $Val;
    private $SlotName;
    
    /**
      * Флаг установленной блокировки
      * После установки этот флаг помечается в 1
      * В методе set проверяется данный флаг, и только если он установлен, тогда снимается блокировка [self::$memcache->delete(self::LOCK_PREF . $CacheKey)]
      * Затем флаг блокировки должен быть снят: $this->is_locked = false;
      */
    private        $is_locked = false;
    
    function __construct($SlotName) {
        self::$memcache = Mcache::init();
        $this->SlotName = 'Counter_Slot_' . $SlotName;
        $this->Key      = self::NAME_SPACE . call_user_func($this->SlotName .'::key');;
        $this->upd_delim= call_user_func($this->SlotName .'::delim');
    }
    
    /*
     * проверяем не установил ли кто либо блокировку
     * Если блокировка не установлена, пытаемся создать ее методом add, что бы предотвратить состояние гонки
     * function set_lock
     * @param $arg void
     */
    private function set_lock() {
        if( !($this->is_locked) && !(self::$memcache->get(self::LOCK_PREF . $this->Key)) )
           $this->is_locked = self::$memcache->add(self::LOCK_PREF . $this->Key,1,false,self::LOCK_TIME);
        return $this->is_locked;
    }
        
     /*
     * Увеличивает значение счетчика в локальном носителе
     * function increment
     * @param $Key   string
     */
    function increment(){
        $this->Val = self::$memcache->increment($this->Key);
        
        if(false==$this->Val){
            # Проверяем установил ли текущий процесс блокировку на эксклюзивное получение данных
            if( $this->set_lock() ){
               # Получаем данные из постоянного хранилища, увеличиваем на 1 и сохраняет в локальное хранилище
               $this->Val = call_user_func($this->SlotName .'::get');
               self::$memcache->add($this->Key, $this->Val, false, call_user_func($this->SlotName .'::expire') );
               # После создания ключа $this->Key, другие процессы уже будут писать в (self::LOCK_PREF . $this->Key) и можно
               # Не опасаться состояния гонки по этому ключу
               $difVal = (int) self::$memcache->get(self::LOCK_PREF . $this->Key);
               self::$memcache->delete(self::LOCK_PREF . $this->Key);
               # Скидываем в основной счетчик все что накопилось во временном хранилище, пока мы получали данные из глобального хранилища
               self::$memcache->increment($this->Key, $difVal);
               
            }else{
                # Если блокировку установил другой процесс, инкрементируем во временном хранилище счетчика
                self::$memcache->increment(self::LOCK_PREF . $this->Key);
            }
            
            return $this->Val;
        }
        
        # Обновляем данные постоянного хранилища по данным локального хранилища
        if($this->upd_delim > 0 && 0 == $this->Val%$this->upd_delim){
            call_user_func($this->SlotName .'::set', $this->Val);
        }
        return $this->Val;
    }
    
    /*
     * Установить данные счетчика
     * function set
     * @param $Key string  Ключ счетчика
     * @param $Val int     Данные счетчика
     * @return     int     counter value
     */
    function set($newVal){
        self::$memcache->set($this->Key, $this->Val=$newVal, false, call_user_func($this->SlotName .'::expire') );
        call_user_func($this->SlotName .'::set', $this->Val);
    }
    
    /*
     * Получить значение кеша если есть, или false, если отсутствует.
     * function get
     * @param  $CacheKey string  Ключ кеша
     * @return           int     counter value
     */
    function get(){
        return ( $this->Val = self::$memcache->get($this->Key) );
    }
    
    /*
     * Функция установки интервала сброса в постоянное хранилище
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
 * Интерфейс для слота счетчика.
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