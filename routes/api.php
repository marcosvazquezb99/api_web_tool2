<?php

use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

use App\Http\Controllers\AuthController;

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);
Route::middleware('auth:sanctum')->get('/logout', [AuthController::class, 'logout']);

Route::prefix('slack')->middleware('auth:sanctum')->group(function () {
    Route::get('usergroups/list', [SlackController::class, 'usergroups_list']);
    Route::post('admin/users/set-owner', [SlackController::class, 'admin_users_set_owner']);
    Route::post('admin/conversations/create', [SlackController::class, 'admin_conversations_create']);
    Route::get('conversations/info', [SlackController::class, 'conversations_info']);
    Route::get('conversations/list', [SlackController::class, 'conversations_list']);
    // Añade aquí el resto de las rutas para las funciones generadas
});

Route::prefix('monday')->middleware('auth:sanctum')->group(function () {

// Ruta para ejecutar cualquier consulta GraphQL
    Route::post('query', [MondayController::class, 'query']);

// Ruta para obtener los tableros (boards)
    Route::get('boards', [MondayController::class, 'getBoards']);

// Ruta para obtener los elementos de un tablero específico
    Route::get('boards/{boardId}/items', [MondayController::class, 'getItemsByBoard']);
});

// Ruta para revocar un token específico
Route::middleware('auth:sanctum')->delete('/revoke-token/{tokenId}', [TokenController::class, 'revokeToken']);

// Ruta para revocar el token actual
Route::middleware('auth:sanctum')->post('/revoke-current-token', [TokenController::class, 'revokeCurrentToken']);

// Ruta para revocar todos los tokens del usuario
Route::middleware('auth:sanctum')->post('/revoke-all-tokens', [TokenController::class, 'revokeAllTokens']);
Route::middleware('auth:sanctum')->post('/create-token', [TokenController::class, 'createToken']);
