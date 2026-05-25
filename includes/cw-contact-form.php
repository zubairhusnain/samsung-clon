<?php
declare(strict_types=1);

require_once __DIR__ . '/cw-php-polyfill.php';
require_once __DIR__ . '/cw-contact-mail.php';

function cw_contact_form_handle(array $post): array
{
    $values = [
        'username' => trim((string)($post['username'] ?? '')),
        'email' => trim((string)($post['email'] ?? '')),
        'subject' => trim((string)($post['subject'] ?? '')),
        'message' => trim((string)($post['message'] ?? '')),
    ];

    if ($values['username'] === '') {
        return ['ok' => false, 'message' => 'Please enter your name.', 'values' => $values];
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.', 'values' => $values];
    }
    if ($values['subject'] === '') {
        return ['ok' => false, 'message' => 'Please enter a subject.', 'values' => $values];
    }
    if ($values['message'] === '') {
        return ['ok' => false, 'message' => 'Please enter a message.', 'values' => $values];
    }
    if (strlen($values['message']) > 10000) {
        return ['ok' => false, 'message' => 'Message is too long (max 10,000 characters).', 'values' => $values];
    }

    if (trim((string)($post['website'] ?? '')) !== '') {
        return ['ok' => true, 'message' => 'Thank you. Your message has been sent.', 'values' => []];
    }

    $result = cw_contact_send_mail(
        $values['username'],
        $values['email'],
        $values['subject'],
        $values['message']
    );

    if ($result['ok']) {
        return ['ok' => true, 'message' => $result['message'], 'values' => []];
    }

    return ['ok' => false, 'message' => $result['message'], 'values' => $values];
}

function cw_contact_form_render(array $state): string
{
    $v = $state['values'] ?? [];
    $ok = !empty($state['ok']);
    $msg = htmlspecialchars((string)($state['message'] ?? ''), ENT_QUOTES, 'UTF-8');
    $alertClass = $ok ? 'cw-contact-alert--success' : 'cw-contact-alert--error';
    $alert = $msg !== ''
        ? '<div class="cw-contact-alert ' . $alertClass . '" role="status">' . $msg . '</div>'
        : '';

    $u = htmlspecialchars((string)($v['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $e = htmlspecialchars((string)($v['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $s = htmlspecialchars((string)($v['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
    $m = htmlspecialchars((string)($v['message'] ?? ''), ENT_QUOTES, 'UTF-8');

    $base = defined('CW_BASE_URL') ? CW_BASE_URL : '';

    return <<<HTML
<link rel="stylesheet" href="{$base}/assets/css/contact-form.css">
<div class="cw-contact-form-root aem-GridColumn aem-GridColumn--default--12">
  <section class="cw-contact-form-section" aria-labelledby="cw-contact-form-title">
    <div class="cw-contact-form-wrap">
      <h2 id="cw-contact-form-title" class="cw-contact-form__title">Send us a message</h2>
      <p class="cw-contact-form__lead">Fill in the form below and our team will get back to you as soon as possible.</p>
      {$alert}
      <form class="cw-contact-form" method="post" action="#cw-contact-form" novalidate>
        <input type="hidden" name="cw_contact_submit" value="1">
        <div class="cw-contact-field">
          <label for="cw-username">Name <span class="cw-contact-required">*</span></label>
          <input type="text" id="cw-username" name="username" required autocomplete="name" maxlength="120" value="{$u}">
        </div>
        <div class="cw-contact-field">
          <label for="cw-email">Email <span class="cw-contact-required">*</span></label>
          <input type="email" id="cw-email" name="email" required autocomplete="email" maxlength="254" value="{$e}">
        </div>
        <div class="cw-contact-field">
          <label for="cw-subject">Subject <span class="cw-contact-required">*</span></label>
          <input type="text" id="cw-subject" name="subject" required maxlength="200" value="{$s}">
        </div>
        <div class="cw-contact-field">
          <label for="cw-message">Message <span class="cw-contact-required">*</span></label>
          <textarea id="cw-message" name="message" required rows="8" maxlength="10000">{$m}</textarea>
        </div>
        <div class="cw-contact-hp" aria-hidden="true">
          <label for="cw-website">Website</label>
          <input type="text" id="cw-website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <button type="submit" class="cw-contact-submit cta cta--contained cta--emphasis">Send message</button>
      </form>
    </div>
  </section>
</div>
HTML;
}
