<?php

namespace App\Http\Controllers;

use App\Models\Holidays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class HolidaysController extends Controller
{
    /**
     * Muestra un listado de festivos.
     */
    public function index(Request $request)
    {
        $query = Holidays::query();

        // Filtros opcionales
        if ($request->has('year')) {
            $query->ofYear($request->year);
        }

        if ($request->has('country')) {
            $query->ofCountry($request->country);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->betweenDates($request->start_date, $request->end_date);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->per_page ?? 15)
        ]);
    }

    /**
     * Muestra un festivo específico.
     */
    public function show($id)
    {
        $holiday = Holidays::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $holiday
        ]);
    }

    /**
     * Almacena un nuevo festivo.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'local_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'country_code' => 'required|string|size:2',
            'year' => 'required|integer',
            'fixed' => 'boolean|nullable',
            'global' => 'boolean|nullable',
            'counties' => 'array|nullable',
            'types' => 'array|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $holiday = Holidays::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $holiday,
            'message' => 'Festivo creado correctamente'
        ], 201);
    }

    /**
     * Actualiza un festivo existente.
     */
    public function update(Request $request, $id)
    {
        $holiday = Holidays::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'date' => 'date',
            'local_name' => 'string|max:255',
            'name' => 'string|max:255',
            'country_code' => 'string|size:2',
            'year' => 'integer',
            'fixed' => 'boolean|nullable',
            'global' => 'boolean|nullable',
            'counties' => 'array|nullable',
            'types' => 'array|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $holiday->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $holiday,
            'message' => 'Festivo actualizado correctamente'
        ]);
    }

    /**
     * Elimina un festivo.
     */
    public function destroy($id)
    {
        $holiday = Holidays::findOrFail($id);
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Festivo eliminado correctamente'
        ]);
    }

    /**
     * Sincroniza festivos desde la API externa.
     */
    public function syncHolidays(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer',
            'country_code' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $response = Http::get("https://date.nager.at/api/v3/PublicHolidays/{$request->year}/{$request->country_code}");

            if ($response->successful()) {
                $holidays = $response->json();
                $importCount = 0;

                foreach ($holidays as $holiday) {
                    Holidays::updateOrCreate(
                        [
                            'date' => $holiday['date'],
                            'country_code' => $request->country_code,
                            'year' => $request->year,
                        ],
                        [
                            'local_name' => $holiday['localName'],
                            'name' => $holiday['name'],
                            'fixed' => $holiday['fixed'] ?? null,
                            'global' => $holiday['global'] ?? null,
                            'counties' => $holiday['counties'] ?? [],
                            'types' => $holiday['types'] ?? [],
                        ]
                    );
                    $importCount++;
                }

                return response()->json([
                    'success' => true,
                    'message' => "Se han importado $importCount festivos",
                    'count' => $importCount
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener datos de la API externa',
                    'error' => $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los países disponibles de la API.
     */
    public function getAvailableCountries()
    {
        try {
            $response = Http::get("https://date.nager.at/api/v3/AvailableCountries");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener países disponibles',
                    'error' => $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica si una fecha es festivo.
     */
    public function checkIfHoliday(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'country_code' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $date = $request->date;
        $year = date('Y', strtotime($date));

        $holiday = Holidays::where('date', $date)
            ->where('country_code', $request->country_code)
            ->first();

        return response()->json([
            'success' => true,
            'is_holiday' => !is_null($holiday),
            'holiday' => $holiday
        ]);
    }
}
