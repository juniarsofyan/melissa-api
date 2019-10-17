<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// database
$container['db'] = function ($c) {
    $settings = $c->get('settings')['db'];
    $server = $settings['driver'] . ":host=" . $settings['host'] . ";dbname=" . $settings['dbname'];
    $conn = new PDO($server, $settings["user"], $settings["pass"]);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $conn;
};

$container['OngkirController'] = function ($c) {
    $db = $c->get("db");
    $config = $c->get('settings')['rajaongkir'];
    return new App\Controllers\OngkirController($db, $config);
};

$container['AuthController'] = function ($c) {
    $db = $c->get("db");
    $mailer = $c->get("Mailer");
    return new App\Controllers\AuthController($db, $mailer);
};

$container['AccountController'] = function ($c) {
    $db = $c->get("db");
    $mailer = $c->get("Mailer");
    return new App\Controllers\AccountController($db, $mailer);
};

$container['ProfileController'] = function ($c) {
    $db = $c->get("db");
    return new App\Controllers\ProfileController($db);
};

$container['ShippingAddressController'] = function ($c) {
    $db = $c->get("db");
    return new App\Controllers\ShippingAddressController($db);
};

$container['ConsultationController'] = function ($c) {
    $db = $c->get("db");
    return new App\Controllers\ConsultationController($db);
};

$container['CartController'] = function ($c) {
    $db = $c->get("db");
    return new App\Controllers\CartController($db);
};

$container['TransactionController'] = function ($c) {
    $db = $c->get("db");
    $ongkir = $c->get("OngkirService");
    return new App\Controllers\TransactionController($db, $ongkir);
};

$container['WishlistController'] = function ($c) {
    $db = $c->get("db");
    return new App\Controllers\WishlistController($db);
};

$container['ProductController'] = function ($c) {
    $db = $c->get("db");
    return new App\Controllers\ProductController($db);
};

$container['PromoController'] = function ($c) {
    $db = $c->get("db");
    $promomanager = $c->get("PromoManager");
    return new App\Controllers\PromoController($db, $promomanager);
};

$container['Mailer'] = function ($c) {
    $mail_settings = $c->get('settings')['mailer'];
    return new App\Services\Mailer($mail_settings);
};

$container['OngkirService'] = function ($c) {
    $config = $c->get('settings')['rajaongkir'];
    return new App\Services\Ongkir($config);
};

$container['PromoManager'] = function () {
    return new App\Services\PromoManager();
};
