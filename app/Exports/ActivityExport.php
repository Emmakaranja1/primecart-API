<?php

namespace App\Exports;

use App\Models\ActivityLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActivityExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $startDate;
    protected $endDate;
    protected $userId;
    protected $action;

    public function __construct($startDate = null, $endDate = null, $userId = null, $action = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->userId = $userId;
        $this->action = $action;
    }

    public function query()
    {
        $query = ActivityLog::with('user:id,username,email');

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->action) {
            $query->where('action', 'like', '%' . $this->action . '%');
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Activity ID',
            'User',
            'User Email',
            'Action',
            'Entity',
            'Entity ID',
            'IP Address',
            'Timestamp'
        ];
    }

    public function map($activity): array
    {
        return [
            $activity->id,
            $activity->user->username ?? 'N/A',
            $activity->user->email ?? 'N/A',
            $activity->action,
            $activity->entity,
            $activity->entity_id,
            $activity->ip_address ?? 'N/A',
            $activity->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}