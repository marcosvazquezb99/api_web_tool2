<?php

namespace App\Http\Controllers;

use App\Services\Sync\HolidaySyncService;
use App\Services\Sync\Holded\HoldedCustomerServiceSyncService;
use App\Services\Sync\Holded\HoldedCustomerSyncService;
use App\Services\Sync\Holded\HoldedEmployeeSyncService;
use App\Services\Sync\Holded\HoldedServiceSyncService;
use App\Services\Sync\Monday\BoardSyncService;
use App\Services\Sync\SlackChannelSyncService;
use App\Services\Sync\UserSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    /**
     * Sync users from Monday.com and Slack
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncUsers(UserSyncService $userSyncService)
    {
        try {
            $userSyncService->setOutputCallback(function ($message, $type) {
                Log::info("User Sync: {$message}");
            });

            $result = $userSyncService->syncAll();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during user synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Slack channels
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncSlackChannels(SlackChannelSyncService $slackChannelSyncService)
    {
        try {
            $slackChannelSyncService->setOutputCallback(function ($message, $type) {
                Log::info("Slack Channel Sync: {$message}");
            });

            $result = $slackChannelSyncService->sync();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during Slack channel synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync holidays
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncHolidays(Request $request, HolidaySyncService $holidaySyncService)
    {
        try {
            $country = $request->input('country', 'ES');
            $region = $request->input('region');
            $year = $request->input('year', date('Y'));
            $years = $request->input('years', 1);

            $holidaySyncService->setOutputCallback(function ($message, $type) {
                Log::info("Holiday Sync: {$message}");
            });

            $result = $holidaySyncService->syncHolidays($country, $region, $year, $years);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during holiday synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Monday boards
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncBoards(BoardSyncService $boardSyncService)
    {
        try {
            $boardSyncService->setOutputCallback(function ($message, $type) {
                Log::info("Board Sync: {$message}");
            });

            $result = $boardSyncService->syncBoards();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during board synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Holded services
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncHoldedServices(HoldedServiceSyncService $serviceSyncService)
    {
        try {
            $serviceSyncService->setOutputCallback(function ($message, $type) {
                Log::info("Holded Service Sync: {$message}");
            });

            $result = $serviceSyncService->syncServices();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during Holded service synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Holded employees
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncHoldedEmployees(HoldedEmployeeSyncService $employeeSyncService)
    {
        try {
            $employeeSyncService->setOutputCallback(function ($message, $type) {
                Log::info("Holded Employee Sync: {$message}");
            });

            $result = $employeeSyncService->syncEmployees();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during Holded employee synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Holded customer services
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncHoldedCustomerServices(HoldedCustomerServiceSyncService $customerServiceSyncService)
    {
        try {
            $customerServiceSyncService->setOutputCallback(function ($message, $type) {
                Log::info("Holded Customer Service Sync: {$message}");
            });

            $result = $customerServiceSyncService->syncCustomerServices();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during Holded customer service synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Holded customers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncHoldedCustomers(HoldedCustomerSyncService $customerSyncService)
    {
        try {
            $customerSyncService->setOutputCallback(function ($message, $type) {
                Log::info("Holded Customer Sync: {$message}");
            });

            $result = $customerSyncService->syncCustomers();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during Holded customer synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync all Holded data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncAllHolded(
        HoldedCustomerSyncService        $customerSyncService,
        HoldedServiceSyncService         $serviceSyncService,
        HoldedEmployeeSyncService        $employeeSyncService,
        HoldedCustomerServiceSyncService $customerServiceSyncService
    )
    {
        try {
            // Configurar callback para logging
            $logCallback = function ($message, $type) {
                Log::info("Holded Sync: {$message}");
            };

            $customerSyncService->setOutputCallback($logCallback);
            $serviceSyncService->setOutputCallback($logCallback);
            $employeeSyncService->setOutputCallback($logCallback);
            $customerServiceSyncService->setOutputCallback($logCallback);

            // Ejecutar sincronización
            $customerResult = $customerSyncService->syncCustomers();
            $serviceResult = $serviceSyncService->syncServices();
            $employeeResult = $employeeSyncService->syncEmployees();
            $customerServiceResult = $customerServiceSyncService->syncCustomerServices();

            return response()->json([
                'success' => true,
                'message' => 'Holded synchronization completed',
                'data' => [
                    'customers' => $customerResult['data'] ?? [],
                    'services' => $serviceResult['data'] ?? [],
                    'employees' => $employeeResult['data'] ?? [],
                    'customerServices' => $customerServiceResult['data'] ?? []
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during Holded synchronization',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
