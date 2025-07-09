<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Ends Soon - Campus Resource Booking System</title>
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
            color: #ff6b35;
            margin-bottom: 10px;
        }
        .title {
            color: #ff6b35;
            font-size: 20px;
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #ff6b35;
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #e55a2b;
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
            color: #ff6b35;
        }
        .details-table tr:nth-child(even) {
            background-color: #f9fafb;
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
            <div class="title">Booking Ends Soon</div>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $notifiable->first_name }} {{ $notifiable->last_name }}</strong>,</p>
            
            <div class="warning">
                <strong>⚠️ Reminder:</strong> Your resource booking will end in 10 minutes.
            </div>
            
            <p>This is a friendly reminder that your booking will be completed soon. Please ensure you have finished using the resource and have collected all your belongings.</p>
            
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
                <tr>
                    <th>Status</th>
                    <td>{{ ucfirst($booking->status) }}</td>
                </tr>
            </table>
            
            <div style="text-align: center;">
                <a href="{{ url('http://localhost:5173/booking/' . $booking->id) }}" class="button">View Booking</a>
            </div>
            
            <p><strong>Important:</strong> Please ensure you leave the resource area promptly when your booking ends to allow the next user to begin their session on time.</p>
        </div>
        <div class="footer">
            <p>Thank you for using our Mzuzu University campus resource booking system!</p>
            <p><strong>Mzuzu University Campus Resource Management Team</strong></p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html> 