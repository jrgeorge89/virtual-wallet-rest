<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->post('/register-customer', 'WalletController@registerCustomer');
$router->post('/recharge-wallet', 'WalletController@rechargeWallet');
$router->post('/pay', 'WalletController@pay');
$router->post('/confirm-payment', 'WalletController@confirmPayment');
$router->post('/get-balance', 'WalletController@getBalance');
