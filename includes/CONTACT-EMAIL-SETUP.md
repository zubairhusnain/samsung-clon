# Contact form email setup

The contact form on `/support/contact/` is **always enabled**. Submissions are sent by email.

## Quick setup (cPanel)

1. Copy the example config:
   ```bash
   cp includes/contact-mail.local.php.example includes/contact-mail.local.php
   ```
2. Edit `includes/contact-mail.local.php` with your Gmail (or SMTP) credentials.
3. For Gmail SMTP, use an [App Password](https://myaccount.google.com/apppasswords), not your normal login password.
4. Install PHPMailer (recommended for reliable delivery):
   ```bash
   composer install
   ```
   Upload the `vendor/` folder to the server with the site.

If SMTP is not configured, the form still works and tries PHP `mail()` (common on cPanel).

## Fields

- Name, Email, Subject, Message (all required)
- Honeypot field `website` (hidden, for spam bots)

## Files

| File | Purpose |
|------|---------|
| `includes/cw-contact-form.php` | Validation and HTML form |
| `includes/cw-contact-mail.php` | Send via SMTP or mail() |
| `includes/contact-mail-config.php` | Default settings |
| `includes/contact-mail.local.php` | Your secrets (not in git) |
| `assets/css/contact-form.css` | Form styles |
