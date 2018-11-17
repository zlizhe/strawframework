<?php
if (TRUE == APP_DEBUG) {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
} else {
    //生产环境
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
}
/* 初始化设置 */
//@ini_set('memory_limit', '128M');
//@ini_set('session.session.cookie_lifetime',3600);
//@ini_set('session.cache_expire', 180);
//@ini_set('session.use_cookies', 1);
//@ini_set('session.auto_start', 0);
@ini_set('display_errors', APP_DEBUG);

//date_default_timezone_set('Asia/Shanghai');
@ini_set('date.timezone', 'Asia/Shanghai');

//header('Cache-control: private');
//header('Content-type: text/html; charset=utf-8');

//源码目录
define("PROTECTED_PATH", ROOT_PATH . 'Protected' . DS);
//库目录
define("LIBRARY_PATH", ROOT_PATH . 'Strawframework' . DS);
//静态资源目录
define("PUBLIC_PATH", ROOT_PATH . 'Public' . DS);
//模板路径
define("TEMPLATES_PATH", PUBLIC_PATH . 'Templates' . DS);
//第三方扩展目录
//define("VENDORS_PATH", PROTECTED_PATH . 'Vendors' . DS);

//配置信息目录,如数据库配置,缓存服务器配置
define("CONFIG_PATH", PROTECTED_PATH . 'Config' . DS);
//logs path
define('LOGS_PATH', PUBLIC_PATH . 'Logs' . DS);
//runtime
define('RUNTIME_PATH', PROTECTED_PATH . 'Runtime' . DS);

define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
define('IS_GET', REQUEST_METHOD == 'GET' ? TRUE : FALSE);
define('IS_POST', REQUEST_METHOD == 'POST' ? TRUE : FALSE);
define('IS_PUT', REQUEST_METHOD == 'PUT' ? TRUE : FALSE);
define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? TRUE : FALSE);
define('IS_AJAX', ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) ? TRUE : FALSE);

// 系统函数
include(LIBRARY_PATH . 'Base' . DS . 'functions.php');
//if (file_exists(PROTECTED_PATH . 'functions.php')) // 用户函数
//    include(PROTECTED_PATH . 'functions.php');

require_once(LIBRARY_PATH . 'Straw.php');


////允许携带 cookie
//header("Access-Control-Allow-Credentials: true");
//unset($siteDomain);
spl_autoload_register(function (string $class): void {
    // echo $class;
    // echo '_____';

    //可用的 namespace Path
    $classPath = [
        'Strawframework\\Base' => LIBRARY_PATH . 'Base' . DS,
        'Strawframework\\Cache' => LIBRARY_PATH . 'Cache' . DS,
        'Strawframework\\Db' => LIBRARY_PATH . 'Db' . DS,
        'Strawframework\\Vendors' => LIBRARY_PATH . 'Vendors'. DS,
        'Strawframework\\Protocol' => LIBRARY_PATH . 'Protocol' . DS,
        'Strawframework\\Factory' => LIBRARY_PATH . 'Factory' . DS,
        'Controllers' => PROTECTED_PATH . 'Controllers' . DS,
        'Models' => PROTECTED_PATH . 'Models' . DS,
        'Views' => TEMPLATES_PATH,
        'Requests' => PROTECTED_PATH . 'Requests' . DS,
    ];
    $cname = end(explode('\\', $class));
    $namespacePath = str_replace('\\' . $cname, '', $class);

    //Controller 增加 version 
    if ('Controller' == substr($namespacePath, 0, strlen('Controller'))){
        $classPath[$namespacePath] = $classPath['Controllers'] . end(explode('\\', $namespacePath)) . DS;
        // $namespacePath = 'controller';
    }

    // var_dump($classPath, $namespacePath);
    if (!in_array($namespacePath, array_keys($classPath)))
        throw new \Exception(sprintf('Load %s class failed!', $class));

    $fileName = $classPath[$namespacePath] . lcfirst($cname) . '.php';
    // echo $fileName;
    // echo "<br/>";
    if (is_file($fileName)) {
        //win平台检查一下 大小写是否一致
        if (FALSE == checkFileNameViaWin($fileName)) {
            throw new \Exception(sprintf('%s 文件名称大小写不一致 !', $fileName));
        }
        require_once($fileName);
    } else {
        throw new \Exception(sprintf('Path %s, file %s can not found !', $class, $fileName));
    }
});

//throw error 错误统一处理
set_exception_handler(function($exception){
    ex($exception);
});
session_start();