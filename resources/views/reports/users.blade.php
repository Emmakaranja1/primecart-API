<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Users Report</title>
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
    </style>
</head>
<body>
    <div class="header">
        <h1>PrimeCart Users Report</h1>
        <p>Generated on: {{ $generated_at }}</p>
    </div>

    @if($filters['start_date'] || $filters['end_date'])
    <div class="filters">
        <strong>Filters Applied:</strong><br>
        @if($filters['start_date'])
            Start Date: {{ $filters['start_date'] }}<br>
        @endif
        @if($filters['end_date'])
            End Date: {{ $filters['end_date'] }}
        @endif
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Phone Number</th>
                <th>Address</th>
                <th>Registration Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $user)
            <tr>
                <td>{{ $user->id }}</td>
                <td>{{ $user->username }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ ucfirst($user->role) }}</td>
                <td>{{ ucfirst($user->status) }}</td>
                <td>{{ $user->phone_number ?? 'N/A' }}</td>
                <td>{{ $user->address ?? 'N/A' }}</td>
                <td>{{ $user->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center">No users found matching the criteria.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Total Users: {{ $data->count() }}</p>
        <p>PrimeCart E-commerce Platform</p>
    </div>
</body>
</html>