<?php

namespace App\Http\Controllers;

use App\Monday\MondayClient;
use App\Monday\Services\BoardService;
use App\Monday\Services\GroupService;
use App\Monday\Services\ItemService;
use App\Monday\Services\TimeTrackingService;
use App\Monday\Services\UserService;
use Illuminate\Http\Request;

class MondayController extends Controller
{
    protected $mondayClient;
    protected $boardService;
    protected $groupService;
    protected $itemService;
    protected $userService;
    protected $timeTrackingService;
    protected $limit = 25;

    public function __construct()
    {
        $this->mondayClient = new MondayClient();
        $this->boardService = new BoardService($this->mondayClient);
        $this->groupService = new GroupService($this->mondayClient);
        $this->itemService = new ItemService($this->mondayClient);
        $this->userService = new UserService($this->mondayClient);
        $this->timeTrackingService = new TimeTrackingService(
            $this->mondayClient,
            $this->userService,
            $this->itemService
        );
    }

    /**
     * Método para realizar una petición GraphQL a la API de Monday.com
     */
    public function query(Request $request)
    {
        // Validar que la consulta GraphQL esté presente en la solicitud
        $query = $request->input('query');

        if (!$query) {
            return response()->json(['error' => 'GraphQL query no proporcionada'], 400);
        }

        $response = $this->mondayClient->query($query);

        if (isset($response['status']) && $response['status'] === 200) {
            return response()->json($response['data']);
        } else {
            return response()->json(
                ['error' => $response['error'] ?? 'Error desconocido', 'message' => $response['message'] ?? ''],
                $response['status'] ?? 500
            );
        }
    }

    /**
     * Método para obtener la información de los tableros (boards)
     */
    public function getBoards($page = 1, $limit = null)
    {
        $boards = $this->boardService->getBoards($page, $limit);
        return response()->json($boards);
    }

    /**
     * Método para obtener los grupos de un tablero específico
     */
    public function getGroupsOfBoard($boardId)
    {
        $groups = $this->groupService->getGroupsOfBoard($boardId);
        return response()->json($groups);
    }

    /**
     * Método para obtener los elementos (items) de un grupo específico
     */
    public function getItemsOfGroup($boardId, $groupId, $cursor = null, $limit = null)
    {
        return $this->itemService->getItemsOfGroup($boardId, $groupId, $cursor, $limit);
    }

    /**
     * Método para obtener los elementos (items) de un tablero específico
     */
    public function getItemsByBoard($boardId, $columns = null, $cursor = null, $limit = null, $rules = [])
    {
        return $this->itemService->getItemsByBoard($boardId, $columns, $cursor, $limit, $rules);
    }

    /**
     * Método para duplicar un tablero en monday.com
     */
    public function duplicateBoard(Request $request)
    {
        $boardId = $request->input('boardId');
        $boardName = $request->input('boardName');

        return $this->boardService->duplicateBoard($boardId, $boardName);
    }

    /**
     * Método para duplicar un grupo de un tablero
     */
    public function duplicateGroupRequest(Request $request)
    {
        $boardId = $request->input('boardId');
        $groupId = $request->input('groupId');

        return $this->groupService->duplicateGroup($boardId, $groupId);
    }

    /**
     * Método para duplicar un grupo de un tablero
     */
    public function duplicateGroup(string $boardId, string $groupId, bool $addToTop = true)
    {
        return $this->groupService->duplicateGroup($boardId, $groupId, $addToTop);
    }

    /**
     * Método para actualizar el título de un grupo
     */
    public function updateGroupTitle(string $boardId, string $groupId, string $title)
    {
        return $this->groupService->updateGroupTitle($boardId, $groupId, $title);
    }

    /**
     * Método para crear un grupo en un tablero
     */
    public function createGroup(string $boardId, string $name)
    {
        return $this->groupService->createGroup($boardId, $name);
    }

    /**
     * Método para eliminar un grupo de un tablero
     */
    public function deleteGroup(string $boardId, string $groupId)
    {
        return $this->groupService->deleteGroup($boardId, $groupId);
    }

    /**
     * Método para cambiar el valor de la columna de un item
     */
    public function changeColumnValue(string $boardId, string $itemId, string $columnId, string $value)
    {
        return $this->itemService->changeColumnValue($boardId, $itemId, $columnId, $value);
    }

    /**
     * Método para mover un item de un tablero a otro
     */
    public function moveItemToBoard(string $boardId, string $groupId, string $itemId)
    {
        return $this->itemService->moveItemToBoard($boardId, $groupId, $itemId);
    }

    /**
     * Método para obtener usuarios de Monday
     */
    public function getUsers($page = 1, $limit = null)
    {
        $users = $this->userService->getUsers($page, $limit);
        return response()->json($users);
    }

    /**
     * Método para obtener un usuario específico
     */
    public function getUser($userId, $bypassLocal = false)
    {

        return $this->userService->getUser($userId, $bypassLocal);
    }

    /**
     * Método para encontrar un tablero por client ID
     */
    public function findBoardIdByClientId($clientId)
    {
        $foundBoard = $this->boardService->findBoardIdByClientId($clientId);
        return $foundBoard;
    }

    /**
     * Método para obtener la información de un tablero por client ID
     */
    public function getFindBoardIdByClientId($clientId)
    {
        $foundBoard = $this->findBoardIdByClientId($clientId);
        if ($foundBoard) {
            return response()->json(['board_id' => $foundBoard['id']]);
        } else {
            return response()->json(['message' => 'Board not found'], 404);
        }
    }

    /**
     * Método para encontrar un tablero por nombre
     */
    public function findBoardIdByName($boardName)
    {
        return $this->boardService->findBoardIdByName($boardName);
    }

    /**
     * Método para obtener la información de un tablero por nombre
     */
    public function getFindBoardIdByName($boardName)
    {
        $foundBoard = $this->findBoardIdByName($boardName);
        if ($foundBoard) {
            return response()->json(['board_id' => $foundBoard['id']]);
        } else {
            return response()->json(['message' => 'Board not found'], 404);
        }
    }

    /**
     * Método para obtener la información de un tablero por su id
     */
    public function getBoardById($boardId)
    {
        $board = $this->boardService->getBoardById($boardId);
        return response()->json($board);
    }

    /**
     * Método para obtener la información de varios tableros por sus ids
     */
    public function getBoardsByIds($boardIds)
    {
        $boards = $this->boardService->getBoardsByIds($boardIds);
        return response()->json($boards);
    }

    /**
     * Método para obtener resumen de time tracking de un tablero
     */
    public function getTimeTrakingMondayBoardSummary($boardId, $fromDate = null, $toDate = null)
    {
        return $this->timeTrackingService->getTimeTrakingMondayBoardSummary($boardId, $fromDate, $toDate);
    }

    /**
     * Generar el reporte de horas trabajadas
     */
    public function generateTimeTrackingReport($usersData)
    {
        return $this->timeTrackingService->generateTimeTrackingReport($usersData);
    }

    /**
     * Método para obtener tareas de varios tableros
     */
    public function getTasksOfBoards($boardsIds)
    {
        $tasks = [];
        return $tasks;
    }
}
