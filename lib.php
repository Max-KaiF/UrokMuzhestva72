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
fb_app_rating INTEGER, fb_app_comment TEXT, fb_app_at VARCHAR(40),
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
foreach([
"ALTER TABLE users ADD COLUMN fund_user_id INTEGER",
"ALTER TABLE users ADD COLUMN email VARCHAR(190)",
"ALTER TABLE users ADD COLUMN op_group VARCHAR(20)",
"ALTER TABLE users ADD COLUMN district VARCHAR(120)",
"ALTER TABLE users ADD COLUMN phone VARCHAR(40)",
"ALTER TABLE users ADD COLUMN contact_name VARCHAR(190)",
"ALTER TABLE users ADD COLUMN position VARCHAR(190)",
"ALTER TABLE users ADD COLUMN parent_fund_id INTEGER",
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
] as $sql){ try{ db()->exec($sql); }catch(Throwable $e){} }
try{ db()->exec("UPDATE requests SET speakers_needed=1 WHERE speakers_needed IS NULL"); }catch(Throwable $e){}
try{ db()->exec("UPDATE requests SET lessons_per_speaker=1 WHERE lessons_per_speaker IS NULL OR lessons_per_speaker<1"); }catch(Throwable $e){}
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
if($u['role']==='school'){
if($blank('address')) $missing[]='Адрес местонахождения';
if($blank('building')) $missing[]='№ корпуса';
}
if($u['role']==='speaker' && $blank('phone')) $missing[]='Телефон';
return $missing;
}
function create_user($role,$login,$pass,$extra=[]){
if(find_user_by_login($login)) return [false,'Такой логин уже занят.'];
$active=array_key_exists('active',$extra)?(int)$extra['active']:(($role==='speaker'||$role==='school')?0:1);
$st=db()->prepare('INSERT INTO users(role,login,pass_hash,display_name,org_name,level,age_groups,active,created_at,email,op_group,district,phone,parent_fund_id,fund_user_id,contact_name,position,address,building) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$st->execute([$role,$login,password_hash($pass,PASSWORD_DEFAULT),$extra['display_name']??'',$extra['org_name']??'',$extra['level']??'',$extra['groups']??'',$active,now(),$extra['email']??'',$extra['op_group']??'',$extra['district']??'',$extra['phone']??'',($extra['parent_fund_id']??null)?:null,($extra['fund_user_id']??null)?:null,$extra['contact_name']??'',$extra['position']??'',$extra['address']??'',$extra['building']??'']);
$newId=db()->lastInsertId();
log_action(null,$role,'user_create','users',$newId,"Создан пользователь $login");
return [true,$newId];
}
function login_user($login,$pass){ $u=find_user_by_login($login); if(!$u||!password_verify($pass,$u['pass_hash'])) return [false,'Неверный логин или пароль.']; session_regenerate_id(true); $_SESSION['uid']=$u['id']; log_action($u['id'],$u['role'],'login','users',$u['id'],'Вход в систему'); return [true,$u]; }
// ========== СБРОС ПАРОЛЯ ==========
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
if($target['role']!=='school') return false;
return school_dept_group($target)===$og;
}
if($actor['role']==='fund'){
return $target['role']==='speaker' && (int)($target['fund_user_id']??0)===(int)$actor['id'];
}
return false;
}
function admin_reset_password($admin,$target_id,$newpass){
$u=find_user_by_id($target_id);
if(!$u) return [false,'Пользователь не найден.'];
if(!can_manage($admin,$u)) return [false,'У вас нет прав управлять этим пользователем.'];
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
function child_subfunds($head){ $st=db()->prepare("SELECT * FROM users WHERE role='fund' AND parent_fund_id=? ORDER BY display_name"); $st->execute([$head['id']]); return $st->fetchAll(); }
function fund_label($f){ $n=($f['display_name']!=='')?$f['display_name']:('Организация №'.$f['id']); if(!empty($f['parent_fund_id'])) $n='— '.$n.' (подразделение'.(!empty($f['district'])?', '.$f['district']:'').')'; elseif(!empty($f['district'])) $n.=' ('.$f['district'].')'; return $n; }
function active_speakers($fund=null){ if($fund){ $st=db()->prepare("SELECT * FROM users WHERE role='speaker' AND active=1 AND fund_user_id=? ORDER BY display_name"); $st->execute([$fund['id']]); return $st->fetchAll(); } return db()->query("SELECT * FROM users WHERE role='speaker' AND active=1 ORDER BY display_name")->fetchAll(); }
// ---- модерация заявителей ----
function applicant_group_levels($opGroup){ if($opGroup==='school') return ['school','other']; if($opGroup==='region') return ['spo','vuz']; return ['school','spo','vuz','other']; }
function pending_applicants($operator){
$og=$operator['op_group']??'';
$st=db()->query("SELECT * FROM users WHERE role='school' AND active=0 ORDER BY id DESC");
$all=$st->fetchAll();
if($og==='') return $all;
return array_values(array_filter($all,fn($a)=>school_dept_group($a)===$og));
}
function approve_applicant($operator,$uid){
$a=find_user_by_id($uid); if(!$a||$a['role']!=='school') return [false,'Заявитель не найден.'];
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
if($actor['role']!=='school'||(int)$r['school_user_id']!==(int)$actor['id']) return [false,'Нет доступа.'];
if(in_array($r['status'],['done','closed','cancelled'])) return [false,'Заявка уже завершена, редактирование недоступно.'];
$allowed=[
'contact_name'=>trim($f['contact_name']??''),
'phone'=>trim($f['phone']??''),
'participants'=>trim($f['participants']??''),
'speakers_needed'=>max(1,(int)($f['speakers_needed']??1)),
'lessons_per_speaker'=>max(1,min(2,(int)($f['lessons_per_speaker']??1))),
'age_group'=>$f['age_group']??$r['age_group'],
'notes'=>trim($f['notes']??''),
];
$topicRaw=$f['topic']??'';
$allowed['topic']=($topicRaw==='__other__')?trim($f['topic_other']??''):trim($topicRaw);
if(!$allowed['contact_name']||!$allowed['phone']) return [false,'Заполните контактное лицо и телефон.'];
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
'phone'=>trim($f['phone']??''),
'participants'=>trim($f['participants']??''),
'age_group'=>$f['age_group']??$r['age_group'],
'notes'=>trim($f['notes']??''),
];
$topicRaw=$f['topic']??'';
$allowed['topic']=($topicRaw==='__other__')?trim($f['topic_other']??''):trim($topicRaw);
if(!$allowed['contact_name']||!$allowed['phone']) return [false,'Заполните контактное лицо и телефон.'];
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
function is_candidate($rid,$sid){ $st=db()->prepare("SELECT id FROM request_speakers WHERE request_id=? AND speaker_user_id=?"); $st->execute([$rid,$sid]); return (bool)$st->fetch(); }
function get_rs_row($rid,$sid){ $st=db()->prepare("SELECT * FROM request_speakers WHERE request_id=? AND speaker_user_id=?"); $st->execute([$rid,$sid]); return $st->fetch()?:null; }
function req_label($rid){ $rows=req_speaker_rows($rid); $conf=array_filter($rows,fn($x)=>$x['confirmed']); if($conf) return implode(', ',array_map(function($x){ $org=speaker_org_name($x['speaker_user_id']); return $x['display_name'].($org!==''?' ('.$org.')':''); },$conf)); if($rows) return 'кандидатов: '.count($rows); return 'не выбран'; }
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
if(($s1-120) < $e2 && $s2 < ($e1+120)) return true;
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
if($claimScope!==null && !in_array((int)$s['fund_user_id'],$claimScope,true)) continue;
$g=array_filter(explode(',',$s['age_groups']));
if($r['age_group'] && $g && !in_array($r['age_group'],$g)) continue;
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
function delete_request($r,$actor){
if($actor['role']!=='school'||(int)$r['school_user_id']!==(int)$actor['id']) return [false,'Нет доступа.'];
if($r['status']!=='new') return [false,'Удалить можно только заявку на этапе «Новая».'];
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
if(speaker_conflicts($sid,$r)) return [false,'Спикер занят в другом учреждении в пределах 2 часов от этого урока.'];
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
if(speaker_conflicts($sid,$r)) return [false,'Спикер занят в другом учреждении в пределах 2 часов от этого урока.'];
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
function mark_done($r){ if($r['status']!=='prep') return [false,'Урок можно отметить проведённым после подтверждения готовности.']; upd($r['id'],['status'=>'done']); log_action(current_user()['id']??null,'operator','mark_done','requests',$r['id'],"Отмечен как проведённый"); return [true,'Отмечено как «Проведён».']; }
function close_request($r){ if($r['status']!=='done') return [false,'Закрыть можно только проведённый урок.']; if(trim((string)($r['fb_app_comment']??''))===''){ return [false,'Заявка закрывается после отзыва заявителя.']; } upd($r['id'],['status'=>'closed','closed_at'=>now()]); log_action(current_user()['id']??null,'operator','close','requests',$r['id'],"Заявка закрыта"); return [true,'Заявка закрыта.']; }
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
function list_materials($type=null){
if($type){
$st=db()->prepare("SELECT m.*, u.display_name uploader FROM materials m LEFT JOIN users u ON u.id=m.uploaded_by WHERE m.type=? ORDER BY m.created_at DESC");
$st->execute([$type]); return $st->fetchAll();
}
$st=db()->query("SELECT m.*, u.display_name uploader FROM materials m LEFT JOIN users u ON u.id=m.uploaded_by ORDER BY m.created_at DESC");
return $st->fetchAll();
}
function get_material($id){
$st=db()->prepare("SELECT * FROM materials WHERE id=?"); $st->execute([$id]); return $st->fetch()?:null;
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
if($sp&&!empty($sp['email'])) $speakerContacts[]=['email'=>$sp['email'],'name'=>$sp['display_name']?:'спикер'];
}
$subject=''; $body='';
switch($type){
case 'confirmed':
$subject="[$site] Состав по заявке {$r['code']} подтверждён";
$body="Здравствуйте!\r\n\r\nПо заявке {$r['code']} от «{$schoolName}» подтверждён состав спикеров.\r\nДата урока: {$r['udate']} {$r['utime']}".(!empty($r['utime_end'])?"–{$r['utime_end']}":'')."\r\nОрганизация: {$r['org']}\r\nСостав:\r\n";
foreach($confirmed as $row) $body.="  • {$row['display_name']}\r\n";
$body.="\r\nПодробнее: $reqUrl\r\n\r\nЭто автоматическое уведомление.\r\n";
if($schoolEmail) @send_smtp_email($schoolEmail,$schoolName,$subject,$body);
foreach($speakerContacts as $sp) @send_smtp_email($sp['email'],$sp['name'],$subject,$body);
break;
case 'replaced':
$oldName=$extra['old_name']??''; $newName=$extra['new_name']??''; $reason=$extra['reason']??'';
$subject="[$site] Замена спикера в заявке {$r['code']}";
$body="Здравствуйте!\r\n\r\nВ заявке {$r['code']} («{$schoolName}») произведена замена спикера.\r\nДата урока: {$r['udate']} {$r['utime']}\r\nБыло: $oldName\r\nСтало: $newName\r\n";
if($reason!=='') $body.="Причина: $reason\r\n";
$body.="\r\nПодробнее: $reqUrl\r\n\r\nЭто автоматическое уведомление.\r\n";
if($schoolEmail) @send_smtp_email($schoolEmail,$schoolName,$subject,$body);
$newSid=(int)($extra['new_sid']??0);
if($newSid){ $newSp=find_user_by_id($newSid); if($newSp&&!empty($newSp['email'])) @send_smtp_email($newSp['email'],$newSp['display_name']?:'спикер',$subject,$body); }
break;
case 'not_held':
$reason=$extra['reason']??'не указана';
$subject="[$site] Урок по заявке {$r['code']} не состоялся";
$body="Здравствуйте!\r\n\r\nУрок по заявке {$r['code']} от «{$schoolName}» не состоялся.\r\nДата: {$r['udate']} {$r['utime']}\r\nПричина: $reason\r\n";
$body.="\r\nПодробнее: $reqUrl\r\n\r\nЭто автоматическое уведомление.\r\n";
foreach($speakerContacts as $sp) @send_smtp_email($sp['email'],$sp['name'],$subject,$body);
$ops=db()->query("SELECT email, display_name FROM users WHERE role='operator' AND email<>'' AND active=1")->fetchAll();
foreach($ops as $op) @send_smtp_email($op['email'],$op['display_name']?:'оператор',$subject,$body);
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
function sort_requests($rows,$key,$dir='asc'){
if(!$key||!$rows) return $rows;
$mul=$dir==='desc'?-1:1;
usort($rows,function($a,$b) use($key,$mul){
switch($key){
case 'code': $cmp=strnatcasecmp($a['code']??'',$b['code']??''); break;
case 'org': $cmp=strcasecmp($a['org']??'',$b['org']??''); break;
case 'date': $cmp=strcmp(($a['udate']??'').' '.($a['utime']??''),($b['udate']??'').' '.($b['utime']??'')); break;
case 'status': $cmp=sort_status_rank($a['status']??'')<=>sort_status_rank($b['status']??''); break;
default: $cmp=0;
}
return $cmp*$mul;
});
return $rows;
}
// ========== АРХИВАЦИЯ ==========
function archive_old_requests($days=30){
$cutoff=date('Y-m-d H:i:s',strtotime("-$days days"));
$st=db()->prepare("UPDATE requests SET archived=1, archived_at=? WHERE status='closed' AND archived=0 AND COALESCE(closed_at, fb_app_at, created_at) < ?");
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
function get_calendar_events($month=null,$year=null,$user=null){
if(!$month) $month=date('m'); if(!$year) $year=date('Y');
$from=sprintf('%04d-%02d-01',(int)$year,(int)$month); $to=date('Y-m-t',strtotime($from));
$role=$user['role']??'operator'; $uid=$user['id']??0;
if($role==='school'){ $st=db()->prepare("SELECT * FROM requests WHERE udate BETWEEN ? AND ? AND school_user_id=? AND (archived=0 OR archived IS NULL) ORDER BY udate,utime"); $st->execute([$from,$to,$uid]); }
elseif($role==='speaker'){ $st=db()->prepare("SELECT r.* FROM requests r JOIN request_speakers rs ON rs.request_id=r.id WHERE r.udate BETWEEN ? AND ? AND rs.speaker_user_id=? AND rs.confirmed=1 AND (r.archived=0 OR r.archived IS NULL) ORDER BY r.udate,r.utime"); $st->execute([$from,$to,$uid]); }
elseif($role==='fund'){ $st=db()->prepare("SELECT * FROM requests WHERE udate BETWEEN ? AND ? AND id IN (SELECT request_id FROM request_speakers) AND (archived=0 OR archived IS NULL) ORDER BY udate,utime"); $st->execute([$from,$to]); }
else { $og=($user['op_group']??'')==='school'?" AND op_group='school'":""; $st=db()->prepare("SELECT * FROM requests WHERE udate BETWEEN ? AND ? AND (archived=0 OR archived IS NULL)$og ORDER BY udate,utime"); $st->execute([$from,$to]); }
$rows=$st->fetchAll();
foreach($rows as &$r){ $r['speakers']=implode(', ', array_map(fn($x)=>$x['display_name'], req_confirmed_rows($r['id']))); }
return $rows;
}
// ---------- показатели ----------
function list_app_reviews($opGroup=null){
$sql="SELECT * FROM requests WHERE (archived=0 OR archived IS NULL) AND fb_app_comment IS NOT NULL AND fb_app_comment<>''"; $p=[];
if($opGroup){ $sql.=' AND op_group=?'; $p[]=$opGroup; }
$sql.=' ORDER BY fb_app_at DESC';
$st=db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}
function list_speaker_reviews($opGroup=null){
$sql="SELECT rs.*, r.code, r.org, r.topic, r.op_group, r.archived, u.display_name speaker_name FROM request_speakers rs JOIN requests r ON r.id=rs.request_id JOIN users u ON u.id=rs.speaker_user_id WHERE (r.archived=0 OR r.archived IS NULL) AND rs.vet_comment IS NOT NULL AND rs.vet_comment<>''"; $p=[];
if($opGroup){ $sql.=' AND r.op_group=?'; $p[]=$opGroup; }
$sql.=' ORDER BY rs.vet_at DESC';
$st=db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}
function kpis($opGroup=null){
$sql='SELECT * FROM requests WHERE (archived=0 OR archived IS NULL)'; $p=[];
if($opGroup){ $sql.=' AND op_group=?'; $p[]=$opGroup; }
$st=db()->prepare($sql); $st->execute($p); $all=$st->fetchAll(); $total=count($all);
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
function fund_scope_requests($fund){
$ids=fund_scope_ids($fund); if(!$ids) return [];
$in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT * FROM requests WHERE (archived=0 OR archived IS NULL) AND claimed_fund_id IN ($in)");
$st->execute($ids); return $st->fetchAll();
}
function list_app_reviews_for_fund($fund){
$ids=fund_scope_ids($fund); if(!$ids) return [];
$in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT * FROM requests WHERE (archived=0 OR archived IS NULL) AND claimed_fund_id IN ($in) AND fb_app_comment IS NOT NULL AND fb_app_comment<>'' ORDER BY fb_app_at DESC");
$st->execute($ids); return $st->fetchAll();
}
function list_speaker_reviews_for_fund($fund){
$ids=fund_scope_ids($fund); if(!$ids) return [];
$in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT rs.*, r.code, r.org, r.topic, u.display_name speaker_name FROM request_speakers rs JOIN requests r ON r.id=rs.request_id JOIN users u ON u.id=rs.speaker_user_id WHERE (r.archived=0 OR r.archived IS NULL) AND rs.vet_comment IS NOT NULL AND rs.vet_comment<>'' AND u.fund_user_id IN ($in) ORDER BY rs.vet_at DESC");
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
if($s==='done') return trim((string)($r['fb_app_comment']??''))==='' ? ['Заявитель','Оставить отзыв об уроке'] : ['Оператор','Закрыть заявку'];
if($s==='closed') return ['—','Заявка закрыта'];
if($s==='cancelled') return ['—','Урок не состоялся'];
return ['—',''];
}
function auto_close_done($days=7){
$cut=date('Y-m-d H:i:s',strtotime("-$days days"));
$st=db()->prepare("SELECT * FROM requests WHERE status='done' AND fb_app_comment IS NOT NULL AND fb_app_comment<>'' AND COALESCE(fb_app_at, created_at) < ?");
$st->execute([$cut]); $n=0;
foreach($st->fetchAll() as $r){ upd($r['id'],['status'=>'closed','closed_at'=>now()]); log_action(null,'system','auto_close','requests',$r['id'],'Автозакрытие через '.$days.' дн. после отзыва'); $n++; }
return $n;
}
function get_action_required_requests($user) {
$role = $user['role'];
$uid = $user['id'];
if ($role === 'school') {
$st = db()->prepare("SELECT * FROM requests WHERE school_user_id = ? AND (archived=0 OR archived IS NULL) AND ((status IN ('confirmed','brief') AND (prep_confirmed_at IS NULL OR prep_confirmed_at='')) OR status='prep' OR (status='done' AND (fb_app_comment IS NULL OR fb_app_comment=''))) ORDER BY udate ASC");
$st->execute([$uid]); return $st->fetchAll();
}
if ($role === 'fund') {
return fund_actionable($user);
}
if ($role === 'operator') {
$scope = (($user['op_group']??'')==='school') ? " AND op_group='school'" : "";
$st = db()->query("SELECT * FROM requests WHERE (archived=0 OR archived IS NULL) AND status='done' AND fb_app_comment IS NOT NULL AND fb_app_comment<>''$scope ORDER BY udate ASC");
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