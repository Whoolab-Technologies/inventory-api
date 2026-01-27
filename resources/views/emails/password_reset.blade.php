<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #ffffff;
            color: #333333;
            padding: 20px;
            line-height: 1.6;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            margin-top: 20px;
            background-color: #007bff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        a:hover {
            opacity: 0.9;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a;
                color: #f0f0f0;
            }

            .button {
                background-color: #3399ff;
            }
        }
    </style>
</head>

<body>
    <h2>Password Reset Request</h2>

    <p>Hello,</p>

    <p>We received a request to reset the password associated with this email address. If you made this request, you can
        reset your password by clicking the button below:</p>

    <p>
        <a href="{{ $resetUrl }}" class="button">Reset Your Password</a>
    </p>

    <p>Or copy and paste the following link into your browser:</p>
    <p>
        <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </p>

    <p>This password reset link will expire in 30 minutes for your security.</p>

    <p>If you did not request a password reset, please ignore this email or contact support if you have questions.</p>

    <p>Thank you,<br> Support Team</p>
</body>

</html>