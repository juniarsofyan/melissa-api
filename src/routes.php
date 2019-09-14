<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

/* $app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
}); */

$app->group('/auth', function () use ($app) {
    $app->post("/login", 'AuthController:login');
    $app->post("/logout", 'AuthController:logout');
});

$app->post("/consultation", 'ConsultationController:add');
$app->get("/ongkirtrans", 'TransactionController:ongkir');

$app->group('/product', function () use ($app) {
    $app->get("/", 'ProductController:get');
    $app->get("/series", 'ProductController:series');
    $app->get("/{type}", 'ProductController:get');
    $app->get("/{type}/related/{product_code}", 'ProductController:related');
    $app->get("/{product_code}/detail", 'ProductController:detail');
    $app->get("/search/{keyword}", 'ProductController:search');
});

$app->group('/account', function () use ($app) {
    $app->get("/get/{token}", 'AccountController:get');
    $app->post("/update", 'AccountController:update');
});

$app->group('/shipping-address', function () use ($app) {
    $app->get("/get/{token}", 'ShippingAddressController:get');
    $app->get("/current/{token}", 'ShippingAddressController:current');
    $app->get("/detail/{id}", 'ShippingAddressController:detail');
    $app->post("/add", 'ShippingAddressController:add');
    $app->post("/update/{id}", 'ShippingAddressController:update');
    $app->get("/delete/{id}", 'ShippingAddressController:delete');
    $app->get("/set-default/{id}", 'ShippingAddressController:setDefault');
});

$app->group('/cart', function () use ($app) {
    $app->get("/get", 'CartController:get');
    $app->post("/add", 'CartController:add');
    $app->post("/remove", 'CartController:remove');
    $app->post("/increase", 'CartController:increase');
    $app->post("/decrease", 'CartController:decrease');
});

$app->group('/transaction', function () use ($app) {
    $app->post("/add", 'TransactionController:add');
    $app->get("/history/{token}", 'TransactionController:history');
    $app->get("/confirm-payment/{transaction_id}", 'TransactionController:setPaymentConfirmed');
    $app->get("/confirm-received/{transaction_id}", 'TransactionController:setItemReceived');
    $app->post("/check-sales-branches-stock", 'TransactionController:checkSalesBranchesStock');
});

$app->group('/wishlist', function () use ($app) {
    $app->get("/get/{customer_id}", 'WishlistController:get');
    $app->post("/add", 'WishlistController:add');
    $app->get("/remove/{id}", 'WishlistController:remove');
});

$app->group('/ongkir', function () use ($app) {
    $app->get("/provinces", 'OngkirController:getAllProvinces');
    $app->get("/province/{province_id}", 'OngkirController:getProvinceDetail');
    $app->get("/province/{province_id}/cities", 'OngkirController:getProvinceCities');
    $app->get("/cities", 'OngkirController:getAllCities');
    $app->get("/city/{city_id}", 'OngkirController:getCityDetail');
    $app->get("/city/{city_id}/subdistricts", 'OngkirController:getAllCitySubdistricts');
    $app->get("/subdistrict/{subdistrict_id}", 'OngkirController:getSubdistrictDetail');
    $app->post("/cost", 'OngkirController:getCost');
    $app->post("/delivery-status", 'OngkirController:getDeliveryStatus');
});

$app->group('/promo', function () use ($app) {
    $app->post("/items", 'PromoController:all');
    $app->post("/{type}/items", 'PromoController:all');
    $app->post("/product/{product_code}/detail", 'PromoController:detail');
    $app->post("/specific-items", 'PromoController:specificItems');
    $app->post("/minimum-purchase", 'PromoController:minimumPurchasePromo');
    $app->post("/discount-get-discount", 'PromoController:discountGetDiscountPromo');
});
