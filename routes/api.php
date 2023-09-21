<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('login', 'API\DinningController@login');
Route::group(['middleware' => 'APIToken'], function () {
    Route::get('rooms-list', 'API\DinningController@getRoomList');
    Route::post('order-list', 'API\DinningController@getOrderList');
    Route::post('item-list', 'API\DinningController@getItemList');
    Route::post('update-order', 'API\DinningController@updateOrder');
    Route::post('get-report-data', 'API\DinningController@getCategoryWiseData');
    Route::post('get-room-data', 'API\DinningController@getRoomData');
    Route::post('get-user-data', 'API\DinningController@getUserData');
    Route::post('print-order-data', 'API\DinningController@printOrderData');
});