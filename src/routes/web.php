<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function() {
    if(config('app.env') != "production"){
      return response()->json([
        'status' => true,
        'data' => [
          'key' => Illuminate\Support\Str::random(32),
        ],
        'message' => 'Welcome to Sample Application!'
      ], 200);
    }

    return response()->json([
      'status' => true,
      'message' => 'Hooray Welcome Onboard!'
    ], 200);
});

$router->get('/health', function() {
    return response()->json([
      'status' => true,
    ], 200);
});


$router->group([
  'prefix' => 'api',
], function() use ($router) {
  /* Version 1 */
  $router->group([
    'prefix' => 'v1'
  ], function() use ($router) {
    /* Payment*/
    $router->group([
      'prefix' => 'payment'
    ], function() use ($router) {
      $router->get('/', 'Apis\v1\PaymentController@fetch');
      $router->get('/user/{userId}', 'Apis\v1\PaymentController@fetchUserPayment');
      $router->get('/{PaymentId}', 'Apis\v1\PaymentController@fetchSingle');
      $router->put('/{PaymentId}', 'Apis\v1\PaymentController@update');
      $router->post('/', 'Apis\v1\PaymentController@store');
    });
  });
  /* Version 1 */
});
