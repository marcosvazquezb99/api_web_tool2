<?php

namespace App\Http\Controllers;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class MondayController extends Controller
{
    protected $client;
    protected $mondayToken;
    protected $limit = 25;

    protected $itemsData = [
        'BoardRelationValue' => '... on BoardRelationValue {
        display_value
        linked_item_ids
    }',
        'ButtonValue' => '... on ButtonValue {
        color
        text
    }',
        'CheckboxValue' => '... on CheckboxValue {
        checked
    }',
        'ColorPickerValue' => '... on ColorPickerValue {
        color
    }',
        'CountryValue' => '... on CountryValue {
        country {
            code
            name
        }
        value
    }',
        'CreationLogValue' => '... on CreationLogValue {
        created_at
        creator {
            id
            birthday
            country_code
            current_language
            email
            url
            title
            phone
        }
    }',
        'DateValue' => '... on DateValue {
        date
        time
        text
    }',
        'DependencyValue' => '... on DependencyValue {
        display_value
        text
    }',
        'DocValue' => '... on DocValue {
        file {
            doc {
                id
                url
                relative_url
                name
            }
            creator {
                id
                email
            }
            url
        }
    }',
        'DropdownValue' => '... on DropdownValue {
        text
        values {
            label
        }
    }',
        'EmailValue' => '... on EmailValue {
        email
        text
    }',
        'FileValue' => '... on FileValue {
        files {
            __typename
        }
    }',
        'FormulaValue' => '... on FormulaValue {
        text
        value
    }',
        'GroupValue' => '... on GroupValue {
        group_id
    }',
        'HourValue' => '... on HourValue {
        hour
        minute
    }',
        'IntegrationValue' => '... on IntegrationValue {
        text
        entity_id
        issue_api_url
        issue_id
    }',
        'ItemIdValue' => '... on ItemIdValue {
        item_id
    }',
        'LastUpdatedValue' => '... on LastUpdatedValue {
        updated_at
        updater {
            name
            id
            email
        }
    }',
        'LinkValue' => '... on LinkValue {
        url
        text
    }',
        'LocationValue' => '... on LocationValue {
        lat
        lng
        address
        city
        city_short
        country_short
        place_id
        street
        street_number
        street_number_short
        street_short
    }',
        'LongTextValue' => '... on LongTextValue {
        text
    }',
        'MirrorValue' => '... on MirrorValue {
        display_value
    }',
        'NumbersValue' => '... on NumbersValue {
        direction
        symbol
        number
    }',
        'PeopleValue' => '... on PeopleValue {
        persons_and_teams {
            id
            kind
        }
    }',
        'PersonValue' => '... on PersonValue {
        person_id
    }',
        'PhoneValue' => '... on PhoneValue {
        phone
        country_short_name
    }',
        'ProgressValue' => '... on ProgressValue {
        text
    }',
        'RatingValue' => '... on RatingValue {
        rating
    }',
        'StatusValue' => '... on StatusValue {
        label
        is_done
        index
    }',
        'SubtasksValue' => '... on SubtasksValue {
        subitems_ids
    }',
        'TagsValue' => '... on TagsValue {
        tag_ids
        tags {
            id
            name
        }
    }',
        'TeamValue' => '... on TeamValue {
        team_id
    }',
        'TextValue' => '... on TextValue {
        text
    }',
        'TimelineValue' => '... on TimelineValue {
        from
        to
        text
    }',
        'TimeTrackingValue' => '... on TimeTrackingValue {
        history {
            started_user_id
            ended_user_id
            started_at
            ended_at
            manually_entered_end_date
            manually_entered_end_time
            manually_entered_start_date
            manually_entered_start_time
        }
        running
        started_at
    }',
        'UnsupportedValue' => '... on UnsupportedValue {
        text
    }',
        'VoteValue' => '... on VoteValue {
        voters {
            id
            name
            email
            url
        }
        vote_count
    }',
        'WeekValue' => '... on WeekValue {
        start_date
        end_date
    }',
        'WorldClockValue' => '... on WorldClockValue {
        timezone
        text
    }'
    ];


    public function __construct()
    {
        $this->client = new Client();
        $this->mondayToken = env('MONDAY_API_TOKEN'); // Asegúrate de agregar tu token en el archivo .env
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

        // Hacer la solicitud POST a la API de Monday con el query GraphQL
        try {
            $response = $this->client->post('https://api.monday.com/v2', [
                'headers' => [
                    'Authorization' => $this->mondayToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                ],
            ]);

            // Decodificar y devolver la respuesta de la API de Monday
            $body = json_decode($response->getBody(), true);
            return response()->json($body);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al realizar la consulta', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Método para obtener la información de los tableros (boards)
     */
    public function getBoards($page = 1, $limit = null)
    {
        $limit = $limit ?? $this->limit;

        $query = <<<GRAPHQL
        {
            boards(limit: $limit, page:$page) {
                id
                url
                name
                state
                workspace_id
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        $response = $this->query(new Request(['query' => $query]));
        return response()->json($response->original['data']['boards']);
    }

    /**
     * Método para obtener los elementos (items) de un tablero específico
     */
    public function getItemsByBoard($boardId, $columns = null, $cursor = null, $limit = null, $rules = [])
    {
        $columnsData = '';
        $queryParams = '';
        // Obtener las columnas a solicitar
        if ($columns) {
            $columns = explode(',', $columns);
        } else {
            $columns = array_keys($this->itemsData);
        }
        // Crear la cadena de columnas a solicitar
        foreach ($columns as $column) {
            $columnsData .= $this->itemsData[$column] . "
            column{title}";
        }

        // Obtener el límite de elementos (items) del tablero
        $limit = $limit ?? $this->limit;

        if ($cursor === null) {
            $cursor = 'null';
        } else {
            $cursor = '"' . $cursor . '"';
        }

        $queryParams .= 'rules: [';
        if (count($rules)) {
            foreach ($rules as $key => $value) {
                $queryParams .= '{column_id: "' . $value['column_id'] . '", operator: ' . $value['operator'] . ', compare_value: ' . json_encode($value['compare_value']) . '}';
                if ($key < count($rules) - 1) {
                    $queryParams .= ',';
                }
            }
        }
        $queryParams .= ']';
        $query = <<<GRAPHQL
        {
            boards(limit: 1, ids: ["$boardId"]) {
                name
                url
                items_page(limit: $limit, cursor: $cursor, query_params:{ $queryParams }) {
                    items {
                        id
                        url
                        name
                        updated_at
                        column_values {
                            $columnsData
                        }
                    }
                    cursor # Cursor para paginar los items
                }
            }
        }
        GRAPHQL;
        // Llamar al método query para realizar la petición GraphQL
        return $this->query(new Request(['query' => $query]));
    }

    /**
     * Método para duplicar un tablero en monday.com a taves de la api
     *
     */
    public function duplicateBoard(Request $request)
    {
        $boardId = $request->input('boardId');
        $boardName = $request->input('boardName');
        $query = <<<GRAPHQL
        mutation {
            duplicate_board (board_id:$boardId, duplicate_type: duplicate_board_with_pulses_and_updates, board_name: $boardName ) {
                board {
                    id
                    name
                }
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        return $this->query(new Request(['query' => $query]));
    }

    public function getUsers($page = 1, $limit = null)
    {
        $limit = $limit ?? $this->limit;

        $query = <<<GRAPHQL
        {
            users(limit: $limit, page:$page) {
                id
                name
                email
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        $response = $this->query(new Request(['query' => $query]));
        return response()->json($response->original['data']['users']);
    }

    public function getUser($userId, $bypassLocal = false)
    {
//        dd($userId);
        $user = null;
        //get user from database
        if (!$bypassLocal) {
            $user = User::where('monday_user_id', $userId)->first();
        }
        //if not exists get user from monday
        if (!$user) {
            $response = $this->getUserRequest($userId);
            //check if user exists in local database monday_user_id

            return $response->original['data']['users'][0];
        } else {
            return $user;
        }
    }

    public function getUserRequest($userId)
    {
        $query = <<<GRAPHQL
        {
            users(ids: [$userId]) {
                id
                name
                email
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        return $this->query(new Request(['query' => $query]));
    }

    /**
     * Método para obtener la información de los tableros (boards)
     */
    public function findBoardIdByClientId($clientId)
    {
        $page = 1;
        $foundBoard = null;

        do {
            $boards = $this->getBoards($page)->original;

//            $response = $this->query(new Request(["query" => $query]));
//            dd($boards->original);


            if (count($boards) > 0) {
                foreach ($boards as $board) {
                    //split board name with _
                    $mondayClientId = explode('_', $board['name']);

                    //check if the first part of the board name is equal to the client id
                    if ($mondayClientId[0] === $clientId) {
                        $foundBoard = $board;
                        break;
                    }
                }

                if ($foundBoard) {
                    break;
                }

                $page++;
            } else {
                break;
            }
        } while ($page);
        return $foundBoard;
    }

    /**
     * Método para obtener la información de los tableros (boards)
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


    public function findBoardIdByName($boardName)
    {
        $page = 1;
        $foundBoard = null;

        do {
            $boards = $this->getBoards($page)->original;

            if (count($boards) > 0) {
                foreach ($boards as $board) {
                    if ($board['name'] === $boardName) {
                        $foundBoard = $board;
                        break;
                    }
                }

                if ($foundBoard) {
                    break;
                }

                $page++;
            } else {
                break;
            }
        } while ($page);
        return $foundBoard;
    }


    //method to get id of board by name
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
     * @param $boardId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBoardById($boardId)
    {
        $query = <<<GRAPHQL
        {
            boards(ids: [$boardId]) {
                id
                name
                state
                workspace_id
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        $response = $this->query(new Request(['query' => $query]));
        return response()->json($response->original['data']['boards']);
    }


    //get boards by array of ids
    public function getBoardsByIds($boardIds)
    {
        $boardIdsStringified = '[';
        foreach ($boardIds as $boardId) {
            $boardIdsStringified .= '"' . $boardId . '",';
        }
        //remove last comma
        $boardIdsStringified = rtrim($boardIdsStringified, ',');
        $boardIdsStringified .= ']';
        //array to string


        $query = <<<GRAPHQL
        {
            boards(ids: $boardIdsStringified) {
                id
                name
                state
                workspace_id
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        $response = $this->query(new Request(['query' => $query]));

        return response()->json($response->original['data']['boards']);
    }

    public function getTimeTrakingMondayBoardSummary($boardId, $fromDate = null, $toDate = null)
    {

        $columns = 'TimeTrackingValue,PeopleValue';
        $cursor = null;
        $usersData = [];
        do {
            $boardResponse = $this->getItemsByBoard($boardId, $columns, $cursor);

            if ($boardResponse->status() !== 200) {
                return response()->json(['error' => 'Error al obtener los items del tablero'], 500);
            }
            $board = $boardResponse->original;
            $items = $board['data']['boards'][0]['items_page']['items'];
            $cursor = $board['data']['boards'][0]['items_page']['cursor'];
            if ($cursor === 'null') {
                $cursor = null;
            }
            $board_name = $board['data']['boards'][0]['name'];
            foreach ($items as $item) {
                foreach ($item['column_values'] as $column) {
                    if (!empty($column['history'])) {
                        foreach ($column['history'] as $record) {

                            $startTime = $record['started_at'] ?? $record['manually_entered_start_date'];
                            $endTime = $record['ended_at'] ?? $record['manually_entered_end_date'];


                            $manuallyEntered = !empty($record['manually_entered_start_date']) || !empty($record['manually_entered_end_time']) ? 'Sí' : 'No';
                            $user = $this->getUser($record['started_user_id']);
//                            dd($user);
                            // Obtener nombres de usuario
                            $startedUserName = $user['name'];

                            if ($user['email']) {
                                $userId = $record['started_user_id'];

                                // Crear entrada para el usuario si no existe
                                if (!isset($usersData[$userId])) {
                                    $usersData[$userId] = [
                                        'name' => $startedUserName,
                                        'slack_user_id' => $user['slack_user_id'] ?? null,
                                        'tableros' => []
                                    ];
                                }


                                // Calcular la duración
                                if ($endTime) {
                                    $duration = (strtotime($endTime) - strtotime($startTime)) / (60 * 60);
                                    if ($duration < 0) {
                                        dd($duration, $startTime, $endTime, $record);
                                    }
                                    if (!isset($usersData[$userId]['tableros'][$board_name][$item['name']])) {
                                        $usersData[$userId]['tableros'][$board_name][$item['name']] = [
                                            'tarea' => $item['name'],
                                            'duracion' => (float)number_format($duration, 2),
                                            'manual' => $manuallyEntered
                                        ];
                                    } else {
                                        $usersData[$userId]['tableros'][$board_name][$item['name']]['duracion'] += (float)number_format($duration, 2);
                                    }
                                }
                            }
                        }
                    }
                }
            }

        } while ($cursor !== null);
        //RETURN USERSDATA VARIABLE IN JSON FORMAT
        return $usersData;
    }

    /**
     * Generar el reporte de horas trabajadas basado en un rango de fechas.
     *
     * @param string $fromDate Fecha de inicio en formato 'YYYY-MM-DD'.
     * @param string|null $toDate Fecha de fin en formato 'YYYY-MM-DD'.
     * Si solo se proporciona uno, o ambos son iguales, se calculará para solo ese día.
     */
    public function generateTimeTrackingReport($usersData)
    {
        $report = '';
        $globalTotalHours = 0;
        foreach ($usersData as $user) {
            $report .= "Usuario: *{$user['name']}*\n";
            $totalUserHours = 0;
            foreach ($user['tableros'] as $tablero => $actividades) {
                $totalHours = 0;
                $report .= "  Tablero: *$tablero*";

                $report .= ":\n";
                foreach ($actividades as $actividad) {
//                    dd($actividad);
                    try {
                        $report .= "    Actividad: *{$actividad['tarea']}* - ";
                        $report .= "Tiempo: " . gmdate('H:i', $actividad['duracion'] * 3600) . " horas - ";
                        $report .= "Manual: {$actividad['manual']}\n";
                        $totalHours += $actividad['duracion'];
                    } catch (\Exception $e) {
                        error_log($e->getMessage() . ' Error en la generación del reporte' . $actividad->toArray());
                    }

                }

                $report .= " - Total de horas: " . gmdate('H:i', $totalHours * 3600) . " horas\n";
                $totalUserHours += $totalHours;
                $globalTotalHours += $totalHours;
            }
//            dd($totalUserHours, $globalTotalHours, gmdate('H:i', $totalUserHours * 3600), gmdate('H:i', $globalTotalHours * 3600));
//            $globalTotalHours += $totalUserHours;
            $report .= "  Total de horas trabajadas por {$user['name']}: " . gmdate('H:i', $totalUserHours * 3600) . " horas\n";
            $report .= "*----------------------------------------*\n\n";
        }
        $report .= "Total de horas: " . gmdate('H:i', $globalTotalHours * 3600) . " horas\n";

        return $report;
    }

    public function getTasksOfBoards($boardsIds)
    {
        $tasks = [];
        /*foreach ($boardsIds as $boardId){
            $columns = 'StatusValue,TextValue,DateValue,TimeTrackingValue,PeopleValue';
            $cursor = null;
            do {
                $boardResponse = $this->getItemsByBoard($boardId, $columns, $cursor);
                if ($boardResponse->status() !== 200) {
                    return response()->json(['error' => 'Error al obtener los items del tablero'], 500);
                }
                $board = $boardResponse->original;
                $items = $board['data']['boards'][0]['items_page']['items'];
                $cursor = $board['data']['boards'][0]['items_page']['cursor'];
                if ($cursor === 'null') {
                    $cursor = null;
                }
                foreach ($items as $item) {
                    $task = [
                        'name' => $item['name'],
                        'status' => $item['column_values'][0]['label'],
                        'description' => $item['column_values'][1]['text'],
                        'deadline' => $item['column_values'][2]['date'],
                        'time_tracking' => $item['column_values'][3]['text'],
                        'assigned' => $item['column_values'][4]['persons_and_teams']
                    ];
                    array_push($tasks, $task);
                }
            } while ($cursor !== null);
        }*/
        return $tasks;

    }
}
