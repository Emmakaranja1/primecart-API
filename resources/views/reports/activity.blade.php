<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Activity Report</title>
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
        .action-column {
            max-width: 200px;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PrimeCart Activity Report</h1>
        <p>Generated on: {{ $generated_at }}</p>
    </div>

    @if($filters['start_date'] || $filters['end_date'] || $filters['user_id'] || $filters['action'])
    <div class="filters">
        <strong>Filters Applied:</strong><br>
        @if($filters['start_date'])
            Start Date: {{ $filters['start_date'] }}<br>
        @endif
        @if($filters['end_date'])
            End Date: {{ $filters['end_date'] }}<br>
        @endif
        @if($filters['user_id'])
            User ID: {{ $filters['user_id'] }}<br>
        @endif
        @if($filters['action'])
            Action: {{ $filters['action'] }}
        @endif
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Activity ID</th>
                <th>User</th>
                <th>User Email</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Entity ID</th>
                <th>IP Address</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $activity)
            <tr>
                <td>{{ $activity->id }}</td>
                <td>{{ $activity->user->username ?? 'N/A' }}</td>
                <td>{{ $activity->user->email ?? 'N/A' }}</td>
                <td class="action-column">{{ $activity->action }}</td>
                <td>{{ $activity->entity }}</td>
                <td>{{ $activity->entity_id ?? 'N/A' }}</td>
                <td>{{ $activity->ip_address ?? 'N/A' }}</td>
                <td>{{ $activity->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center">No activities found matching the criteria.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Total Activities: {{ $data->count() }}</p>
        <p>PrimeCart E-commerce Platform</p>
    </div>
</body>
</html>