<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');

// Handle preflight request dari Vue/Axios
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

use Phalcon\Di\FactoryDefault;
use Phalcon\Autoload\Loader; // <-- Namespace baru di Phalcon 5
use Phalcon\Mvc\Application;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Http\Response;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// 1. Daftarkan Autoloader (Versi Phalcon 5)
$loader = new Loader();
$loader->setDirectories([ // <-- Fungsi baru di Phalcon 5
    APP_PATH . '/controllers/',
    APP_PATH . '/models/',
])->register();

// 2. Setup Dependency Injection (DI Container)
$di = new FactoryDefault();

// 3. Setup Koneksi Database MariaDB
$di->setShared('db', function () {
    return new DbAdapter([
        'host'     => '127.0.0.1',
        'username' => 'root',
        'password' => 'mariadb', // <-- Ingat untuk ubah ke password MariaDB Anda
        'dbname'   => 'pos'
    ]);
});

// 4. Handle Request API
$application = new Application($di);

try {
    $application->useImplicitView(false); 
    
    $response = $application->handle($_SERVER['REQUEST_URI']);
    $response->send();
} catch (\Exception $e) {
    $response = new Response();
    $response->setStatusCode(500, 'Internal Server Error');
    $response->setJsonContent([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
    $response->send();
}