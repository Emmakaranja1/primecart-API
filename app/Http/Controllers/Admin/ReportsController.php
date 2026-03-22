<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ActivityExport;
use App\Exports\OrdersExport;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportsController extends Controller
{
    /**
     * Generate users report with optional date filtering.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function usersReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = User::query();

        
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $limit = $request->get('limit', 20);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $users = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'username', 'email', 'role', 'status', 'created_at']);

        $total = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ],
                'filters' => [
                    'start_date' => $request->get('start_date'),
                    'end_date' => $request->get('end_date'),
                ]
            ]
        ]);
    }

    /**
     * Generate orders report with optional date filtering.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ordersReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'status' => 'nullable|in:pending,approved,rejected,delivered',
            'payment_status' => 'nullable|in:pending,paid,failed',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Order::with('user:id,username,email');

        
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $limit = $request->get('limit', 20);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $orders = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'user_id', 'total_amount', 'status', 'payment_status', 'payment_method', 'created_at']);

        $total = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ],
                'filters' => [
                    'start_date' => $request->get('start_date'),
                    'end_date' => $request->get('end_date'),
                    'status' => $request->get('status'),
                    'payment_status' => $request->get('payment_status'),
                ]
            ]
        ]);
    }

    /**
     * Generate activity logs report with optional date filtering.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function activityReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'user_id' => 'nullable|integer|exists:users,id',
            'action' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = ActivityLog::with('user:id,username,email');

        
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        
        if ($request->has('action')) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        $limit = $request->get('limit', 20);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $activities = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'user_id', 'action', 'entity', 'entity_id', 'ip_address', 'created_at']);

        $total = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'activities' => $activities,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ],
                'filters' => [
                    'start_date' => $request->get('start_date'),
                    'end_date' => $request->get('end_date'),
                    'user_id' => $request->get('user_id'),
                    'action' => $request->get('action'),
                ]
            ]
        ]);
    }

    /**
     * Export reports in Excel or PDF format.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function exportReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:users,orders,activity',
            'format' => 'required|in:excel,pdf',
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'status' => 'nullable|in:pending,approved,rejected,delivered',
            'payment_status' => 'nullable|in:pending,paid,failed',
            'user_id' => 'nullable|integer|exists:users,id',
            'action' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $reportType = $request->report_type;
        $format = $request->format;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $filename = $this->generateFilename($reportType, $format, $startDate, $endDate);

        try {
            if ($format === 'excel') {
                return $this->exportToExcel($reportType, $filename, $request);
            } else {
                return $this->exportToPdf($reportType, $filename, $request);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate filename for export.
     *
     * @param string $reportType
     * @param string $format
     * @param string|null $startDate
     * @param string|null $endDate
     * @return string
     */
    private function generateFilename($reportType, $format, $startDate = null, $endDate = null)
    {
        $dateRange = '';
        if ($startDate && $endDate) {
            $dateRange = "_{$startDate}_to_{$endDate}";
        } elseif ($startDate) {
            $dateRange = "_from_{$startDate}";
        } elseif ($endDate) {
            $dateRange = "_until_{$endDate}";
        }

        $extension = $format === 'excel' ? 'xlsx' : 'pdf';
        return "{$reportType}_report{$dateRange}." . $extension;
    }

    /**
     * Export to Excel format.
     *
     * @param string $reportType
     * @param string $filename
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportToExcel($reportType, $filename, $request)
    {
        switch ($reportType) {
            case 'users':
                return Excel::download(
                    new UsersExport($request->start_date, $request->end_date),
                    $filename
                );
            
            case 'orders':
                return Excel::download(
                    new OrdersExport(
                        $request->start_date,
                        $request->end_date,
                        $request->status,
                        $request->payment_status
                    ),
                    $filename
                );
            
            case 'activity':
                return Excel::download(
                    new ActivityExport(
                        $request->start_date,
                        $request->end_date,
                        $request->user_id,
                        $request->action
                    ),
                    $filename
                );
            
            default:
                throw new \InvalidArgumentException("Invalid report type: {$reportType}");
        }
    }

    /**
     * Export to PDF format.
     *
     * @param string $reportType
     * @param string $filename
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    private function exportToPdf($reportType, $filename, $request)
    {
        $data = $this->getReportData($reportType, $request);
        
        $pdf = Pdf::loadView("reports.{$reportType}", [
            'data' => $data,
            'filters' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->status,
                'payment_status' => $request->payment_status,
                'user_id' => $request->user_id,
                'action' => $request->action,
            ],
            'generated_at' => now()->format('Y-m-d H:i:s')
        ]);

        return $pdf->download($filename);
    }

    /**
     * Get report data for PDF export.
     *
     * @param string $reportType
     * @param Request $request
     * @return array
     */
    private function getReportData($reportType, $request)
    {
        switch ($reportType) {
            case 'users':
                $query = User::query();
                if ($request->start_date) {
                    $query->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->end_date) {
                    $query->whereDate('created_at', '<=', $request->end_date);
                }
                return $query->orderBy('created_at', 'desc')->get();
            
            case 'orders':
                $query = Order::with('user:id,username,email');
                if ($request->start_date) {
                    $query->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->end_date) {
                    $query->whereDate('created_at', '<=', $request->end_date);
                }
                if ($request->status) {
                    $query->where('status', $request->status);
                }
                if ($request->payment_status) {
                    $query->where('payment_status', $request->payment_status);
                }
                return $query->orderBy('created_at', 'desc')->get();
            
            case 'activity':
                $query = ActivityLog::with('user:id,username,email');
                if ($request->start_date) {
                    $query->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->end_date) {
                    $query->whereDate('created_at', '<=', $request->end_date);
                }
                if ($request->user_id) {
                    $query->where('user_id', $request->user_id);
                }
                if ($request->action) {
                    $query->where('action', 'like', '%' . $request->action . '%');
                }
                return $query->orderBy('created_at', 'desc')->get();
            
            default:
                return [];
        }
    }
}
