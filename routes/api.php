<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientServiceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\FormsController;
use App\Http\Controllers\MondayController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\TriggeredActionController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WordPressMigrationController;
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

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('newform', [FormsController::class, 'formCreation']);
Route::post('slack/summary_proyect', [SlackController::class, 'getTimeTrackingMondayBoardSummaryRequest']);
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);
Route::middleware('auth:sanctum')->get('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->post('/migrate-wordpress', [WordPressMigrationController::class, 'migrate']);

Route::prefix('slack')->middleware('auth:sanctum')->group(function () {
    Route::get('usergroups/list', [SlackController::class, 'usergroups_list']);
    Route::post('admin/users/set-owner', [SlackController::class, 'admin_users_set_owner']);
    Route::post('admin/conversations/create', [SlackController::class, 'admin_conversations_create']);
    Route::get('conversations/info', [SlackController::class, 'conversations_info']);
    Route::get('conversations/list', [SlackController::class, 'get_conversations_list']);
    Route::get('users/list', [SlackController::class, 'users_list_request']);
    // Añade aquí el resto de las rutas para las funciones generadas
});

Route::prefix('monday')->middleware('auth:sanctum')->group(function () {

// Ruta para ejecutar cualquier consulta GraphQL
    Route::post('query', [MondayController::class, 'query']);

// Ruta para obtener los tableros (boards)
    Route::get('boards', [MondayController::class, 'getBoards']);
    Route::post('duplicateBoard', [MondayController::class, 'duplicateBoard']);

// Ruta para obtener los elementos de un tablero específico
    Route::get('boards/{boardId}/items', [MondayController::class, 'getItemsByBoard']);
    Route::get('boards/by_name/{boardName}', [MondayController::class, 'getFindBoardIdByName']);

});

// Ruta para revocar un token específico
Route::middleware('auth:sanctum')->delete('/revoke-token/{tokenId}', [TokenController::class, 'revokeToken']);

// Ruta para revocar el token actual
Route::middleware('auth:sanctum')->post('/revoke-current-token', [TokenController::class, 'revokeCurrentToken']);

// Ruta para revocar todos los tokens del usuario
Route::middleware('auth:sanctum')->post('/revoke-all-tokens', [TokenController::class, 'revokeAllTokens']);
Route::middleware('auth:sanctum')->post('/create-token', [TokenController::class, 'createToken']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('actions', ActionController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('clients-services', ClientServiceController::class);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('features', FeatureController::class);
    Route::apiResource('plans', PlansController::class);
    Route::apiResource('services', ServiceController::class);
    Route::apiResource('triggered-actions', TriggeredActionController::class);
    Route::apiResource('websites', WebsiteController::class);
    Route::apiResource('servers', ServerController::class);
    Route::apiResource('wp-migrations', \App\Http\Controllers\WpMigrationController::class);
    Route::get('elementor/page-composer', [\App\Http\Controllers\elementorController::class, 'generatePageFromTemplate']);
});
