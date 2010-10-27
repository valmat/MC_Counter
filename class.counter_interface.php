<?php
/**
 * Интерфейс для счетчика.
 * 
 */

interface Counter_Interface
 {
     /*
     * Увеличивает значение счетчика в локальном носителе
     * function increment
     * @param $Key   string
     */
    function increment($Key);
    
    /*
     * Установить данные счетчика
     * function set
     * @param $Key string  Ключ счетчика
     * @param $Val int     Данные счетчика
     * @return     int     counter value
     */
    function set($Key, $Val);
    
    /*
     * Получить значение кеша если есть, или false, если отсутствует.
     * function get
     * @param  $CacheKey string  Ключ кеша
     * @return           int     counter value
     */
    function get($Key);
    
    /*
     * Обновляет данные постоянного хранилища по данным локального хранилища
     * function update
     * @param $Key string
     * @return     int    counter value
     */
    function _update($Key);
 }
?>