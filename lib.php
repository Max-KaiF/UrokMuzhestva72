<?php
// ================== ЯДРО ПРИЛОЖЕНИЯ ==================
if(function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
function cfg(){ static $c=null; if($c===null){ $c=require __DIR__.'/config.php'; } return $c; }
function db(){
static $pdo=null; if($pdo) return $pdo; $c=cfg();
if($c['driver']==='sqlite'){ $pdo=new PDO('sqlite:'.$c['sqlite']['path']); $pdo->exec('PRAGMA foreign_keys=ON'); }
else { $m=$c['mysql']; $pdo=new PDO("mysql:host={$m['host']};dbname={$m['db']};charset={$m['charset']}",$m['user'],$m['pass']); }
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC); return $pdo;
}
function init_schema(){
$sqlite=cfg()['driver']==='sqlite';
$pk=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INTEGER PRIMARY KEY AUTO_INCREMENT';
$tail=$sqlite?'':' DEFAULT CHARSET=utf8mb4';
db()->exec("CREATE TABLE IF NOT EXISTS users (
id $pk, role VARCHAR(20), login VARCHAR(190) UNIQUE, pass_hash VARCHAR(255),
display_name VARCHAR(255), org_name VARCHAR(255), level VARCHAR(20),
age_groups VARCHAR(255), fund_user_id INTEGER, active INTEGER DEFAULT 1, created_at VARCHAR(40),
email VARCHAR(190)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS requests (
id $pk, code VARCHAR(40) UNIQUE, school_user_id INTEGER, org VARCHAR(255),
level VARCHAR(20), op_group VARCHAR(20), operator VARCHAR(255),
contact_name VARCHAR(255), phone VARCHAR(100), email VARCHAR(190),
udate VARCHAR(40), utime VARCHAR(20), utime_end VARCHAR(20), age_group VARCHAR(20), participants VARCHAR(40),
speakers_needed INTEGER DEFAULT 1, lessons_per_speaker INTEGER DEFAULT 1,
topic TEXT, notes TEXT, status VARCHAR(20),
speaker_user_id INTEGER, fund_confirmed_at VARCHAR(40), fund_coordinator VARCHAR(255),
briefing_done INTEGER DEFAULT 0, briefing_date VARCHAR(40),
prep_parents INTEGER DEFAULT 0, prep_venue INTEGER DEFAULT 0, prep_escort INTEGER DEFAULT 0, prep_confirmed_at VARCHAR(40),
fb_app_rating INTEGER, fb_app_comment TEXT, fb_app_at VARCHAR(40), fb_post_url VARCHAR(500),
fb_vet_rating INTEGER, fb_vet_comment TEXT, fb_vet_at VARCHAR(40), created_at VARCHAR(40),
archived INTEGER DEFAULT 0, archived_at VARCHAR(40), closed_at VARCHAR(40),
cancel_reason VARCHAR(255), cancelled_at VARCHAR(40),
district VARCHAR(120), needs_replacement INTEGER DEFAULT 0
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS request_speakers (
id $pk, request_id INTEGER, speaker_user_id INTEGER, confirmed INTEGER DEFAULT 0, recommended INTEGER DEFAULT 0,
vet_rating INTEGER, vet_comment TEXT, vet_at VARCHAR(40), created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
id $pk, user_id INTEGER, token VARCHAR(128), expires_at VARCHAR(40), used INTEGER DEFAULT 0, created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS login_otp (
id $pk, user_id INTEGER, code VARCHAR(10), expires_at VARCHAR(40), used INTEGER DEFAULT 0, attempts INTEGER DEFAULT 0, created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS audit_log (
id $pk, user_id INTEGER, user_role VARCHAR(20), action VARCHAR(100), target_type VARCHAR(40), target_id INTEGER, details TEXT, created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS districts (
id $pk, name VARCHAR(120) UNIQUE, neighbors TEXT, created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS materials (
id $pk, title VARCHAR(255) NOT NULL, description TEXT, type VARCHAR(50), topic VARCHAR(255), link VARCHAR(500),
file_path VARCHAR(500), file_name VARCHAR(255), file_size INTEGER,
uploaded_by INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS speaker_unavailable (
id $pk, speaker_user_id INTEGER, date_from VARCHAR(20), date_to VARCHAR(20), reason VARCHAR(120), created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS login_attempts (
id $pk, login VARCHAR(190) UNIQUE, fail_count INTEGER DEFAULT 0, locked_until VARCHAR(40), last_attempt_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS password_reset_throttle (
id $pk, email VARCHAR(190) UNIQUE, requested_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
id $pk, user_id INTEGER, endpoint VARCHAR(500) UNIQUE, p256dh VARCHAR(255), auth VARCHAR(100), created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS speaker_test_questions (
id $pk, age_group VARCHAR(20), question TEXT,
option_a VARCHAR(500), option_b VARCHAR(500), option_c VARCHAR(500), option_d VARCHAR(500),
correct_option VARCHAR(1), active INTEGER DEFAULT 1, created_by INTEGER, created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS speaker_test_attempts (
id $pk, speaker_user_id INTEGER, age_group VARCHAR(20), score INTEGER, total INTEGER,
passed INTEGER DEFAULT 0, answers TEXT, created_at VARCHAR(40)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS speaker_readiness_attempts (
id $pk, speaker_user_id INTEGER, block_key VARCHAR(20), score INTEGER, total INTEGER DEFAULT 20,
status VARCHAR(20), final_answer VARCHAR(1), answers TEXT, created_at VARCHAR(40)
)$tail");
// Отдельная от speaker_readiness_attempts «Анкета самооценки психологической готовности» —
// привязана к конкретной возрастной группе (age_group), служит дополнительным ориентиром для
// фонда рядом с тестом знаний на «Допуск к возрастным группам», сама ничего не блокирует.
db()->exec("CREATE TABLE IF NOT EXISTS speaker_psych_readiness_attempts (
id $pk, speaker_user_id INTEGER, age_group VARCHAR(20), score INTEGER, total INTEGER DEFAULT 60,
status VARCHAR(20), acute INTEGER DEFAULT 0, answers TEXT, created_at VARCHAR(40)
)$tail");
// Проверка готовности спикера к конкретному занятию: 4 фиксированных вопроса (тема, цели
// урока, материальная база, методика проведения) с вариантами ответа — банк ведёт оператор
// отдельно по каждой теме занятия (requests.topic).
db()->exec("CREATE TABLE IF NOT EXISTS lesson_readiness_questions (
id $pk, topic VARCHAR(255), dimension VARCHAR(20), question TEXT,
option_a TEXT, option_b TEXT, option_c TEXT, option_d TEXT, correct_option VARCHAR(1),
created_by INTEGER, created_at VARCHAR(40), UNIQUE(topic,dimension)
)$tail");
db()->exec("CREATE TABLE IF NOT EXISTS lesson_readiness_attempts (
id $pk, speaker_user_id INTEGER, topic VARCHAR(255), request_id INTEGER, score INTEGER, total INTEGER,
passed INTEGER, answers TEXT, created_at VARCHAR(40)
)$tail");
// Новости на публичной стартовой странице — ведёт оператор.
db()->exec("CREATE TABLE IF NOT EXISTS news (
id $pk, title VARCHAR(255) NOT NULL, body TEXT, image_path VARCHAR(255),
published INTEGER DEFAULT 1, created_by INTEGER, created_at VARCHAR(40)
)$tail");
foreach([
"ALTER TABLE users ADD COLUMN fund_user_id INTEGER",
"ALTER TABLE users ADD COLUMN email VARCHAR(190)",
"ALTER TABLE users ADD COLUMN op_group VARCHAR(20)",
"ALTER TABLE users ADD COLUMN district VARCHAR(120)",
"ALTER TABLE users ADD COLUMN phone VARCHAR(40)",
"ALTER TABLE users ADD COLUMN contact_name VARCHAR(190)",
"ALTER TABLE users ADD COLUMN position VARCHAR(190)",
"ALTER TABLE users ADD COLUMN parent_fund_id INTEGER",
"ALTER TABLE users ADD COLUMN parent_school_id INTEGER",
"ALTER TABLE requests ADD COLUMN utime VARCHAR(20)",
"ALTER TABLE requests ADD COLUMN prep_confirmed_at VARCHAR(40)",
"ALTER TABLE requests ADD COLUMN speakers_needed INTEGER DEFAULT 1",
"ALTER TABLE requests ADD COLUMN lessons_per_speaker INTEGER DEFAULT 1",
"ALTER TABLE requests ADD COLUMN utime_end VARCHAR(20)",
"ALTER TABLE requests ADD COLUMN archived INTEGER DEFAULT 0",
"ALTER TABLE requests ADD COLUMN archived_at VARCHAR(40)",
"ALTER TABLE requests ADD COLUMN closed_at VARCHAR(40)",
"ALTER TABLE requests ADD COLUMN cancel_reason VARCHAR(255)",
"ALTER TABLE requests ADD COLUMN cancelled_at VARCHAR(40)",
"ALTER TABLE requests ADD COLUMN district VARCHAR(120)",
"ALTER TABLE request_speakers ADD COLUMN recommended INTEGER DEFAULT 0",
"ALTER TABLE requests ADD COLUMN needs_replacement INTEGER DEFAULT 0",
"ALTER TABLE users ADD COLUMN address VARCHAR(255)",
"ALTER TABLE users ADD COLUMN building VARCHAR(40)",
"ALTER TABLE materials ADD COLUMN topic VARCHAR(255)",
"ALTER TABLE materials ADD COLUMN link VARCHAR(500)",
"ALTER TABLE materials MODIFY COLUMN file_path VARCHAR(500) NULL",
"UPDATE requests SET status='confirmed', briefing_done=1 WHERE status='brief'",
"ALTER TABLE users ADD COLUMN photo_path VARCHAR(255)",
"ALTER TABLE users ADD COLUMN sp_rank VARCHAR(190)",
"ALTER TABLE users ADD COLUMN sp_branch VARCHAR(190)",
"ALTER TABLE users ADD COLUMN sp_awards TEXT",
"ALTER TABLE users ADD COLUMN sp_achievements TEXT",
"ALTER TABLE users ADD COLUMN sp_hobbies VARCHAR(255)",
"ALTER TABLE users ADD COLUMN sp_bio TEXT",
"ALTER TABLE requests ADD COLUMN claimed_fund_id INTEGER",
"ALTER TABLE requests ADD COLUMN claimed_at VARCHAR(40)",
"ALTER TABLE requests ADD COLUMN desired_speaker_id INTEGER",
"ALTER TABLE requests ADD COLUMN fb_post_url VARCHAR(500)",
"ALTER TABLE users ADD COLUMN certified_age_groups VARCHAR(255)",
"ALTER TABLE users ADD COLUMN criminal_record VARCHAR(10)",
"ALTER TABLE users ADD COLUMN criminal_record_set_at VARCHAR(40)",
"ALTER TABLE users ADD COLUMN criminal_cert_path VARCHAR(255)",
"ALTER TABLE users ADD COLUMN criminal_cert_date VARCHAR(20)",
"CREATE INDEX idx_requests_status ON requests(status)",
"CREATE INDEX idx_requests_school ON requests(school_user_id)",
"CREATE INDEX idx_requests_fund ON requests(claimed_fund_id)",
"CREATE INDEX idx_requests_archived ON requests(archived)",
"CREATE INDEX idx_requests_opgroup ON requests(op_group)",
"CREATE INDEX idx_requests_udate ON requests(udate)",
"CREATE INDEX idx_users_role ON users(role)",
"CREATE INDEX idx_users_parent_school ON users(parent_school_id)",
"CREATE INDEX idx_users_fund ON users(fund_user_id)",
"CREATE INDEX idx_rs_request ON request_speakers(request_id)",
"CREATE INDEX idx_rs_speaker ON request_speakers(speaker_user_id)",
] as $sql){ try{ db()->exec($sql); }catch(Throwable $e){} }
try{ db()->exec("UPDATE requests SET speakers_needed=1 WHERE speakers_needed IS NULL"); }catch(Throwable $e){}
try{ db()->exec("UPDATE requests SET lessons_per_speaker=1 WHERE lessons_per_speaker IS NULL OR lessons_per_speaker<1"); }catch(Throwable $e){}
// Тестирование по возрастным группам вводится не с нуля: спикерам, зарегистрированным
// ДО этой доработки, самозаявленные группы сохраняются как допуск без пересдачи.
// Важно: рубеж по дате регистрации, а не просто «допуск ещё не проставлен» — иначе
// это правило подхватывало бы и будущих спикеров при каждом запросе (init_schema()
// выполняется на каждый запрос), и тест перестал бы быть обязательным для новых.
try{ db()->exec("UPDATE users SET certified_age_groups=age_groups WHERE role='speaker' AND active=1 AND (certified_age_groups IS NULL OR certified_age_groups='') AND age_groups IS NOT NULL AND age_groups<>'' AND created_at<='2026-08-21 23:59:59'"); }catch(Throwable $e){}
try{ if((int)db()->query("SELECT COUNT(*) c FROM districts")->fetch()['c']===0){
foreach(['Абатский','Армизонский','Аромашевский','Бердюжский','Вагайский','Викуловский','Голышмановский','Заводоуковский','Исетский','Ишимский','Казанский','Нижнетавдинский','Омутинский','Сладковский','Сорокинский','Тобольский','Тюменский','Тюмень','Уватский','Упоровский','Юргинский','Ялуторовский','Ярковский'] as $dn){
$ins=db()->prepare("INSERT INTO districts(name,neighbors,created_at) VALUES(?,?,?)"); try{ $ins->execute([$dn,'',now()]); }catch(Throwable $e){}
}
}}catch(Throwable $e){}
try{ foreach(default_neighbors() as $dn=>$nb){ db()->prepare("UPDATE districts SET neighbors=? WHERE name=? AND (neighbors IS NULL OR neighbors='')")->execute([implode(',',$nb),$dn]); } }catch(Throwable $e){}
try{
if((int)db()->query("SELECT COUNT(*) c FROM request_speakers")->fetch()['c']===0){
foreach(db()->query("SELECT id,speaker_user_id,fb_vet_rating,fb_vet_comment,fb_vet_at FROM requests WHERE speaker_user_id IS NOT NULL")->fetchAll() as $r){
db()->prepare("INSERT INTO request_speakers(request_id,speaker_user_id,confirmed,vet_rating,vet_comment,vet_at,created_at) VALUES(?,?,1,?,?,?,?)")
->execute([$r['id'],$r['speaker_user_id'],$r['fb_vet_rating'],$r['fb_vet_comment'],$r['fb_vet_at'],now()]);
}
}
}catch(Throwable $e){}
try{
$cnt=(int)db()->query("SELECT COUNT(DISTINCT r.id) c FROM requests r JOIN request_speakers rs ON rs.request_id=r.id WHERE r.claimed_fund_id IS NULL")->fetch()['c'];
if($cnt>0){
foreach(db()->query("SELECT DISTINCT request_id FROM request_speakers")->fetchAll(PDO::FETCH_COLUMN) as $rid){
$sp=db()->prepare("SELECT u.fund_user_id FROM request_speakers rs JOIN users u ON u.id=rs.speaker_user_id WHERE rs.request_id=? AND u.fund_user_id IS NOT NULL ORDER BY rs.confirmed DESC, rs.id LIMIT 1");
$sp->execute([$rid]); $row=$sp->fetch();
if($row) db()->prepare("UPDATE requests SET claimed_fund_id=?, claimed_at=COALESCE(claimed_at,?) WHERE id=? AND claimed_fund_id IS NULL")->execute([$row['fund_user_id'],now(),$rid]);
}
}
}catch(Throwable $e){}
try{
db()->exec("UPDATE users SET op_group='school' WHERE role='school' AND (op_group IS NULL OR op_group='') AND level IN ('school','other')");
db()->exec("UPDATE users SET op_group='region' WHERE role='school' AND (op_group IS NULL OR op_group='') AND level IN ('spo','vuz')");
}catch(Throwable $e){}
}
// ---------- утилиты ----------
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function now(){ return date('Y-m-d H:i:s'); }
function redirect($u){ header('Location: '.$u); exit; }
function flash_set($m){ $_SESSION['flash']=$m; }
function flash_get(){ $m=$_SESSION['flash']??null; unset($_SESSION['flash']); return $m; }
// ---------- телефон: приводим к формату +7XXXXXXXXXX (10 цифр после +7) ----------
function normalize_phone($raw){
$d=preg_replace('/\D+/','',(string)$raw);
if($d==='') return null;
if(strlen($d)===11 && ($d[0]==='7'||$d[0]==='8')) $d=substr($d,1);
if(strlen($d)!==10) return null;
return '+7'.$d;
}
// ---------- сохранение введённых данных формы при ошибке валидации ----------
function fail_redirect($msg,$url){ flash_set($msg); $_SESSION['old_input']=$_POST; redirect($url); }
function pull_old_input(){ $v=$_SESSION['old_input']??[]; unset($_SESSION['old_input']); return $v; }
function ov($old,$key,$default=''){ return esc($old[$key]??$default); }
function osel($old,$key,$val){ return ((string)($old[$key]??''))===(string)$val?' selected':''; }
function ochk($old,$group,$val){ return isset($old[$group][$val])?' checked':''; }
// ---------- предметная область ----------
const OP_CITY='Департамент образования Администрации города Тюмени';
const OP_REGION='Департамент образования и науки Тюменской области';
function operator_of($lvl){ return in_array($lvl,['school','other'],true)?OP_CITY:OP_REGION; }
function opgroup_of($lvl){ return in_array($lvl,['school','other'],true)?'school':'region'; }
function operator_label_for_group($og){ return $og==='region'?OP_REGION:OP_CITY; }
function school_dept_group($school){ return $school['op_group']?:opgroup_of($school['level']??''); }
function ages(){ return ['1-4'=>'1–4 классы','5-9'=>'5–9 классы','10-11'=>'10–11 классы','spo'=>'профессиональные образовательные организации','vuz'=>'высшие учебные заведения']; }
function level_label($l){ return ['school'=>'Школа','spo'=>'Профессиональная образовательная организация','vuz'=>'Высшее учебное заведение','other'=>'Иное'][$l]??$l; }
function level_ages($lvl){ return ['school'=>['1-4','5-9','10-11'],'spo'=>['spo'],'vuz'=>['vuz'],'other'=>['1-4','5-9','10-11']][$lvl]??[]; }
function statuses(){ return [
['k'=>'new','label'=>'Новая','color'=>'gray'],
['k'=>'chosen','label'=>'Спикеры выбраны','color'=>'blue'],
['k'=>'confirmed','label'=>'Состав подтверждён Фондом','color'=>'blue'],
['k'=>'prep','label'=>'Готовность подтверждена','color'=>'blue'],
['k'=>'done','label'=>'Проведён','color'=>'amber'],
['k'=>'closed','label'=>'Закрыта','color'=>'green'],
]; }
function st_index($k){ if($k==='brief') $k='confirmed'; foreach(statuses() as $i=>$s) if($s['k']===$k) return $i; return 0; }
function st_meta($k){ if($k==='cancelled') return ['k'=>'cancelled','label'=>'Не состоялся','color'=>'red']; foreach(statuses() as $s) if($s['k']===$k) return $s; return statuses()[0]; }
// ========== СЦЕНАРИЙ РАБОТЫ ПО РОЛЯМ (для страницы «Как это работает») ==========
function guide_content(){ return [
'school'=>['label'=>'Образовательная организация','short'=>'Заявитель','subtitle'=>'Подаёт заявки на Урок Мужества и принимает спикеров','steps'=>[
['t'=>'Регистрация','d'=>'Укажите наименование организации, уровень (школа / СПО / вуз / иное), адрес, ФИО и телефон контактного лица. Департамент подставляется автоматически по уровню, но его можно выбрать вручную — и поменять позже в профиле.'],
['t'=>'Подтверждение','d'=>'Заявку на регистрацию проверяет выбранный департамент образования. После одобрения открывается доступ в личный кабинет.'],
['t'=>'Подать заявку','d'=>'Укажите дату, время, возрастную группу, число участников и тему урока (из готовых технологических карт или свою). Можно указать желаемого спикера — это необязательное пожелание, не обязывающее Фонд.'],
['t'=>'Ожидание Фонда','d'=>'Заявка сразу становится доступна всем Фондам. Один из них берёт её в обработку и сам назначает спикера — выбирать спикеров вручную не нужно.'],
['t'=>'Подтвердить готовность','d'=>'Отметьте готовность площадки: согласие родителей (если нужно), готовность аудитории, сопровождение.'],
['t'=>'Провести урок','d'=>'После урока отметьте «Проведён» или «Не состоялся» с указанием причины.'],
['t'=>'Оставить отзыв','d'=>'Короткий отзыв о том, как прошёл урок — это последний шаг перед закрытием заявки. Полную историю действий по заявке можно посмотреть кнопкой «История».'],
]],
'speaker'=>['label'=>'Спикер','short'=>'Ветеран','subtitle'=>'Проводит уроки в образовательных организациях','steps'=>[
['t'=>'Регистрация','d'=>'Укажите ФИО, телефон, выберите Фонд, который вас направляет, и возрастные группы, с которыми готовы работать.'],
['t'=>'Подтверждение Фондом','d'=>'Фонд проверяет данные и подтверждает регистрацию либо отклоняет её, если данные не подходят.'],
['t'=>'Профиль','d'=>'Добавьте фото и информацию о себе — звание, род войск, награды, достижения, увлечения. Это увидит Фонд, а после назначения на урок — и заявитель, чтобы правильно вас представить.'],
['t'=>'Календарь недоступности','d'=>'Отметьте даты, когда не сможете проводить уроки (отпуск, лечение и т.п.) — в эти дни вас не предложат заявителям.'],
['t'=>'Заявки Фонда в обработке','d'=>'Пока Фонд не назначил конкретного спикера, заявка видна всем спикерам фонда в разделе «Заявки фонда в обработке».'],
['t'=>'Вас назначили','d'=>'Как только Фонд назначил и подтвердил именно вас, заявка появляется в разделе «Мои уроки» и пропадает у остальных спикеров фонда.'],
['t'=>'Проведение урока и отзыв','d'=>'Проведите урок в назначенные дату и время, затем оставьте короткий отзыв.'],
['t'=>'Статистика','d'=>'На главной странице видно, сколько уроков вы провели и сколько детей охватили — за сегодня, неделю, месяц и всё время, с разбивкой по месяцам.'],
]],
'fund'=>['label'=>'Фонд','short'=>'Направляет спикеров','subtitle'=>'Формирует и подтверждает состав спикеров для заявок','steps'=>[
['t'=>'Регистрация','d'=>'Головной фонд регистрируется по коду регистрации. Структурное подразделение указывает головной фонд и ждёт его подтверждения.'],
['t'=>'Подтверждение спикеров','d'=>'Проверяйте спикеров, указавших ваш фонд при регистрации: подтверждайте с указанием возрастных групп или отклоняйте, если данные не подходят.'],
['t'=>'Новые заявки','d'=>'Все новые заявки сразу видны всем Фондам. Возьмите подходящую «в обработку» — она закрепится за вашим фондом, и другие фонды её уже не возьмут. Если не нашли спикера — верните заявку в общий список.'],
['t'=>'Назначение спикера','d'=>'Назначьте своего спикера (при желании — учитывая пожелание заявителя), затем подтвердите его в списке кандидатов.'],
['t'=>'Состав укомплектован','d'=>'Как только подтверждено нужное число спикеров, заявка переходит на этап «Состав подтверждён», остальные кандидаты снимаются автоматически.'],
['t'=>'Инструктаж','d'=>'При необходимости отметьте дату проведения инструктажа спикеров перед уроком.'],
['t'=>'После урока','d'=>'Доступен просмотр отзывов заявителя и спикеров, а также история действий по заявке.'],
['t'=>'Показатели фонда','d'=>'В личном кабинете — сводка по вашим заявкам и спикерам: в работе, проведено, охвачено детей, отзывы.'],
]],
'operator'=>['label'=>'Оператор','short'=>'Департамент образования','subtitle'=>'Модерирует заявителей и контролирует ход заявок','steps'=>[
['t'=>'Регистрация','d'=>'По коду регистрации оператора, с указанием департамента: школы города или СПО/вузы области.'],
['t'=>'Модерация заявителей','d'=>'Подтверждайте или отклоняйте новые образовательные организации. Департамент, к которому относится заявитель, указывается им явно при регистрации — видно в карточке заявки на модерацию.'],
['t'=>'Реестр заявок','d'=>'Отслеживайте все заявки своего департамента: фильтры, сортировка по коду/организации/дате/статусу, показатели.'],
['t'=>'Материалы и пользователи','d'=>'Публикуйте методические материалы и технологические карты, ведите реестр пользователей и спикеров.'],
['t'=>'Контроль исполнения','d'=>'Блок «Требует действия» показывает, на чьей стороне сейчас заявка и что нужно сделать дальше. Журнал действий фиксирует всю историю по каждой заявке.'],
['t'=>'Закрытие заявки','d'=>'После отзыва заявителя закрывайте заявку — закрытые заявки старше 30 дней автоматически переносятся в архив.'],
]],
]; }
// ========== СКВОЗНОЙ ПУТЬ ЗАЯВКИ (интерактивная презентация на «Как это работает») ==========
function journey_roles(){ return [
'school'=>['label'=>'Заявитель','color'=>'#2E5496'],
'fund'=>['label'=>'Фонд','color'=>'#C77700'],
'speaker'=>['label'=>'Спикер','color'=>'#2E7D52'],
'operator'=>['label'=>'Оператор','color'=>'#16305B'],
]; }
function guide_journey(){ return [
['role'=>'school','emoji'=>'📝','status'=>'Новая','short'=>'Подача заявки','t'=>'Заявитель подаёт заявку','d'=>'Указываются дата, время, возрастная группа, число участников и тема урока. Можно отметить «желаемого спикера» — это необязательное пожелание, а не гарантия. Заявка сразу становится видна всем Фондам.'],
['role'=>'fund','emoji'=>'📥','status'=>'Спикеры выбраны','short'=>'Фонд берёт в работу','t'=>'Фонд берёт заявку в обработку и назначает спикера','d'=>'Один из Фондов берёт заявку «в обработку» — она закрепляется за ним, и остальные Фонды её больше не видят как свободную. Затем Фонд назначает подходящего спикера (своего или подразделения).','note'=>'Если со спикером не срослось, на этом и следующих трёх шагах у Фонда есть кнопка «Вернуть в обработку» — заявка возвращается на этот же первый шаг, и её может забрать любой другой Фонд.'],
['role'=>'fund','emoji'=>'✅','status'=>'Состав подтверждён Фондом','short'=>'Состав подтверждён','t'=>'Фонд подтверждает состав спикеров','d'=>'Назначенный спикер (или несколько, если нужно больше одного) подтверждается в списке кандидатов. Как только подтверждено нужное число — остальные кандидаты снимаются автоматически.','note'=>'Заявка не может попасть на этот шаг, пока не подтверждён хотя бы один спикер — это встроенное ограничение, обойти его нельзя.'],
['role'=>'school','emoji'=>'🏫','status'=>'Готовность подтверждена','short'=>'Готовность площадки','t'=>'Заявитель подтверждает готовность','d'=>'Отмечается готовность площадки: согласие родителей (если нужно), готовность аудитории, сопровождение. Как только отмечены все пункты — готовность фиксируется автоматически.'],
['role'=>'school','emoji'=>'🎤','status'=>'Проведён','short'=>'Урок проведён','t'=>'Урок проводится','d'=>'После урока заявитель отмечает «Проведён» либо «Не состоялся» с указанием причины (спикер не приехал, отменила организация, технические причины и другое).'],
['role'=>'school','emoji'=>'💬','status'=>'Проведён','short'=>'Отзывы','t'=>'Отзывы','d'=>'Заявитель и спикер оставляют короткие отзывы о том, как прошёл урок — это последний шаг перед закрытием заявки.','role2'=>'speaker'],
['role'=>'operator','emoji'=>'🔒','status'=>'Закрыта','short'=>'Закрытие заявки','t'=>'Оператор закрывает заявку','d'=>'После отзыва заявителя оператор закрывает заявку. Полную историю всех действий по заявке в любой момент можно посмотреть кнопкой «История». Закрытые заявки старше 30 дней автоматически переносятся в архив.'],
]; }
// ========== ДЕМО-РЕЖИМ (?p=demo) — интерактивный аналог сайта на выдуманных данных ==========
function demo_profile(){ return [
'code'=>'ДЕМО-0001',
'org'=>'МАОУ СОШ №1 г. Тюмени',
'operator'=>'Департамент образования Администрации города Тюмени',
'fund'=>'Благотворительный фонд «Пример»',
'speaker'=>'Иванов Иван Иванович',
'speaker_rank'=>'полковник запаса, ВДВ',
'contact_name'=>'Иванова Мария Петровна',
'contact_pos'=>'Заместитель директора по ВР',
'contact_phone'=>'+79001234567',
'age_group'=>'5–9 классы',
'participants'=>24,
'topic'=>'Герои нашего времени',
'udate'=>date('Y-m-d',strtotime('+15 days')),
'utime'=>'10:00',
'utime_end'=>'10:40',
]; }
function demo_status_for_step($step){ return [1=>'new',2=>'chosen',3=>'confirmed',4=>'prep',5=>'done',6=>'done',7=>'closed',8=>'closed'][$step]??'new'; }
function demo_total_steps(){ return 7; }
function demo_banner($roleKey,$step,$total){
$jr=journey_roles(); $col=$jr[$roleKey]['color']; $label=$jr[$roleKey]['label'];
$h='<div class="demo-banner" style="border-left-color:'.esc($col).'"><div><b>🔎 Демо-режим.</b> Вы смотрите интерфейс глазами роли <span class="demo-role-chip" style="background:'.esc($col).'">'.esc($label).'</span>';
if($step>0) $h.=' <span class="muted">· шаг '.$step.' из '.$total.'</span>';
$h.='</div><a class="demo-exit" href="?p=demo">Начать заново ✕</a></div>';
return $h;
}
// ========== ТИПОВЫЕ ПРИЧИНЫ ==========
function cancel_reasons(){ return ['Спикер не приехал','Отменила организация-заявитель','Технические причины','Другое']; }
function reject_reasons(){ return [
'Спикер занят в другое время',
'Не подходит по тематике',
'Не подходит по возрастной группе',
'Спикер заболел',
'Не согласован с подразделением',
'Превышена нагрузка спикера',
'Другое'
]; }
function replace_reasons(){ return [
'Спикер заболел',
'Спикер отозван по службе',
'Спикер не может прибыть',
'Личные обстоятельства спикера',
'Производственная необходимость',
'Другое'
]; }
function material_types(){ return [
'tech_map'=>'Технологическая карта',
'quiz'=>'Викторина',
'video'=>'Видеоролик',
'methodology'=>'Методические рекомендации',
'other'=>'Иное'
]; }
function material_type_icon($type){ return ['tech_map'=>'🗂️','quiz'=>'❓','video'=>'🎬','methodology'=>'📘','other'=>'📎'][$type]??'📄'; }
// ---------- CSRF ----------
function csrf_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.esc(csrf_token()).'">'; }
function csrf_check(){ if(($_POST['csrf']??'')!==($_SESSION['csrf']??'-')){ http_response_code(400); exit('Ошибка безопасности (CSRF). Обновите страницу.'); } }
// ---------- имперсонация («войти как») ----------
// Доступно только логинам из cfg()['superuser_logins'] (задаётся на сервере переменной
// окружения UM_SUPERUSER_LOGINS, в код/репозиторий логин не попадает).
function can_impersonate($u){ return $u && in_array($u['login']??'',cfg()['superuser_logins'],true); }
function is_impersonating(){ return !empty($_SESSION['impersonator_id']); }
// ---------- пользователи / вход ----------
function current_user(){ if(empty($_SESSION['uid'])) return null; static $u=null; if($u&&$u['id']===$_SESSION['uid']) return $u; $st=db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$_SESSION['uid']]); $u=$st->fetch()?:null; return $u; }
function require_login(){ if(!current_user()) redirect('?p=login'); }
function require_role($role){ $u=current_user(); $ok=is_array($role)?in_array($u['role']??null,$role,true):(($u['role']??null)===$role); if(!$u||!$ok){ http_response_code(403); exit('Нет доступа.'); } }
function find_user_by_login($login){ $st=db()->prepare('SELECT * FROM users WHERE login=?'); $st->execute([$login]); return $st->fetch()?:null; }
function find_user_by_email($email){ $st=db()->prepare('SELECT * FROM users WHERE email=?'); $st->execute([$email]); return $st->fetch()?:null; }
function find_user_by_id($id){ if(!$id) return null; $st=db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$id]); return $st->fetch()?:null; }
function profile_missing_fields($u){
if(!$u) return [];
$missing=[];
$blank=fn($k)=>trim((string)($u[$k]??''))==='';
if(in_array($u['role'],['school','fund','operator'],true)){
if($blank('contact_name')) $missing[]='ФИО контактного лица';
if($blank('position')) $missing[]='Должность';
if($blank('phone')) $missing[]='Телефон';
}
if($u['role']==='school' && empty($u['parent_school_id'])){
if($blank('address')) $missing[]='Адрес местонахождения';
if($blank('building')) $missing[]='№ корпуса';
}
if($u['role']==='speaker' && $blank('phone')) $missing[]='Телефон';
return $missing;
}
function create_user($role,$login,$pass,$extra=[]){
if(find_user_by_login($login)) return [false,'Такой логин уже занят.'];
$active=array_key_exists('active',$extra)?(int)$extra['active']:(($role==='speaker'||$role==='school')?0:1);
$st=db()->prepare('INSERT INTO users(role,login,pass_hash,display_name,org_name,level,age_groups,active,created_at,email,op_group,district,phone,parent_fund_id,fund_user_id,contact_name,position,address,building,parent_school_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$st->execute([$role,$login,password_hash($pass,PASSWORD_DEFAULT),$extra['display_name']??'',$extra['org_name']??'',$extra['level']??'',$extra['groups']??'',$active,now(),$extra['email']??'',$extra['op_group']??'',$extra['district']??'',$extra['phone']??'',($extra['parent_fund_id']??null)?:null,($extra['fund_user_id']??null)?:null,$extra['contact_name']??'',$extra['position']??'',$extra['address']??'',$extra['building']??'',($extra['parent_school_id']??null)?:null]);
$newId=db()->lastInsertId();
log_action(null,$role,'user_create','users',$newId,"Создан пользователь $login");
return [true,$newId];
}
function verify_credentials($login,$pass){ $u=find_user_by_login($login); if(!$u||!password_verify($pass,$u['pass_hash'])) return [false,'Неверный логин или пароль.']; return [true,$u]; }
// ---------- защита от подбора пароля ----------
define('LOGIN_MAX_ATTEMPTS',5);
define('LOGIN_LOCKOUT_MINUTES',15);
function login_lock_until($login){
$st=db()->prepare("SELECT locked_until FROM login_attempts WHERE login=?"); $st->execute([$login]); $row=$st->fetch();
if($row&&!empty($row['locked_until'])&&strtotime($row['locked_until'])>time()) return $row['locked_until'];
return null;
}
function record_login_failure($login){
$st=db()->prepare("SELECT id,fail_count FROM login_attempts WHERE login=?"); $st->execute([$login]); $row=$st->fetch();
if($row){
$fc=(int)$row['fail_count']+1;
$locked=$fc>=LOGIN_MAX_ATTEMPTS?date('Y-m-d H:i:s',strtotime('+'.LOGIN_LOCKOUT_MINUTES.' minutes')):null;
db()->prepare("UPDATE login_attempts SET fail_count=?,locked_until=?,last_attempt_at=? WHERE id=?")->execute([$fc,$locked,now(),$row['id']]);
} else {
db()->prepare("INSERT INTO login_attempts(login,fail_count,locked_until,last_attempt_at) VALUES(?,1,NULL,?)")->execute([$login,now()]);
}
}
function reset_login_failures($login){ db()->prepare("DELETE FROM login_attempts WHERE login=?")->execute([$login]); }
// ========== СУТОЧНЫЕ ЛИМИТЫ НА ПОДАЧУ (защита от флуда) ==========
function today_bounds(){ $d=date('Y-m-d'); return [$d.' 00:00:00', $d.' 23:59:59']; }
// Заявитель (вся организация — головной аккаунт + сотрудники) — не более 4 заявок на урок в сутки.
function school_requests_today_count($school){
[$from,$to]=today_bounds();
$scope=school_scope_ids($school);
if(!$scope) return 0;
$in=implode(',',array_fill(0,count($scope),'?'));
$st=db()->prepare("SELECT COUNT(*) c FROM requests WHERE school_user_id IN ($in) AND created_at BETWEEN ? AND ?");
$st->execute(array_merge($scope,[$from,$to]));
return (int)$st->fetch()['c'];
}
function school_daily_limit_reached($school,$limit=6){ return school_requests_today_count($school)>=$limit; }
// Фонд — не более 4 новых регистраций спикеров в сутки (чтобы очередь на подтверждение не заваливало).
function fund_speaker_registrations_today_count($fundUserId){
[$from,$to]=today_bounds();
$st=db()->prepare("SELECT COUNT(*) c FROM users WHERE role='speaker' AND fund_user_id=? AND created_at BETWEEN ? AND ?");
$st->execute([$fundUserId,$from,$to]);
return (int)$st->fetch()['c'];
}
function fund_speaker_reg_limit_reached($fundUserId,$limit=4){ return $fundUserId && fund_speaker_registrations_today_count($fundUserId)>=$limit; }
// Департамент — не более 5 новых заявок на регистрацию организаций в сутки (на модерацию).
function dept_school_registrations_today_count($opGroup){
[$from,$to]=today_bounds();
$st=db()->prepare("SELECT COUNT(*) c FROM users WHERE role='school' AND op_group=? AND (parent_school_id IS NULL) AND created_at BETWEEN ? AND ?");
$st->execute([$opGroup,$from,$to]);
return (int)$st->fetch()['c'];
}
function dept_school_reg_limit_reached($opGroup,$limit=5){ return dept_school_registrations_today_count($opGroup)>=$limit; }
function finalize_login($u){ session_regenerate_id(true); $_SESSION['uid']=$u['id']; log_action($u['id'],$u['role'],'login','users',$u['id'],'Вход в систему'); }
// ========== ДВУХФАКТОРНАЯ АУТЕНТИФИКАЦИЯ (код на email) ==========
function generate_otp_code(){ return str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT); }
function otp_resend_allowed($userId,$cooldownSeconds=60){
$st=db()->prepare("SELECT created_at FROM login_otp WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$userId]); $row=$st->fetch();
if(!$row) return true;
return (time()-strtotime($row['created_at']))>=$cooldownSeconds;
}
function create_login_otp($userId){
$code=generate_otp_code();
$expires=date('Y-m-d H:i:s',strtotime('+10 minutes'));
db()->prepare("INSERT INTO login_otp(user_id,code,expires_at,used,attempts,created_at) VALUES(?,?,?,0,0,?)")->execute([$userId,$code,$expires,now()]);
return $code;
}
function send_login_otp($u){
$code=create_login_otp($u['id']);
$site=cfg()['site_title'];
$body="Здравствуйте, {$u['display_name']}!\r\n\r\nВаш код подтверждения входа в систему \"$site\": $code\r\n\r\nКод действует 10 минут. Если вы не пытались войти — просто проигнорируйте это письмо.\r\n";
return send_smtp_email($u['email'],$u['display_name']?:$u['login'],"[$site] Код подтверждения входа",$body);
}
function verify_login_otp($userId,$code){
$st=db()->prepare("SELECT * FROM login_otp WHERE user_id=? AND used=0 ORDER BY id DESC LIMIT 1");
$st->execute([$userId]); $row=$st->fetch();
if(!$row) return [false,'Код не найден. Запросите новый.'];
if(strtotime($row['expires_at'])<time()) return [false,'Код истёк. Запросите новый.'];
if((int)$row['attempts']>=5) return [false,'Превышено число попыток. Запросите новый код.'];
if(trim((string)$code)!==$row['code']){
db()->prepare("UPDATE login_otp SET attempts=attempts+1 WHERE id=?")->execute([$row['id']]);
return [false,'Неверный код.'];
}
db()->prepare("UPDATE login_otp SET used=1 WHERE id=?")->execute([$row['id']]);
return [true,null];
}
// ========== PUSH-УВЕДОМЛЕНИЯ (Web Push) ==========
// Пока в конфиге нет пары VAPID-ключей — функция выключена, ничего не делает.
function vapid_public_key(){ return cfg()['vapid']['public'] ?? ''; }
function push_enabled(){ $v=cfg()['vapid']??[]; return !empty($v['public']) && !empty($v['private']); }
function save_push_subscription($userId,$endpoint,$p256dh,$auth){
db()->prepare("DELETE FROM push_subscriptions WHERE endpoint=?")->execute([$endpoint]);
db()->prepare("INSERT INTO push_subscriptions(user_id,endpoint,p256dh,auth,created_at) VALUES(?,?,?,?,?)")->execute([$userId,$endpoint,$p256dh,$auth,now()]);
}
function remove_push_subscription($endpoint){
db()->prepare("DELETE FROM push_subscriptions WHERE endpoint=?")->execute([$endpoint]);
}
function push_subscriptions_for_user($userId){
$st=db()->prepare("SELECT * FROM push_subscriptions WHERE user_id=?"); $st->execute([$userId]); return $st->fetchAll();
}
function has_push_subscription($userId){
$st=db()->prepare("SELECT COUNT(*) c FROM push_subscriptions WHERE user_id=?"); $st->execute([$userId]); return (int)$st->fetch()['c']>0;
}
// ---- Реализация протокола Web Push (RFC 8291 шифрование + RFC 8292 VAPID) без внешних библиотек ----
function b64url_encode($bin){ return rtrim(strtr(base64_encode($bin),'+/','-_'),'='); }
function b64url_decode($str){ $pad=strlen((string)$str)%4; if($pad) $str.=str_repeat('=',4-$pad); return base64_decode(strtr((string)$str,'-_','+/')); }
function hkdf_extract($salt,$ikm){ return hash_hmac('sha256',$ikm,$salt,true); }
function hkdf_expand($prk,$info,$len){ return substr(hash_hmac('sha256',$info."\x01",$prk,true),0,$len); }
// Подпись ECDSA от openssl_sign приходит в DER — JOSE/ES256 ждёт «сырые» r||s по 32 байта.
function ecdsa_der_to_raw($der){
$off=1; // пропустить тег SEQUENCE (0x30)
$off++; // пропустить байт длины (для P-256 подписи всегда короткая форма)
$off++; // тег INTEGER (0x02) для r
$rlen=ord($der[$off]); $off++;
$r=substr($der,$off,$rlen); $off+=$rlen;
$off++; // тег INTEGER (0x02) для s
$slen=ord($der[$off]); $off++;
$s=substr($der,$off,$slen); $off+=$slen;
$norm=function($b){ while(strlen($b)>32 && ord($b[0])===0) $b=substr($b,1); return str_pad($b,32,"\x00",STR_PAD_LEFT); };
return $norm($r).$norm($s);
}
function der_to_pem($der,$label){ return "-----BEGIN $label-----\n".chunk_split(base64_encode($der),64,"\n")."-----END $label-----\n"; }
// Собрать PEM приватного EC-ключа (P-256) из «сырых» d и публичной точки — так как ключи
// хранятся в конфиге в компактном base64url-виде, а не как готовый PEM-файл.
function vapid_private_pem(){
$cfg=cfg()['vapid'];
$d=b64url_decode($cfg['private']);
$pub=b64url_decode($cfg['public']); // 65 байт: 0x04 || X(32) || Y(32)
$version="\x02\x01\x01";
$privKey="\x04\x20".$d;
$oidTlv="\x06\x08".hex2bin('2a8648ce3d030107'); // OID prime256v1
$params="\xa0".chr(strlen($oidTlv)).$oidTlv;
$pubBit="\x00".$pub;
$pubTlv="\x03".chr(strlen($pubBit)).$pubBit;
$pubField="\xa1".chr(strlen($pubTlv)).$pubTlv;
$body=$version.$privKey.$params.$pubField;
$der="\x30".chr(strlen($body)).$body;
return der_to_pem($der,'EC PRIVATE KEY');
}
function vapid_public_pem_for($pubBin){
// Публичный ключ подписчика (p256dh) в виде PEM — нужен для ECDH через openssl_pkey_derive.
$oidTlv="\x06\x08".hex2bin('2a8648ce3d030107');
$algId="\x30".chr(strlen("\x06\x07".hex2bin('2a8648ce3d0201').$oidTlv))."\x06\x07".hex2bin('2a8648ce3d0201').$oidTlv; // ecPublicKey + prime256v1
$bit="\x00".$pubBin;
$bitTlv="\x03".chr(strlen($bit)).$bit;
$body=$algId.$bitTlv;
$der="\x30".chr(strlen($body)).$body;
return der_to_pem($der,'PUBLIC KEY');
}
function vapid_jwt($audience){
$cfg=cfg()['vapid'];
$header=b64url_encode(json_encode(['typ'=>'JWT','alg'=>'ES256']));
$claims=b64url_encode(json_encode(['aud'=>$audience,'exp'=>time()+12*3600,'sub'=>$cfg['subject']]));
$signInput=$header.'.'.$claims;
$pk=openssl_pkey_get_private(vapid_private_pem());
if(!$pk) return false;
if(!openssl_sign($signInput,$derSig,$pk,OPENSSL_ALGO_SHA256)) return false;
return $signInput.'.'.b64url_encode(ecdsa_der_to_raw($derSig));
}
// Шифрование тела сообщения по RFC 8291 (aes128gcm) под ключи конкретной подписки браузера.
function webpush_encrypt($payload,$p256dhB64,$authB64){
if(!function_exists('openssl_pkey_derive')) return false; // старый PHP без ECDH — пуш просто не отправится
$uaPublic=b64url_decode($p256dhB64);
$authSecret=b64url_decode($authB64);
if(strlen($uaPublic)!==65 || strlen($authSecret)<1) return false;
// Разовая (эфемерная) пара ключей нашего сервера для этого конкретного сообщения.
$eph=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);
if(!$eph) return false;
$ephDetails=openssl_pkey_get_details($eph);
$asPublic="\x04".$ephDetails['ec']['x'].$ephDetails['ec']['y'];
$uaPem=vapid_public_pem_for($uaPublic);
$uaPub=openssl_pkey_get_public($uaPem);
if(!$uaPub) return false;
$sharedSecret=openssl_pkey_derive($uaPub,$eph,32);
if($sharedSecret===false) return false;
$keyInfo="WebPush: info\x00".$uaPublic.$asPublic;
$prkCombine=hkdf_extract($authSecret,$sharedSecret);
$ikm=hkdf_expand($prkCombine,$keyInfo,32);
$salt=random_bytes(16);
$prk=hkdf_extract($salt,$ikm);
$cek=hkdf_expand($prk,"Content-Encoding: aes128gcm\x00",16);
$nonce=hkdf_expand($prk,"Content-Encoding: nonce\x00",12);
$plain=$payload."\x02"; // делимитер конца записи (единственная запись, без паддинга)
$tag='';
$cipher=openssl_encrypt($plain,'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag);
if($cipher===false) return false;
$recordSize=pack('N',4096);
$header=$salt.$recordSize.chr(strlen($asPublic)).$asPublic;
return $header.$cipher.$tag;
}
// Отправить одно push-сообщение одной подписке. Никогда не бросает исключение наружу —
// сбой пуша не должен ронять действие пользователя (назначение спикера, подтверждение и т.п.).
function send_web_push($sub,$title,$body,$url=''){
if(!push_enabled()) return false;
try{
$endpoint=$sub['endpoint'];
$encrypted=webpush_encrypt(json_encode(['title'=>$title,'body'=>$body,'url'=>$url]),$sub['p256dh'],$sub['auth']);
if($encrypted===false) return false;
$origin=parse_url($endpoint,PHP_URL_SCHEME).'://'.parse_url($endpoint,PHP_URL_HOST);
$jwt=vapid_jwt($origin);
if($jwt===false) return false;
$ch=curl_init($endpoint);
curl_setopt_array($ch,[
CURLOPT_POST=>true,
CURLOPT_POSTFIELDS=>$encrypted,
CURLOPT_HTTPHEADER=>[
'Content-Type: application/octet-stream',
'Content-Encoding: aes128gcm',
'TTL: 86400',
'Authorization: vapid t='.$jwt.', k='.cfg()['vapid']['public'],
],
CURLOPT_RETURNTRANSFER=>true,
CURLOPT_TIMEOUT=>8,
]);
curl_exec($ch);
$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
curl_close($ch);
if($code===404 || $code===410){ remove_push_subscription($endpoint); }
return $code>=200 && $code<300;
}catch(Throwable $e){
error_log('Push-уведомление не отправлено: '.$e->getMessage());
return false;
}
}
// Отправить пуш всем устройствам пользователя, на которых он разрешил уведомления.
function notify_push($userId,$title,$body,$url=''){
if(!push_enabled()) return;
foreach(push_subscriptions_for_user($userId) as $sub) send_web_push($sub,$title,$body,$url);
}
// ========== СБРОС ПАРОЛЯ ==========
// Ограничение частоты — по email, а не по пользователю: чужой аккаунт можно
// заваливать письмами о сбросе, даже не имея своего логина.
function reset_request_allowed($email,$cooldownSeconds=60){
$email=strtolower(trim($email));
if($email==='') return true;
$st=db()->prepare("SELECT requested_at FROM password_reset_throttle WHERE email=?");
$st->execute([$email]); $row=$st->fetch();
$allowed=!$row || (time()-strtotime($row['requested_at']))>=$cooldownSeconds;
if($allowed){
if($row) db()->prepare("UPDATE password_reset_throttle SET requested_at=? WHERE email=?")->execute([now(),$email]);
else db()->prepare("INSERT INTO password_reset_throttle(email,requested_at) VALUES(?,?)")->execute([$email,now()]);
}
return $allowed;
}
function create_reset_token($email){
$u=find_user_by_email($email);
if(!$u) return [false,'Пользователь с таким email не найден.'];
$token=bin2hex(random_bytes(32));
$expires=date('Y-m-d H:i:s',strtotime('+1 hour'));
db()->prepare("INSERT INTO password_resets(user_id,token,expires_at,created_at) VALUES(?,?,?,?)")
->execute([$u['id'],$token,$expires,now()]);
return [true,['user'=>$u,'token'=>$token]];
}
function verify_reset_token($token){
$st=db()->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at>?");
$st->execute([$token,now()]); $r=$st->fetch();
if(!$r) return [false,'Ссылка недействительна или истекла.'];
return [true,$r];
}
function reset_password($token,$newpass){
[$ok,$row]=verify_reset_token($token); if(!$ok) return [false,$row];
$hash=password_hash($newpass,PASSWORD_DEFAULT);
db()->prepare("UPDATE users SET pass_hash=? WHERE id=?")->execute([$hash,$row['user_id']]);
db()->prepare("UPDATE password_resets SET used=1 WHERE token=?")->execute([$token]);
log_action($row['user_id'],'system','password_reset','users',$row['user_id'],'Сброс пароля по токену');
return [true,'Пароль изменён. Войдите с новым паролем.'];
}
function can_manage($actor,$target){
if(!$target) return false;
if($actor['role']==='operator'){
$og=$actor['op_group']??'';
if($og==='') return true;
if($target['role']==='admin') return true;
if($target['role']!=='school') return false;
return school_dept_group($target)===$og;
}
if($actor['role']==='fund'){
return $target['role']==='speaker' && (int)($target['fund_user_id']??0)===(int)$actor['id'];
}
if($actor['role']==='school'){
return $target['role']==='school' && (int)($target['parent_school_id']??0)===(int)$actor['id'];
}
return false;
}
// Фонды не привязаны к школьному/региональному отделу (в отличие от заявителей), поэтому
// сбросить пароль фонду — без почты, из раздела «Пользователи» — может любой оператор,
// даже если can_manage() не даёт ему прав управлять фондом в остальном (например, удалять).
function can_reset_password($actor,$target){
if(!$target) return false;
if($actor['role']==='operator' && $target['role']==='fund') return true;
return can_manage($actor,$target);
}
function admin_reset_password($admin,$target_id,$newpass){
$u=find_user_by_id($target_id);
if(!$u) return [false,'Пользователь не найден.'];
if(!can_reset_password($admin,$u)) return [false,'У вас нет прав управлять этим пользователем.'];
$hash=password_hash($newpass,PASSWORD_DEFAULT);
db()->prepare("UPDATE users SET pass_hash=? WHERE id=?")->execute([$hash,$target_id]);
log_action($admin['id'],$admin['role'],'admin_password_reset','users',$target_id,"Административный сброс пароля для {$u['login']}");
return [true,"Пароль пользователя {$u['login']} изменён на: $newpass"];
}
function delete_user($actor,$target_id){
$u=find_user_by_id($target_id);
if(!$u) return [false,'Пользователь не найден.'];
if(!can_manage($actor,$u)) return [false,'У вас нет прав удалять этого пользователя.'];
if((int)$target_id===(int)$actor['id']) return [false,'Нельзя удалить собственную учётную запись.'];
if($u['role']==='school'){ $c=db()->prepare("SELECT COUNT(*) c FROM requests WHERE school_user_id=?"); $c->execute([$target_id]); if($c->fetch()['c']>0) return [false,'У организации есть заявки — удаление невозможно.']; }
if($u['role']==='speaker'){ $c=db()->prepare("SELECT COUNT(*) c FROM request_speakers WHERE speaker_user_id=?"); $c->execute([$target_id]); if($c->fetch()['c']>0) return [false,'Спикер участвовал в заявках — удаление невозможно.']; }
db()->prepare("DELETE FROM users WHERE id=?")->execute([$target_id]);
log_action($actor['id'],$actor['role'],'delete_user','users',$target_id,"Удалён пользователь {$u['login']}");
return [true,"Пользователь {$u['login']} удалён."];
}
// ---------- спикеры ----------
// ---- календарь недоступности спикера ----
function list_unavailable($sid){ $st=db()->prepare("SELECT * FROM speaker_unavailable WHERE speaker_user_id=? ORDER BY date_from DESC"); $st->execute([$sid]); return $st->fetchAll(); }
function add_unavailable_period($speaker,$from,$to,$reason){
$from=substr((string)$from,0,10); $to=substr((string)$to,0,10);
if(!DateTime::createFromFormat('Y-m-d',$from) || !DateTime::createFromFormat('Y-m-d',$to)) return [false,'Укажите корректные даты.'];
if($to<$from) return [false,'Дата окончания раньше даты начала.'];
$reason=trim((string)$reason); if($reason==='') return [false,'Укажите причину.'];
db()->prepare("INSERT INTO speaker_unavailable(speaker_user_id,date_from,date_to,reason,created_at) VALUES(?,?,?,?,?)")->execute([$speaker['id'],$from,$to,$reason,now()]);
log_action($speaker['id'],'speaker','add_unavailable','users',$speaker['id'],"Добавлен период недоступности: $from — $to ($reason)");
return [true,'Период добавлен.'];
}
function remove_unavailable_period($speaker,$id){
$st=db()->prepare("SELECT * FROM speaker_unavailable WHERE id=? AND speaker_user_id=?"); $st->execute([$id,$speaker['id']]); $row=$st->fetch();
if(!$row) return [false,'Период не найден.'];
db()->prepare("DELETE FROM speaker_unavailable WHERE id=?")->execute([$id]);
log_action($speaker['id'],'speaker','remove_unavailable','users',$speaker['id'],"Удалён период недоступности: {$row['date_from']} — {$row['date_to']}");
return [true,'Период удалён.'];
}
function speaker_is_unavailable($sid,$date){
$date=substr((string)$date,0,10); if($date==='') return false;
$st=db()->prepare("SELECT COUNT(*) c FROM speaker_unavailable WHERE speaker_user_id=? AND date_from<=? AND date_to>=?");
$st->execute([$sid,$date,$date]); return (int)$st->fetch()['c']>0;
}
function pending_speakers($fund=null){ if($fund){ $st=db()->prepare("SELECT * FROM users WHERE role='speaker' AND active=0 AND fund_user_id=? ORDER BY id DESC"); $st->execute([$fund['id']]); return $st->fetchAll(); } return db()->query("SELECT * FROM users WHERE role='speaker' AND active=0 ORDER BY id DESC")->fetchAll(); }
// ---- районы и фонды ----
function districts_list(){ return db()->query("SELECT * FROM districts ORDER BY name")->fetchAll(); }
function district_names(){ return array_map(fn($d)=>$d['name'],districts_list()); }
function add_district($name){ $name=trim($name); if($name==='') return ''; try{ db()->prepare("INSERT INTO districts(name,neighbors,created_at) VALUES(?,?,?)")->execute([$name,'',now()]); }catch(Throwable $e){} return $name; }
function ensure_district($name){ $name=trim($name); if($name==='') return ''; $st=db()->prepare("SELECT name FROM districts WHERE name=?"); $st->execute([$name]); if($st->fetch()) return $name; return add_district($name); }
function default_neighbors(){ return [
'Тюмень'=>['Тюменский','Нижнетавдинский','Ярковский','Ялуторовский','Исетский','Заводоуковский'],
'Тюменский'=>['Тюмень','Нижнетавдинский','Ярковский','Ялуторовский','Исетский','Заводоуковский'],
'Нижнетавдинский'=>['Тюменский','Тюмень','Ярковский'],
'Ярковский'=>['Тюменский','Тюмень','Нижнетавдинский','Тобольский','Вагайский','Ялуторовский'],
'Тобольский'=>['Ярковский','Вагайский','Уватский'],
'Уватский'=>['Тобольский','Вагайский'],
'Вагайский'=>['Тобольский','Уватский','Ярковский','Аромашевский','Викуловский','Сорокинский'],
'Аромашевский'=>['Вагайский','Голышмановский','Сорокинский','Омутинский','Юргинский'],
'Юргинский'=>['Аромашевский','Омутинский','Заводоуковский','Ялуторовский'],
'Заводоуковский'=>['Ялуторовский','Упоровский','Омутинский','Юргинский','Тюменский'],
'Ялуторовский'=>['Тюменский','Тюмень','Заводоуковский','Упоровский','Исетский','Юргинский','Ярковский'],
'Исетский'=>['Тюменский','Тюмень','Ялуторовский','Упоровский'],
'Упоровский'=>['Исетский','Ялуторовский','Заводоуковский'],
'Омутинский'=>['Юргинский','Аромашевский','Голышмановский','Армизонский','Заводоуковский'],
'Голышмановский'=>['Аромашевский','Омутинский','Армизонский','Бердюжский','Ишимский','Сорокинский'],
'Армизонский'=>['Омутинский','Голышмановский','Бердюжский'],
'Бердюжский'=>['Армизонский','Голышмановский','Ишимский','Казанский'],
'Ишимский'=>['Голышмановский','Бердюжский','Казанский','Сорокинский','Абатский','Сладковский'],
'Казанский'=>['Бердюжский','Ишимский','Сладковский'],
'Сладковский'=>['Казанский','Ишимский','Абатский'],
'Абатский'=>['Ишимский','Сладковский','Викуловский','Сорокинский'],
'Сорокинский'=>['Викуловский','Абатский','Ишимский','Голышмановский','Аромашевский','Вагайский'],
'Викуловский'=>['Сорокинский','Абатский','Вагайский'],
]; }
function district_neighbors($name){ if($name==='') return []; $st=db()->prepare("SELECT neighbors FROM districts WHERE name=?"); $st->execute([$name]); $r=$st->fetch(); if(!$r||trim((string)$r['neighbors'])==='') return []; return array_values(array_filter(array_map('trim',explode(',',$r['neighbors'])))); }
function allowed_speaker_districts($applicantDistrict){ $applicantDistrict=trim((string)$applicantDistrict); if($applicantDistrict==='') return []; $set=[$applicantDistrict]; foreach(district_neighbors($applicantDistrict) as $n) $set[]=$n; return array_values(array_unique($set)); }
function update_district_neighbors($name,$neighbors){ $clean=implode(',',array_values(array_filter(array_map('trim',(array)$neighbors)))); db()->prepare("UPDATE districts SET neighbors=? WHERE name=?")->execute([$clean,$name]); }
function request_district($r){ $d=trim((string)($r['district']??'')); if($d!=='') return $d; $s=find_user_by_id($r['school_user_id']??0); return $s? (string)($s['district']??'') : ''; }
function all_funds(){ return db()->query("SELECT * FROM users WHERE role='fund' ORDER BY (parent_fund_id IS NOT NULL AND parent_fund_id<>0), display_name")->fetchAll(); }
function speaker_org_name($sid){ $s=find_user_by_id((int)$sid); if(!$s || empty($s['fund_user_id'])) return ''; $f=find_user_by_id((int)$s['fund_user_id']); return $f ? (string)$f['display_name'] : ''; }
function fund_name_by_id($fid){ $f=$fid?find_user_by_id((int)$fid):null; return $f ? (string)$f['display_name'] : ''; }
function head_funds(){ return db()->query("SELECT * FROM users WHERE role='fund' AND (parent_fund_id IS NULL OR parent_fund_id=0) ORDER BY display_name")->fetchAll(); }
function pending_subfunds($head){ $st=db()->prepare("SELECT * FROM users WHERE role='fund' AND parent_fund_id=? AND active=0 ORDER BY id DESC"); $st->execute([$head['id']]); return $st->fetchAll(); }
// ========== ДОПУСК К ВОЗРАСТНЫМ ГРУППАМ ==========
// Раньше допуск давал отдельный тест знаний оператора (банк вопросов, авто-проверка) — эта
// механика убрана целиком (банк вопросов пустовал, наполнить его было нечем). Теперь решение
// о допуске фонд/оператор принимает по результатам «Анкеты самооценки психологической
// готовности» (ниже) — она сдаётся по каждой возрастной группе, но сама ничего не блокирует.
function speaker_certified_groups($speaker){ return array_values(array_filter(explode(',',(string)($speaker['certified_age_groups']??'')))); }
// Фонд/оператор включает или отключает допуск спикера к возрастной группе. Включить можно
// только после того, как спикер хотя бы раз заполнил анкету самооценки психологической
// готовности по этой группе — по её результатам (баллы, разбор по линиям, возможный маркер
// острой реакции) фонд и принимает решение; сама анкета допуск не выдаёт автоматически.
function set_speaker_certification($actor,$speaker,$ageGroup,$on){
if(!array_key_exists($ageGroup,ages())) return [false,'Некорректная возрастная группа.'];
if($actor['role']==='fund'){
$spFund=speaker_fund_of($speaker['id']);
if(!$spFund || !in_array((int)$spFund['id'],fund_scope_ids($actor),true)) return [false,'Этот спикер относится к другому фонду.'];
} elseif($actor['role']!=='operator'){
return [false,'Нет прав.'];
}
$cur=speaker_certified_groups($speaker);
if($on){
if(!speaker_psych_readiness_last($speaker['id'],$ageGroup)) return [false,'Сначала спикер должен заполнить анкету самооценки психологической готовности по этой группе.'];
if(!in_array($ageGroup,$cur,true)) $cur[]=$ageGroup;
} else {
$cur=array_values(array_diff($cur,[$ageGroup]));
}
db()->prepare("UPDATE users SET certified_age_groups=? WHERE id=?")->execute([implode(',',$cur),$speaker['id']]);
log_action($actor['id'],$actor['role'],'set_speaker_certification','users',$speaker['id'],($on?'Включён':'Отключён')." допуск к группе «".ages()[$ageGroup]."»");
return [true,$on?'Допуск включён.':'Допуск отключён.'];
}
// ========== СУДИМОСТЬ СПИКЕРА ==========
// Самостоятельное декларирование при регистрации (и позже, в профиле). Наличие судимости не
// блокирует регистрацию и не отключает учётную запись — человек может пользоваться платформой,
// но исключается из подбора спикеров на занятия с детьми (см. free_speakers_for_request()).
function criminal_record_notice(){
return "В соответствии со статьёй 351.1 Трудового кодекса Российской Федерации к трудовой, ".
"волонтёрской и иной деятельности, связанной с обучением, воспитанием, развитием несовершеннолетних, ".
"организацией их отдыха и оздоровления, медицинским обеспечением, социальной защитой и социальным ".
"обслуживанием, в сфере детско-юношеского спорта, культуры и искусства с участием несовершеннолетних ".
"не допускаются лица, имеющие или имевшие судимость, подвергающиеся или подвергавшиеся уголовному ".
"преследованию за преступления против жизни и здоровья, свободы, чести и достоинства личности (за ".
"исключением незаконного помещения в психиатрический стационар, клеветы и оскорбления), половой ".
"неприкосновенности и половой свободы личности, против семьи и несовершеннолетних, здоровья населения ".
"и общественной нравственности, основ конституционного строя и безопасности государства, мира и ".
"безопасности человечества, а также против общественной безопасности.\n\n".
"На основании указанных вами при регистрации сведений организация не может допустить вас к проведению ".
"занятий с детьми и подростками в рамках программы «Уроки мужества». Ваша учётная запись при этом ".
"сохраняется и не блокируется — вы можете пользоваться другими доступными вам функциями платформы, ".
"но не будете предложены заявителям в качестве спикера ни по одной заявке.\n\n".
"Если вы считаете, что это ограничение применено к вам ошибочно, обратитесь к куратору вашего фонда ".
"или напишите на ".cfg()['support_email'].".";
}
function speaker_has_criminal_record($speaker){ return ($speaker['criminal_record']??'')==='yes'; }
function speaker_criminal_answer_needed($speaker){ return ($speaker['role']??'')==='speaker' && empty($speaker['criminal_record']); }
// Валидация и сохранение загруженной справки о несудимости (PDF/JPG/PNG, с проверкой реального
// содержимого файла, а не только расширения). Возвращает [ok, ошибка_или_null, имя_файла_или_null].
function criminal_cert_allowed_extensions(){ return ['pdf','jpg','jpeg','png']; }
function validate_uploaded_criminal_cert($file){
if(empty($file['name'])) return [true,null,null];
if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return [false,'Ошибка загрузки справки.',null];
if($file['size']>PHOTO_MAX_BYTES) return [false,'Файл справки слишком большой (максимум 8 МБ).',null];
$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
if(!in_array($ext,criminal_cert_allowed_extensions(),true)) return [false,'Справка должна быть в формате PDF, JPG или PNG.',null];
if($ext==='pdf'){
$head=@file_get_contents($file['tmp_name'],false,null,0,5);
if($head!=='%PDF-') return [false,'Файл повреждён или не является настоящим PDF.',null];
} else {
$info=@getimagesize($file['tmp_name']);
if(!$info||!in_array($info['mime'],['image/jpeg','image/png'],true)) return [false,'Файл повреждён или не является настоящим изображением JPG/PNG.',null];
}
$dir=__DIR__.'/uploads/certs'; if(!is_dir($dir)) mkdir($dir,0755,true);
$stored=bin2hex(random_bytes(16)).'.'.$ext;
if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$stored)) return [false,'Не удалось сохранить файл справки.',null];
return [true,null,$stored];
}
// $certPath может быть null при answer='no', если файл уже был загружен ранее (правка в профиле).
function set_speaker_criminal_record($speaker,$answer,$certDate,$certPath){
if(!in_array($answer,['yes','no'],true)) return [false,'Ответьте на вопрос о судимости.'];
$fields=['criminal_record'=>$answer,'criminal_record_set_at'=>now()];
if($answer==='no'){
$certDate=trim((string)$certDate);
if($certDate==='') return [false,'Укажите дату справки о несудимости.'];
if(!$certPath && empty($speaker['criminal_cert_path'])) return [false,'Загрузите справку о несудимости.'];
$fields['criminal_cert_date']=$certDate;
if($certPath) $fields['criminal_cert_path']=$certPath;
} else {
$fields['criminal_cert_date']=null;
}
$set=implode(',',array_map(fn($k)=>"$k=?",array_keys($fields)));
db()->prepare("UPDATE users SET $set WHERE id=?")->execute([...array_values($fields),$speaker['id']]);
log_action($speaker['id'],'speaker','criminal_record_set','users',$speaker['id'],$answer==='yes'?'Указана судимость — исключён из подбора на занятия':'Указано отсутствие судимости, справка приложена');
return [true,$answer];
}
// ========== ПРОВЕРКА ГОТОВНОСТИ СПИКЕРА К КОНКРЕТНОМУ ЗАНЯТИЮ ==========
// 4 фиксированных вопроса на тему занятия (requests.topic) — банк ведёт оператор. Спикер
// проходит проверку по теме конкретной подтверждённой заявки; если он уже проходил проверку по
// этой же теме успешно в пределах последних 14 дней (неважно, по какой заявке) — можно не
// пересдавать. Порог прохождения — все 4 вопроса верно (тема/цели/база/методика равноценны).
// Список тем для банка вопросов «Готовность к занятию» — только темы, у которых уже есть
// материалы (технологическая карта и т.п.) или заведённые вопросы. Свободные темы, которые
// заявитель когда-либо вписал вручную в заявке, сюда сознательно не попадают — иначе список
// быстро зарастает разовым текстом, никак не связанным с материалами.
function lesson_readiness_all_topics(){
$configured=array_column(db()->query("SELECT DISTINCT topic FROM lesson_readiness_questions ORDER BY topic")->fetchAll(),'topic');
$all=array_values(array_unique(array_merge($configured,material_topics())));
sort($all,SORT_STRING|SORT_FLAG_CASE);
return $all;
}
function lesson_readiness_dimensions(){ return [
'topic'=>'Тема занятия','goals'=>'Цели урока','materials'=>'Материальная база','method'=>'Методика проведения',
]; }
function lesson_readiness_questions_for_topic($topic){
$st=db()->prepare("SELECT * FROM lesson_readiness_questions WHERE topic=?"); $st->execute([$topic]);
$out=[]; foreach($st->fetchAll() as $q) $out[$q['dimension']]=$q; return $out;
}
function lesson_readiness_ready($topic){
$qs=lesson_readiness_questions_for_topic($topic);
foreach(array_keys(lesson_readiness_dimensions()) as $d) if(empty($qs[$d])) return false;
return true;
}
function set_lesson_readiness_question($topic,$dimension,$question,$opts,$correct,$actorId){
if(!array_key_exists($dimension,lesson_readiness_dimensions())) return [false,'Некорректный раздел вопроса.'];
$existing=db()->prepare("SELECT id FROM lesson_readiness_questions WHERE topic=? AND dimension=?");
$existing->execute([$topic,$dimension]); $row=$existing->fetch();
if($row){
db()->prepare("UPDATE lesson_readiness_questions SET question=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_option=?,created_by=?,created_at=? WHERE id=?")
->execute([$question,$opts['a'],$opts['b'],$opts['c'],$opts['d'],$correct,$actorId,now(),$row['id']]);
log_action($actorId,'operator','lesson_readiness_update','lesson_readiness_questions',$row['id'],"Изменён вопрос «{$dimension}» по теме «{$topic}»");
} else {
db()->prepare("INSERT INTO lesson_readiness_questions(topic,dimension,question,option_a,option_b,option_c,option_d,correct_option,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)")
->execute([$topic,$dimension,$question,$opts['a'],$opts['b'],$opts['c'],$opts['d'],$correct,$actorId,now()]);
$id=db()->lastInsertId();
log_action($actorId,'operator','lesson_readiness_add','lesson_readiness_questions',$id,"Добавлен вопрос «{$dimension}» по теме «{$topic}»");
}
return [true,'Сохранено.'];
}
function speaker_topic_last_pass($speakerId,$topic){
$st=db()->prepare("SELECT * FROM lesson_readiness_attempts WHERE speaker_user_id=? AND topic=? AND passed=1 ORDER BY id DESC LIMIT 1");
$st->execute([$speakerId,$topic]); return $st->fetch()?:null;
}
function speaker_topic_ready($speakerId,$topic,$days=14){
if(trim((string)$topic)==='') return true; // у заявки не указана тема — проверять нечего
$last=speaker_topic_last_pass($speakerId,$topic);
if(!$last) return false;
return (time()-strtotime($last['created_at']))<=$days*86400;
}
function submit_lesson_readiness($speaker,$topic,$requestId,$answers){
if(!lesson_readiness_ready($topic)) return [false,'По этой теме пока не заполнены вопросы проверки готовности.'];
$qs=lesson_readiness_questions_for_topic($topic);
$score=0; $total=0; $detail=[];
foreach(lesson_readiness_dimensions() as $dk=>$dl){
$q=$qs[$dk]; $total++;
$given=$answers[$dk]??null;
$ok=$given===$q['correct_option'];
if($ok) $score++;
$detail[]=['dimension'=>$dk,'label'=>$dl,'question'=>$q['question'],'given'=>$given,'correct'=>$q['correct_option'],'ok'=>$ok];
}
$passed=$score>=$total;
db()->prepare("INSERT INTO lesson_readiness_attempts(speaker_user_id,topic,request_id,score,total,passed,answers,created_at) VALUES(?,?,?,?,?,?,?,?)")
->execute([$speaker['id'],$topic,$requestId,$score,$total,$passed?1:0,json_encode($detail,JSON_UNESCAPED_UNICODE),now()]);
log_action($speaker['id'],'speaker','lesson_readiness_attempt','users',$speaker['id'],"Проверка готовности по теме «{$topic}»: {$score} из {$total}".($passed?' — пройдена':' — не пройдена'));
return [true,['score'=>$score,'total'=>$total,'passed'=>$passed]];
}
function speaker_lesson_readiness_history($speakerId){
$st=db()->prepare("SELECT * FROM lesson_readiness_attempts WHERE speaker_user_id=? ORDER BY id DESC"); $st->execute([$speakerId]); return $st->fetchAll();
}
// Перемешивает порядок вопросов (по разделам) и порядок вариантов ответа внутри каждого —
// как в остальных анкетах платформы. Буква варианта (для проверки correct_option) не меняется.
function lesson_readiness_questions_shuffled($topic){
$qs=lesson_readiness_questions_for_topic($topic);
$dims=array_keys(lesson_readiness_dimensions()); shuffle($dims);
$out=[];
foreach($dims as $dk){
$q=$qs[$dk]; if(!$q) continue;
$opts=['a'=>$q['option_a'],'b'=>$q['option_b'],'c'=>$q['option_c'],'d'=>$q['option_d']];
$keys=array_keys($opts); shuffle($keys);
$shuffled=[]; foreach($keys as $k) $shuffled[$k]=$opts[$k];
$out[]=['dimension'=>$dk,'label'=>lesson_readiness_dimensions()[$dk],'text'=>$q['question'],'options'=>$shuffled];
}
return $out;
}
// ========== АНКЕТА САМООЦЕНКИ ПСИХОЛОГИЧЕСКОЙ ГОТОВНОСТИ ==========
// Отдельный от теста знаний механизм: не блокирует подбор кандидатов (free_speakers_for_request
// его не учитывает) — это рабочий ориентир для куратора (фонд/оператор), не автоматический допуск.
// Блок "10-11" закрывает сразу три возрастные группы платформы (10-11 кл., СПО, вуз) — как в анкете.
function readiness_blocks(){
$w=['A'=>2,'B'=>1,'C'=>1,'D'=>0]; // единая шкала весов у всех 30 вопросов анкеты (кроме итогового)
$q=function($text,$a,$b,$c,$d) use($w){ return ['text'=>$text,'options'=>['A'=>['label'=>$a,'score'=>$w['A']],'B'=>['label'=>$b,'score'=>$w['B']],'C'=>['label'=>$c,'score'=>$w['C']],'D'=>['label'=>$d,'score'=>$w['D']]]]; };
return [
'1-4'=>['label'=>'1–4 класс (7–10 лет)','groups'=>['1-4'],'questions'=>[
$q('Вам предстоит рассказать первоклассникам о том, чем занимаются защитники Родины. Как вы себя чувствуете, представляя этот разговор?',
'Спокойно — знаю, что и как рассказать простыми словами','В целом готов, но заранее хочу проговорить формулировки с методистом','Есть тревога, не уверен, что найду нужный тон для малышей','Сама мысль о разговоре с детьми этого возраста вызывает у меня сильное внутреннее напряжение'),
$q('Вам нужно объяснить первоклассникам простыми словами, почему важно защищать Родину, — без пафоса и без страшных деталей. Насколько вы готовы к этому?',
'Легко нахожу простые и добрые слова о смелости, взаимовыручке, любви к Родине','В целом готов, но хочу заранее подобрать формулировки вместе с методистом','Мне сложно говорить об этом просто, без лишнего пафоса или сухости','Не представляю, как в двух словах объяснить это семилетнему ребёнку'),
$q('Заранее продумывая занятие, вы представляете, что ребёнок может спросить: «А тебе было страшно?». Что вы чувствуете, готовясь к такому вопросу?',
'Спокоен — заранее знаю, как коротко и честно ответить','Немного волнуюсь, но готов ответить','Не уверен, что сумею подобрать подходящие слова в моменте','Уже сама мысль о таком вопросе вызывает у меня сильное беспокойство'),
$q('Ребёнок спрашивает: «А у тебя друзья не потерялись?». Что вы делаете?',
'Отвечаю коротко и честно, без деталей, перевожу разговор на тему смелости и взаимовыручки','Теряюсь на секунду, но справляюсь и отвечаю спокойно','Не знаю заранее, как отвечу — предпочёл бы, чтобы такие вопросы снял куратор','Такой вопрос способен выбить меня из колеи настолько, что я не смогу продолжать занятие'),
$q('Во время рассказа неожиданно всплывает личное воспоминание, которое вы не планировали затрагивать. Как вы обычно реагируете в похожих ситуациях?',
'Замечаю это внутри, но веду занятие дальше без внешних проявлений','Может дрогнуть голос, но я быстро возвращаю себе контроль','Не уверен, что смогу справиться с этим при детях именно этого возраста','Знаю по опыту, что такие воспоминания выбивают меня надолго, а не на секунду'),
$q('Как быстро вы обычно замечаете, что начинаете внутренне «накручиваться» во время рассказа о своём опыте?',
'Замечаю почти сразу, ещё до того, как это становится заметно окружающим','Замечаю, но чуть позже, чем хотелось бы','Чаще замечаю уже постфактум, когда эмоция уже проявилась','Обычно не замечаю этого вовремя вообще'),
$q('Вы чувствуете, что разговор уходит в сторону, слишком тяжёлую для 7–10 лет. Что вы делаете?',
'Сразу мягко меняю тему на более лёгкую и понятную детям','Замечаю это не сразу, но перестраиваюсь по ходу занятия','Хотел бы заранее иметь готовый «план Б» с методистом на такой случай','Не уверен, что вообще замечу этот момент вовремя'),
$q('Тема разговора неожиданно становится тяжёлой лично для вас, а не только потенциально для детей. Как вы обычно поступаете?',
'Осознанно перевожу разговор в более спокойное русло, не теряя контакта с классом','Умею остановиться, но не всегда делаю это вовремя','Затрудняюсь заранее сказать, как поступлю в такой ситуации','Склонен продолжать говорить, даже когда чувствую, что тема стала тяжёлой для меня'),
$q('Как вы определяете для себя границу того, что можно и нельзя рассказывать первоклассникам?',
'Чётко понимаю, что с этим возрастом не говорю о потерях и риске напрямую, и держу эту границу','В целом понимаю границу, но хочу свериться с методистом перед первым занятием','Мне сложно заранее оценить, что «слишком» для этого возраста','Я привык рассказывать о своём опыте так, как есть, и мне трудно его «смягчать»'),
$q('Умеете ли вы завершить рассказ для этой аудитории на светлой, ободряющей ноте — так, чтобы дети ушли не напуганными, а с чувством гордости и тепла?',
'Да, для меня это естественная часть рассказа — я всегда веду разговор к смыслу и добрым чувствам','В целом умею, но хочу заранее продумать финал занятия с методистом','Мне сложно самому найти такой финал без подготовки','Я не думал об этом заранее и не уверен, что смогу так завершить разговор'),
]],
'5-9'=>['label'=>'5–9 класс (11–15 лет)','groups'=>['5-9'],'questions'=>[
$q('Подростки 11–15 лет обычно задают более прямые вопросы, чем малыши. Как вы оцениваете свою готовность к такому разговору?',
'Готов — понимаю, что этот возраст ждёт более взрослого и честного разговора','Готов, но хочу заранее продумать структуру рассказа с методистом','Не уверен, что удержу внимание и доверие этой аудитории','Испытываю сильное напряжение уже на этапе подготовки к такой встрече'),
$q('С этим возрастом уже можно говорить не только о фактах, но и о ценностях — долге, ответственности, любви к Родине. Насколько вы готовы говорить об этом убедительно, не скатываясь в лозунги?',
'Готов — умею говорить об этом искренне, через личный опыт, а не общими фразами','В целом готов, но хочу заранее продумать формулировки с методистом','Мне сложно говорить об этом так, чтобы не звучало как лозунг','Не уверен, что смогу удержать внимание подростков на этой теме'),
$q('Заранее готовясь к встрече, вы понимаете, что подросток может спросить что-то прямое и неудобное. Что вы чувствуете по этому поводу?',
'Спокоен — заранее продумываю возможные вопросы и свои ответы','Немного волнуюсь, но в целом готов','Стараюсь не думать об этом заранее, полагаясь на импровизацию','Заранее опасаюсь таких вопросов настолько, что это мешает мне готовиться'),
$q('Подросток спрашивает: «А ты видел, как погибают?» или «А тебе не страшно было умереть?». Ваша реакция?',
'Отвечаю честно, но дозированно — без травмирующих подробностей, с акцентом на смысл и ценности','Отвечаю, но чувствую, что мне нужно заранее продумать границы допустимой откровенности','Такие вопросы застают меня врасплох, не знаю заранее свою реакцию','Подобные вопросы способны выбить меня из равновесия настолько, что занятие придётся прервать'),
$q('Рассказывая о конкретном эпизоде своей службы, вы чувствуете, что эмоции нарастают сильнее, чем ожидали. Что происходит дальше?',
'Делаю паузу, дышу, продолжаю в спокойном темпе','Эмоция заметна классу, но я довожу мысль до конца','Не уверен, смогу ли остановить нарастание вовремя','По опыту знаю, что в такие моменты теряю контроль над собой на длительное время'),
$q('Насколько хорошо вы умеете отслеживать собственное эмоциональное состояние во время рассказа перед этой аудиторией?',
'Хорошо — обычно замечаю первые признаки волнения ещё до того, как оно усиливается','Замечаю, но иногда с небольшим запозданием','Часто понимаю, что «накрыло», только когда это уже произошло','Мне сложно отслеживать своё состояние в процессе рассказа'),
$q('Один из подростков явно расстроен темой разговора — видно по его реакции. Что вы делаете?',
'Замечаю сразу, снижаю накал, переключаюсь на более спокойный аспект темы','Замечаю, но не всегда знаю, как правильно среагировать в моменте','Предпочёл бы, чтобы за эмоциональным состоянием класса следил учитель или куратор, а не я','Обычно я сосредоточен на своём рассказе и могу не заметить реакцию отдельного ребёнка'),
$q('Вы замечаете, что тема разговора становится тяжёлой лично для вас во время встречи с подростками. Как вы обычно поступаете?',
'Осознанно перевожу разговор в более устойчивое для себя русло, сохраняя контакт с аудиторией','Умею остановиться, но не всегда делаю это вовремя','Затрудняюсь заранее сказать, как поступлю в такой ситуации','Склонен продолжать говорить даже тогда, когда чувствую, что тема стала тяжёлой для меня самого'),
$q('Как вы определяете, насколько подробно можно рассказывать этой аудитории о потерях и риске?',
'Ясно чувствую границу — говорю честно, но без деталей, травмирующих психику подростка','В целом понимаю, но перед первым занятием хочу обсудить формулировки с методистом','Мне сложно самому найти эту границу без подсказки','Считаю, что с этим возрастом уже нужно говорить всё как есть, без ограничений'),
$q('Умеете ли вы связать рассказ о личном опыте с более широким смыслом — долгом, ответственностью, любовью к Родине — так, чтобы подростки восприняли это искренне, а не как навязанную мораль?',
'Да, умею органично подвести разговор к смыслу и ценностям через собственный опыт','В целом умею, но хочу заранее продумать этот переход с методистом','Мне сложно сделать это без ощущения фальши','Обычно я не задумываюсь об этом отдельно и веду занятие как получится'),
]],
'10-11'=>['label'=>'10–11 класс, СПО и вуз (16+)','groups'=>['10-11','spo','vuz'],'questions'=>[
$q('Старшеклассники и студенты способны задавать взрослые, порой провокационные вопросы о смысле службы и цене решений. Как вы оцениваете свою готовность?',
'Готов к открытому, взрослому разговору, включая сложные темы','Готов, но хочу заранее обсудить с методистом рамки того, что уместно говорить публично','Испытываю неуверенность перед аудиторией этого возраста — она чувствует фальшь','Мысль о таком разговоре вызывает у меня сильное сопротивление или тревогу'),
$q('С этой аудиторией можно и нужно говорить о смысле выбора, о цене решений, о том, что стоит за понятием «служение Родине» — без упрощений. Насколько вы готовы к такому взрослому разговору о ценностях?',
'Готов — умею говорить об этом честно, без лозунгов, опираясь на собственный опыт и рассуждение','В целом готов, но хочу заранее обсудить рамки этого разговора с методистом','Мне сложно говорить об этом убедительно перед взрослой и критично настроенной аудиторией','Не уверен, что смогу удержать разговор на уровне серьёзной дискуссии'),
$q('Готовясь к встрече, вы понимаете, что студенты могут задать прямые и провокационные вопросы о смысле и цене службы. Что вы чувствуете по этому поводу?',
'Спокоен — заранее продумываю, как отвечать честно и по существу','Немного напряжён, но готов к такому разговору','Стараюсь не думать об этом заранее, что делает меня уязвимым в моменте','Заранее сильно тревожусь о таких вопросах, вплоть до желания отказаться от занятия'),
$q('Студент задаёт острый вопрос: «А стоило ли оно того?» или «Что ты чувствовал, когда терял товарищей?». Ваша реакция?',
'Отвечаю честно и по существу, опираясь на личный опыт, сохраняя спокойствие','Отвечаю, но такие вопросы требуют от меня внутреннего усилия каждый раз','Не уверен, как отреагирую — зависит от дня и состояния','Подобные вопросы способны вызвать у меня острую реакцию, с которой я не могу справиться при аудитории'),
$q('Рассказ о личном опыте потерь захватывает вас сильнее, чем вы рассчитывали, на глазах у взрослой аудитории. Как вы обычно с этим справляетесь?',
'Осознаю состояние, делаю паузу и продолжаю осознанно','Справляюсь, но с видимым усилием','Не всегда уверен, что смогу восстановить контроль в моменте','По опыту знаю, что такие темы выбивают меня из колеи на часы или дни после'),
$q('Насколько хорошо вы отслеживаете собственное эмоциональное состояние, выступая перед взрослой аудиторией на эту тему?',
'Хорошо — замечаю первые признаки задолго до того, как теряю контроль','Замечаю, но иногда с запозданием','Чаще осознаю состояние уже постфактум','Мне сложно отслеживать себя в процессе выступления на эту тему'),
$q('Вы видите, что часть аудитории тяжело воспринимает разговор. Что вы делаете?',
'Замечаю сразу и корректирую тон и содержание, не теряя контакта с аудиторией','Замечаю, но не всегда своевременно реагирую','Предпочёл бы, чтобы за реакцией аудитории следил куратор или преподаватель','Обычно сосредоточен на своём рассказе и могу не заметить реакцию аудитории'),
$q('Вы чувствуете, что разговор идёт в сторону, тяжёлую лично для вас, а не только для аудитории. Что вы делаете?',
'Осознанно перевожу разговор в более устойчивое для себя русло, сохраняя контакт с аудиторией','Умею остановиться, но не всегда делаю это вовремя','Затрудняюсь заранее сказать, как поступлю в такой ситуации','Склонен продолжать говорить даже тогда, когда чувствую, что тема стала тяжёлой для меня самого'),
$q('С этой аудиторией допустима большая откровенность. Как вы определяете для себя, где проходит граница личного и уместного для публичного разговора?',
'Чётко понимаю разницу между личным опытом, которым делюсь, и тем, что оставляю при себе','В целом понимаю, но хочу свериться с куратором по конкретным формулировкам','Мне сложно провести эту границу самостоятельно','Я готов рассказывать всё как есть, не задумываясь заранее о том, что стоит оставить за кадром'),
$q('Умеете ли вы вывести взрослый, порой тяжёлый разговор к осмысленному выводу о долге и ценностях — так, чтобы он не выглядел ни пропагандой, ни просто тяжёлым воспоминанием?',
'Да, умею выстроить разговор так, чтобы он завершился честным и осмысленным выводом','В целом умею, но хочу заранее продумать эту часть разговора с методистом','Мне сложно найти этот баланс самостоятельно','Обычно я не задумываюсь об этом отдельно и веду занятие как получится'),
]],
];
}
function readiness_final_question(){ return [
'text'=>'Сама анкета затрагивает личный опыт и воспоминания. Как вы себя чувствовали, отвечая на неё?',
'options'=>[
'A'=>'Спокойно, вопросы не вызвали у меня напряжения (норма)',
'B'=>'Было некоторое волнение при отдельных вопросах, но оно быстро проходило (лёгкий дискомфорт)',
'C'=>'Некоторые вопросы дались тяжело, потребовалось время, чтобы собраться и продолжить (выраженный дискомфорт)',
'D'=>'Отвечая, я испытал(а) сильное волнение, мне было трудно сосредоточиться, или появились физические проявления стресса просто от того, что я вспоминал(а) эти темы (острая реакция)',
]]; }
// Перемешивает порядок вопросов блока и порядок вариантов ответа КАЖДОГО вопроса при каждом
// показе анкеты. Индексы вопросов (для submit_readiness()) и буквы вариантов (для подсчёта
// баллов) не меняются — меняется только то, в каком порядке всё это показывается на экране.
function readiness_questions_shuffled($block){
$order=array_keys($block['questions']);
shuffle($order);
$out=[];
foreach($order as $i){
$q=$block['questions'][$i];
$optKeys=array_keys($q['options']); shuffle($optKeys);
$opts=[]; foreach($optKeys as $ok) $opts[$ok]=$q['options'][$ok];
$out[]=['orig_index'=>$i,'text'=>$q['text'],'options'=>$opts];
}
return $out;
}
function readiness_final_question_shuffled(){
$fq=readiness_final_question();
$keys=array_keys($fq['options']); shuffle($keys);
$opts=[]; foreach($keys as $k) $opts[$k]=$fq['options'][$k];
return ['text'=>$fq['text'],'options'=>$opts];
}
function readiness_status_for_score($score){ if($score>=15) return 'ready'; if($score>=7) return 'needs_prep'; return 'not_ready'; }
function readiness_status_meta($status){ return [
'ready'=>['label'=>'Готов','color'=>'green','desc'=>'Допуск к занятиям в этой возрастной группе без дополнительной подготовки.'],
'needs_prep'=>['label'=>'Условно готов, нужна подготовка','color'=>'amber','desc'=>'Направление на короткую подготовку с методистом перед первым занятием.'],
'not_ready'=>['label'=>'Не готов на данный момент','color'=>'gray','desc'=>'Рекомендация повременить с этим форматом работы или предложить другую возрастную группу.'],
][$status]??['label'=>$status,'color'=>'gray','desc'=>'']; }
function speaker_readiness_last($speakerId,$blockKey){
$st=db()->prepare("SELECT * FROM speaker_readiness_attempts WHERE speaker_user_id=? AND block_key=? ORDER BY id DESC LIMIT 1");
$st->execute([$speakerId,$blockKey]); return $st->fetch()?:null;
}
function speaker_readiness_overview($speaker){
$out=[]; foreach(readiness_blocks() as $bk=>$b) $out[$bk]=['label'=>$b['label'],'last'=>speaker_readiness_last($speaker['id'],$bk)];
return $out;
}
function speaker_readiness_history($speakerId){
$st=db()->prepare("SELECT * FROM speaker_readiness_attempts WHERE speaker_user_id=? ORDER BY id DESC"); $st->execute([$speakerId]); return $st->fetchAll();
}
// Самый свежий сигнал об острой реакции (ответ D на итоговый вопрос) — для куратора.
// Особый случай (см. документацию анкеты): НЕ трактуется как "не готов" и не влияет на допуск —
// только повод для мягкого личного разговора куратора со спикером и, при необходимости, рекомендации психолога.
// Смотрим последнюю попытку КАЖДОГО блока отдельно (не только самую свежую по времени
// среди всех блоков вообще) — иначе спокойное заполнение одного блока сразу после
// острой реакции в другом молча спрятало бы сигнал ещё до того, как куратор его увидит.
function speaker_readiness_acute_signals($speakerId){
$out=[];
foreach(readiness_blocks() as $bk=>$b){
$last=speaker_readiness_last($speakerId,$bk);
if($last && $last['final_answer']==='D') $out[$bk]=$last;
}
return $out;
}
function submit_readiness($speaker,$blockKey,$answers,$finalAnswer){
$blocks=readiness_blocks();
if(!array_key_exists($blockKey,$blocks)) return [false,'Некорректный блок анкеты.'];
if(!in_array($finalAnswer,['A','B','C','D'],true)) return [false,'Ответьте на итоговый вопрос анкеты.'];
$score=0; $detail=[];
foreach($blocks[$blockKey]['questions'] as $i=>$q){
$given=$answers[$i]??null;
if(!isset($q['options'][$given])) return [false,'Ответьте на все вопросы анкеты.'];
$score+=$q['options'][$given]['score'];
$detail[]=['question'=>$q['text'],'given'=>$given,'given_label'=>$q['options'][$given]['label']];
}
$status=readiness_status_for_score($score);
db()->prepare("INSERT INTO speaker_readiness_attempts(speaker_user_id,block_key,score,total,status,final_answer,answers,created_at) VALUES(?,?,?,?,?,?,?,?)")
->execute([$speaker['id'],$blockKey,$score,20,$status,$finalAnswer,json_encode($detail,JSON_UNESCAPED_UNICODE),now()]);
log_action($speaker['id'],'speaker','speaker_readiness_submit','users',$speaker['id'],"Анкета готовности «".$blocks[$blockKey]['label']."»: $score из 20 (".readiness_status_meta($status)['label'].")".($finalAnswer==='D'?' — острая реакция на анкету':''));
return [true,['score'=>$score,'status'=>$status,'final_answer'=>$finalAnswer]];
}
// ========== АНКЕТА САМООЦЕНКИ ПСИХОЛОГИЧЕСКОЙ ГОТОВНОСТИ (по возрастным группам) ==========
// Отдельный от «🧭 Анкеты самооценки готовности» механизм — привязан к конкретной возрастной
// группе (1-4/5-9/10-11/spo/vuz) и служит дополнительным ориентиром для фонда при решении о
// допуске (?p=speaker_view, переключатель set_speaker_certification()). Сама по себе НИЧЕГО
// не блокирует и не выдаёт автоматически — итоговое решение о допуске всегда за человеком.
function psych_readiness_lines(){
return [
1=>'Готовность говорить о теме СВО с этой аудиторией',
2=>'Реакция на вероятные вопросы аудитории',
3=>'Контроль над эмоциональным состоянием при воспоминаниях',
4=>'Умение остановиться или переключить тему',
5=>'Дозирование откровенности под уровень зрелости аудитории',
];
}
function psych_readiness_intro(){
return [
'purpose'=>'Это инструмент организационного отбора, а не клиническая психодиагностика. Анкета помогает спикеру и куратору увидеть реальную степень готовности говорить с конкретной возрастной аудиторией о теме участия в СВО — и понять, нужна ли дополнительная подготовка перед допуском к занятиям. Анкета не заменяет консультацию психолога.',
'howto'=>'Спикер заполняет тот блок (или блоки), с которой возрастной группой планирует работать. В каждом вопросе — конкретная ситуация и 4 варианта реакции с баллами (A=4, B=3, C=2, D=1). Выбирайте вариант, который честно отражает то, как вы, скорее всего, поведёте себя, а не то, что кажется «правильным» ответом. Здесь нет плохих ответов — есть разные этапы готовности, и это нормально.',
'why3'=>'Один вопрос на конструкт даёт много случайного шума — единственная неудачная формулировка или настроение в моменте искажает результат всей линии. Три вопроса на линию — рабочий психометрический минимум для организационного инструмента такого типа: достаточно, чтобы случайный «сбой» на одном вопросе не переворачивал итоговый статус, но анкета всё ещё проходится за разумное время.',
'footer'=>'Анкета разработана как инструмент организационного отбора для проекта «Уроки мужества 72» и не является медицинским или психодиагностическим документом.',
];
}
function psych_readiness_blocks(){
$q=function($line,$text,$a,$b,$c,$d){ return ['line'=>$line,'text'=>$text,'options'=>['A'=>['label'=>$a,'score'=>4],'B'=>['label'=>$b,'score'=>3],'C'=>['label'=>$c,'score'=>2],'D'=>['label'=>$d,'score'=>1]]]; };
return [
'1-4'=>['label'=>'1–4 класс (7–10 лет)','audience'=>'Дети этого возраста мыслят конкретно, задают прямые вопросы без учёта такта («А вы кого-то убили?», «А это больно — когда ранили?»), могут не осознавать тяжести темы и быстро переключаться. Разговор требует максимальной дозировки и бережности.','questions'=>[
$q(1,'Вам предстоит занятие с первоклассниками. Тема — «Что такое быть защитником». Как вы формулируете для себя цель разговора?',
'Рассказать простыми словами о мужестве, взаимовыручке и долге — без деталей боевых действий.',
'В целом понимаю цель, но не до конца продумал(а), как упростить свой опыт для этого возраста.',
'Мне сложно определить, что вообще уместно рассказывать таким маленьким детям.',
'Собираюсь рассказать, «как было на самом деле», в том числе непростые эпизоды — дети должны знать правду.'),
$q(1,'Накануне занятия с малышами вы понимаете, что нужно объяснить тему без единого пугающего слова. Насколько уверенно вы себя чувствуете при подготовке?',
'Уверенно — заранее продумываю простые формулировки и проверяю их на понятность для этого возраста.',
'В целом уверенно, но формулировки рождаются скорее по ходу занятия, чем заранее.',
'Испытываю трудности с подбором простых слов для тяжёлой темы.',
'Ощущаю сильную неуверенность уже на этапе подготовки к разговору с такими маленькими детьми.'),
$q(1,'Вас неожиданно попросили заменить другого спикера и провести занятие именно в 1–4 классе — раньше вы с этим возрастом не работали.',
'Соглашаюсь спокойно, адаптирую материал под возраст, при необходимости советуюсь с куратором.',
'Соглашаюсь, но чувствую заметное напряжение из-за незнакомого формата работы с самыми маленькими.',
'Соглашаюсь неохотно, не будучи уверенным(ой), что справлюсь с этой возрастной группой.',
'Стараюсь отказаться — работа с этим возрастом вызывает у меня выраженную тревогу.'),
$q(2,'Ребёнок поднимает руку и спрашивает: «А вам было страшно? А вас могли убить?»',
'Отвечаю спокойно и коротко, признавая чувство страха как нормальное, и перевожу разговор на то, что помогало справляться.',
'Отвечаю честно, но чувствую внутреннее напряжение и не всегда нахожу нужные слова сразу.',
'Теряюсь, не знаю, что ответить ребёнку, и либо ухожу от вопроса резко, либо говорю больше, чем планировал(а).',
'Вопрос вызывает у меня сильный дискомфорт, который я с трудом скрываю перед классом.'),
$q(2,'Один ребёнок вслух говорит: «А у моего папы тоже автомат, он тоже может умереть?»',
'Отвечаю бережно, не давая прямых прогнозов, переключаю внимание на то, что рядом есть взрослые, которые заботятся о нём.',
'Отвечаю, но чувствую, что не совсем готов(а) к такому личному повороту вопроса.',
'Теряюсь и не знаю, как реагировать на вопрос, затрагивающий личную семью ребёнка.',
'Такие вопросы вызывают у меня сильный дискомфорт, с которым сложно справиться на месте.'),
$q(2,'Несколько детей подряд поднимают руки с похожими вопросами про войну, ранения и смерть.',
'Спокойно отвечаю на каждый вопрос по очереди, при необходимости мягко ограничиваю количество вопросов на эту тему.',
'Отвечаю, но к концу серии вопросов чувствую нарастающую усталость и раздражение.',
'После нескольких похожих вопросов подряд теряю терпение или сбиваюсь с настроя.',
'Серия таких вопросов подряд может выбить меня из колеи вплоть до прекращения занятия.'),
$q(3,'Во время рассказа всплывает конкретное воспоминание, которое вызывает у вас сильные эмоции прямо перед классом.',
'Замечаю нахлынувшую эмоцию, делаю короткую паузу, беру себя в руки и продолжаю занятие в рабочем русле.',
'Справляюсь, но это стоит заметных усилий, и после занятия долго «отхожу».',
'Голос срывается, эмоция читается детьми, приходится прерывать занятие или откровенно менять тему.',
'Такие воспоминания периодически выбивают меня из колеи настолько, что я боюсь идти на занятие именно из-за них.'),
$q(3,'Вы замечаете у себя физические признаки волнения — дрожат руки, перехватывает дыхание — ещё до того, как это стало заметно детям.',
'Замечаю сигналы заранее и использую заготовленный приём (дыхание, пауза, глоток воды), чтобы вернуть контроль незаметно.',
'Замечаю, но не всегда успеваю справиться до того, как это станет заметно.',
'Обычно замечаю такие сигналы только тогда, когда они уже проявились внешне.',
'Такие физические реакции у меня случаются часто и плохо поддаются контролю.'),
$q(3,'После занятия с младшими классами вы возвращаетесь домой. Как обычно проходит ваше восстановление?',
'Спокойно прихожу в обычное состояние в течение короткого времени, занятие не «тянется» за мной.',
'Восстановление занимает заметное время, но в целом проходит без посторонней помощи.',
'После таких занятий подолгу нахожусь в тяжёлом состоянии, это заметно окружающим.',
'Тяжёлое состояние после занятия может длиться днями и влиять на другие сферы жизни.'),
$q(4,'Вы чувствуете, что тема становится слишком тяжёлой — либо для вас, либо класс явно теряется и пугается.',
'Заранее держу в голове 2–3 «мостика» — фразы, чтобы мягко перевести разговор в более лёгкое русло.',
'Понимаю, что нужно остановиться, но заранее не готовил(а) конкретных приёмов переключения.',
'Замечаю проблему только тогда, когда она уже видна всем в классе.',
'Мне трудно остановиться самому/самой, даже когда я вижу, что аудитории тяжело.'),
$q(4,'Один из детей явно расстроился или испугался от услышанного — видно по лицу, возможно, по слезам.',
'Сразу замечаю реакцию ребёнка, мягко подхожу, переключаю внимание всей группы на более лёгкую тему.',
'Замечаю реакцию, но не всегда сразу нахожу, что сказать в этот момент.',
'Замечаю реакцию ребёнка с заметной задержкой, уже после того как ситуация усугубилась.',
'Обычно не замечаю такие реакции у детей вовремя.'),
$q(4,'Занятие идёт по эмоционально нарастающей — тема сама по себе тянет к более тяжёлым деталям.',
'Сознательно ограничиваю глубину погружения заранее, не позволяя разговору «набрать обороты» сверх нужного для этого возраста.',
'В целом слежу за этим, но иногда увлекаюсь и захожу немного дальше, чем планировал(а).',
'Мне сложно вовремя затормозить нарастающий эмоциональный ход разговора.',
'Обычно замечаю, что зашёл(шла) слишком далеко, только по факту, а не заранее.'),
$q(5,'Как вы определяете границу «что можно, а что нельзя» рассказывать первоклассникам?',
'Заранее продумал(а) содержание занятия с методистом/куратором и убрал(а) всё, что может травмировать.',
'Ориентируюсь по ситуации, интуитивно, без предварительной подготовки материала.',
'Мне сложно понять, где проходит граница уместного для такого маленького возраста.',
'Не вижу большой разницы в подаче между младшими школьниками и более старшей аудиторией.'),
$q(5,'Ребёнок просит рассказать «поподробнее» о конкретном тяжёлом эпизоде вашей службы.',
'Мягко объясняю, что об этом лучше поговорить, когда они станут постарше, и предлагаю более подходящую по возрасту тему.',
'В целом соблюдаю границу, но иногда поддаюсь и рассказываю чуть больше, чем планировал(а).',
'Мне сложно отказать ребёнку в его любопытстве, даже когда понимаю, что деталь не для этого возраста.',
'Обычно рассказываю то, что просят, не оценивая заранее уместность деталей для возраста.'),
$q(5,'В одном классе оказались дети с разным уровнем зрелости — кто-то уже слышал о войне дома, кто-то нет.',
'Ориентируюсь на самого чувствительного ребёнка в группе, выбирая наиболее бережный уровень подачи для всех.',
'Стараюсь балансировать, но иногда подача получается не совсем подходящей кому-то из детей.',
'Обычно ориентируюсь на «среднего» ребёнка, не всегда учитывая более чувствительных.',
'Не задумываюсь отдельно о разнице в зрелости детей внутри одного класса.'),
]],
'5-9'=>['label'=>'5–9 класс (11–15 лет)','audience'=>'Подростки уже способны на эмпатию, но реагируют остро, задают более «взрослые» и провокационные вопросы (про смерть товарищей, страх, справедливость войны), могут проверять спикера на прочность.','questions'=>[
$q(1,'Занятие с 7-м классом. Как вы формулируете для себя рамку разговора?',
'Готов(а) говорить честно о сложности опыта, но в рамках, которые не травмируют и не героизируют насилие.',
'В целом готов(а), но чувствую неуверенность, как удержать баланс между честностью и бережностью.',
'Планирую рассказывать так же, как рассказал(а) бы взрослым — подростки уже почти взрослые.',
'Испытываю сильную тревогу перед самой мыслью об этом разговоре с этой аудиторией.'),
$q(1,'Готовясь к занятию с этим возрастом, вы продумываете, как удержать баланс между честностью и бережностью. Насколько уверенно это получается?',
'Уверенно — заранее выстраиваю структуру разговора и проверяю формулировки на этичность и понятность подросткам.',
'В целом уверенно, но окончательные формулировки чаще рождаются в моменте, чем заранее.',
'Испытываю трудности с балансом между честностью и бережностью при подготовке.',
'Ощущаю выраженную неуверенность уже на этапе подготовки к этой аудитории.'),
$q(1,'Вас просят срочно заменить спикера для параллельного класса — группа подростков вам незнакома и, по слухам, «сложная».',
'Соглашаюсь спокойно, адаптируюсь по ходу, при необходимости прошу совета у куратора о специфике группы.',
'Соглашаюсь, но ощущаю заметное напряжение из-за неизвестной, потенциально «сложной» аудитории.',
'Соглашаюсь неохотно, не уверен(а) в своей готовности к незнакомой сложной группе.',
'Стараюсь отказаться — незнакомая «сложная» аудитория подростков вызывает у меня выраженную тревогу.'),
$q(2,'Подросток спрашивает: «А у вас погибали друзья? Что вы почувствовали?» — весь класс замолкает.',
'Отвечаю честно, но дозированно, называя чувство, не погружаясь в подробности события.',
'Отвечаю, но ощущаю, что теряю часть контроля над тоном и темпом речи.',
'Такой вопрос застаёт меня врасплох, и я не знаю заранее, как на него реагировать.',
'Вопрос о погибших товарищах — тема, которую я стараюсь избегать в принципе, в любой аудитории.'),
$q(2,'Подросток задаёт провокационный вопрос: «А вам не страшно, что вас потом будут не героем считать, а виноватым?»',
'Спокойно воспринимаю как часть честного диалога, отвечаю по существу, не переходя в оборону.',
'Отвечаю по существу, но вопрос задевает сильнее, чем хотелось бы показать.',
'Такой вопрос вызывает у меня раздражение, которое трудно скрыть.',
'Провокационные вопросы такого рода выбивают меня из разговора почти полностью.'),
$q(2,'Несколько подростков подряд задают похожие острые вопросы, явно проверяя вашу реакцию «на прочность».',
'Сохраняю ровный тон на протяжении всей серии вопросов, не поддаваясь на провокацию.',
'Держусь, но к концу серии заметно устаю и теряю часть уверенности в ответах.',
'После нескольких таких вопросов подряд начинаю раздражаться или защищаться.',
'Серия проверочных вопросов может выбить меня из равновесия настолько, что теряю контроль над занятием.'),
$q(3,'Рассказывая о конкретном эпизоде, вы замечаете, что голос дрожит, к горлу подступает ком.',
'Замечаю это заранее по «внутренним маркерам» и успеваю сделать паузу или сменить тему до того, как это станет заметно.',
'Справляюсь через усилие воли, но эмоция всё равно частично проявляется внешне.',
'Эмоция становится заметной для класса, и мне сложно вернуть контроль в моменте.',
'Подобные состояния случаются регулярно и заставляют меня сомневаться, стоит ли мне вообще проводить такие встречи.'),
$q(3,'Вы замечаете физические признаки волнения (дрожь в голосе, учащённое дыхание) ещё до того, как это стало заметно классу.',
'Замечаю сигналы заранее и использую отработанный приём, чтобы вернуть контроль до того, как это станет видно классу.',
'Замечаю, но не всегда успеваю справиться прежде, чем это станет заметно.',
'Обычно замечаю такие сигналы только тогда, когда они уже видны подросткам.',
'Такие реакции случаются часто и плохо поддаются моему контролю.'),
$q(3,'После занятия с этим возрастом — как обычно проходит ваше восстановление?',
'Прихожу в обычное состояние в течение короткого времени, занятие не «тянется» за мной.',
'Восстановление занимает заметное время, но проходит без посторонней помощи.',
'После таких занятий подолгу нахожусь в тяжёлом состоянии, это заметно окружающим.',
'Тяжёлое состояние может длиться днями и влиять на другие сферы жизни.'),
$q(4,'Обсуждение уходит в сторону, которая явно перегружает подростков или задевает лично вас — например, спор о справедливости конфликта.',
'Уверенно возвращаю разговор в заданные рамки, обозначив вслух, что тема требует более взрослого и отдельного разговора.',
'Пытаюсь вернуть тему в рамки, но не всегда получается сделать это уверенно.',
'Позволяю дискуссии развиваться дальше, даже понимая, что теряю контроль над форматом занятия.',
'Сам(а) невольно втягиваюсь в спор эмоционально и теряю позицию ведущего/спикера.'),
$q(4,'Часть класса явно перегружена темой — кто-то замыкается, кто-то шутит нервно, чтобы снять напряжение.',
'Считываю разные типы реакции (замыкание, нервный смех) и подбираю способ снизить напряжение для всей группы.',
'Считываю часть реакций, но не всегда нахожу подходящий способ снизить напряжение сразу.',
'Замечаю перегруз аудитории с заметной задержкой.',
'Обычно не отслеживаю подобные групповые реакции в процессе занятия.'),
$q(4,'Разговор идёт по нарастающей, и вы сами чувствуете азарт и желание рассказать больше, хотя понимаете, что уже достаточно.',
'Сознательно останавливаю себя, ориентируясь на заранее продуманную границу, а не на собственный эмоциональный подъём.',
'В целом слежу за этим, но иногда увлекаюсь и захожу дальше, чем планировал(а).',
'Мне сложно вовремя затормозить, когда сам(а) эмоционально вовлечён(а) в рассказ.',
'Обычно замечаю, что зашёл(шла) слишком далеко, только постфактум.'),
$q(5,'Как вы определяете, какие детали своего опыта уместны для 7–9 класса, а какие — нет?',
'У меня есть чёткое разграничение по темам и деталям, согласованное заранее с куратором.',
'Ориентируюсь по реакции аудитории в моменте, без заранее подготовленных границ.',
'Считаю, что подростки способны воспринять почти всё, что я готов(а) рассказать взрослым.',
'Не уверен(а), что вообще умею оценивать эту границу самостоятельно.'),
$q(5,'Подросток настойчиво просит рассказать «как было на самом деле», без прикрас, включая самые тяжёлые детали.',
'Мягко, но твёрдо объясняю границу, предлагая обсудить тему в ином, более подходящем формате при необходимости.',
'В целом соблюдаю границу, но иногда поддаюсь настойчивости и рассказываю больше, чем планировал(а).',
'Мне сложно отказать настойчивому подростку, даже понимая неуместность деталей.',
'Обычно рассказываю то, что просят, не оценивая заранее уместность для возраста.'),
$q(5,'В классе есть подростки с очень разным жизненным опытом — кто-то уже сталкивался с потерей близких, кто-то нет.',
'Ориентируюсь на самого уязвимого подростка в группе, выбирая бережный уровень подачи для всех.',
'Стараюсь балансировать, но иногда подача получается не совсем подходящей для кого-то из ребят.',
'Обычно ориентируюсь на «среднего» подростка, не всегда учитывая более уязвимых.',
'Не задумываюсь отдельно о разнице в жизненном опыте подростков внутри одного класса.'),
]],
'10-11'=>['label'=>'10–11 класс (16–17 лет)','audience'=>'Старшеклассники близки к призывному/выборному возрасту, задают прямые мировоззренческие вопросы о смысле, страхе, выборе, смерти; ожидают взрослого, честного разговора без «сюсюканья», но всё ещё психологически уязвимы.','questions'=>[
$q(1,'Занятие с выпускным классом, часть из которых уже задумывается о службе. Как вы формулируете свою позицию?',
'Готов(а) к прямому, взрослому разговору о плюсах, трудностях и личной ответственности выбора, без прикрас и без запугивания.',
'Готов(а) в целом, но опасаюсь либо перегнуть в сторону тяжести, либо, наоборот, приукрасить.',
'Планирую в основном мотивационную подачу — считаю, что в этом возрасте важнее вдохновить, чем показывать сложность.',
'Не уверен(а), что готов(а) к разговору со старшеклассниками именно на тему личного выбора и его последствий.'),
$q(1,'Готовясь к разговору о личном выборе и его последствиях с выпускным классом, насколько уверенно вы формулируете свою позицию заранее?',
'Уверенно — заранее продумываю честную и взвешенную формулировку, не скатываясь ни в пропаганду, ни в запугивание.',
'В целом уверенно, но формулировки чаще рождаются по ходу разговора, чем заранее.',
'Испытываю трудности с формулировкой взвешенной позиции для этой аудитории.',
'Ощущаю выраженную неуверенность уже на этапе подготовки к этому разговору.'),
$q(1,'Вас просят провести встречу с классом, где есть ребята, уже принявшие решение о службе после школы.',
'Спокойно соглашаюсь, понимая ответственность момента, готовлю честный и взвешенный разговор.',
'Соглашаюсь, но ощущаю дополнительное напряжение из-за близости темы к реальному выбору ребят.',
'Соглашаюсь неохотно, чувствуя дискомфорт от близости темы к реальным жизненным решениям слушателей.',
'Стараюсь избежать такой встречи — слишком высокая ответственность момента вызывает у меня сильную тревогу.'),
$q(2,'Старшеклассник прямо спрашивает: «А вы бы снова пошли, зная всё, что знаете сейчас?»',
'Готов(а) ответить честно и развёрнуто, включая амбивалентность своих чувств, без готового «правильного» шаблона.',
'Отвечу честно, но такой прямой вопрос выбивает меня из равновесия сильнее, чем хотелось бы.',
'У меня есть заготовленный «правильный» ответ, который я использую вне зависимости от того, что чувствую на самом деле.',
'Такие прямые вопросы я стараюсь избегать или обходить в разговоре.'),
$q(2,'Старшеклассник спрашивает прямо: «А что вы думаете о тех, кто отказался идти?»',
'Отвечаю честно и без осуждения, разделяя личный выбор и уважение к решению других людей.',
'Отвечаю честно, но вопрос вызывает у меня заметное внутреннее напряжение.',
'Такой вопрос вызывает желание уйти от прямого ответа или закрыть тему.',
'Подобные вопросы вызывают сильную эмоциональную реакцию, которую сложно скрыть.'),
$q(2,'Несколько старшеклассников подряд задают острые мировоззренческие вопросы, ожидая развёрнутых честных ответов.',
'Сохраняю ровный, содержательный тон на протяжении всей серии вопросов.',
'Держусь, но к концу серии заметно устаю и начинаю сокращать ответы.',
'После нескольких таких вопросов подряд теряю часть уверенности в ответах.',
'Серия острых вопросов может выбить меня из равновесия настолько, что теряю нить разговора.'),
$q(3,'Вопрос аудитории затрагивает конкретное тяжёлое воспоминание в разгар занятия.',
'Умею быстро восстановить равновесие и осознанно решаю, стоит ли делиться воспоминанием целиком или частично.',
'Справляюсь, но чувствую, что граница «поделиться / не поделиться» размывается под влиянием момента.',
'В такие моменты мне трудно отследить, что я говорю больше, чем изначально планировал(а).',
'Подобные воспоминания могут выбить меня из колеи на всё оставшееся занятие.'),
$q(3,'Вы замечаете у себя физические признаки волнения ещё до того, как это стало заметно классу.',
'Замечаю сигналы заранее и использую отработанный приём, чтобы вернуть контроль незаметно.',
'Замечаю, но не всегда успеваю справиться прежде, чем это станет видно.',
'Обычно замечаю такие сигналы только тогда, когда они уже видны аудитории.',
'Такие реакции случаются часто и плохо поддаются контролю.'),
$q(3,'После встречи с выпускным классом — как обычно проходит ваше восстановление?',
'Прихожу в обычное состояние достаточно быстро, встреча не «тянется» за мной.',
'Восстановление занимает заметное время, но проходит без посторонней помощи.',
'После таких встреч подолгу нахожусь в тяжёлом состоянии, это заметно окружающим.',
'Тяжёлое состояние может длиться днями и влиять на другие сферы жизни.'),
$q(4,'Дискуссия становится слишком тяжёлой для конкретных ребят в классе (видно по реакции), хотя вам лично продолжать несложно.',
'Замечаю состояние аудитории в первую очередь и меняю тему, даже если сам(а) мог(ла) бы продолжать.',
'В целом слежу за реакцией класса, но иногда замечаю перегруз с опозданием.',
'Ориентируюсь в основном на себя — если мне комфортно продолжать, не всегда считываю дискомфорт аудитории.',
'Обычно не отслеживаю реакцию аудитории в процессе рассказа.'),
$q(4,'Часть класса явно перегружена темой выбора и ответственности, хотя внешне держится спокойно.',
'Считываю скрытые признаки перегруза и корректирую темп и глубину разговора для всей группы.',
'Считываю часть таких сигналов, но не всегда нахожу подходящий момент скорректировать разговор.',
'Замечаю перегруз аудитории с заметной задержкой.',
'Обычно не отслеживаю скрытые сигналы перегруза у старшеклассников.'),
$q(4,'Обсуждение личного выбора незаметно перерастает в спор о более широких, политических вопросах.',
'Уверенно возвращаю разговор к личному опыту и формату встречи, не давая ему перерасти в политическую дискуссию.',
'В целом удерживаю рамки, но не всегда сразу и не без внутреннего напряжения.',
'Позволяю дискуссии развиваться дальше, теряя контроль над форматом встречи.',
'Сам(а) невольно втягиваюсь в спор, теряя позицию ведущего.'),
$q(5,'Что определяет глубину откровенности с 10–11 классом по сравнению со взрослой аудиторией?',
'Понимаю, что несмотря на кажущуюся взрослость, это всё ещё школьники, и заранее выстраиваю границы вместе с куратором.',
'В целом понимаю разницу, но границы держу скорее интуитивно, чем через подготовленный план.',
'Считаю, что с этим возрастом уже можно говорить практически как со взрослыми, без существенных ограничений.',
'Не задумывался(ась) отдельно об этой возрастной специфике.'),
$q(5,'Старшеклассник настойчиво просит рассказать «без цензуры», аргументируя тем, что скоро сам может там оказаться.',
'Твёрдо, но уважительно объясняю границу откровенности, не поддаваясь на аргумент «скоро сам окажется».',
'В целом соблюдаю границу, но иногда поддаюсь настойчивости и рассказываю больше, чем планировал(а).',
'Мне сложно отказать в таком настойчивом и по-своему обоснованном запросе.',
'Обычно рассказываю то, что просят, не оценивая заранее уместность деталей.'),
$q(5,'В классе есть ребята, уже точно решившие пойти на службу, и те, кто настроен категорически против.',
'Выстраиваю подачу так, чтобы быть честным и уважительным к обеим позициям одновременно, не давя ни на одну из сторон.',
'Стараюсь балансировать, но иногда подача получается ближе к одной из позиций.',
'Обычно ориентируюсь на «средний» настрой класса, не всегда учитывая полярные позиции.',
'Не задумываюсь отдельно о разнице позиций внутри одного класса.'),
]],
'spo'=>['label'=>'СПО (студенты колледжей и техникумов)','audience'=>'Часто более социально разнородная и «взрослая по опыту» аудитория, среди слушателей могут быть ребята из сложных семей, будущие призывники, порой скептически или отстранённо настроенные; ожидают уважения к себе как к почти взрослым, реагируют на фальшь.','questions'=>[
$q(1,'Группа СПО слушает без явного интереса, часть демонстративно отстранена. Как вы формулируете свою задачу?',
'Готов(а) удерживать содержательный, уважительный разговор независимо от первоначальной реакции аудитории.',
'Готов(а), но отстранённость и скепсис части аудитории вызывают у меня внутреннее напряжение.',
'Такая реакция аудитории заставляет меня либо сворачивать в формальность, либо, наоборот, форсировать эмоциональность рассказа.',
'Скептически настроенная аудитория — то, к чему я пока не готов(а) выходить.'),
$q(1,'Готовясь к встрече с группой СПО, вы понимаете, что часть аудитории может быть настроена скептически. Насколько уверенно вы себя чувствуете при подготовке?',
'Уверенно — заранее продумываю содержательную подачу, устойчивую к скепсису и отстранённости аудитории.',
'В целом уверенно, но конкретные формулировки чаще рождаются по ходу занятия.',
'Испытываю трудности с подготовкой к потенциально скептически настроенной аудитории.',
'Ощущаю выраженную неуверенность уже на этапе подготовки к этой группе.'),
$q(1,'Вас просят провести встречу в группе, где, по словам куратора, «сложный контингент» — ребята из непростых жизненных обстоятельств.',
'Соглашаюсь спокойно, готовлюсь с учётом специфики группы, при необходимости советуюсь с куратором.',
'Соглашаюсь, но ощущаю заметное напряжение из-за формулировки «сложный контингент».',
'Соглашаюсь неохотно, не уверен(а) в своей готовности к такой группе.',
'Стараюсь отказаться — подобная характеристика группы вызывает у меня выраженную тревогу.'),
$q(2,'Студент задаёт прямой, отчасти провокационный вопрос: «А смысл вообще был в том, через что вы прошли?»',
'Спокойно воспринимаю провокацию как часть честного диалога и отвечаю по существу, без обиды и без давления в ответ.',
'Отвечаю по существу, но вопрос задевает меня сильнее, чем хотелось бы показать.',
'Такой вопрос вызывает у меня раздражение, которое я не всегда успеваю скрыть.',
'Провокационные вопросы такого рода выбивают меня из разговора почти полностью.'),
$q(2,'Студент задаёт неожиданный личный вопрос не по теме занятия: «А как вам вообще потом на гражданке — тяжело было?»',
'Спокойно отвечаю по существу, даже если вопрос вышел за рамки подготовленной темы.',
'Отвечаю, но неожиданность вопроса заметно выбивает меня из плана занятия.',
'Такой неожиданный личный вопрос застаёт меня врасплох, и я теряюсь с ответом.',
'Стараюсь уйти от таких личных вопросов, выходящих за рамки темы.'),
$q(2,'Несколько студентов подряд задают провокационные вопросы, явно проверяя вашу реакцию.',
'Сохраняю ровный тон на протяжении всей серии, не поддаваясь на провокацию.',
'Держусь, но к концу серии заметно устаю и теряю часть уверенности.',
'После нескольких таких вопросов подряд начинаю раздражаться.',
'Серия провокационных вопросов может выбить меня из равновесия настолько, что теряю контроль над встречей.'),
$q(3,'Аудитория реагирует прохладно именно в момент, когда вы делитесь личным тяжёлым эпизодом.',
'Сохраняю спокойствие независимо от реакции зала, продолжая занятие в рабочем темпе.',
'Внешне сохраняю контроль, но прохладная реакция на личный рассказ ощутимо задевает меня внутри.',
'Мне сложно продолжать ровно, если чувствую, что мой рассказ «не воспринимают всерьёз».',
'Такая ситуация может выбить меня из равновесия настолько, что занятие приходится прерывать или сворачивать.'),
$q(3,'Вы замечаете физические признаки волнения ещё до того, как это стало заметно группе.',
'Замечаю сигналы заранее и использую отработанный приём, чтобы вернуть контроль незаметно для аудитории.',
'Замечаю, но не всегда успеваю справиться прежде, чем это станет видно.',
'Обычно замечаю такие сигналы только тогда, когда они уже заметны группе.',
'Такие реакции случаются часто и плохо поддаются контролю.'),
$q(3,'После встречи с группой СПО — как обычно проходит ваше восстановление?',
'Прихожу в обычное состояние достаточно быстро, встреча не «тянется» за мной.',
'Восстановление занимает заметное время, но проходит без посторонней помощи.',
'После таких встреч подолгу нахожусь в тяжёлом состоянии, это заметно окружающим.',
'Тяжёлое состояние может длиться днями и влиять на другие сферы жизни.'),
$q(4,'Разговор в группе СПО сворачивает в сторону личных историй студентов о собственных сложных семейных или жизненных ситуациях.',
'Уверенно возвращаю фокус к теме занятия, при этом уважительно отмечая ценность того, чем поделились студенты.',
'Возвращаю тему в рамки, но не всегда чётко и не сразу.',
'Затрудняюсь корректно завершить чужой личный рассказ и вернуть занятие в русло.',
'Обычно позволяю разговору уходить далеко от темы, даже если время занятия ограничено.'),
$q(4,'Часть группы демонстративно теряет интерес — отвлекается на телефоны, переговаривается.',
'Спокойно отмечаю это, корректирую подачу или формат, не переходя в раздражение или давление на группу.',
'Замечаю, но не всегда нахожу способ вернуть внимание группы без внутреннего раздражения.',
'Такая реакция группы выбивает меня из уверенного темпа занятия.',
'Обычно не отслеживаю подобную групповую динамику в процессе занятия.'),
$q(4,'Обсуждение личных историй студентов затягивается, а время занятия ограничено.',
'Уверенно и уважительно завершаю обсуждение, возвращая фокус к теме занятия в рамках отведённого времени.',
'Завершаю обсуждение, но не всегда чётко укладываюсь в отведённое время.',
'Затрудняюсь корректно завершить обсуждение, время занятия часто выбивается из графика.',
'Обычно позволяю обсуждению идти своим чередом, невзирая на ограничения по времени.'),
$q(5,'Как вы определяете уровень откровенности со студентами СПО с учётом их разного жизненного опыта?',
'Заранее продумываю универсальную, но честную подачу, подходящую для разнородной по опыту аудитории.',
'В целом ориентируюсь по ситуации, без заранее продуманной универсальной подачи.',
'Считаю, что со студентами СПО можно говорить максимально жёстко и без ограничений — они уже «повидали жизнь».',
'Не уверен(а), как вообще калибровать откровенность под эту конкретную аудиторию.'),
$q(5,'Студент настойчиво просит рассказать «максимально жёстко, без цензуры», аргументируя тем, что «мы уже не дети».',
'Твёрдо, но уважительно объясняю личную границу откровенности, не поддаваясь на аргумент о взрослости.',
'В целом соблюдаю границу, но иногда поддаюсь настойчивости.',
'Мне сложно отказать в таком настойчивом запросе.',
'Обычно рассказываю то, что просят, не оценивая заранее уместность деталей.'),
$q(5,'В группе СПО студенты сильно различаются по жизненной зрелости — от очень юных до почти взрослых с непростым личным опытом.',
'Выстраиваю универсальную, но честную подачу, учитывающую разброс зрелости внутри одной группы.',
'Стараюсь балансировать, но иногда подача получается не совсем подходящей для части группы.',
'Обычно ориентируюсь на «средний» уровень зрелости, не всегда учитывая крайние случаи.',
'Не задумываюсь отдельно о разбросе зрелости внутри группы СПО.'),
]],
'vuz'=>['label'=>'ВУЗ','audience'=>'Наиболее «взрослая» по формату аудитория: способна к рефлексии, историческому и политическому контексту, может задавать сложные аналитические вопросы, ожидает содержательного, а не «упрощённого» разговора; при этом отдельные студенты также психологически уязвимы.','questions'=>[
$q(1,'Занятие или встреча со студентами вуза, ожидается содержательная дискуссия, включая непростые вопросы.',
'Готов(а) к содержательному, взрослому диалогу, включая сложные и неудобные вопросы, сохраняя личные границы.',
'Готов(а) в целом, но опасаюсь глубины аналитических вопросов больше, чем эмоциональных.',
'Предпочитаю по возможности избегать аналитической и политической части разговора, ограничиваясь личным опытом.',
'Формат содержательной дискуссии со студентами вызывает у меня сильную неуверенность.'),
$q(1,'Готовясь к встрече со студентами вуза, вы понимаете, что от вас ожидают содержательного анализа, а не только личной истории.',
'Уверенно — заранее продумываю содержательную, взвешенную подачу с учётом возможных сложных вопросов.',
'В целом уверенно, но конкретные формулировки чаще рождаются по ходу встречи.',
'Испытываю трудности с подготовкой содержательной части разговора для этой аудитории.',
'Ощущаю выраженную неуверенность уже на этапе подготовки к встрече со студентами.'),
$q(1,'Вас приглашают выступить на открытой встрече в вузе, где формат предполагает вопросы из зала без предварительного согласования тем.',
'Соглашаюсь спокойно, понимая формат и его открытость, готовлюсь к широкому спектру вопросов.',
'Соглашаюсь, но ощущаю заметное напряжение из-за открытого формата вопросов без подготовки.',
'Соглашаюсь неохотно, открытый формат вызывает у меня значительный дискомфорт.',
'Стараюсь отказаться от такого открытого формата — он вызывает у меня выраженную тревогу.'),
$q(2,'Студент задаёт развёрнутый вопрос с элементами критики или сомнения в отдельных решениях, принятых в ходе службы.',
'Спокойно разделяю личный опыт и общую оценку, отвечаю по существу без защитной реакции.',
'Отвечаю по существу, но ощущаю внутреннее сопротивление и защитную реакцию.',
'Такие вопросы вызывают у меня желание закрыть тему или уйти от прямого ответа.',
'Подобные вопросы вызывают у меня сильное раздражение или обиду, которые сложно скрыть.'),
$q(2,'Студент задаёт сложный, многосоставной вопрос, объединяющий личный опыт и политический контекст.',
'Спокойно разделяю части вопроса, отвечаю по существу на ту часть, что касается личного опыта, не уходя в политическую полемику.',
'Отвечаю по существу, но сложность и многосоставность вопроса заметно выбивают меня из равновесия.',
'Такой составной вопрос застаёт меня врасплох, и я теряюсь со структурой ответа.',
'Стараюсь уйти от таких сложных многосоставных вопросов целиком.'),
$q(2,'Несколько студентов подряд задают критические, аналитически выстроенные вопросы, ожидая содержательной полемики.',
'Сохраняю ровный, содержательный тон на протяжении всей серии вопросов.',
'Держусь, но к концу серии заметно устаю и начинаю сокращать содержательность ответов.',
'После нескольких таких вопросов подряд теряю часть уверенности в аргументации.',
'Серия критических вопросов может выбить меня из равновесия настолько, что теряю нить разговора.'),
$q(3,'Углублённый вопрос заставляет вас вернуться мысленно к тяжёлому эпизоду прямо во время выступления перед аудиторией вуза.',
'Умею произвольно регулировать глубину погружения в воспоминание и в любой момент вернуться в рабочее состояние.',
'В целом справляюсь, но заметно, что это требует внутреннего усилия.',
'Мне сложно контролировать степень погружения в воспоминание в моменте разговора.',
'Такие эпизоды могут выбивать меня из колеи надолго, в том числе после занятия.'),
$q(3,'Вы замечаете у себя физические признаки волнения ещё до того, как это стало заметно аудитории вуза.',
'Замечаю сигналы заранее и использую отработанный приём, чтобы вернуть контроль незаметно для аудитории.',
'Замечаю, но не всегда успеваю справиться прежде, чем это станет видно.',
'Обычно замечаю такие сигналы только тогда, когда они уже заметны аудитории.',
'Такие реакции случаются часто и плохо поддаются контролю.'),
$q(3,'После встречи со студентами вуза — как обычно проходит ваше восстановление?',
'Прихожу в обычное состояние достаточно быстро, встреча не «тянется» за мной.',
'Восстановление занимает заметное время, но проходит без посторонней помощи.',
'После таких встреч подолгу нахожусь в тяжёлом состоянии, это заметно окружающим.',
'Тяжёлое состояние может длиться днями и влиять на другие сферы жизни.'),
$q(4,'Дискуссия перерастает в острый спор о политическом контексте, который выходит за рамки формата встречи и лично вас задевает.',
'Уверенно обозначаю границы формата и мягко, но твёрдо возвращаю разговор к личному опыту, а не к политической полемике.',
'В целом удерживаю границы, но не всегда сразу и не без внутреннего напряжения.',
'Позволяю себе втянуться в полемику, теряя нейтральную позицию ведущего.',
'Такие споры выбивают меня настолько, что я теряю контроль над ходом всей встречи.'),
$q(4,'Часть аудитории явно перегружена темой, хотя формат встречи предполагает интеллектуальную дискуссию, а не эмоциональную поддержку.',
'Считываю признаки перегруза даже в интеллектуальном формате и корректирую тон и глубину для всей аудитории.',
'Считываю часть таких сигналов, но не всегда нахожу подходящий момент скорректировать разговор.',
'Замечаю перегруз аудитории с заметной задержкой.',
'Обычно не отслеживаю такие сигналы в интеллектуальном формате встречи.'),
$q(4,'Дискуссия становится излишне академичной и отстранённой, теряя связь с живым личным опытом, который важен для формата занятия.',
'Уверенно возвращаю разговор к личному опыту, сохраняя баланс между анализом и живым свидетельством.',
'В целом удерживаю баланс, но не всегда сразу и не без внутреннего напряжения.',
'Позволяю дискуссии уходить в чистую отстранённую академичность, теряя личный контакт с аудиторией.',
'Мне сложно удерживать баланс между анализом и личным опытом в таком формате.'),
$q(5,'Как вы определяете, где заканчивается уместная откровенность в разговоре со взрослой студенческой аудиторией?',
'Понимаю, что «взрослая» аудитория не означает «без ограничений» — заранее продумываю личные границы откровенности.',
'В целом ориентируюсь по ситуации, специальных границ заранее не продумываю.',
'Считаю, что со студентами вуза уместно говорить полностью откровенно, без ограничений.',
'Не задумывался(ась) об этом вопросе применительно именно к вузовской аудитории.'),
$q(5,'Студент настойчиво просит дать личную оценку конкретным решениям командования, аргументируя научным интересом.',
'Твёрдо, но уважительно разделяю личный опыт и оценку решений, которые не входят в мою компетенцию комментировать.',
'В целом соблюдаю границу, но иногда поддаюсь настойчивости и высказываюсь шире, чем планировал(а).',
'Мне сложно отказать в таком настойчивом, «научно обоснованном» запросе.',
'Обычно высказываюсь по существу запроса, не оценивая заранее уместность такой оценки.'),
$q(5,'Аудитория вуза включает студентов разных факультетов — от гуманитарных до технических, с разным уровнем контекста о теме.',
'Выстраиваю универсальную, но содержательную подачу, понятную и уместную независимо от бэкграунда слушателей.',
'Стараюсь балансировать, но иногда подача получается более подходящей для одной части аудитории.',
'Обычно ориентируюсь на «средний» уровень контекста, не всегда учитывая расхождения между факультетами.',
'Не задумываюсь отдельно о разнице бэкграунда студентов разных факультетов.'),
]],
];
}
function psych_readiness_status_for_score($score){ if($score>=48) return 'ready'; if($score>=30) return 'needs_prep'; return 'not_ready'; }
function psych_readiness_status_meta($status){ return [
'ready'=>['label'=>'Готов','color'=>'green','desc'=>'Устойчивая саморегуляция по всем пяти линиям, есть заранее продуманные границы откровенности и приёмы управления разговором для этого возраста.','next'=>'Рекомендуется супервизия куратора после первых 1–2 занятий как обратная связь, а не как контроль готовности.'],
'needs_prep'=>['label'=>'Условно готов, нужна подготовка','color'=>'amber','desc'=>'Базовая готовность есть, но отдельные линии (эмоциональный контроль, умение вовремя остановиться, калибровка откровенности) требуют укрепления.','next'=>'Перед допуском — очная встреча с куратором/методистом программы: разбор линий, где сумма баллов ниже среднего (варианты C/D преобладают); при необходимости — участие в занятии в паре с более опытным спикером в качестве наблюдателя перед самостоятельным допуском.'],
'not_ready'=>['label'=>'Не готов к этому формату сейчас','color'=>'red','desc'=>'Текущее состояние не позволяет обеспечить безопасность разговора ни для спикера, ни для аудитории этой возрастной группы.','next'=>'Занятия с этой аудиторией временно не рекомендуются; можно вернуться к анкете позже или попробовать себя со старшей/более подготовленной аудиторией, где нагрузка ниже.'],
][$status]; }
// Маркер возможной острой травматической реакции — НЕ про организационный статус, оценивается
// отдельно от суммы баллов. Срабатывает, если по Линии 3 (контроль над эмоциональным
// состоянием) ИЛИ по Линии 2 (реакция на вопросы аудитории о потерях/ранениях/гибели) выбрано
// 2 или 3 варианта D из 3 вопросов линии.
function psych_readiness_acute_from_answers($answers){
$line2D=0; $line3D=0;
foreach($answers as $a){
if(($a['line']??null)===2 && ($a['given']??'')==='D') $line2D++;
if(($a['line']??null)===3 && ($a['given']??'')==='D') $line3D++;
}
return $line3D>=2 || $line2D>=2;
}
function psych_readiness_acute_note(){
return 'Результаты анкеты отдельно от организационного статуса указывают на возможные признаки выраженной эмоциональной реакции при обращении к тяжёлым воспоминаниям. Это частая и понятная реакция после сложного опыта — и она никак не связана с оценкой вас как человека или как специалиста. Рекомендуется обсудить это со специалистом (психологом), прежде чем принимать решение об участии в программе. Это не входит в организационную оценку «готов/не готов» и остаётся конфиденциальным вопросом самого спикера.';
}
function submit_psych_readiness($speaker,$ageGroup,$answers){
$blocks=psych_readiness_blocks();
if(!array_key_exists($ageGroup,$blocks)) return [false,'Некорректная возрастная группа.'];
$qs=$blocks[$ageGroup]['questions'];
$score=0; $detail=[];
foreach($qs as $i=>$q){
$given=$answers[$i]??null;
if(!isset($q['options'][$given])) return [false,'Ответьте на все вопросы анкеты.'];
$score+=$q['options'][$given]['score'];
$detail[]=['line'=>$q['line'],'text'=>$q['text'],'given'=>$given,'given_label'=>$q['options'][$given]['label'],'score'=>$q['options'][$given]['score']];
}
$status=psych_readiness_status_for_score($score);
$acute=psych_readiness_acute_from_answers($detail)?1:0;
db()->prepare("INSERT INTO speaker_psych_readiness_attempts(speaker_user_id,age_group,score,total,status,acute,answers,created_at) VALUES(?,?,?,?,?,?,?,?)")
->execute([$speaker['id'],$ageGroup,$score,60,$status,$acute,json_encode($detail,JSON_UNESCAPED_UNICODE),now()]);
$meta=psych_readiness_status_meta($status);
log_action($speaker['id'],'speaker','psych_readiness_attempt','users',$speaker['id'],"Анкета психологической готовности «".(ages()[$ageGroup]??$ageGroup)."»: $score из 60 — {$meta['label']}".($acute?' (маркер острой реакции)':''));
return [true,['score'=>$score,'status'=>$status,'acute'=>$acute]];
}
function speaker_psych_readiness_last($speakerId,$ageGroup){
$st=db()->prepare("SELECT * FROM speaker_psych_readiness_attempts WHERE speaker_user_id=? AND age_group=? ORDER BY id DESC LIMIT 1");
$st->execute([$speakerId,$ageGroup]); return $st->fetch()?:null;
}
function speaker_psych_readiness_history($speakerId){
$st=db()->prepare("SELECT * FROM speaker_psych_readiness_attempts WHERE speaker_user_id=? ORDER BY id DESC"); $st->execute([$speakerId]); return $st->fetchAll();
}
// Сводка по всем группам — для кабинета спикера.
function speaker_psych_readiness_overview($speaker){
$out=[]; foreach(psych_readiness_blocks() as $gk=>$b) $out[$gk]=['label'=>$b['label'],'last'=>speaker_psych_readiness_last($speaker['id'],$gk)];
return $out;
}
// Разбор последней попытки по линиям — сумма баллов на линию (диапазон 3–12).
function psych_readiness_line_breakdown($attempt){
$detail=json_decode((string)($attempt['answers']??''),true)?:[];
$sums=[1=>0,2=>0,3=>0,4=>0,5=>0];
foreach($detail as $d) $sums[$d['line']]=($sums[$d['line']]??0)+(int)($d['score']??0);
return $sums;
}
// Перемешивает порядок линий, порядок вопросов внутри линии и порядок вариантов внутри
// вопроса — каждое прохождение анкеты выглядит по-разному, но исходные индексы вопросов (для
// submit_psych_readiness()) и буквы вариантов (для подсчёта баллов) не меняются.
function psych_readiness_questions_shuffled($block){
$byLine=[];
foreach($block['questions'] as $i=>$q) $byLine[$q['line']][]=$i;
$lineOrder=array_keys($byLine); shuffle($lineOrder);
$out=[];
foreach($lineOrder as $line){
$idxs=$byLine[$line]; shuffle($idxs);
foreach($idxs as $i){
$q=$block['questions'][$i];
$optKeys=array_keys($q['options']); shuffle($optKeys);
$opts=[]; foreach($optKeys as $ok) $opts[$ok]=$q['options'][$ok];
$out[]=['orig_index'=>$i,'line'=>$line,'text'=>$q['text'],'options'=>$opts];
}
}
return $out;
}
function child_subfunds($head){ $st=db()->prepare("SELECT * FROM users WHERE role='fund' AND parent_fund_id=? ORDER BY display_name"); $st->execute([$head['id']]); return $st->fetchAll(); }
function fund_label($f){ $n=($f['display_name']!=='')?$f['display_name']:('Организация №'.$f['id']); if(!empty($f['parent_fund_id'])) $n='— '.$n.' (подразделение'.(!empty($f['district'])?', '.$f['district']:'').')'; elseif(!empty($f['district'])) $n.=' ('.$f['district'].')'; return $n; }
function active_speakers($fund=null){ if($fund){ $st=db()->prepare("SELECT * FROM users WHERE role='speaker' AND active=1 AND fund_user_id=? ORDER BY display_name"); $st->execute([$fund['id']]); return $st->fetchAll(); } return db()->query("SELECT * FROM users WHERE role='speaker' AND active=1 ORDER BY display_name")->fetchAll(); }
// ---- объединение заявителей в одну организацию (школа/СПО/вуз) ----
function school_root_row($school){ if(!empty($school['parent_school_id'])){ $r=find_user_by_id((int)$school['parent_school_id']); if($r) return $r; } return $school; }
function school_scope_ids($school){ $root=school_root_row($school); $ids=[(int)$root['id'],(int)$school['id']]; $st=db()->prepare("SELECT id FROM users WHERE role='school' AND parent_school_id=?"); $st->execute([$root['id']]); foreach($st->fetchAll(PDO::FETCH_COLUMN) as $mid) $ids[]=(int)$mid; return array_values(array_unique($ids)); }
// Без фильтра по archived — источник для «Показатели за месяц» на кабинете заявителя,
// архивация не должна прятать историю проведённых уроков из статистики.
function school_requests_for_stats($school){ $ids=school_scope_ids($school); $in=implode(',',array_fill(0,count($ids),'?')); $st=db()->prepare("SELECT * FROM requests WHERE school_user_id IN ($in) ORDER BY id DESC"); $st->execute($ids); return $st->fetchAll(); }
function school_effective($school){ $root=school_root_row($school); if((int)$root['id']===(int)$school['id']) return $school; $eff=$school; foreach(['org_name','level','district','address','building','op_group'] as $k) $eff[$k]=$root[$k]; return $eff; }
function school_owns_request($school,$r){ return in_array((int)$r['school_user_id'],school_scope_ids($school),true); }
function head_schools(){ return db()->query("SELECT * FROM users WHERE role='school' AND active=1 AND (parent_school_id IS NULL OR parent_school_id=0) ORDER BY org_name")->fetchAll(); }
function normalize_org_name($s){
$s=mb_strtolower(trim((string)$s),'UTF-8');
$s=preg_replace('/[^a-zа-яё0-9 ]+/iu',' ',$s);
$s=preg_replace('/\s+/',' ',$s);
return trim($s);
}
function find_similar_school_org($name,$threshold=72){
$norm=normalize_org_name($name);
if($norm==='') return null;
foreach(head_schools() as $s){
$n2=normalize_org_name($s['org_name']);
if($n2==='') continue;
if($n2===$norm) return $s;
similar_text($norm,$n2,$pct);
if($pct>=$threshold) return $s;
}
return null;
}
function school_label($s){ $n=($s['org_name']!=='')?$s['org_name']:('Организация №'.$s['id']); return $n.' — '.level_label($s['level']??'').(!empty($s['district'])?', '.$s['district']:''); }
function pending_school_members($actor){ $root=school_root_row($actor); $st=db()->prepare("SELECT * FROM users WHERE role='school' AND parent_school_id=? AND active=0 ORDER BY id DESC"); $st->execute([$root['id']]); return $st->fetchAll(); }
function school_members($actor){ $root=school_root_row($actor); $st=db()->prepare("SELECT * FROM users WHERE role='school' AND parent_school_id=? ORDER BY contact_name"); $st->execute([$root['id']]); return $st->fetchAll(); }
function school_staff_contacts($schoolUserId){
$s=find_user_by_id((int)$schoolUserId); if(!$s) return [];
$root=school_root_row($s);
$rows=[$root];
foreach(school_members($root) as $m) $rows[]=$m;
return array_values(array_filter($rows,fn($x)=>!empty($x['active'])));
}
function approve_school_member($actor,$uid){
if(!empty($actor['parent_school_id'])) return [false,'Подтверждать сотрудников может только головной аккаунт организации.'];
$root=school_root_row($actor);
$m=find_user_by_id($uid); if(!$m||$m['role']!=='school') return [false,'Сотрудник не найден.'];
if((int)($m['parent_school_id']??0)!==(int)$root['id']) return [false,'Этот сотрудник относится к другой организации.'];
db()->prepare("UPDATE users SET active=1 WHERE id=?")->execute([$uid]);
log_action($actor['id'],'school','approve_school_member','users',$uid,"Сотрудник подтверждён: ".$m['contact_name']);
return [true,'Сотрудник подтверждён и получил доступ.'];
}
function reject_school_member($actor,$uid){
if(!empty($actor['parent_school_id'])) return [false,'Отклонять сотрудников может только головной аккаунт организации.'];
$root=school_root_row($actor);
$m=find_user_by_id($uid); if(!$m||$m['role']!=='school') return [false,'Сотрудник не найден.'];
if((int)($m['parent_school_id']??0)!==(int)$root['id']) return [false,'Этот сотрудник относится к другой организации.'];
if((int)$m['active']===1) return [false,'Этот сотрудник уже подтверждён.'];
db()->prepare("DELETE FROM users WHERE id=? AND active=0")->execute([$uid]);
log_action($actor['id'],'school','reject_school_member','users',$uid,"Сотрудник отклонён: ".$m['contact_name']);
return [true,'Сотрудник отклонён.'];
}
function transfer_school_head($actor,$newHeadId){
if(!empty($actor['parent_school_id'])) return [false,'Передать права головного аккаунта может только текущий головной аккаунт.'];
$target=find_user_by_id($newHeadId);
if(!$target||$target['role']!=='school'||(int)($target['parent_school_id']??0)!==(int)$actor['id']||!$target['active']) return [false,'Выберите подтверждённого сотрудника вашей организации.'];
// остальные сотрудники (кроме нового головного) переходят под нового головного
db()->prepare("UPDATE users SET parent_school_id=? WHERE role='school' AND parent_school_id=? AND id<>?")->execute([$target['id'],$actor['id'],$target['id']]);
// новый головной получает данные организации и становится корнем
db()->prepare("UPDATE users SET org_name=?,level=?,district=?,address=?,building=?,op_group=?,display_name=?,parent_school_id=NULL WHERE id=?")
->execute([$actor['org_name'],$actor['level'],$actor['district'],$actor['address'],$actor['building'],$actor['op_group'],$actor['org_name'],$target['id']]);
// прежний головной становится обычным сотрудником
db()->prepare("UPDATE users SET org_name='',address='',building='',op_group='',parent_school_id=? WHERE id=?")->execute([$target['id'],$actor['id']]);
log_action($actor['id'],'school','transfer_school_head','users',$target['id'],"Права головного аккаунта переданы сотруднику: ".$target['contact_name']);
return [true,'Права головного аккаунта переданы сотруднику «'.$target['contact_name'].'». Теперь вы — сотрудник организации.'];
}
// ---- модерация заявителей ----
function applicant_group_levels($opGroup){ if($opGroup==='school') return ['school','other']; if($opGroup==='region') return ['spo','vuz']; return ['school','spo','vuz','other']; }
function pending_applicants($operator){
$og=$operator['op_group']??'';
$st=db()->query("SELECT * FROM users WHERE role='school' AND active=0 AND (parent_school_id IS NULL OR parent_school_id=0) ORDER BY id DESC");
$all=$st->fetchAll();
if($og==='') return $all;
return array_values(array_filter($all,fn($a)=>school_dept_group($a)===$og));
}
function approve_applicant($operator,$uid){
$a=find_user_by_id($uid); if(!$a||$a['role']!=='school') return [false,'Заявитель не найден.'];
if(!empty($a['parent_school_id'])) return [false,'Это сотрудник организации — подтверждается представителем организации, а не департаментом.'];
$og=$operator['op_group']??'';
if($og!=='' && school_dept_group($a)!==$og) return [false,'Этот заявитель относится к другому департаменту.'];
db()->prepare("UPDATE users SET active=1 WHERE id=?")->execute([$uid]);
log_action($operator['id'],'operator','approve_applicant','users',$uid,"Заявитель подтверждён: ".$a['org_name']);
return [true,'Заявитель подтверждён и получил доступ.'];
}
function reject_applicant($operator,$uid){
$a=find_user_by_id($uid); if(!$a||$a['role']!=='school') return [false,'Заявитель не найден.'];
$og=$operator['op_group']??'';
if($og!=='' && school_dept_group($a)!==$og) return [false,'Этот заявитель относится к другому департаменту.'];
if((int)$a['active']===1) return [false,'Этот заявитель уже подтверждён.'];
db()->prepare("DELETE FROM users WHERE id=? AND active=0")->execute([$uid]);
log_action($operator['id'],'operator','reject_applicant','users',$uid,"Заявитель отклонён: ".$a['org_name']);
return [true,'Заявитель отклонён.'];
}
function reject_speaker($fund,$uid){
$s=find_user_by_id($uid); if(!$s||$s['role']!=='speaker') return [false,'Спикер не найден.'];
if((int)($s['fund_user_id']??0)!==(int)$fund['id']) return [false,'Этот спикер относится к другому фонду.'];
if((int)$s['active']===1) return [false,'Этот спикер уже подтверждён.'];
db()->prepare("DELETE FROM users WHERE id=? AND active=0")->execute([$uid]);
log_action($fund['id'],'fund','reject_speaker','users',$uid,"Спикер отклонён: ".$s['display_name']);
return [true,'Спикер отклонён.'];
}
function speaker_lessons($id){ $st=db()->prepare("SELECT r.*, rs.confirmed cfd, rs.vet_rating vr, rs.vet_comment vc FROM requests r JOIN request_speakers rs ON rs.request_id=r.id WHERE rs.speaker_user_id=? ORDER BY r.id DESC"); $st->execute([$id]); return $st->fetchAll(); }
function month_label($ym){
$names=['01'=>'Январь','02'=>'Февраль','03'=>'Март','04'=>'Апрель','05'=>'Май','06'=>'Июнь','07'=>'Июль','08'=>'Август','09'=>'Сентябрь','10'=>'Октябрь','11'=>'Ноябрь','12'=>'Декабрь'];
$parts=explode('-',$ym); if(count($parts)!==2) return $ym;
return ($names[$parts[1]]??$parts[1]).' '.$parts[0];
}
function speaker_stats($sid){
$today=date('Y-m-d');
$st=db()->prepare("SELECT r.id,r.udate,r.participants FROM request_speakers rs JOIN requests r ON r.id=rs.request_id WHERE rs.speaker_user_id=? AND rs.confirmed=1 AND r.status<>'cancelled' AND r.udate<=? ORDER BY r.udate DESC");
$st->execute([$sid,$today]); $rows=$st->fetchAll();
$monthStart=date('Y-m-01'); $weekStart=date('Y-m-d',strtotime('monday this week'));
$total=0; $totalKids=0; $month=0; $monthKids=0; $week=0; $weekKids=0; $day=0; $dayKids=0;
$byMonth=[];
foreach($rows as $r){
$d=substr((string)$r['udate'],0,10);
$n=max(1,count_confirmed($r['id']));
$kids=intdiv((int)($r['participants']??0),$n);
$total++; $totalKids+=$kids;
if($d>=$monthStart){ $month++; $monthKids+=$kids; }
if($d>=$weekStart){ $week++; $weekKids+=$kids; }
if($d===$today){ $day++; $dayKids+=$kids; }
$mk=substr($d,0,7);
if(!isset($byMonth[$mk])) $byMonth[$mk]=['lessons'=>0,'kids'=>0];
$byMonth[$mk]['lessons']++; $byMonth[$mk]['kids']+=$kids;
}
krsort($byMonth);
return ['total'=>$total,'total_kids'=>$totalKids,'month'=>$month,'month_kids'=>$monthKids,'week'=>$week,'week_kids'=>$weekKids,'day'=>$day,'day_kids'=>$dayKids,'by_month'=>$byMonth];
}
// ========== ОГРАНИЧЕНИЯ НАГРУЗКИ СПИКЕРА ==========
// В памятные даты (ФЗ №32-ФЗ «О днях воинской славы и памятных датах России») и в дни
// всенародных праздников (ст.112 ТК РФ) школы закономерно подают повышенный спрос на уроки —
// дневной/недельный лимит нагрузки спикера в эти даты не действует. Ключ — 'MM-DD' (без года,
// повторяется ежегодно).
function speaker_load_exempt_dates(){
return [
// --- Дни воинской славы России ---
'01-27'=>'День снятия блокады Ленинграда',
'02-02'=>'День разгрома советских войск немецко-фашистских войск в Сталинградской битве',
'02-23'=>'День защитника Отечества',
'04-18'=>'День победы А.Невского над немецкими рыцарями на Чудском озере (Ледовое побоище)',
'05-09'=>'День Победы',
'07-10'=>'День победы русской армии в Полтавском сражении',
'08-09'=>'День первой в истории России морской победы у мыса Гангут',
'08-23'=>'День разгрома советскими войсками немецко-фашистских войск в Курской битве',
'09-08'=>'День Бородинского сражения',
'09-11'=>'День победы русской эскадры у мыса Тендра',
'09-21'=>'День победы в Куликовской битве',
'11-07'=>'День проведения военного парада на Красной площади в 1941 году',
'11-24'=>'День победы русской эскадры у мыса Синоп',
'12-05'=>'День начала контрнаступления советских войск в битве под Москвой',
'12-24'=>'День взятия крепости Измаил',
// --- Памятные даты России ---
'01-25'=>'День российского студенчества',
'02-15'=>'День памяти о россиянах, исполнявших служебный долг за пределами Отечества',
'03-18'=>'День воссоединения Крыма с Россией',
'04-26'=>'День памяти погибших в радиационных авариях и катастрофах',
'05-24'=>'День славянской письменности и культуры',
'06-22'=>'День памяти и скорби',
'06-29'=>'День партизан и подпольщиков',
'08-22'=>'День Государственного флага Российской Федерации',
'09-02'=>'День окончания Второй мировой войны',
'09-03'=>'День солидарности в борьбе с терроризмом',
'10-30'=>'День памяти жертв политических репрессий',
'12-03'=>'День Неизвестного Солдата',
'12-09'=>'День Героев Отечества',
'12-12'=>'День Конституции Российской Федерации',
// --- Всенародные праздники (нерабочие праздничные дни, ст.112 ТК РФ) ---
'01-01'=>'Новый год','01-02'=>'Новогодние каникулы','01-03'=>'Новогодние каникулы',
'01-04'=>'Новогодние каникулы','01-05'=>'Новогодние каникулы','01-06'=>'Новогодние каникулы',
'01-07'=>'Рождество Христово','01-08'=>'Новогодние каникулы',
'03-08'=>'Международный женский день',
'05-01'=>'Праздник Весны и Труда',
'06-12'=>'День России',
'11-04'=>'День народного единства',
// --- Практическое исключение для платформы (не входит в официальный перечень выше) ---
'09-01'=>'День знаний',
];
}
function speaker_load_exempt_label($date){
$md=date('m-d',strtotime((string)$date));
$list=speaker_load_exempt_dates();
return $list[$md]??null;
}
function speaker_daily_count($sid,$date,$excludeRid=null){
$sql="SELECT COALESCE(SUM(COALESCE(r.lessons_per_speaker,1)),0) c FROM request_speakers rs JOIN requests r ON r.id=rs.request_id WHERE rs.speaker_user_id=? AND rs.confirmed=1 AND r.udate=? AND r.status NOT IN ('closed','cancelled')";
$params=[$sid,$date];
if($excludeRid){ $sql.=' AND r.id<>?'; $params[]=$excludeRid; }
$st=db()->prepare($sql); $st->execute($params); return (int)$st->fetch()['c'];
}
function speaker_weekly_count($sid,$date,$excludeRid=null){
$week_start=date('Y-m-d',strtotime('monday this week',strtotime($date)));
$week_end=date('Y-m-d',strtotime('sunday this week',strtotime($date)));
$sql="SELECT COALESCE(SUM(COALESCE(r.lessons_per_speaker,1)),0) c FROM request_speakers rs JOIN requests r ON r.id=rs.request_id WHERE rs.speaker_user_id=? AND rs.confirmed=1 AND r.udate BETWEEN ? AND ? AND r.status NOT IN ('closed','cancelled')";
$params=[$sid,$week_start,$week_end];
if($excludeRid){ $sql.=' AND r.id<>?'; $params[]=$excludeRid; }
$st=db()->prepare($sql); $st->execute($params); return (int)$st->fetch()['c'];
}
function check_speaker_load($sid,$date,$additionalLessons=1,$excludeRid=null){
if(speaker_load_exempt_label($date)) return [true,''];
$daily=speaker_daily_count($sid,$date,$excludeRid);
$weekly=speaker_weekly_count($sid,$date,$excludeRid);
if($daily+$additionalLessons>2) return [false,"Спикер не может провести ещё $additionalLessons урок(а) — превышен дневной лимит (2 урока в день). Сейчас занято: $daily."];
if($weekly+$additionalLessons>7) return [false,"Спикер не может провести ещё $additionalLessons урок(а) — превышен недельный лимит (7 уроков). Сейчас занято: $weekly."];
return [true,''];
}
// ---------- заявки и состав ----------
function gen_code(){ $max=db()->query('SELECT COALESCE(MAX(id),0) m FROM requests')->fetch()['m']; return date('Y').'-'.str_pad($max+1,4,'0',STR_PAD_LEFT); }
function get_request($id){ $st=db()->prepare('SELECT * FROM requests WHERE id=?'); $st->execute([$id]); return $st->fetch()?:null; }
function create_request($schoolUser,$f){
$lvl=$f['level'];
$og=school_dept_group($schoolUser);
$lps=max(1,min(2,(int)($f['lessons_per_speaker']??1)));
$st=db()->prepare('INSERT INTO requests(code,school_user_id,org,level,op_group,operator,contact_name,phone,email,udate,utime,utime_end,age_group,participants,speakers_needed,lessons_per_speaker,topic,notes,status,created_at,district,desired_speaker_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$st->execute([gen_code(),$schoolUser['id'],$schoolUser['org_name'],$lvl,$og,operator_label_for_group($og),
$f['contact_name'],$f['phone'],$f['email']??'',$f['udate'],$f['utime']??'',$f['utime_end']??'',$f['age_group'],$f['participants']??'',max(1,(int)($f['speakers_needed']??1)),$lps,$f['topic']??'',$f['notes']??'','new',now(),$schoolUser['district']??'',$f['desired_speaker_id']?:null]);
$rid=db()->lastInsertId();
log_action($schoolUser['id'],'school','request_create','requests',$rid,"Создана заявка {$f['udate']}");
return $rid;
}
// ========== ПЕРЕНОС ДАТЫ/ВРЕМЕНИ УРОКА ==========
function reschedulable_status($r){ return in_array($r['status'],['chosen','confirmed']); }
function reschedule_fields($r,$udate,$utime,$utimeEnd){
$udate=substr((string)$udate,0,10);
if(!DateTime::createFromFormat('Y-m-d',$udate)) return [false,'Укажите корректную дату урока.'];
if($udate<date('Y-m-d')) return [false,'Дата урока не может быть в прошлом.'];
if(!valid_slot($utime)||!valid_slot($utimeEnd)) return [false,'Время урока — с 8:00 до 18:00, минуты кратны 10.'];
if(tmin($utimeEnd)<=tmin($utime)) return [false,'Время окончания должно быть позже времени начала.'];
$lps=max(1,(int)($r['lessons_per_speaker']??1));
$probe=['id'=>$r['id'],'udate'=>$udate,'utime'=>$utime,'utime_end'=>$utimeEnd,'org'=>$r['org']];
foreach(req_confirmed_rows($r['id']) as $row){
$sid=$row['speaker_user_id'];
if(speaker_conflicts($sid,$probe)) return [false,'Спикер '.$row['display_name'].' занят в другом учреждении в это время.'];
[$ok,$err]=check_speaker_load($sid,$udate,$lps,$r['id']); if(!$ok) return [false,'Спикер '.$row['display_name'].': '.$err];
}
$fields=['udate'=>$udate,'utime'=>$utime,'utime_end'=>$utimeEnd];
if(!empty($r['briefing_done']) && !empty($r['briefing_date']) && $udate<substr((string)$r['briefing_date'],0,10)){ $fields['briefing_done']=0; $fields['briefing_date']=null; }
return [true,$fields];
}
function update_request($r,$f,$actor){
if($actor['role']!=='school'||!school_owns_request($actor,$r)) return [false,'Нет доступа.'];
if(in_array($r['status'],['done','closed','cancelled'])) return [false,'Заявка уже завершена, редактирование недоступно.'];
$allowed=[
'contact_name'=>trim($f['contact_name']??''),
'phone'=>normalize_phone($f['phone']??''),
'participants'=>trim($f['participants']??''),
'speakers_needed'=>max(1,(int)($f['speakers_needed']??1)),
'lessons_per_speaker'=>max(1,min(2,(int)($f['lessons_per_speaker']??1))),
'age_group'=>$f['age_group']??$r['age_group'],
'notes'=>trim($f['notes']??''),
];
$allowed['topic']=trim($f['topic']??'');
if($allowed['topic']!=='' && $allowed['topic']!==($r['topic']??'') && !in_array($allowed['topic'],material_topics(),true)) return [false,'Выберите тему из списка.'];
if(!$allowed['contact_name']) return [false,'Заполните контактное лицо.'];
if($allowed['phone']===null) return [false,'Укажите телефон в формате +7XXXXXXXXXX (10 цифр после +7).'];
if(!in_array($allowed['age_group'],array_keys(ages()),true)) return [false,'Некорректная возрастная группа.'];
if($r['status']==='new'){
$dsid=(int)($f['desired_speaker_id']??0);
if($dsid){ $dsp=find_user_by_id($dsid); if(!$dsp||$dsp['role']!=='speaker'||!$dsp['active']) $dsid=0; }
$allowed['desired_speaker_id']=$dsid?:null;
}
$rescheduled=false;
if(reschedulable_status($r) && isset($f['udate'])){
[$ok,$res]=reschedule_fields($r,$f['udate'],$f['utime']??'',$f['utime_end']??'');
if(!$ok) return [false,$res];
$allowed=array_merge($allowed,$res); $rescheduled=true;
}
upd($r['id'],$allowed);
log_action($actor['id'],'school','request_update','requests',$r['id'],'Заявка отредактирована'.($rescheduled?' (в т.ч. дата/время урока)':''));
return [true,'Заявка обновлена.'.($rescheduled?'':' Дата и время проведения не изменяются.')];
}
function fund_edit_request($actor,$r,$f){
if($actor['role']!=='fund') return [false,'Нет доступа.'];
if(!empty($r['claimed_fund_id']) && !in_array((int)$r['claimed_fund_id'],fund_scope_ids($actor),true)) return [false,'Эта заявка закреплена за другим фондом.'];
if(!reschedulable_status($r)) return [false,'Редактирование доступно на этапах «Спикеры выбраны» и «Состав подтверждён Фондом».'];
$allowed=[
'contact_name'=>trim($f['contact_name']??''),
'phone'=>normalize_phone($f['phone']??''),
'participants'=>trim($f['participants']??''),
'age_group'=>$f['age_group']??$r['age_group'],
'notes'=>trim($f['notes']??''),
];
$allowed['topic']=trim($f['topic']??'');
if($allowed['topic']!=='' && $allowed['topic']!==($r['topic']??'') && !in_array($allowed['topic'],material_topics(),true)) return [false,'Выберите тему из списка.'];
if(!$allowed['contact_name']) return [false,'Заполните контактное лицо.'];
if($allowed['phone']===null) return [false,'Укажите телефон в формате +7XXXXXXXXXX (10 цифр после +7).'];
$rescheduled=false;
if(isset($f['udate'])){
[$ok,$res]=reschedule_fields($r,$f['udate'],$f['utime']??'',$f['utime_end']??'');
if(!$ok) return [false,$res];
$allowed=array_merge($allowed,$res); $rescheduled=true;
}
upd($r['id'],$allowed);
log_action($actor['id'],'fund','fund_edit_request','requests',$r['id'],'Фонд отредактировал заявку'.($rescheduled?' (в т.ч. дата/время урока)':''));
return [true,'Заявка отредактирована Фондом.'];
}
function upd($id,$fields){ $set=implode(',',array_map(fn($k)=>"$k=?",array_keys($fields))); $st=db()->prepare("UPDATE requests SET $set WHERE id=?"); $st->execute([...array_values($fields),$id]); }
function prep_complete($r){ return $r['prep_venue'] && $r['prep_escort']; }
function req_speaker_rows($rid){ $st=db()->prepare("SELECT rs.*, u.display_name FROM request_speakers rs JOIN users u ON u.id=rs.speaker_user_id WHERE rs.request_id=? ORDER BY rs.confirmed DESC, rs.id"); $st->execute([$rid]); return $st->fetchAll(); }
function req_confirmed_rows($rid){ return array_values(array_filter(req_speaker_rows($rid),fn($x)=>$x['confirmed'])); }
function count_confirmed($rid){ $st=db()->prepare("SELECT COUNT(*) c FROM request_speakers WHERE request_id=? AND confirmed=1"); $st->execute([$rid]); return (int)$st->fetch()['c']; }
function count_confirmed_bulk($requestIds){
$requestIds=array_values(array_unique(array_map('intval',$requestIds)));
if(!$requestIds) return [];
$in=implode(',',array_fill(0,count($requestIds),'?'));
$st=db()->prepare("SELECT request_id, COUNT(*) c FROM request_speakers WHERE request_id IN ($in) AND confirmed=1 GROUP BY request_id");
$st->execute($requestIds);
$out=[]; foreach($st->fetchAll() as $row) $out[(int)$row['request_id']]=(int)$row['c'];
return $out;
}
function is_candidate($rid,$sid){ $st=db()->prepare("SELECT id FROM request_speakers WHERE request_id=? AND speaker_user_id=?"); $st->execute([$rid,$sid]); return (bool)$st->fetch(); }
function get_rs_row($rid,$sid){ $st=db()->prepare("SELECT * FROM request_speakers WHERE request_id=? AND speaker_user_id=?"); $st->execute([$rid,$sid]); return $st->fetch()?:null; }
function req_label($rid){ $rows=req_speaker_rows($rid); $conf=array_filter($rows,fn($x)=>$x['confirmed']); if($conf) return implode(', ',array_map(function($x){ $org=speaker_org_name($x['speaker_user_id']); return $x['display_name'].($org!==''?' ('.$org.')':''); },$conf)); if($rows) return 'кандидатов: '.count($rows); return 'не выбран'; }
// Пакетная версия req_label() для таблиц из многих строк — один запрос вместо одного на строку.
function req_labels_bulk($requestIds,$withOrg=true){
$requestIds=array_values(array_unique(array_map('intval',$requestIds)));
if(!$requestIds) return [];
$in=implode(',',array_fill(0,count($requestIds),'?'));
$st=db()->prepare("SELECT rs.request_id, rs.confirmed, u.id AS speaker_id, u.display_name FROM request_speakers rs JOIN users u ON u.id=rs.speaker_user_id WHERE rs.request_id IN ($in) ORDER BY rs.confirmed DESC, rs.id");
$st->execute($requestIds);
$byReq=[];
foreach($st->fetchAll() as $row){ $byReq[(int)$row['request_id']][]=$row; }
$fundCache=[]; $labels=[];
foreach($requestIds as $rid){
$rows=$byReq[$rid]??[];
$conf=array_filter($rows,fn($x)=>$x['confirmed']);
if($conf){
$parts=[];
foreach($conf as $x){
if(!$withOrg){ $parts[]=$x['display_name']; continue; }
$sid=(int)$x['speaker_id'];
if(!array_key_exists($sid,$fundCache)) $fundCache[$sid]=speaker_org_name($sid);
$org=$fundCache[$sid];
$parts[]=$x['display_name'].($org!==''?' ('.$org.')':'');
}
$labels[$rid]=implode(', ',$parts);
} elseif($rows){ $labels[$rid]='кандидатов: '.count($rows); }
else { $labels[$rid]='не выбран'; }
}
return $labels;
}
function tmin($s){ if(!$s||strpos($s,':')===false) return null; $p=explode(':',$s); return ((int)$p[0])*60+((int)$p[1]); }
function valid_slot($s){ $m=tmin($s); if($m===null) return false; if($m%10!==0) return false; return $m>=8*60 && $m<=18*60; }
function time_options($sel){ $o=''; for($m=8*60;$m<=18*60;$m+=10){ $v=sprintf('%02d:%02d',intdiv($m,60),$m%60); $o.='<option value="'.$v.'"'.($sel===$v?' selected':'').'>'.$v.'</option>'; } return $o; }
function min_create_date(){ return date('Y-m-d', strtotime('+10 days')); }
function same_org($a,$b){ $a=trim((string)$a); $b=trim((string)$b); return $a!=='' && $a===$b; }
function speaker_conflicts($sid,$r){
$s1=tmin($r['utime']); if($s1===null) return false; $e1=tmin($r['utime_end']??'') ?? $s1;
$st=db()->prepare("SELECT r.utime, r.utime_end, r.org FROM request_speakers rs JOIN requests r ON r.id=rs.request_id WHERE rs.confirmed=1 AND rs.speaker_user_id=? AND r.udate=? AND r.status NOT IN ('closed','cancelled') AND r.id<>?");
$st->execute([$sid,$r['udate'],$r['id']]);
foreach($st->fetchAll() as $o){
if(same_org($o['org'],$r['org'])) continue;
$s2=tmin($o['utime']); if($s2===null) continue; $e2=tmin($o['utime_end']??'') ?? $s2;
if(($s1-20) < $e2 && $s2 < ($e1+20)) return true;
}
return false;
}
function free_speakers_for_request($r){
$res=[];
$appDistrict=request_district($r);
$allowed=allowed_speaker_districts($appDistrict);
$lps=max(1,(int)($r['lessons_per_speaker']??1));
$claimScope=null;
if(!empty($r['claimed_fund_id'])){ $cf=find_user_by_id((int)$r['claimed_fund_id']); if($cf) $claimScope=fund_scope_ids($cf); }
foreach(active_speakers() as $s){
if(is_candidate($r['id'],$s['id'])) continue;
if(speaker_has_criminal_record($s)) continue;
if($claimScope!==null && !in_array((int)$s['fund_user_id'],$claimScope,true)) continue;
$g=array_filter(explode(',',(string)($s['certified_age_groups']??'')));
if($r['age_group'] && !in_array($r['age_group'],$g,true)) continue;
if($allowed && !in_array(trim((string)$s['district']),$allowed)) continue;
if(speaker_is_unavailable($s['id'],$r['udate'])) continue;
if(speaker_conflicts($s['id'],$r)) continue;
[$ok,$err]=check_speaker_load($s['id'],$r['udate'],$lps); if(!$ok) continue;
$res[]=$s;
}
usort($res,function($a,$b) use($appDistrict){ $aa=(trim((string)$a['district'])===$appDistrict)?0:1; $bb=(trim((string)$b['district'])===$appDistrict)?0:1; return $aa<=>$bb; });
return $res;
}
function auto_claim_from_speaker($rid,$sid){
$sp=find_user_by_id($sid); if(!$sp||empty($sp['fund_user_id'])) return;
db()->prepare("UPDATE requests SET claimed_fund_id=?, claimed_at=COALESCE(claimed_at,?) WHERE id=? AND claimed_fund_id IS NULL")->execute([$sp['fund_user_id'],now(),$rid]);
}
function claim_request($fund,$r){
if($r['status']!=='new') return [false,'Взять в обработку можно только новую заявку.'];
$st=db()->prepare("UPDATE requests SET claimed_fund_id=?, claimed_at=? WHERE id=? AND claimed_fund_id IS NULL AND status='new'");
$st->execute([$fund['id'],now(),$r['id']]);
if($st->rowCount()===0) return [false,'Заявка уже взята в обработку другим фондом.'];
log_action($fund['id'],'fund','claim_request','requests',$r['id'],'Фонд взял заявку в обработку');
return [true,'Заявка взята в обработку. Теперь назначьте спикера.'];
}
function release_request($fund,$r){
$scope=fund_scope_ids($fund);
if(empty($r['claimed_fund_id']) || !in_array((int)$r['claimed_fund_id'],$scope,true)) return [false,'Эта заявка не в вашей обработке.'];
if(count(req_speaker_rows($r['id']))>0) return [false,'Сначала уберите назначенного спикера через отклонение/замену кандидата.'];
db()->prepare("UPDATE requests SET claimed_fund_id=NULL, claimed_at=NULL WHERE id=?")->execute([$r['id']]);
log_action($fund['id'],'fund','release_request','requests',$r['id'],'Фонд вернул заявку в обработку (без назначения спикера)');
return [true,'Заявка возвращена в общий список — её могут взять другие фонды.'];
}
function fund_returnable_status($r){ return in_array($r['status'],['chosen','confirmed','prep','done'],true); }
function fund_return_to_processing($fund,$r,$reason=''){
$scope=fund_scope_ids($fund);
if(empty($r['claimed_fund_id']) || !in_array((int)$r['claimed_fund_id'],$scope,true)) return [false,'Эта заявка не в вашей обработке.'];
if(!fund_returnable_status($r)) return [false,'Вернуть в обработку можно на этапах «Спикеры выбраны», «Состав подтверждён Фондом», «Готовность подтверждена» или «Проведён».'];
notify_event('returned_to_processing',$r,['reason'=>trim((string)$reason)]);
db()->prepare("DELETE FROM request_speakers WHERE request_id=?")->execute([$r['id']]);
upd($r['id'],['status'=>'new','claimed_fund_id'=>null,'claimed_at'=>null,'fund_confirmed_at'=>null,'briefing_done'=>0,'briefing_date'=>null,'needs_replacement'=>0,'prep_confirmed_at'=>null]);
$full='Заявка возвращена в общую обработку, этап сброшен на «Новая»'.($reason!==''?': '.trim((string)$reason):'');
log_action($fund['id'],'fund','fund_return_to_processing','requests',$r['id'],$full);
return [true,'Заявка возвращена на этап «Новая». Теперь любой фонд может взять её в обработку.'];
}
function request_deletable($r){ return in_array($r['status'],['new','chosen','confirmed','brief','prep'],true); }
function delete_request($r,$actor){
if($actor['role']!=='school'||!school_owns_request($actor,$r)) return [false,'Нет доступа.'];
if(!request_deletable($r)) return [false,'Удалить можно только заявку до этапа «Проведён».'];
db()->prepare("DELETE FROM requests WHERE id=?")->execute([$r['id']]);
log_action($actor['id'],'school','request_delete','requests',$r['id'],"Удалена заявка {$r['code']}");
return [true,'Заявка удалена.'];
}
function fund_scope_ids($fund){ $ids=[(int)$fund['id']]; if(empty($fund['parent_fund_id'])){ foreach(child_subfunds($fund) as $c) $ids[]=(int)$c['id']; } return array_values(array_unique($ids)); }
function speaker_fund_of($sid){ $sp=find_user_by_id($sid); if(!$sp) return null; return $sp['fund_user_id']?find_user_by_id($sp['fund_user_id']):null; }
function candidate_action($fund,$row){
$spFund=speaker_fund_of($row['speaker_user_id']); if(!$spFund) return 'foreign';
if(!empty($row['confirmed'])) return 'done';
$isSub=!empty($spFund['parent_fund_id']);
if($isSub){ $subId=(int)$spFund['id']; $headId=(int)$spFund['parent_fund_id'];
if(empty($row['recommended'])){ if((int)$fund['id']===$subId) return 'recommend'; if((int)$fund['id']===$headId) return 'wait_recommend'; return 'foreign'; }
else { if((int)$fund['id']===$headId) return 'confirm'; if((int)$fund['id']===$subId) return 'wait_confirm'; return 'foreign'; }
}
return ((int)$fund['id']===(int)$spFund['id']) ? 'confirm' : 'foreign';
}
function fund_recommend($fund,$r,$sid){
if($r['status']!=='chosen') return [false,'Рекомендация возможна на этапе «Спикеры выбраны».'];
$row=get_rs_row($r['id'],$sid); if(!$row) return [false,'Спикер не в списке кандидатов.'];
if(candidate_action($fund,$row)!=='recommend') return [false,'Этого кандидата рекомендует другое подразделение или он уже обработан.'];
if(speaker_conflicts($sid,$r)) return [false,'Спикер занят в другом учреждении в пределах 20 минут от этого урока.'];
[$ok,$err]=check_speaker_load($sid,$r['udate'],max(1,(int)($r['lessons_per_speaker']??1))); if(!$ok) return [false,$err];
db()->prepare("UPDATE request_speakers SET recommended=1 WHERE request_id=? AND speaker_user_id=?")->execute([$r['id'],$sid]);
log_action($fund['id'],'fund','fund_recommend','requests',$r['id'],"Подразделение рекомендовало спикера #$sid");
return [true,'Спикер рекомендован. Окончательно подтверждает головной фонд.'];
}
function fund_confirm_speaker($fund,$r,$sid){
if($r['status']!=='chosen') return [false,'Подтверждение возможно на этапе «Спикеры выбраны».'];
$row=get_rs_row($r['id'],$sid); if(!$row) return [false,'Спикер не в списке кандидатов.'];
if(candidate_action($fund,$row)!=='confirm') return [false,'Вы не можете подтвердить этого кандидата (нет прав или нет рекомендации подразделения).'];
if(count_confirmed($r['id'])>=$r['speakers_needed']) return [false,'Нужное число спикеров уже подтверждено.'];
if(speaker_conflicts($sid,$r)) return [false,'Спикер занят в другом учреждении в пределах 20 минут от этого урока.'];
[$ok,$err]=check_speaker_load($sid,$r['udate'],max(1,(int)($r['lessons_per_speaker']??1))); if(!$ok) return [false,$err];
db()->prepare("UPDATE request_speakers SET confirmed=1 WHERE request_id=? AND speaker_user_id=?")->execute([$r['id'],$sid]);
if(empty($r['fund_confirmed_at'])) upd($r['id'],['fund_confirmed_at'=>now()]);
log_action($fund['id'],'fund','fund_confirm_speaker','requests',$r['id'],"Фонд подтвердил спикера #$sid");
if(count_confirmed($r['id'])>=$r['speakers_needed']){
db()->prepare("DELETE FROM request_speakers WHERE request_id=? AND confirmed=0")->execute([$r['id']]);
upd($r['id'],['status'=>'confirmed']);
log_action($fund['id'],'fund','fund_confirm','requests',$r['id'],"Состав закрыт — набрано нужное число спикеров");
notify_event('confirmed',$r);
return [true,'Спикер подтверждён. Состав укомплектован, остальные кандидаты отклонены.'];
}
return [true,'Спикер подтверждён. Ожидаются остальные ('.count_confirmed($r['id']).' из '.$r['speakers_needed'].').'];
}
function fund_requests($fund){ $ids=fund_scope_ids($fund); if(!$ids) return []; $in=implode(',',array_fill(0,count($ids),'?')); $st=db()->prepare("SELECT DISTINCT r.* FROM requests r JOIN request_speakers rs ON rs.request_id=r.id JOIN users u ON u.id=rs.speaker_user_id WHERE u.fund_user_id IN ($in) AND (r.archived=0 OR r.archived IS NULL) ORDER BY r.id DESC"); $st->execute($ids); return $st->fetchAll(); }
function fund_dashboard_requests($fund){
$ids=fund_scope_ids($fund); if(!$ids) return [];
$in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT * FROM requests WHERE (archived=0 OR archived IS NULL) AND ((status='new' AND claimed_fund_id IS NULL) OR claimed_fund_id IN ($in)) ORDER BY id DESC");
$st->execute($ids); return $st->fetchAll();
}
// Число заявок этого фонда, уже перенесённых в архив (та же выборка, что fund_dashboard_requests,
// только archived=1) — используется, чтобы пояснить расхождение между «Реестр заявок (N)» и
// КПИ «Всего заявок» (который считает и архивные тоже).
function fund_archived_count($fund){
$ids=fund_scope_ids($fund); if(!$ids) return 0;
$in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT COUNT(*) c FROM requests WHERE archived=1 AND ((status='new' AND claimed_fund_id IS NULL) OR claimed_fund_id IN ($in))");
$st->execute($ids); return (int)$st->fetch()['c'];
}
function fund_pool_requests_for_speaker($speaker){
if(empty($speaker['fund_user_id'])) return [];
$f=find_user_by_id($speaker['fund_user_id']); if(!$f) return [];
$ids=fund_scope_ids($f); $in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT r.* FROM requests r WHERE (r.archived=0 OR r.archived IS NULL) AND r.claimed_fund_id IN ($in) AND r.status IN ('new','chosen') AND NOT EXISTS (SELECT 1 FROM request_speakers rs WHERE rs.request_id=r.id) ORDER BY r.udate ASC");
$st->execute($ids); return $st->fetchAll();
}
function fund_actionable($fund){ $res=[]; foreach(fund_requests($fund) as $r){ if($r['status']!=='chosen') continue; foreach(req_speaker_rows($r['id']) as $row){ $a=candidate_action($fund,$row); if($a==='recommend'||$a==='confirm'){ $res[]=$r; break; } } } return $res; }
function fund_briefing($fund,$r,$date){
if(!empty($r['claimed_fund_id']) && !in_array((int)$r['claimed_fund_id'],fund_scope_ids($fund),true)) return [false,'Эта заявка закреплена за другим фондом.'];
if(!$r['fund_confirmed_at']) return [false,'Инструктаж отмечается после подтверждения состава.'];
if(in_array($r['status'],['closed','cancelled'])) return [false,'Заявка уже завершена.'];
$d=DateTime::createFromFormat('Y-m-d',substr((string)$date,0,10));
if(!$d) return [false,'Укажите дату инструктажа.'];
if(strtotime(substr((string)$date,0,10)) > strtotime(substr((string)$r['udate'],0,10))) return [false,'Дата инструктажа не может быть позже даты урока ('.$r['udate'].').'];
upd($r['id'],['briefing_done'=>1,'briefing_date'=>$date]);
log_action(current_user()['id']??null,'fund','fund_briefing','requests',$r['id'],"Инструктаж проведён $date");
return [true,'Инструктаж отмечен как проведённый.'];
}
function school_set_prep($r,$parents,$venue,$escort){
upd($r['id'],['prep_parents'=>$parents,'prep_venue'=>$venue,'prep_escort'=>$escort]); $nr=get_request($r['id']);
if(prep_complete($nr)){ upd($r['id'],['prep_confirmed_at'=>now()]); if(in_array($nr['status'],['confirmed','brief'])) upd($r['id'],['status'=>'prep']); log_action(current_user()['id']??null,'school','confirm_prep','requests',$r['id'],"Готовность подтверждена"); return [true,'Готовность подтверждена.']; }
upd($r['id'],['prep_confirmed_at'=>null]); return [true,'Сохранено. Отметьте все пункты, чтобы подтвердить готовность.'];
}
function mark_done($r){
if($r['status']!=='prep') return [false,'Урок можно отметить проведённым после подтверждения готовности.'];
$topic=trim((string)($r['topic']??''));
// Проверка обязательна, только если по теме реально заполнены все 4 вопроса — иначе
// незаполненный банк вопросов молча блокировал бы отметку «Проведён» для всех подряд.
if($topic!=='' && lesson_readiness_ready($topic)){
foreach(req_confirmed_rows($r['id']) as $row){
if(!speaker_topic_ready($row['speaker_user_id'],$topic)) return [false,'Спикер '.$row['display_name'].' ещё не прошёл проверку готовности по теме «'.$topic.'» (или проходил её более 14 дней назад) — сначала пусть пройдёт её на странице заявки.'];
}
}
upd($r['id'],['status'=>'done']); log_action(current_user()['id']??null,'operator','mark_done','requests',$r['id'],"Отмечен как проведённый"); return [true,'Отмечено как «Проведён».'];
}
function close_request($r){
if($r['status']!=='done') return [false,'Закрыть можно только проведённый урок.'];
if(trim((string)($r['fb_app_comment']??''))==='') return [false,'Заявка закрывается после отзыва заявителя.'];
if(trim((string)($r['fb_post_url']??''))==='') return [false,'Заявка закрывается после того, как заявитель укажет ссылку на пост о проведённом занятии.'];
upd($r['id'],['status'=>'closed','closed_at'=>now()]); log_action(current_user()['id']??null,'operator','close','requests',$r['id'],"Заявка закрыта"); return [true,'Заявка закрыта.'];
}
// Заявки со статусом «Не состоялся» через close_request() не проходят (там нужен статус done) —
// но и без этого зависают в активном реестре навсегда, поскольку автоархивация по расписанию
// смотрит только на статус 'closed'. Даём оператору отдельную кнопку — переносит в архив сразу,
// не дожидаясь общей автоархивации; статус остаётся 'cancelled' (для статистики важно отличать
// несостоявшийся урок от проведённого и закрытого).
function close_cancelled_request($r){
if($r['status']!=='cancelled') return [false,'Эта заявка не отмечена как «Не состоялся».'];
if(!empty($r['archived'])) return [false,'Заявка уже в архиве.'];
upd($r['id'],['archived'=>1,'archived_at'=>now()]);
log_action(current_user()['id']??null,'operator','close_cancelled','requests',$r['id'],'Заявка (урок не состоялся) закрыта и перенесена в архив');
return [true,'Заявка закрыта и перенесена в архив.'];
}
function mark_not_held($r,$reason,$comment=''){
if($r['status']!=='prep') return [false,'Отметить «не состоялся» можно на этапе «Готовность подтверждена».'];
if(!in_array($reason,cancel_reasons())) return [false,'Укажите причину.'];
$full=$reason.($comment!==''?': '.$comment:'');
upd($r['id'],['status'=>'cancelled','cancel_reason'=>$full,'cancelled_at'=>now(),'closed_at'=>now()]);
log_action(current_user()['id']??null,'school','not_held','requests',$r['id'],"Урок не состоялся: ".$full);
notify_event('not_held',$r,['reason'=>$full]);
return [true,'Отмечено: урок не состоялся.'];
}
function step_back($r){ $i=st_index($r['status']); if($i>0) upd($r['id'],['status'=>statuses()[$i-1]['k']]); log_action(current_user()['id']??null,'operator','step_back','requests',$r['id'],"Возврат на шаг назад"); }
function replaceable_status($r){ return in_array($r['status'],['confirmed','brief','prep']); }
function speaker_decline($r,$sid){
if(!replaceable_status($r)) return [false,'Сообщить о невозможности можно только до проведения урока.'];
$row=get_rs_row($r['id'],$sid); if(!$row||!$row['confirmed']) return [false,'Вы не входите в утверждённый состав по этой заявке.'];
upd($r['id'],['needs_replacement'=>1]);
log_action($sid,'speaker','speaker_decline','requests',$r['id'],"Спикер #$sid сообщил, что не сможет провести урок");
return [true,'Фонд уведомлён о том, что вы не сможете провести урок. Он назначит замену.'];
}
function replacement_speakers($fund,$r,$exceptSid){
$scope=fund_scope_ids($fund); $res=[];
foreach(free_speakers_for_request($r) as $s){ if((int)$s['id']===(int)$exceptSid) continue; if(!in_array((int)$s['fund_user_id'],$scope,true)) continue; $res[]=$s; }
return $res;
}
function fund_replace_speaker($fund,$r,$oldSid,$newSid,$reason){
if(!replaceable_status($r)) return [false,'Заменить спикера можно только до проведения урока.'];
$old=get_rs_row($r['id'],$oldSid); if(!$old||!$old['confirmed']) return [false,'Заменяемый спикер не входит в утверждённый состав.'];
$sp=speaker_fund_of($oldSid); if(!$sp || !in_array((int)$sp['id'],fund_scope_ids($fund),true)) return [false,'Этот спикер относится к другому фонду.'];
if((int)$newSid===(int)$oldSid) return [false,'Выберите другого спикера.'];
$ok=false; foreach(replacement_speakers($fund,$r,$oldSid) as $s){ if((int)$s['id']===(int)$newSid){ $ok=true; break; } }
if(!$ok) return [false,'Новый спикер недоступен (занят, не подходит по возрасту/району, превышен лимит или относится к другому фонду).'];
db()->prepare("DELETE FROM request_speakers WHERE request_id=? AND speaker_user_id=?")->execute([$r['id'],$oldSid]);
db()->prepare("INSERT INTO request_speakers(request_id,speaker_user_id,confirmed,recommended,created_at) VALUES(?,?,1,1,?)")->execute([$r['id'],$newSid,now()]);
upd($r['id'],['status'=>'confirmed','needs_replacement'=>0,'briefing_done'=>0,'briefing_date'=>null,'fund_confirmed_at'=>now()]);
$oldName=(find_user_by_id($oldSid)['display_name']??('#'.$oldSid)); $newName=(find_user_by_id($newSid)['display_name']??('#'.$newSid));
$note=trim((string)$reason);
if(!in_array($note,replace_reasons())&&$note!=='') $full="Замена: $oldName → $newName (другое: $note)";
else $full="Замена: $oldName → $newName".($note!==''?" ($note)":'');
log_action($fund['id'],'fund','fund_replace_speaker','requests',$r['id'],$full);
notify_event('replaced',$r,['old_sid'=>$oldSid,'old_name'=>$oldName,'new_sid'=>$newSid,'new_name'=>$newName,'reason'=>$note]);
return [true,'Спикер заменён: '.$newName.'. Состав подтверждён, требуется инструктаж нового спикера.'];
}
function fund_reject_candidate($fund,$r,$sid,$reason=''){
if($r['status']!=='chosen') return [false,'Отклонить кандидата можно только на этапе согласования состава.'];
$row=get_rs_row($r['id'],$sid); if(!$row) return [false,'Кандидат не найден в заявке.'];
if(!empty($row['confirmed'])) return [false,'Спикер уже в составе — воспользуйтесь заменой спикера.'];
$sp=speaker_fund_of($sid); if(!$sp || !in_array((int)$sp['id'],fund_scope_ids($fund),true)) return [false,'Этот спикер относится к другому фонду.'];
db()->prepare("DELETE FROM request_speakers WHERE request_id=? AND speaker_user_id=?")->execute([$r['id'],$sid]);
$nm=(find_user_by_id($sid)['display_name']??('#'.$sid)); $rn=trim((string)$reason);
$full="Отклонён кандидат: $nm".($rn!==''?" (причина: $rn)":'');
log_action($fund['id'],'fund','fund_reject_candidate','requests',$r['id'],$full);
return [true,'Кандидат отклонён: '.$nm.'. Назначьте другого спикера.'];
}
function fund_assign_speaker($fund,$r,$sid){
if(!in_array($r['status'],['new','chosen'])) return [false,'Назначить спикера можно только до подтверждения состава.'];
$ok=false; foreach(replacement_speakers($fund,$r,0) as $s){ if((int)$s['id']===(int)$sid){ $ok=true; break; } }
if(!$ok) return [false,'Спикер недоступен (занят, не подходит по возрасту/району, превышен лимит или относится к другому фонду).'];
db()->prepare("INSERT INTO request_speakers(request_id,speaker_user_id,confirmed,recommended,created_at) VALUES(?,?,0,0,?)")->execute([$r['id'],$sid,now()]);
auto_claim_from_speaker($r['id'],$sid);
if($r['status']==='new') upd($r['id'],['status'=>'chosen']);
$nm=(find_user_by_id($sid)['display_name']??('#'.$sid));
log_action($fund['id'],'fund','fund_assign_speaker','requests',$r['id'],"Фонд назначил спикера: $nm");
return [true,'Спикер назначен: '.$nm.'. Теперь рекомендуйте или подтвердите его в списке ниже.'];
}
function fund_finalize($fund,$r){
if(!empty($r['claimed_fund_id']) && !in_array((int)$r['claimed_fund_id'],fund_scope_ids($fund),true)) return [false,'Эта заявка закреплена за другим фондом.'];
if($r['status']!=='chosen') return [false,'Закрыть состав можно только на этапе согласования.'];
$c=count_confirmed($r['id']);
if($c<1) return [false,'Сначала подтвердите хотя бы одного спикера.'];
db()->prepare("DELETE FROM request_speakers WHERE request_id=? AND confirmed=0")->execute([$r['id']]);
if(empty($r['fund_confirmed_at'])) upd($r['id'],['fund_confirmed_at'=>now()]);
upd($r['id'],['status'=>'confirmed']);
log_action($fund['id'],'fund','fund_finalize','requests',$r['id'],"Состав закрыт Фондом: $c из ".$r['speakers_needed']." спикеров");
notify_event('confirmed',$r);
return [true,'Состав закрыт ('.$c.' из '.$r['speakers_needed'].'). Заявка передана дальше.'];
}
function set_vet_feedback($rid,$sid,$comment){ db()->prepare("UPDATE request_speakers SET vet_comment=?, vet_at=? WHERE request_id=? AND speaker_user_id=?")->execute([$comment,now(),$rid,$sid]); log_action(current_user()['id']??null,'speaker','vet_feedback','requests',$rid,"Отзыв спикера #$sid сохранён"); }
// ========== МАТЕРИАЛЫ ==========
const MATERIAL_MAX_BYTES = 104857600; // 100 МБ
const PHOTO_MAX_BYTES = 8388608; // 8 МБ
// Белый список расширений — тот же, что уже разрешён к раздаче в uploads/ (см. .htaccess).
// Всё остальное (.html, .svg, .js и т.п.) сервер отдал бы напрямую по прямой ссылке,
// поэтому такие файлы не принимаем ещё на этапе загрузки.
function material_allowed_extensions(){ return ['pdf','doc','docx','ppt','pptx','xls','xlsx','mp4','avi','mov','mkv','zip','rar','7z','jpg','jpeg','png','txt','webm','flv','wmv']; }
function material_topics(){ return db()->query("SELECT DISTINCT topic FROM materials WHERE type='tech_map' AND topic IS NOT NULL AND topic<>'' ORDER BY topic")->fetchAll(PDO::FETCH_COLUMN); }
function add_material($title,$desc,$type,$topic,$link,$filePath,$fileName,$fileSize,$uploadedBy){
db()->prepare("INSERT INTO materials(title,description,type,topic,link,file_path,file_name,file_size,uploaded_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)")
->execute([$title,$desc,$type,$topic,$link,$filePath,$fileName,$fileSize,$uploadedBy,now()]);
$id=db()->lastInsertId();
log_action($uploadedBy,'operator','material_upload','materials',$id,"Загружен материал: $title");
return [true,$id];
}
function update_material($id,$title,$desc,$type,$topic,$link,$filePath,$fileName,$fileSize){
$fields=['title'=>$title,'description'=>$desc,'type'=>$type,'topic'=>$topic,'link'=>$link];
if($filePath!==null){ $fields['file_path']=$filePath; $fields['file_name']=$fileName; $fields['file_size']=$fileSize; }
$set=implode(',',array_map(fn($k)=>"$k=?",array_keys($fields)));
db()->prepare("UPDATE materials SET $set WHERE id=?")->execute([...array_values($fields),$id]);
log_action(current_user()['id']??null,'operator','material_update','materials',$id,"Изменён материал: $title");
return [true,'Материал обновлён.'];
}
// $topic: null — без фильтра по теме; '' — только материалы БЕЗ темы; строка — конкретная тема.
function list_materials($type=null,$topic=null){
$where=[]; $params=[];
if($type){ $where[]='m.type=?'; $params[]=$type; }
if($topic!==null){
if($topic===''){ $where[]="(m.topic IS NULL OR m.topic='')"; }
else { $where[]='m.topic=?'; $params[]=$topic; }
}
$sql="SELECT m.*, u.display_name uploader FROM materials m LEFT JOIN users u ON u.id=m.uploaded_by".($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY m.created_at DESC';
$st=db()->prepare($sql); $st->execute($params); return $st->fetchAll();
}
// «Папки» на странице материалов — темы, по которым уже загружен хотя бы один материал любого
// вида (техкарта, ролик, документ и т.п.), плюс отдельно материалы без темы, если такие есть.
function material_topics_all(){ return db()->query("SELECT DISTINCT topic FROM materials WHERE topic IS NOT NULL AND topic<>'' ORDER BY topic")->fetchAll(PDO::FETCH_COLUMN); }
function material_topic_counts(){
$out=[]; foreach(db()->query("SELECT COALESCE(NULLIF(topic,''),'') topic, COUNT(*) c FROM materials GROUP BY COALESCE(NULLIF(topic,''),'')")->fetchAll() as $row) $out[$row['topic']]=(int)$row['c'];
return $out;
}
// Простое склонение по числу (1 материал / 2 материала / 5 материалов).
function ru_plural($n,$one,$few,$many){
$n=abs((int)$n); $n10=$n%10; $n100=$n%100;
if($n10===1 && $n100!==11) return $one;
if($n10>=2 && $n10<=4 && ($n100<12 || $n100>14)) return $few;
return $many;
}
function get_material($id){
$st=db()->prepare("SELECT * FROM materials WHERE id=?"); $st->execute([$id]); return $st->fetch()?:null;
}
// ========== НОВОСТИ (стартовая страница) ==========
function list_news($onlyPublished=true,$limit=null){
$where=$onlyPublished?'WHERE published=1':'';
$sql="SELECT n.*, u.display_name author FROM news n LEFT JOIN users u ON u.id=n.created_by $where ORDER BY n.created_at DESC".($limit?' LIMIT '.(int)$limit:'');
return db()->query($sql)->fetchAll();
}
function get_news($id){ $st=db()->prepare("SELECT * FROM news WHERE id=?"); $st->execute([$id]); return $st->fetch()?:null; }
function news_image_allowed_extensions(){ return ['jpg','jpeg','png','webp']; }
function validate_uploaded_news_image($file){
if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return [false,'Ошибка загрузки фото.',null];
if($file['size']>PHOTO_MAX_BYTES) return [false,'Фото слишком большое (максимум 8 МБ).',null];
$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
if(!in_array($ext,news_image_allowed_extensions(),true)) return [false,'Фото должно быть в формате JPG, PNG или WEBP.',null];
$info=@getimagesize($file['tmp_name']);
if(!$info||!in_array($info['mime'],['image/jpeg','image/png','image/webp'],true)) return [false,'Файл повреждён или не является настоящим изображением.',null];
$dir=__DIR__.'/uploads/news'; if(!is_dir($dir)) mkdir($dir,0755,true);
$stored=bin2hex(random_bytes(16)).'.'.$ext;
if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$stored)) return [false,'Не удалось сохранить фото.',null];
return [true,null,$stored];
}
function add_news($title,$body,$imagePath,$published,$actorId){
db()->prepare("INSERT INTO news(title,body,image_path,published,created_by,created_at) VALUES(?,?,?,?,?,?)")
->execute([$title,$body,$imagePath,$published?1:0,$actorId,now()]);
$id=db()->lastInsertId();
log_action($actorId,current_user()['role']??'operator','news_add','news',$id,"Добавлена новость: $title");
return [true,$id];
}
function update_news($id,$title,$body,$imagePath,$published,$actorId){
$fields=['title'=>$title,'body'=>$body,'published'=>$published?1:0];
if($imagePath!==null) $fields['image_path']=$imagePath;
$set=implode(',',array_map(fn($k)=>"$k=?",array_keys($fields)));
db()->prepare("UPDATE news SET $set WHERE id=?")->execute([...array_values($fields),$id]);
log_action($actorId,current_user()['role']??'operator','news_update','news',$id,"Изменена новость: $title");
return [true,'Новость обновлена.'];
}
function delete_news($id,$actorId){
$n=get_news($id); if(!$n) return [false,'Новость не найдена.'];
if(!empty($n['image_path'])){ $path=__DIR__.'/uploads/news/'.$n['image_path']; if(is_file($path)) @unlink($path); }
db()->prepare("DELETE FROM news WHERE id=?")->execute([$id]);
log_action($actorId,current_user()['role']??'operator','news_delete','news',$id,"Удалена новость: ".$n['title']);
return [true,'Новость удалена.'];
}
// Сводка для публичной стартовой страницы — уроки, которые реально состоялись, активные
// спикеры и головные организации-заявители (без сотрудников, чтобы не считать дважды).
function public_site_stats(){
$lessons=(int)db()->query("SELECT COUNT(*) c FROM requests WHERE status IN ('done','closed')")->fetch()['c'];
$speakers=(int)db()->query("SELECT COUNT(*) c FROM users WHERE role='speaker' AND active=1")->fetch()['c'];
$schools=(int)db()->query("SELECT COUNT(*) c FROM users WHERE role='school' AND active=1 AND (parent_school_id IS NULL OR parent_school_id=0)")->fetch()['c'];
return ['lessons'=>$lessons,'speakers'=>$speakers,'schools'=>$schools];
}
function delete_material($id){
$m=get_material($id); if(!$m) return [false,'Материал не найден.'];
if(!empty($m['file_path'])){ $path=__DIR__.'/uploads/materials/'.$m['file_path']; if(is_file($path)) @unlink($path); }
db()->prepare("DELETE FROM materials WHERE id=?")->execute([$id]);
log_action(current_user()['id']??null,'operator','material_delete','materials',$id,"Удалён материал: ".$m['title']);
return [true,'Материал удалён.'];
}
// ========== МГНОВЕННЫЕ УВЕДОМЛЕНИЯ ==========
function notify_event($type,$r,$extra=[]){
try{
$site=cfg()['site_title'];
$url='https://урокмужества72.рф';
$reqUrl=$url.'?p=request&id='.$r['id'];
$school=find_user_by_id((int)($r['school_user_id']??0));
$schoolEmail=$school['email']??'';
$schoolName=($school['display_name']?:($school['org_name']??'заявитель'));
$confirmed=req_confirmed_rows($r['id']);
$speakerContacts=[];
foreach($confirmed as $row){
$sp=find_user_by_id((int)$row['speaker_user_id']);
if($sp) $speakerContacts[]=['id'=>$sp['id'],'email'=>$sp['email']??'','name'=>$sp['display_name']?:'спикер'];
}
$pushUrl='?p=request&id='.$r['id'];
$subject=''; $body='';
switch($type){
case 'confirmed':
$subject="[$site] Состав по заявке {$r['code']} подтверждён";
$body="Здравствуйте!\r\n\r\nПо заявке {$r['code']} от «{$schoolName}» подтверждён состав спикеров.\r\nДата урока: {$r['udate']} {$r['utime']}".(!empty($r['utime_end'])?"–{$r['utime_end']}":'')."\r\nОрганизация: {$r['org']}\r\nСостав:\r\n";
foreach($confirmed as $row) $body.="  • {$row['display_name']}\r\n";
$body.="\r\nПодробнее: $reqUrl\r\n\r\nЭто автоматическое уведомление.\r\n";
if($schoolEmail) @send_smtp_email($schoolEmail,$schoolName,$subject,$body);
foreach($speakerContacts as $sp) if($sp['email']) @send_smtp_email($sp['email'],$sp['name'],$subject,$body);
if($school) notify_push($school['id'],'Состав подтверждён',"Заявка {$r['code']}: спикеры на урок подтверждены.",$pushUrl);
foreach($speakerContacts as $sp) notify_push($sp['id'],'Вы подтверждены на урок',"Заявка {$r['code']}, {$r['udate']} {$r['utime']}.",$pushUrl);
break;
case 'replaced':
$oldName=$extra['old_name']??''; $newName=$extra['new_name']??''; $reason=$extra['reason']??'';
$subject="[$site] Замена спикера в заявке {$r['code']}";
$body="Здравствуйте!\r\n\r\nВ заявке {$r['code']} («{$schoolName}») произведена замена спикера.\r\nДата урока: {$r['udate']} {$r['utime']}\r\nБыло: $oldName\r\nСтало: $newName\r\n";
if($reason!=='') $body.="Причина: $reason\r\n";
$body.="\r\nПодробнее: $reqUrl\r\n\r\nЭто автоматическое уведомление.\r\n";
if($schoolEmail) @send_smtp_email($schoolEmail,$schoolName,$subject,$body);
$newSid=(int)($extra['new_sid']??0);
$newSp=$newSid?find_user_by_id($newSid):null;
if($newSp&&!empty($newSp['email'])) @send_smtp_email($newSp['email'],$newSp['display_name']?:'спикер',$subject,$body);
if($school) notify_push($school['id'],'Замена спикера',"Заявка {$r['code']}: $oldName → $newName.",$pushUrl);
if($newSp) notify_push($newSp['id'],'Вы назначены на урок',"Заявка {$r['code']}, {$r['udate']} {$r['utime']} (замена).",$pushUrl);
break;
case 'not_held':
$reason=$extra['reason']??'не указана';
$subject="[$site] Урок по заявке {$r['code']} не состоялся";
$body="Здравствуйте!\r\n\r\nУрок по заявке {$r['code']} от «{$schoolName}» не состоялся.\r\nДата: {$r['udate']} {$r['utime']}\r\nПричина: $reason\r\n";
$body.="\r\nПодробнее: $reqUrl\r\n\r\nЭто автоматическое уведомление.\r\n";
foreach($speakerContacts as $sp) if($sp['email']) @send_smtp_email($sp['email'],$sp['name'],$subject,$body);
$ops=db()->query("SELECT id, email, display_name FROM users WHERE role='operator' AND email<>'' AND active=1")->fetchAll();
foreach($ops as $op) @send_smtp_email($op['email'],$op['display_name']?:'оператор',$subject,$body);
foreach($speakerContacts as $sp) notify_push($sp['id'],'Урок не состоялся',"Заявка {$r['code']}: $reason",$pushUrl);
foreach($ops as $op) notify_push($op['id'],'Урок не состоялся',"Заявка {$r['code']} от «{$schoolName}».",$pushUrl);
break;
case 'returned_to_processing':
$reason=$extra['reason']??'';
$subject="[$site] Заявка {$r['code']} возвращена в обработку";
$body="Здравствуйте!\r\n\r\nЗаявка {$r['code']} от «{$schoolName}» возвращена в общую обработку и снова доступна всем фондам.\r\nДата урока: {$r['udate']} {$r['utime']}\r\n";
if($reason!=='') $body.="Причина: $reason\r\n";
$body.="\r\nПодробнее: $reqUrl\r\n\r\nЭто автоматическое уведомление.\r\n";
if($schoolEmail) @send_smtp_email($schoolEmail,$schoolName,$subject,$body);
foreach($speakerContacts as $sp) if($sp['email']) @send_smtp_email($sp['email'],$sp['name'],$subject,$body);
if($school) notify_push($school['id'],'Заявка возвращена в обработку',"Заявка {$r['code']} снова доступна фондам.",$pushUrl);
foreach($speakerContacts as $sp) notify_push($sp['id'],'Заявка возвращена в обработку',"Заявка {$r['code']}.",$pushUrl);
break;
}
}catch(Throwable $e){ /* не ломаем основной поток */ }
}
// ========== ЖУРНАЛ ДЕЙСТВИЙ ==========
function log_action($uid,$role,$action,$target_type,$target_id,$details){
try{ db()->prepare("INSERT INTO audit_log(user_id,user_role,action,target_type,target_id,details,created_at) VALUES(?,?,?,?,?,?,?)")
->execute([$uid,$role,$action,$target_type,$target_id,$details,now()]); }catch(Throwable $e){}
}
function get_audit_log($filters=[],$scopeUserIds=null){
$where='1=1'; $params=[];
if(!empty($filters['target_type'])) { $where.=' AND target_type=?'; $params[]=$filters['target_type']; }
if(!empty($filters['target_id'])) { $where.=' AND target_id=?'; $params[]=$filters['target_id']; }
if(!empty($filters['user_role'])) { $where.=' AND user_role=?'; $params[]=$filters['user_role']; }
if(!empty($filters['from_date'])) { $where.=' AND created_at>=?'; $params[]=$filters['from_date']; }
if(!empty($filters['to_date'])) { $where.=' AND created_at<=?'; $params[]=$filters['to_date'].' 23:59:59'; }
if($scopeUserIds!==null){ if(!$scopeUserIds) return []; $in=implode(',',array_fill(0,count($scopeUserIds),'?')); $where.=" AND user_id IN ($in)"; $params=array_merge($params,$scopeUserIds); }
$st=db()->prepare("SELECT al.*, u.login, u.display_name FROM audit_log al LEFT JOIN users u ON u.id=al.user_id WHERE $where ORDER BY al.id DESC LIMIT 200");
$st->execute($params); return $st->fetchAll();
}
// ========== СОРТИРОВКА СПИСКОВ ЗАЯВОК ==========
function sort_status_rank($k){
$ranks=['new'=>0,'chosen'=>1,'confirmed'=>2,'brief'=>2,'prep'=>3,'done'=>4,'cancelled'=>5,'closed'=>6];
return $ranks[$k]??0;
}
function sort_users_rows($rows,$key,$dir='asc'){
if(!$key||!$rows) return $rows;
$mul=$dir==='desc'?-1:1;
usort($rows,function($a,$b) use($key,$mul){
switch($key){
case 'name': $cmp=strcasecmp($a['display_name']?:$a['org_name'],$b['display_name']?:$b['org_name']); break;
case 'role': $cmp=strcasecmp($a['role']??'',$b['role']??''); break;
case 'email': $cmp=strcasecmp($a['email']??'',$b['email']??''); break;
case 'contact_name': $cmp=strcasecmp($a['contact_name']??'',$b['contact_name']??''); break;
case 'position': $cmp=strcasecmp($a['position']??'',$b['position']??''); break;
case 'phone': $cmp=strcasecmp($a['phone']??'',$b['phone']??''); break;
case 'address': $cmp=strcasecmp($a['address']??'',$b['address']??''); break;
case 'building': $cmp=strnatcasecmp($a['building']??'',$b['building']??''); break;
case 'status': $cmp=(int)($a['active']??0)<=>(int)($b['active']??0); break;
case 'district': $cmp=strcasecmp($a['district']??'',$b['district']??''); break;
case 'fund_name': $fa=$a['fund_user_id']?find_user_by_id((int)$a['fund_user_id']):null; $fb=$b['fund_user_id']?find_user_by_id((int)$b['fund_user_id']):null; $cmp=strcasecmp($fa['display_name']??'',$fb['display_name']??''); break;
default: $cmp=0;
}
return $cmp*$mul;
});
return $rows;
}
function sort_requests($rows,$key,$dir='asc'){
if(!$key||!$rows) return $rows;
$mul=$dir==='desc'?-1:1;
usort($rows,function($a,$b) use($key,$mul){
switch($key){
case 'code': $cmp=strnatcasecmp($a['code']??'',$b['code']??''); break;
case 'org': $cmp=strcasecmp($a['org']??'',$b['org']??''); break;
case 'date': $cmp=strcmp(($a['udate']??'').' '.($a['utime']??''),($b['udate']??'').' '.($b['utime']??'')); break;
case 'status': $cmp=sort_status_rank($a['status']??'')<=>sort_status_rank($b['status']??''); break;
case 'fund': $cmp=strcasecmp(!empty($a['claimed_fund_id'])?fund_name_by_id($a['claimed_fund_id']):'',!empty($b['claimed_fund_id'])?fund_name_by_id($b['claimed_fund_id']):''); break;
case 'speakers': $cmp=strcasecmp(req_label($a['id']),req_label($b['id'])); break;
default: $cmp=0;
}
return $cmp*$mul;
});
return $rows;
}
// ========== АРХИВАЦИЯ ==========
// Отсчёт N рабочих дней назад от текущего момента (пропускаем субботы/воскресенья).
// Официальные праздники/переносы не учитываются — только будни.
function business_days_ago($fromTs,$days){
$t=strtotime($fromTs); $counted=0;
while($counted<$days){ $t=strtotime('-1 day',$t); if((int)date('N',$t)<6) $counted++; }
return date('Y-m-d H:i:s',$t);
}
function archive_old_requests($businessDays=5){
$cutoff=business_days_ago(now(),$businessDays);
$st=db()->prepare("UPDATE requests SET archived=1, archived_at=? WHERE status IN ('closed','cancelled') AND archived=0 AND COALESCE(closed_at, fb_app_at, created_at) < ?");
$st->execute([now(),$cutoff]); return $st->rowCount();
}
function get_requests_list($filters=[]){
$where='1=1'; $params=[];
if(!empty($filters['archived'])) { $where.=' AND archived=1'; } else { $where.=' AND (archived=0 OR archived IS NULL)'; }
if(!empty($filters['status'])) { $where.=' AND status=?'; $params[]=$filters['status']; }
if(!empty($filters['org'])) { $where.=' AND org LIKE ?'; $params[]='%'.$filters['org'].'%'; }
if(!empty($filters['from_date'])) { $where.=' AND udate>=?'; $params[]=$filters['from_date']; }
if(!empty($filters['to_date'])) { $where.=' AND udate<=?'; $params[]=$filters['to_date']; }
if(!empty($filters['search'])) { $s='%'.$filters['search'].'%'; $where.=' AND (code LIKE ? OR org LIKE ? OR contact_name LIKE ?)'; $params=array_merge($params,[$s,$s,$s]); }
if(!empty($filters['op_group'])) { $where.=' AND op_group=?'; $params[]=$filters['op_group']; }
if(!empty($filters['fund_ids'])) { $in=implode(',',array_fill(0,count($filters['fund_ids']),'?')); $where.=" AND claimed_fund_id IN ($in)"; $params=array_merge($params,$filters['fund_ids']); }
$order=!empty($filters['order'])?$filters['order']:'id DESC';
$st=db()->prepare("SELECT * FROM requests WHERE $where ORDER BY $order");
$st->execute($params); return $st->fetchAll();
}
// ========== ЭКСПОРТ В CSV ==========
function export_requests_csv($rows){
$fp=fopen('php://temp','w+');
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($fp,['Код','Организация','Оператор','Дата','Время','Аудитория','Спикеров нужно','Уроков на спикера','Статус','Контакт','Телефон'], ';', '"', '\\');
foreach($rows as $r){
fputcsv($fp,[
$r['code'],$r['org'],$r['operator'],$r['udate'],$r['utime'].($r['utime_end']?'-'.$r['utime_end']:''),
ages()[$r['age_group']]??'',$r['speakers_needed'],$r['lessons_per_speaker']??1,st_meta($r['status'])['label'],$r['contact_name'],$r['phone']
], ';', '"', '\\');
}
rewind($fp); $csv=stream_get_contents($fp); fclose($fp); return $csv;
}
function export_speakers_csv(){
$rows=active_speakers();
$fp=fopen('php://temp','w+');
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($fp,['ФИО','Логин','Email','Телефон','Возрастные группы','Уроков проведено'], ';', '"', '\\');
foreach($rows as $s){
$lessons=count(array_filter(speaker_lessons($s['id']),fn($x)=>in_array($x['status'],['done','closed'])));
$groups=implode(', ',array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$s['age_groups']))));
fputcsv($fp,[$s['display_name'],$s['login'],$s['email']??'',$s['phone']??'',$groups,$lessons], ';', '"', '\\');
}
rewind($fp); $csv=stream_get_contents($fp); fclose($fp); return $csv;
}
// ========== ЭКСПОРТ ОТЧЁТА ==========
function export_report_csv($filters){
$where="1=1"; $params=[];
if(!empty($filters['from_date'])) { $where.=' AND r.udate>=?'; $params[]=$filters['from_date']; }
if(!empty($filters['to_date'])) { $where.=' AND r.udate<=?'; $params[]=$filters['to_date']; }
if(!empty($filters['op_group'])) { $where.=' AND r.op_group=?'; $params[]=$filters['op_group']; }
if(!empty($filters['status'])) { $where.=' AND r.status=?'; $params[]=$filters['status']; }
else { $where.=" AND r.status IN ('done','closed')"; }
$gc=(cfg()['driver']==='sqlite')?"GROUP_CONCAT(u.display_name, '; ')":"GROUP_CONCAT(u.display_name SEPARATOR '; ')";
$sql="SELECT r.*,
(SELECT $gc FROM request_speakers rs JOIN users u ON u.id=rs.speaker_user_id WHERE rs.request_id=r.id AND rs.confirmed=1) AS speakers_names,
(SELECT COUNT(*) FROM request_speakers rs WHERE rs.request_id=r.id AND rs.confirmed=1) AS speakers_count
FROM requests r WHERE $where ORDER BY r.udate, r.utime";
$st=db()->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
$totalLessons=0; $totalParticipants=0; $schoolsSet=[]; $speakersSet=[];
foreach($rows as $r){
$lps=max(1,(int)($r['lessons_per_speaker']??1));
$totalLessons+=$lps*(int)$r['speakers_count'];
$totalParticipants+=(int)($r['participants']??0);
$schoolsSet[$r['org']]=1;
if(!empty($r['speakers_names'])) foreach(explode('; ',$r['speakers_names']) as $nm) if($nm!=='') $speakersSet[$nm]=1;
}
$fp=fopen('php://temp','w+');
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($fp,['ОТЧЁТ О ПРОВЕДЕНИИ УРОКОВ МУЖЕСТВА'], ';', '"', '\\');
fputcsv($fp,['Период',(!empty($filters['from_date'])?$filters['from_date']:'начало').' — '.(!empty($filters['to_date'])?$filters['to_date']:'по настоящее время')], ';', '"', '\\');
fputcsv($fp,['Всего проведённых уроков',count($rows)], ';', '"', '\\');
fputcsv($fp,['Суммарный охват (уроков × спикеров)',$totalLessons], ';', '"', '\\');
fputcsv($fp,['Охвачено детей',$totalParticipants], ';', '"', '\\');
fputcsv($fp,['Уникальных организаций',count($schoolsSet)], ';', '"', '\\');
fputcsv($fp,['Уникальных спикеров',count($speakersSet)], ';', '"', '\\');
fputcsv($fp,[], ';', '"', '\\');
fputcsv($fp,['ДЕТАЛИЗАЦИЯ'], ';', '"', '\\');
fputcsv($fp,['Код','Организация','Район','Дата','Время','Аудитория','Участников','Спикеров нужно','Уроков на спикера','Спикеры','Тема','Комментарий заявителя'], ';', '"', '\\');
foreach($rows as $r){
fputcsv($fp,[
$r['code'],$r['org'],$r['district']??'',
$r['udate'],$r['utime'].(!empty($r['utime_end'])?'-'.$r['utime_end']:''),
ages()[$r['age_group']]??'',
$r['participants']??'',
$r['speakers_needed'],
$r['lessons_per_speaker']??1,
$r['speakers_names']??'',
$r['topic']??'',
$r['fb_app_comment']??''
], ';', '"', '\\');
}
rewind($fp); $csv=stream_get_contents($fp); fclose($fp); return $csv;
}
// ========== КАЛЕНДАРЬ ==========
// Без фильтра по archived — при просмотре прошлых месяцев календарь должен показывать
// проведённые уроки, даже если заявка уже ушла в архив спустя 30 дней после закрытия.
function get_calendar_events($month=null,$year=null,$user=null){
if(!$month) $month=date('m'); if(!$year) $year=date('Y');
$from=sprintf('%04d-%02d-01',(int)$year,(int)$month); $to=date('Y-m-t',strtotime($from));
$role=$user['role']??'operator'; $uid=$user['id']??0;
if($role==='school'){ $st=db()->prepare("SELECT * FROM requests WHERE udate BETWEEN ? AND ? AND school_user_id=? ORDER BY udate,utime"); $st->execute([$from,$to,$uid]); }
elseif($role==='speaker'){ $st=db()->prepare("SELECT r.* FROM requests r JOIN request_speakers rs ON rs.request_id=r.id WHERE r.udate BETWEEN ? AND ? AND rs.speaker_user_id=? AND rs.confirmed=1 ORDER BY r.udate,r.utime"); $st->execute([$from,$to,$uid]); }
elseif($role==='fund'){ $st=db()->prepare("SELECT * FROM requests WHERE udate BETWEEN ? AND ? AND id IN (SELECT request_id FROM request_speakers) ORDER BY udate,utime"); $st->execute([$from,$to]); }
else { $og=($user['op_group']??'')==='school'?" AND op_group='school'":""; $st=db()->prepare("SELECT * FROM requests WHERE udate BETWEEN ? AND ?$og ORDER BY udate,utime"); $st->execute([$from,$to]); }
$rows=$st->fetchAll();
foreach($rows as &$r){ $r['speakers']=implode(', ', array_map(fn($x)=>$x['display_name'], req_confirmed_rows($r['id']))); }
return $rows;
}
// ---------- показатели ----------
// Без фильтра по archived — отчёт «Отзывы заявителей/спикеров» должен показывать всю историю,
// включая заявки, ушедшие в архив спустя 30 дней после закрытия.
function list_app_reviews($opGroup=null){
$sql="SELECT * FROM requests WHERE fb_app_comment IS NOT NULL AND fb_app_comment<>''"; $p=[];
if($opGroup){ $sql.=' AND op_group=?'; $p[]=$opGroup; }
$sql.=' ORDER BY fb_app_at DESC';
$st=db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}
function list_speaker_reviews($opGroup=null){
$sql="SELECT rs.*, r.code, r.org, r.topic, r.op_group, r.archived, u.display_name speaker_name FROM request_speakers rs JOIN requests r ON r.id=rs.request_id JOIN users u ON u.id=rs.speaker_user_id WHERE rs.vet_comment IS NOT NULL AND rs.vet_comment<>''"; $p=[];
if($opGroup){ $sql.=' AND r.op_group=?'; $p[]=$opGroup; }
$sql.=' ORDER BY rs.vet_at DESC';
$st=db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}
// Без фильтра по archived: это источник данных для показателей/статистики (KPI, «Показатели
// за месяц»), а не рабочего списка заявок — архивация не должна прятать историю из отчётов.
function operator_scope_requests($opGroup=null){
$sql='SELECT * FROM requests WHERE 1=1'; $p=[];
if($opGroup){ $sql.=' AND op_group=?'; $p[]=$opGroup; }
$st=db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}
function requests_month_breakdown($all){
$by=[];
foreach($all as $r){
$ym=substr((string)($r['udate']??''),0,7); if($ym==='') continue;
if(!isset($by[$ym])) $by[$ym]=['done'=>0,'kids'=>0,'cancelled'=>0];
if(in_array($r['status'],['done','closed'],true)){ $by[$ym]['done']++; $by[$ym]['kids']+=(int)($r['participants']??0); }
if($r['status']==='cancelled') $by[$ym]['cancelled']++;
}
$cur=date('Y-m'); if(!isset($by[$cur])) $by[$cur]=['done'=>0,'kids'=>0,'cancelled'=>0];
krsort($by);
return $by;
}
function month_stats_widget($all,$domId){
$by=requests_month_breakdown($all);
$opts=''; foreach(array_keys($by) as $ym) $opts.='<option value="'.esc($ym).'">'.esc(month_label($ym)).'</option>';
$html='<div class="card pad" style="margin:14px 0;background:var(--tint);border-left:4px solid var(--navy)"><div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><label class="lbl" style="margin:0">📅 Показатели за месяц <span class="muted" style="font-weight:400">(в отличие от карточек выше — не за всё время, а только за выбранный месяц)</span></label><select id="'.$domId.'_sel" onchange="'.$domId.'_upd()">'.$opts.'</select></div>'
.'<div style="display:flex;gap:28px;margin-top:12px;flex-wrap:wrap">'
.'<div><div class="n" id="'.$domId.'_done" style="font-size:22px;color:var(--navy)">0</div><div class="l muted">Проведено и закрыто</div></div>'
.'<div><div class="n" id="'.$domId.'_cancel" style="font-size:22px;color:var(--navy)">0</div><div class="l muted">Не состоялось</div></div>'
.'<div><div class="n" id="'.$domId.'_kids" style="font-size:22px;color:var(--green)">0</div><div class="l muted">Охвачено детей</div></div>'
.'</div></div>'
.'<script>var '.$domId.'_data='.json_encode($by).';function '.$domId.'_upd(){var v=document.getElementById("'.$domId.'_sel").value;var d='.$domId.'_data[v]||{done:0,cancelled:0,kids:0};document.getElementById("'.$domId.'_done").textContent=d.done;document.getElementById("'.$domId.'_cancel").textContent=d.cancelled;document.getElementById("'.$domId.'_kids").textContent=d.kids;}'.$domId.'_upd();</script>';
return $html;
}
function school_month_report($rows,$domId){
$by=[];
foreach($rows as $r){
$ym=substr((string)($r['udate']??''),0,7); if($ym==='') continue;
if(!isset($by[$ym])) $by[$ym]=[];
$by[$ym][]=$r;
}
$cur=date('Y-m'); if(!isset($by[$cur])) $by[$cur]=[];
krsort($by);
$opts=''; foreach(array_keys($by) as $ym) $opts.='<option value="'.esc($ym).'"'.($ym===$cur?' selected':'').'>'.esc(month_label($ym)).'</option>';
$panels='';
foreach($by as $ym=>$list){
usort($list,fn($a,$b)=>strcmp(($a['udate']??'').($a['utime']??''),($b['udate']??'').($b['utime']??'')));
$done=array_values(array_filter($list,fn($r)=>in_array($r['status'],['done','closed'],true)));
$cancelled=count(array_filter($list,fn($r)=>$r['status']==='cancelled'));
$kids=array_sum(array_map(fn($r)=>(int)($r['participants']??0),$done));
$rowsHtml='';
foreach($done as $r){
$t=$r['utime']?(' '.esc($r['utime']).(!empty($r['utime_end'])?('–'.esc($r['utime_end'])):'')):'';
$m=st_meta($r['status']);
$rowsHtml.='<tr><td data-label="Дата">'.esc($r['udate']).$t.'</td><td data-label="Тема">'.esc($r['topic']?:'—').'</td><td data-label="Кто проводил">'.esc(req_label($r['id'])).'</td><td data-label="Детей">'.esc($r['participants']?:'0').'</td><td data-label="Статус"><span class="pill '.esc($m['color']).'">'.esc($m['label']).'</span></td></tr>';
}
$table=$done?'<table class="card-view" style="margin-top:10px"><thead><tr><th>Дата</th><th>Тема</th><th>Кто проводил</th><th>Детей</th><th>Статус</th></tr></thead><tbody>'.$rowsHtml.'</tbody></table>':'<p class="muted" style="margin-top:8px">В этом месяце проведённых уроков пока нет.</p>';
$panels.='<div class="month-panel" data-ym="'.esc($ym).'" style="display:'.($ym===$cur?'block':'none').'">'
.'<div style="display:flex;gap:28px;margin:10px 0;flex-wrap:wrap">'
.'<div><div class="n" style="font-size:22px;color:var(--navy)">'.count($done).'</div><div class="l muted">Проведено и закрыто</div></div>'
.'<div><div class="n" style="font-size:22px;color:var(--navy)">'.$cancelled.'</div><div class="l muted">Не состоялось</div></div>'
.'<div><div class="n" style="font-size:22px;color:var(--green)">'.$kids.'</div><div class="l muted">Охвачено детей</div></div>'
.'</div>'.$table.'</div>';
}
$html='<div class="card pad" style="margin:14px 0;background:var(--tint);border-left:4px solid var(--navy)" id="'.$domId.'_wrap"><div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><h4 style="margin:0">📅 Уроки за месяц <span class="muted" style="font-weight:400;text-transform:none;letter-spacing:normal">(в отличие от карточек выше — не за всё время, а только за выбранный месяц)</span></h4><select id="'.$domId.'_sel" onchange="'.$domId.'_upd()">'.$opts.'</select></div>'.$panels.'</div>'
.'<script>function '.$domId.'_upd(){var v=document.getElementById("'.$domId.'_sel").value;document.querySelectorAll("#'.$domId.'_wrap .month-panel").forEach(function(el){el.style.display=(el.getAttribute("data-ym")===v)?"block":"none";});}</script>';
return $html;
}
function kpis($opGroup=null){
$all=operator_scope_requests($opGroup); $total=count($all);
$inwork=count(array_filter($all,fn($r)=>in_array($r['status'],['chosen','confirmed','brief','prep'])));
$reached=array_filter($all,fn($r)=>in_array($r['status'],['done','closed']));
$done=count($reached);
$participants=array_sum(array_map(fn($r)=>(int)($r['participants']??0),$reached));
$spk=db()->query("SELECT COUNT(*) c FROM users WHERE role='speaker' AND active=1")->fetch()['c'];
$cancelled=count(array_filter($all,fn($r)=>$r['status']==='cancelled'));
$appReviews=count(list_app_reviews($opGroup));
$spkReviews=count(list_speaker_reviews($opGroup));
$rq='&dept='.urlencode((string)$opGroup);
return [
['Всего заявок',$total],
['В работе',$inwork],
['Проведено и закрыто',$done],
['Не состоялось',$cancelled],
['Спикеров в реестре',$spk],
['Охвачено детей',$participants],
['Отзывы заявителей',$appReviews,'?p=reviews&type=app'.$rq],
['Отзывы спикеров',$spkReviews,'?p=reviews&type=speaker'.$rq],
];
}
// Без фильтра по archived — как и operator_scope_requests(), это источник для KPI/«Показатели
// за месяц», архивация не должна прятать историю из статистики.
function fund_scope_requests($fund){
$ids=fund_scope_ids($fund); if(!$ids) return [];
$in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT * FROM requests WHERE claimed_fund_id IN ($in)");
$st->execute($ids); return $st->fetchAll();
}
function list_app_reviews_for_fund($fund){
$ids=fund_scope_ids($fund); if(!$ids) return [];
$in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT * FROM requests WHERE claimed_fund_id IN ($in) AND fb_app_comment IS NOT NULL AND fb_app_comment<>'' ORDER BY fb_app_at DESC");
$st->execute($ids); return $st->fetchAll();
}
function list_speaker_reviews_for_fund($fund){
$ids=fund_scope_ids($fund); if(!$ids) return [];
$in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT rs.*, r.code, r.org, r.topic, u.display_name speaker_name FROM request_speakers rs JOIN requests r ON r.id=rs.request_id JOIN users u ON u.id=rs.speaker_user_id WHERE rs.vet_comment IS NOT NULL AND rs.vet_comment<>'' AND u.fund_user_id IN ($in) ORDER BY rs.vet_at DESC");
$st->execute($ids); return $st->fetchAll();
}
function fund_kpis($fund){
$all=fund_scope_requests($fund); $total=count($all);
$inwork=count(array_filter($all,fn($r)=>in_array($r['status'],['chosen','confirmed','brief','prep'])));
$reached=array_filter($all,fn($r)=>in_array($r['status'],['done','closed']));
$done=count($reached);
$participants=array_sum(array_map(fn($r)=>(int)($r['participants']??0),$reached));
$ids=fund_scope_ids($fund); $in=implode(',',array_fill(0,count($ids),'?'));
$spkSt=db()->prepare("SELECT COUNT(*) c FROM users WHERE role='speaker' AND active=1 AND fund_user_id IN ($in)"); $spkSt->execute($ids); $spk=$spkSt->fetch()['c'];
$cancelled=count(array_filter($all,fn($r)=>$r['status']==='cancelled'));
$appReviews=count(list_app_reviews_for_fund($fund));
$spkReviews=count(list_speaker_reviews_for_fund($fund));
return [
['Всего заявок',$total],
['В работе',$inwork],
['Проведено и закрыто',$done],
['Не состоялось',$cancelled],
['Спикеров в реестре',$spk],
['Охвачено детей',$participants],
['Отзывы заявителей',$appReviews,'?p=reviews&type=app'],
['Отзывы спикеров',$spkReviews,'?p=reviews&type=speaker'],
];
}
// ========== СЛОЙ 1: БЛОК "ТРЕБУЕТ ДЕЙСТВИЯ" ==========
function whose_turn($r){
$s=$r['status']; if($s==='brief') $s='confirmed';
if($s==='new') return ['Фонд','Взять заявку в обработку и назначить спикера'];
if($s==='chosen') return ['Фонд','Сформировать и подтвердить состав'];
if($s==='confirmed') return ['Заявитель','Подтвердить готовность площадки'];
if($s==='prep'){ $over=!empty($r['udate']) && strtotime(substr($r['udate'],0,10))<strtotime(date('Y-m-d')); return ['Заявитель',$over?'Урок должен был состояться — отметьте «Проведён» или «Не состоялся»':'После урока отметить «Проведён» или «Не состоялся»']; }
if($s==='done'){ $needsFeedback=trim((string)($r['fb_app_comment']??''))===''||trim((string)($r['fb_post_url']??''))===''; return $needsFeedback ? ['Заявитель','Оставить отзыв и ссылку на пост об уроке'] : ['Оператор','Закрыть заявку']; }
if($s==='closed') return ['—','Заявка закрыта'];
if($s==='cancelled') return ['—','Урок не состоялся'];
return ['—',''];
}
function auto_close_done($days=7){
$cut=date('Y-m-d H:i:s',strtotime("-$days days"));
$st=db()->prepare("SELECT * FROM requests WHERE status='done' AND fb_app_comment IS NOT NULL AND fb_app_comment<>'' AND fb_post_url IS NOT NULL AND fb_post_url<>'' AND COALESCE(fb_app_at, created_at) < ?");
$st->execute([$cut]); $n=0;
foreach($st->fetchAll() as $r){ upd($r['id'],['status'=>'closed','closed_at'=>now()]); log_action(null,'system','auto_close','requests',$r['id'],'Автозакрытие через '.$days.' дн. после отзыва'); $n++; }
return $n;
}
function get_action_required_requests($user) {
$role = $user['role'];
$uid = $user['id'];
if ($role === 'school') {
$ids=school_scope_ids($user); $in=implode(',',array_fill(0,count($ids),'?'));
$st = db()->prepare("SELECT * FROM requests WHERE school_user_id IN ($in) AND (archived=0 OR archived IS NULL) AND ((status IN ('confirmed','brief') AND (prep_confirmed_at IS NULL OR prep_confirmed_at='')) OR status='prep' OR (status='done' AND (fb_app_comment IS NULL OR fb_app_comment='' OR fb_post_url IS NULL OR fb_post_url=''))) ORDER BY udate ASC");
$st->execute($ids); return $st->fetchAll();
}
if ($role === 'fund') {
return fund_actionable($user);
}
if ($role === 'operator') {
$scope = (($user['op_group']??'')==='school') ? " AND op_group='school'" : "";
$st = db()->query("SELECT * FROM requests WHERE (archived=0 OR archived IS NULL) AND status='done' AND fb_app_comment IS NOT NULL AND fb_app_comment<>'' AND fb_post_url IS NOT NULL AND fb_post_url<>''$scope ORDER BY udate ASC");
return $st->fetchAll();
}
return [];
}
function is_request_urgent($r) {
if (empty($r['udate']) || in_array($r['status'], ['done', 'closed'])) return false;
$lesson_date = strtotime($r['udate']);
$today = strtotime(date('Y-m-d'));
$diff = ($lesson_date - $today) / (60 * 60 * 24);
return ($diff >= 0 && $diff <= 3);
}
// ========== НАПОМИНАНИЯ О ЗАВТРАШНИХ УРОКАХ ==========
function requests_tomorrow(){
$tomorrow=date('Y-m-d',strtotime('+1 day'));
$st=db()->prepare("SELECT * FROM requests WHERE udate=? AND status NOT IN ('done','closed','cancelled')");
$st->execute([$tomorrow]); return $st->fetchAll();
}
function send_lesson_reminders(){
$site=cfg()['site_title']; $url='https://урокмужества72.рф'; $n=0;
foreach(requests_tomorrow() as $r){
try{
$school=find_user_by_id((int)($r['school_user_id']??0));
$schoolEmail=$school['email']??''; $schoolName=$school['display_name']?:($school['org_name']??'заявитель');
$reqUrl=$url.'?p=request&id='.$r['id'];
$subject="[$site] Напоминание: урок по заявке {$r['code']} завтра";
$time=$r['utime'].(!empty($r['utime_end'])?"–{$r['utime_end']}":'');
$body="Здравствуйте!\r\n\r\nНапоминаем: урок по заявке {$r['code']} («{$schoolName}») запланирован на завтра, {$r['udate']} в {$time}.\r\nТема: ".($r['topic']?:'не указана')."\r\n\r\nПодробнее: $reqUrl\r\n\r\nЭто автоматическое напоминание.\r\n";
$pushUrl='?p=request&id='.$r['id'];
if($schoolEmail){ @send_smtp_email($schoolEmail,$schoolName,$subject,$body); $n++; }
if($school) notify_push($school['id'],'Урок завтра',"Заявка {$r['code']}, завтра в $time.",$pushUrl);
foreach(req_confirmed_rows($r['id']) as $row){
$sp=find_user_by_id((int)$row['speaker_user_id']);
if($sp && !empty($sp['email'])){ @send_smtp_email($sp['email'],$sp['display_name']?:'спикер',$subject,$body); $n++; }
if($sp) notify_push($sp['id'],'Урок завтра',"Заявка {$r['code']}, завтра в $time.",$pushUrl);
}
}catch(Throwable $e){}
}
return $n;
}
// ========== СЛОЙ 2: EMAIL-УВЕДОМЛЕНИЯ ==========
function get_users_with_pending_actions() {
$users = db()->query("SELECT id, role, login, display_name, org_name, email FROM users WHERE active=1 AND email IS NOT NULL AND email<>''")->fetchAll();
$result = [];
foreach ($users as $u) {
$pending = get_action_required_requests($u);
if (count($pending) > 0) {
$result[] = ['user' => $u, 'count' => count($pending), 'urgent' => count(array_filter($pending, 'is_request_urgent'))];
}
}
return $result;
}
function mail_log($msg){ @file_put_contents(__DIR__.'/mail_error.log', date('Y-m-d H:i:s').' — '.$msg."\n", FILE_APPEND); }
function send_smtp_email($toEmail, $toName, $subject, $body) {
$smtp = cfg()['smtp'] ?? [];
$smtpHost = $smtp['host'] ?? '';
$smtpPort = (int)($smtp['port'] ?? 465);
$smtpUser = $smtp['user'] ?? '';
$smtpPass = $smtp['pass'] ?? '';
$smtpSecure = $smtp['secure'] ?? '';
if ($smtpHost==='' || $smtpUser==='' || $smtpPass==='') {
mail_log('SMTP не настроен');
return false;
}
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
$mail = new PHPMailer\PHPMailer\PHPMailer(true);
try {
$mail->isSMTP();
$mail->Host = $smtpHost;
$mail->SMTPAuth = true;
$mail->Username = $smtpUser;
$mail->Password = $smtpPass;
if ($smtpSecure==='tls' || $smtpPort===587) { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
else { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; }
$mail->Port = $smtpPort;
$mail->CharSet = 'UTF-8';
$mail->SMTPOptions = array('ssl' => array('verify_peer' => false,'verify_peer_name' => false,'allow_self_signed' => true));
$mail->setFrom($smtpUser, 'Уроки мужества 72');
$mail->addReplyTo($smtpUser, 'Уроки мужества 72');
$mail->addAddress($toEmail, $toName);
$mail->isHTML(false);
$mail->Subject = $subject;
$mail->Body = $body;
$mail->send();
return true;
} catch (Exception $e) {
mail_log('Ошибка отправки на '.$toEmail.': '.$mail->ErrorInfo);
return false;
}
}
function send_notify_email_smtp($toEmail, $toName, $count, $urgentCount) {
$site = cfg()['site_title'];
$url = 'https://урокмужества72.рф';
$subject = "[$site] У вас $count заявка(-ок), требующих действия";
$body = "Здравствуйте, $toName!\r\n\r\n";
$body .= "В системе \"$site\" у вас есть $count заявка(-ок), по которым требуется ваше действие.\r\n";
if ($urgentCount > 0) {
$body .= "ВНИМАНИЕ: $urgentCount из них требуют срочного внимания (близится дата проведения).\r\n\r\n";
} else {
$body .= "\r\n";
}
$body .= "Пожалуйста, войдите в систему для просмотра деталей:\r\n$url\r\n\r\n";
$body .= "Это автоматическое уведомление. Не отвечайте на это письмо.\r\n";
return send_smtp_email($toEmail, $toName, $subject, $body);
}