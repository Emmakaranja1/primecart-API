<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orders Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .filters {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .status-paid {
            color: #28a745;
            font-weight: bold;
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
        .status-failed {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PrimeCart Orders Report</h1>
        <p>Generated on: {{ $generated_at }}</p>
    </div>

    @if($filters['start_date'] || $filters['end_date'] || $filters['status'] || $filters['payment_status'])
    <div class="filters">
        <strong>Filters Applied:</strong><br>
        @if($filters['start_date'])
            Start Date: {{ $filters['start_date'] }}<br>
        @endif
        @if($filters['end_date'])
            End Date: {{ $filters['end_date'] }}<br>
        @endif
        @if($filters['status'])
            Order Status: {{ ucfirst($filters['status']) }}<br>
        @endif
        @if($filters['payment_status'])
            Payment Status: {{ ucfirst($filters['payment_status']) }}
        @endif
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Customer Email</th>
                <th>Total Amount</th>
                <th>Order Status</th>
                <th>Payment Status</th>
                <th>Payment Method</th>
                <th>Transaction Reference</th>
                <th>Order Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $order)
            <tr>
                <td>{{ $order->id }}</td>
                <td>{{ $order->user->username ?? 'N/A' }}</td>
                <td>{{ $order->user->email ?? 'N/A' }}</td>
                <td class="text-right">${{ number_format($order->total_amount, 2) }}</td>
                <td>{{ ucfirst($order->status) }}</td>
                <td class="status-{{ $order->payment_status }}">{{ ucfirst($order->payment_status) }}</td>
                <td>{{ $order->payment_method ?? 'N/A' }}</td>
                <td>{{ $order->transaction_reference ?? 'N/A' }}</td>
                <td>{{ $order->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No orders found matching the criteria.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Total Orders: {{ $data->count() }}</p>
        <p>Total Revenue: ${{ number_format($data->sum('total_amount'), 2) }}</p>
        <p>PrimeCart E-commerce Platform</p>
    </div>
</body>
</html>