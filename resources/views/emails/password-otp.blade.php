<!DOCTYPE html>
<html>
<head>
    <title>PrimeCart Password Reset</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 30px; border-radius: 10px;">
        <h2 style="color: #333; text-align: center;">PrimeCart Password Reset</h2>
        
        <p style="color: #666; font-size: 16px;">Hello,</p>
        
        <p style="color: #666; font-size: 16px;">You requested to reset your password. Use the OTP code below to proceed:</p>
        
        <div style="background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px; margin: 20px 0;">
            <span style="font-size: 24px; font-weight: bold; letter-spacing: 3px;">{{ $otp }}</span>
        </div>
        
        <p style="color: #666; font-size: 16px;">This OTP will expire in <strong>10 minutes</strong>.</p>
        
        <p style="color: #666; font-size: 16px;">If you didn't request this password reset, please ignore this email.</p>
        
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        
        <p style="color: #999; font-size: 12px; text-align: center;">
            PrimeCart E-commerce Platform<br>
            This is an automated message, please do not reply.
        </p>
    </div>
</body>
</html>