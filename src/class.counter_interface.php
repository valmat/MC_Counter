<?php
/**
 * ��������� ��� ��������.
 * 
 */

interface Counter_Interface
 {
     /*
     * ����������� �������� �������� � ��������� ��������
     * function increment
     * @param $Key   string
     */
    function increment($Key);
    
    /*
     * ���������� ������ ��������
     * function set
     * @param $Key string  ���� ��������
     * @param $Val int     ������ ��������
     * @return     int     counter value
     */
    function set($Key, $Val);
    
    /*
     * �������� �������� ���� ���� ����, ��� false, ���� �����������.
     * function get
     * @param  $CacheKey string  ���� ����
     * @return           int     counter value
     */
    function get($Key);
    
    /*
     * ��������� ������ ����������� ��������� �� ������ ���������� ���������
     * function update
     * @param $Key string
     * @return     int    counter value
     */
    function _update($Key);
 }
?>