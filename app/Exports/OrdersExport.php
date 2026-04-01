<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithChunkReading
{
    protected $startDate;
    protected $endDate;
    protected $status;
    protected $paymentStatus;

    public function __construct($startDate = null, $endDate = null, $status = null, $paymentStatus = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
        $this->paymentStatus = $paymentStatus;
    }

    public function query()
    {
        $query = Order::with('user:id,username,email');

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->paymentStatus) {
            $query->where('payment_status', $this->paymentStatus);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Order ID',
            'Customer',
            'Customer Email',
            'Total Amount',
            'Order Status',
            'Payment Status',
            'Payment Method',
            'Transaction Reference',
            'Order Date'
        ];
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->user->username ?? 'N/A',
            $order->user->email ?? 'N/A',
            number_format($order->total_amount, 2),
            ucfirst($order->status),
            ucfirst($order->payment_status),
            $order->payment_method ?? 'N/A',
            $order->transaction_reference ?? 'N/A',
            $order->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function chunkSize(): int
    {
        return 200;
    }
}