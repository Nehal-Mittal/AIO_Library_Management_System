<?php
/**
 * Email OTP Helper
 * 
 * This file provides a wrapper function for sending OTP emails.
 * It uses the send_email() function from config.php which handles
 * all SMTP configuration via environment variables.
 * 
 * To configure email:
 * Set these environment variables:
 * - SMTP_HOST (e.g., smtp.gmail.com)
 * - SMTP_USERNAME (your email)
 * - SMTP_PASSWORD (your app password for Gmail)
 * - SMTP_PORT (587 for TLS, 465 for SSL)
 * - MAIL_FROM_ADDRESS (sender email)
 * - MAIL_FROM_NAME (sender name)
 */

require_once __DIR__ . '/../config.php';

function send_email_otp($to, $otp) {
    $subject = 'Your Email OTP Code';
    $htmlBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #333;'>Library Management System</h2>
        <p>Hello,</p>
        <p>Your One-Time Password (OTP) for email verification is:</p>
        <div style='background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px;'>
            {$otp}
        </div>
        <p>This OTP will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
        <p style='color: #666; font-size: 12px;'>If you didn't request this code, please ignore this email.</p>
        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='color: #999; font-size: 11px;'>This is an automated message. Please do not reply.</p>
    </div>
    ";
    
    $textBody = "Your OTP code is: {$otp}. It expires in " . OTP_EXPIRY_MINUTES . " minutes.";
    
    $result = send_email($to, $subject, $htmlBody, $textBody);
    
    if ($result) {
        return ['success' => true, 'message' => 'OTP email sent successfully.'];
    } else {
        return ['success' => false, 'message' => 'Failed to send OTP email. Please check your email configuration.'];
    }
}
