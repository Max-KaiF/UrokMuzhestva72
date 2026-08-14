<?php
// Ежедневные задачи по расписанию (CRON): рассылка сводок + автозакрытие + архивация.
require __DIR__.'/lib.php';
// Защита: из браузера — только с ключом cron_key из config.php; из консоли (CRON) — всегда можно.
if (php_sapi_name() !== 'cli') {
$k = cfg()['cron_key'] ?? '';
if ($k === '' || ($_GET['key'] ?? '') !== $k) { http_response_code(403); exit('Нет доступа.'); }
}
init_schema();
// 1) Автозакрытие проведённых заявок с отзывом (через 7 дней) и архивация старых закрытых (30 дней)
$closed   = auto_close_done(7);
$archived = archive_old_requests(30);
// 2) Напоминания об уроках, запланированных на завтра (школе и подтверждённым спикерам)
$reminders = send_lesson_reminders();
// 3) Рассылка писем-сводок «требует действия»
$list = get_users_with_pending_actions();
$sent = 0; $fail = 0;
foreach ($list as $row) {
$u = $row['user'];
$name = ($u['display_name'] ?? '') !== '' ? $u['display_name'] : $u['login'];
if (send_notify_email_smtp($u['email'], $name, $row['count'], $row['urgent'])) $sent++; else $fail++;
}
$log = date('Y-m-d H:i:s')." — автозакрыто: $closed, в архив: $archived, напоминаний о завтрашних уроках: $reminders, получателей сводки: ".count($list).", отправлено: $sent, ошибок: $fail\n";
echo $log;
@file_put_contents(__DIR__.'/cron_notify.log', $log, FILE_APPEND);