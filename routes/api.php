<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrdenTrabajoController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ApiMiddleware;
use App\Models\OrdenTrabajo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware([ApiMiddleware::class])->group(function () {

    //AUTH
    Route::group([
        'middleware' => 'api',
        'prefix' => 'auth'
    ], function ($router) {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('me', [AuthController::class, 'me']);
    });

    //USUARIOS
    Route::group([
        'middleware' => 'api',
        'prefix' => 'user'
    ], function ($router) {
        Route::post('crear', [UserController::class, 'postCreate']);
        Route::get('traerTodos', [UserController::class, 'getTodos']);
        Route::get('paginado', [UserController::class, 'getTodosPaginado']);
        Route::get('traerUno', [UserController::class, 'getDatos']);
    });

    //ORDEN TRABAJO
    Route::group([
        'middleware' => 'api',
        'prefix' => 'ordenTrabajo'
    ], function ($router) {
        Route::post('crear', [OrdenTrabajoController::class, 'postCreate']);
        Route::get('traerTodos', [OrdenTrabajoController::class, 'getTodos']);
        Route::get('paginado', [OrdenTrabajoController::class, 'getTodosPaginado']);
        Route::get('traerUno', [OrdenTrabajoController::class, 'getDatos']);
    });

});

?>
