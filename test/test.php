<?php
require '../vendor/autoload.php';

use tool\RedisLock;

$lock = new RedisLock('test_key', 10, 29, 1);
$rs = $lock->lock();

echo $rs ? 'lock success' : 'lock error';
