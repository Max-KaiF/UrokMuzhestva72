<?php
// ====== НАСТРОЙКИ ======
// Секреты (пароли БД, SMTP, коды регистрации) в этом файле БОЛЬШЕ НЕ ХРАНЯТСЯ.
// Они берутся:
//   1) из переменных окружения хостинга (если заданы), либо
//   2) из файла secrets.local.php рядом с этим файлом (закрыт .htaccess).
// Так config.php можно спокойно показывать и коммитить — в нём нет паролей.
$secrets = [];
$secretsFile = __DIR__ . '/secrets.local.php';
if (is_file($secretsFile)) { $secrets = require $secretsFile; }
// Помощник: сперва окружение, затем secrets.local.php, затем значение по умолчанию.
$get = function ($key, $default = null) use ($secrets) {
$env = getenv($key);
if ($env !== false && $env !== '') return $env;
return array_key_exists($key, $secrets) ? $secrets[$key] : $default;
};
$driver = $get('UM_DRIVER', 'mysql');
return [
'driver' => $driver,
'mysql' => [
'host'    => $get('UM_DB_HOST', 'localhost'),
'db'      => $get('UM_DB_NAME', ''),
'user'    => $get('UM_DB_USER', ''),
'pass'    => $get('UM_DB_PASS', ''),
'charset' => 'utf8mb4',
],
'sqlite' => [ 'path' => getenv('UM_SQLITE') ?: __DIR__ . '/data.sqlite' ],
// Коды регистрации (раздаются только доверенным операторам/фондам).
'operator_signup_code' => $get('UM_OP_CODE', ''),
'fund_signup_code'     => $get('UM_FUND_CODE', ''),
'cron_key'             => $get('UM_CRON_KEY', ''),
'support_email'        => $get('UM_SUPPORT_EMAIL', 'task@урокмужества72.рф'),
// Логины, которым разрешено «войти как» другой пользователь без пароля (см. ?p=impersonate).
// Список через запятую, например: UM_SUPERUSER_LOGINS=ivanov
'superuser_logins'     => array_values(array_filter(array_map('trim', explode(',', $get('UM_SUPERUSER_LOGINS', ''))))),
// Настройки почты (SMTP).
'smtp' => [
'host' => $get('UM_SMTP_HOST', 'mail.xn--72-6kchof1aqqtqmfe.xn--p1ai'),
'port' => (int) $get('UM_SMTP_PORT', 465),
'user' => $get('UM_SMTP_USER', 'task@урокмужества72.рф'),
'pass' => $get('UM_SMTP_PASS', ''),
'secure' => $get('UM_SMTP_SECURE', ''),
],
// Push-уведомления (Web Push). Пока ключи не заданы — функция просто выключена,
// на остальную работу сайта никак не влияет.
'vapid' => [
'public'  => $get('UM_VAPID_PUBLIC', ''),
'private' => $get('UM_VAPID_PRIVATE', ''),
'subject' => $get('UM_VAPID_SUBJECT', 'mailto:'.$get('UM_SUPPORT_EMAIL', 'task@урокмужества72.рф')),
],
'site_title' => 'Уроки мужества 72',
];