<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Rejected - Campus Resource Booking System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: green;
            margin-bottom: 10px;
        }
        .title {
            color: green;
            font-size: 20px;
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: green;
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: green;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .details-table th, .details-table td {
            text-align: left;
            padding: 8px 12px;
        }
        .details-table th {
            background-color: #f3f4f6;
            color: green;
        }
        .details-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .reason {
            background-color: #fee2e2;
            border: 1px solid #f87171;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #b91c1c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://127.0.0.1:8000/images/logo.png" alt="Mzuzu University Logo" class="logo">
            <div class="logo">Mzuzu University Campus Resource Booking System</div>
            <div class="title">Booking Rejected</div>
        </div>
        <div class="content">
            <p>Dear <strong>{{ $notifiable->first_name }} {{ $notifiable->last_name }}</strong>,</p>
            <p>We regret to inform you that your booking request could not be approved.</p>
            <table class="details-table">
                <tr>
                    <th>Booking Reference</th>
                    <td>{{ $booking->booking_reference }}</td>
                </tr>
                <tr>
                    <th>Resource</th>
                    <td>{{ $booking->resource->name }}</td>
                </tr>
                <tr>
                    <th>Start Time</th>
                    <td>{{ $booking->start_time->format('Y-m-d H:i:s') }}</td>
                </tr>
                <tr>
                    <th>End Time</th>
                    <td>{{ $booking->end_time->format('Y-m-d H:i:s') }}</td>
                </tr>
            </table>
            <div class="reason">
                <strong>Reason for rejection:</strong><br>
                {{ $reason }}
            </div>
            <p>This often happens when the resource is already booked by an activity with higher or equal priority during your requested time.</p>
            <div style="text-align: center;">
                <a href="{{ url('http://localhost:5173/viewResource') }}" class="button">Browse Available Resources</a>
            </div>
            <p>Please consider booking an alternative time or resource. We apologize for any inconvenience.</p>
            @if(!empty($suggestions))
                <div style="margin-top: 30px;">
                    <h3 style="color: green;">Suggested Alternatives</h3>
                    <ul>
                        @foreach($suggestions as $suggestion)
                            <li>
                                @if($suggestion['type'] === 'shifted_earlier')
                                    <strong>Earlier Slot:</strong> {{ $suggestion['start_time'] }} - {{ $suggestion['end_time'] }} (Same Resource)
                                @elseif($suggestion['type'] === 'shifted_later')
                                    <strong>Later Slot:</strong> {{ $suggestion['start_time'] }} - {{ $suggestion['end_time'] }} (Same Resource)
                                @elseif($suggestion['type'] === 'alternative_resource')
                                    <strong>Alternative Resource:</strong> Resource ID {{ $suggestion['resource_id'] }}, {{ $suggestion['start_time'] }} - {{ $suggestion['end_time'] }}
                                @elseif($suggestion['type'] === 'minor_overlap_allowed')
                                    <strong>Minor Overlap Allowed:</strong> {{ $suggestion['start_time'] }} - {{ $suggestion['end_time'] }} (Same Resource)
                                @else
                                    <strong>Suggestion:</strong> {{ $suggestion['start_time'] }} - {{ $suggestion['end_time'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <div class="footer">
            <p>Sincerely,<br>
            <strong>Mzuzu University Campus Resource Booking Team</strong></p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html> 