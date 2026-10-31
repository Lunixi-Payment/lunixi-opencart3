<?php

declare (strict_types=1);
$site = getenv('LUNIXI_TEST_SITE');
$source = getenv('OPENCART_SOURCE');
$connection = getenv('LUNIXI_TEST_DB');
if (!is_string($site) || !is_file($site . '/config.php') || !is_string($source) || !is_string($connection)) {
    throw new RuntimeException('Native acceptance requires an explicitly installed official disposable fixture.');
}
$db = json_decode(file_get_contents($connection), true, 16, JSON_THROW_ON_ERROR);
if (($db['database'] ?? '') !== 'lunixi_oc3') {
    throw new RuntimeException('Dedicated acceptance schema required.');
}
define('VERSION', '3.0.5.1');
require $site . (getenv('LUNIXI_TEST_ADMIN') === '1' ? '/admin/config.php' : '/config.php');
$_SERVER += ['HTTPS' => 'on', 'HTTP_HOST' => 'merchant.example', 'REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1', 'SERVER_PORT' => 443, 'REQUEST_URI' => '/index.php', 'PHP_SELF' => '/index.php'];
require DIR_SYSTEM . 'startup.php';
require dirname(__DIR__) . '/tools/vendor/autoload.php';
require DIR_SYSTEM . 'library/lunixi3/autoload.php';
function nativeRegistry(): Registry
{
    $r = new Registry();
    $config = new Config();
    $config->load('default');
    $config->load(getenv('LUNIXI_TEST_ADMIN') === '1' ? 'admin' : 'catalog');
    $config->set('config_store_id', 0);
    $config->set('config_language', 'en-gb');
    $config->set('config_language_id', 1);
    $config->set('template_directory', getenv('LUNIXI_TEST_ADMIN') === '1' ? '' : 'default/template/');
    $config->set('template_engine', 'twig');
    $r->set('config', $config);
    $db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
    $r->set('db', $db);
    foreach ($db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id=0")->rows as $row) {
        $config->set($row['key'], $row['serialized'] ? json_decode($row['value'], true) : $row['value']);
    }
    $r->set('event', new Event($r));
    $r->set('load', new Loader($r));
    $language = new Language('en-gb');
    $language->load('en-gb');
    $language->load('extension/payment/lunixi');
    $r->set('language', $language);
    $r->set('cache', new Cache('file', 3600));
    $r->set('log', new Log('lunixi-native-test.log'));
    $r->set('request', new Request());
    $r->set('response', new Response());
    $r->set('url', new Url('https://merchant.example/', 'https://merchant.example/'));
    $r->set('session', new Session('file', $r));
    $r->get('session')->start();
    $r->set('document', new Document());
    $r->set('customer', new Cart\Customer($r));
    $r->set('currency', new Cart\Currency($r));
    require_once DIR_APPLICATION . 'controller/startup/event.php';
    (new ControllerStartupEvent($r))->index();
    return $r;
}
