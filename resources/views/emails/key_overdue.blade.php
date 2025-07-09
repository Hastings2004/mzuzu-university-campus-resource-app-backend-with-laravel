<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Key Overdue - Campus Resource Booking System</title>
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
            color: #dc3545;
            margin-bottom: 10px;
        }
        .title {
            color: #dc3545;
            font-size: 20px;
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #dc3545;
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #c82333;
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
            color: #dc3545;
        }
        .details-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .urgent {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="Mzuzu University Logo" class="logo">
            <div class="logo">Mzuzu University Campus Resource Booking System</div>
            <div class="title">URGENT: Key Overdue</div>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $notifiable->first_name }} {{ $notifiable->last_name }}</strong>,</p>
            
            <div class="urgent">
                <strong>🚨 URGENT:</strong> Your key is overdue and needs to be returned immediately!
            </div>
            
            <div class="warning">
                <strong>⚠️ Important:</strong> This key was due back {{ $overdueHours }} hour(s) ago. Please return it as soon as possible to avoid any penalties or restrictions on future bookings.
            </div>
            
            <p>This is an urgent reminder that you have not returned the key for the resource you booked. Please return the key to the designated custodian immediately.</p>
            
            <table class="details-table">
                <tr>
                    <th>Key Code</th>
                    <td>{{ $transaction->key->key_code }}</td>
                </tr>
                <tr>
                    <th>Resource</th>
                    <td>{{ $transaction->key->resource->name }}</td>
                </tr>
                <tr>
                    <th>Checked Out</th>
                    <td>{{ $transaction->checked_out_at->format('Y-m-d H:i:s') }}</td>
                </tr>
                <tr>
                    <th>Expected Return</th>
                    <td>{{ $transaction->expected_return_at->format('Y-m-d H:i:s') }}</td>
                </tr>
                <tr>
                    <th>Overdue By</th>
                    <td>{{ $overdueHours }} hour(s)</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>{{ ucfirst($transaction->status) }}</td>
                </tr>
            </table>
            
            <div style="text-align: center;">
                <a href="{{ url('http://localhost:5173/booking/' . $transaction->booking->id) }}" class="button">View Booking Details</a>
            </div>
            
            <p><strong>Immediate Action Required:</strong></p>
            <ul>
                <li>Return the key to the designated custodian immediately</li>
                <li>Contact the campus administration if you cannot locate the key</li>
                <li>Report any issues or delays to avoid further penalties</li>
            </ul>
            
            <p><strong>Consequences of Late Returns:</strong></p>
            <ul>
                <li>Potential fines or penalties</li>
                <li>Restrictions on future key checkouts</li>
                <li>Impact on your booking privileges</li>
            </ul>
        </div>
        <div class="footer">
            <p>Thank you for your immediate attention to this matter.</p>
            <p><strong>Mzuzu University Campus Resource Management Team</strong></p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html> 