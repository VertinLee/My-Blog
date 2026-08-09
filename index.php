<?php
/**
 * 前台单一入口：所有伪静态请求经此路由分发
 */

require dirname(__FILE__) . '/core/bootstrap.php';
require APP_ROOT . '/core/Front.php';

Front::handle();
