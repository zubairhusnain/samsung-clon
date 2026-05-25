<?php
declare(strict_types=1);

/**
 * Contact form email settings.
 * Override secrets in includes/contact-mail.local.php (not committed).
 */
return [
    'recipient' => 'zubairhusnain58@gmail.com',
    'from_email' => 'noreply@samsung-electronics.com.pk',
    'from_name' => 'Samsung Pakistan Contact Form',

    'smtp' => [
        'enabled' => true,
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
    ],

    'fallback_mail' => true,
];
