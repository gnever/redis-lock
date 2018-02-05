<?php

namespace tool;

use sw_redis\CommonRedis; 

/**
 * redis 并发锁，适用于高并发业务下。解决并发重复执行的问题
 * 
 * @version $Id$
 * @author gao 
 */
class RedisLock {
    protected $prefix = 'redis_lock_';

    protected $key = null;
    protected $timeout = null;
    protected $retry_num = null;
    protected $retry_delay = null;

    /**
     * __construct 
     * 
     * @param mixed $key 
     * @param int $timeout 强制过期时间，避免死锁
     * @param int $retry_delay 单位毫秒，如果发现被锁则等待该时间
     * @param int $retry_num 等待重试的次数
     * @access public
     * @return void
     */
    public function __construct($key, $timeout = 30, $retry_delay = 100, $retry_num = 0) {
        if(!$key) {
            Throw new \Exception('key');
        }

        $this->key = $this->prefix . $key;
        $this->timeout = $timeout + 2;
        $this->retry_delay = $retry_delay;
        $this->retry_num = $retry_num;
    }

    /**
     * lock 加锁
     * 
     * @access public
     * @return bool true :加锁成功
     *              false : 已经被锁定
     */
    public function lock() {
        $retry_num = $this->retry_num;
        do {
            $retry_num--;

            $rs = $this->doLock();
            if($rs) {
                return true;
            }

            if($retry_num > 0) {
                $delay = mt_rand(floor($this->retry_delay / 2), $this->retry_delay);
                usleep($delay * 1000);
            }

        } while($retry_num > 0);
        return false;
    }

    /**
     * unLock 解锁
     * 
     * @access public
     * @return bool
     */
    public function unLock() {
        $obj = CommonRedis::init(); 
        $lock_time = $obj->get($this->key);
        //如果锁已经超时，则不能再删除key了
        if(time() - $lock_time < $this->timeout) {
            return $obj->del($this->key);
        }
        return false;
    }

    private function doLock() {
        $obj = CommonRedis::init(); 
        $rs = $obj->set($this->key, time(), array('nx', 'ex'=>$this->timeout));
        if($rs) {
            return true;
        }

        $lock_time = $obj->get($this->key);
        if(time() - $lock_time < $this->timeout) {
            return false;
        }

        $lock_time = $obj->getSet($this->key, time());
        if(time() - $lock_time < $this->timeout) {
            return false;
        }
        $obj->expire($this->key, $this->timeout);
        return true;
    }
}
