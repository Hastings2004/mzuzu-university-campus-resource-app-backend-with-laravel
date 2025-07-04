<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Campus Resource Booking System</title>
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
            color: #2563eb;
            margin-bottom: 10px;
        }
        .title {
            color: #1f2937;
            font-size: 20px;
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #dc2626;
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #b91c1c;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .warning {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #92400e;
        }
        .info {
            background-color: #dbeafe;
            border: 1px solid #3b82f6;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="Mzuzu University Logo" class="logo">
            <div class="logo">Mzuzu University Campus Resource Booking System</div>
            <div class="title">Reset Your Password</div>
        </div>
        
        <div class="content">
            <p>Hello <strong>{{ $notifiable->first_name }} {{ $notifiable->last_name }}</strong>,</p>
            
            <p>You are receiving this email because we received a password reset request for your account in the Mzuzu University Campus Resource Booking System.</p>
            
            <div style="text-align: center;">
                <a href="{{ $url }}" class="button">Reset Password</a>
            </div>
            
            <div class="warning">
                <strong>Important:</strong> This password reset link will expire in 60 minutes. If you don't reset your password within this time, you'll need to request a new reset link.
            </div>
            
            <div class="info">
                <strong>Security Notice:</strong> If you did not request a password reset, please ignore this email. Your password will remain unchanged.
            </div>
            
            <p>If the button above doesn't work, you can copy and paste the following link into your browser:</p>
            <p style="word-break: break-all; color: #2563eb;">{{ $url }}</p>
            
            <p>If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:</p>
            <p style="word-break: break-all; color: #6b7280; font-size: 12px;">{{ $url }}</p>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>
            <strong>Mzuzu University Campus Resource Management Team</strong></p>
            
            <p>This is an automated message. Please do not reply to this email.</p>
            
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html> 