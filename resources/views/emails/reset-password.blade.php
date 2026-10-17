<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #F8FAFC;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1E293B;
            -webkit-text-size-adjust: 100%;
        }
        .container {
            max-width: 560px;
            margin: 40px auto;
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #E2E8F0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            padding: 32px 40px 24px;
            text-align: left;
            border-bottom: 1px solid #F1F5F9;
        }
        .brand {
            font-size: 22px;
            font-weight: 700;
            color: #0F172A;
            letter-spacing: -0.5px;
        }
        .brand span {
            color: #0062FF;
        }
        .content {
            padding: 36px 40px;
        }
        h1 {
            font-size: 20px;
            font-weight: 600;
            color: #0F172A;
            margin-top: 0;
            margin-bottom: 16px;
        }
        p {
            font-size: 15px;
            line-height: 24px;
            color: #475569;
            margin: 0 0 24px;
        }
        .btn-wrapper {
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #0062FF;
            color: #FFFFFF !important;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        .meta-info {
            background-color: #F8FAFC;
            border-radius: 8px;
            padding: 16px;
            border: 1px dashed #CBD5E1;
            margin-bottom: 24px;
        }
        .meta-info p {
            margin: 0;
            font-size: 13px;
            color: #64748B;
            line-height: 20px;
        }
        .break-link {
            font-size: 12px;
            color: #94A3B8;
            word-break: break-all;
            line-height: 18px;
        }
        .footer {
            padding: 24px 40px;
            background-color: #F8FAFC;
            border-top: 1px solid #F1F5F9;
            text-align: center;
        }
        .footer p {
            font-size: 12px;
            color: #94A3B8;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Brand Header -->
        <div class="header">
            <div class="brand">Collab<span>space</span></div>
        </div>

        <!-- Email Body -->
        <div class="content">
            <h1>Password Reset Request</h1>
            <p>Hello {{ $userName }},</p>
            <p>We received a request to reset your password for your <strong>CollabSpace</strong> account. Click the button below to choose a new one:</p>
            
            <div class="btn-wrapper">
                <a href="{{ $resetUrl }}" target="_blank" class="btn">Reset Password</a>
            </div>

            <div class="meta-info">
                <p>⏳ This link is valid for <strong>60 minutes</strong>. If you did not request this, you can safely ignore this email; your account remains secure.</p>
            </div>

            <p style="margin-bottom: 8px; font-size: 13px; color: #64748B;">Button not working? Copy and paste the link below into your browser:</p>
            <div class="break-link">{{ $resetUrl }}</div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; {{ date('Y') }} CollabSpace AI. All rights reserved.</p>
        </div>
    </div>
</body>
</html>