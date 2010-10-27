<?php
  /**
    * class Counter
    * Это образец реализации счетчика на memcache
    * Можно построить другие реализации на общем интерфейсе
    * Сохранение результатов применения значений счетчика осуществляется по заданному числу.
    * Можно реализовать сохранение по заданному интервалу времени
    *
    * Конструктор принимает три аргумента: ключ, имя слота, и идентификатор для инициализации слота.
    * Для чего это сделано: инримент счетчика должен быть очень быстрой операцией.
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
    * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    * Пример использования:
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
      * Префикс для формирования ключа блокировки
      */
    const LOCK_PREF = COUNTER_LOCK_PREF;
    /**
      * Время жизни ключа блокировки. Если во время перестроения кеша процесс аварийно завершится,
      * то блокировка останется включенной и другие процессы будут продолжать выдавать протухший кеш LOCK_TIME секунд.
      * С другой стороны если срок блокировки истечет до того, как кеш будет перестроен, то возникнет состояние гонки и блокировочный механизм перестанет работать.
      * Т.е. LOCK_TIME нужно устанавливать таким, что бы кеш точно успел быть построен, и не слишком больши, что бы протухание кеша было заметно в выдаче клиенту
      */
    const LOCK_TIME = COUNTER_LOCK_TIME;
    
    const SLOT_PATH = COUNTER_SLOT_PATH;
    
    /**
      * Разделитель для сохранения локального значения в глобальное
      */
    private $upd_delim = COUNTER_UPD_DELUM;
    private $Key;
    private $ld_Key;
    private $Val;
    private $SlotName;
    private $SlotArg;
    
    /**
      * Флаг установленной блокировки
      * После установки этот флаг помечается в 1
      * В методе set проверяется данный флаг, и только если он установлен, тогда снимается блокировка [self::$memcache->delete(self::LOCK_PREF . $CacheKey)]
      * Затем флаг блокировки должен быть снят: $this->is_locked = false;
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
               if(!defined('COUNTER_SLOT_REQUIRED'))
                  require self::SLOT_PATH;
               
               # Получаем данные из постоянного хранилища, увеличиваем на 1 и сохраняет в локальное хранилище
               $this->Val = call_user_func($this->SlotName .'::get', $this->SlotArg);
               self::$memcache->add($this->Key, $this->Val, false);
               # После создания ключа $this->Key, другие процессы уже не будут писать в (self::LOCK_PREF . $this->Key) и можно
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
        if(0 == $this->Val%$this->upd_delim){
            if(!defined('COUNTER_SLOT_REQUIRED'))
               require self::SLOT_PATH;
               
            call_user_func($this->SlotName .'::set', $this->SlotArg, $this->Val);
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
        self::$memcache->set($this->Key, $this->Val=$newVal, false);
        if(!defined('COUNTER_SLOT_REQUIRED'))
           require self::SLOT_PATH;
        call_user_func($this->SlotName .'::set', $this->SlotArg, $this->Val);
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
        if($var>0)
            $this->upd_delim = $var;
    }
    
}



?>
