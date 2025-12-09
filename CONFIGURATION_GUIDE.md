# Library Management System - Configuration Guide

This guide will help you configure Email (PHPMailer) and SMS (Twilio/Fast2SMS) for OTP functionality.

## 📧 Email Configuration (PHPMailer)

### Option 1: Using Gmail (Recommended for Testing)

1. **Enable 2-Step Verification** on your Gmail account
2. **Generate an App Password**:
   - Go to Google Account Settings → Security
   - Under "2-Step Verification", click "App passwords"
   - Generate a new app password for "Mail"
   - Copy the 16-character password

3. **Set Environment Variables** (or update `config.php` directly):

```bash
# For Windows (PowerShell)
$env:SMTP_HOST="smtp.gmail.com"
$env:SMTP_USERNAME="your_email@gmail.com"
$env:SMTP_PASSWORD="your_16_char_app_password"
$env:SMTP_PORT="587"
$env:MAIL_FROM_ADDRESS="your_email@gmail.com"
$env:MAIL_FROM_NAME="Library Management System"

# For Linux/Mac
export SMTP_HOST="smtp.gmail.com"
export SMTP_USERNAME="your_email@gmail.com"
export SMTP_PASSWORD="your_16_char_app_password"
export SMTP_PORT="587"
export MAIL_FROM_ADDRESS="your_email@gmail.com"
export MAIL_FROM_NAME="Library Management System"
```

### Option 2: Using Other SMTP Providers

**Outlook/Hotmail:**
- SMTP_HOST: `smtp-mail.outlook.com`
- SMTP_PORT: `587`
- SMTP_USERNAME: Your Outlook email
- SMTP_PASSWORD: Your Outlook password

**Yahoo:**
- SMTP_HOST: `smtp.mail.yahoo.com`
- SMTP_PORT: `587`
- SMTP_USERNAME: Your Yahoo email
- SMTP_PASSWORD: Your Yahoo app password

**Custom SMTP:**
- Update the SMTP settings in `config.php` or use environment variables

## 📱 SMS Configuration

### Option 1: Twilio (Recommended)

1. **Sign up** at [Twilio.com](https://www.twilio.com)
2. **Get your credentials**:
   - Account SID
   - Auth Token
   - Phone Number (with country code, e.g., +1234567890)

3. **Set Environment Variables**:

```bash
# Windows (PowerShell)
$env:TWILIO_ACCOUNT_SID="your_account_sid"
$env:TWILIO_AUTH_TOKEN="your_auth_token"
$env:TWILIO_PHONE_NUMBER="+1234567890"

# Linux/Mac
export TWILIO_ACCOUNT_SID="your_account_sid"
export TWILIO_AUTH_TOKEN="your_auth_token"
export TWILIO_PHONE_NUMBER="+1234567890"
```

### Option 2: Fast2SMS (India Only)

1. **Sign up** at [Fast2SMS.com](https://www.fast2sms.com)
2. **Get your API Key** from the dashboard
3. **Set Environment Variable**:

```bash
# Windows (PowerShell)
$env:FAST2SMS_API_KEY="your_api_key"

# Linux/Mac
export FAST2SMS_API_KEY="your_api_key"
```

## 🔧 Alternative: Direct Configuration in config.php

If you prefer not to use environment variables, you can directly edit `config.php`:

```php
// In app_mailer() function, replace:
$smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$smtpUsername = getenv('SMTP_USERNAME') ?: 'your_email@gmail.com';
$smtpPassword = getenv('SMTP_PASSWORD') ?: 'your_app_password';

// In send_sms() function, replace:
$twilioSid = getenv('TWILIO_ACCOUNT_SID') ?: 'your_twilio_sid';
$twilioToken = getenv('TWILIO_AUTH_TOKEN') ?: 'your_twilio_token';
$twilioFrom = getenv('TWILIO_PHONE_NUMBER') ?: '+1234567890';
```

## ✅ Testing Your Configuration

1. **Test Email**: Register a new user and check if OTP email is received
2. **Test SMS**: Register a new user and check if OTP SMS is received
3. **Check Logs**: View `storage/logs/app.log` for any errors

## 🐛 Troubleshooting

### Email Not Sending?

1. Check SMTP credentials are correct
2. For Gmail: Make sure you're using an App Password, not your regular password
3. Check firewall/antivirus isn't blocking SMTP connections
4. Enable `SMTPDebug = 2` in `config.php` temporarily to see detailed errors
5. Check `storage/logs/app.log` for error messages

### SMS Not Sending?

1. Verify Twilio credentials are correct
2. Check phone number format (must include country code)
3. Ensure Twilio account has sufficient balance
4. Check `storage/logs/app.log` for error messages
5. Try Fast2SMS as fallback if in India

## 🔒 Security Notes

- **Never commit** your credentials to version control
- Use environment variables or `.env` files (not included in repo)
- Keep your app passwords and API keys secure
- Rotate credentials periodically

## 📝 Notes

- The system will try Twilio first, then fallback to Fast2SMS if Twilio is not configured
- Email uses TLS encryption by default (port 587)
- For SSL encryption, use port 465 and the system will automatically switch
- OTP expires in 10 minutes (configurable in `config.php`)

