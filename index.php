<?php
$__https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https')||(($_SERVER['SERVER_PORT']??'')=='443');
// Долгий срок жизни сессии — иначе установленное PWA разлогинивает при сворачивании
// и переключении между приложениями (браузер/ОС может закрыть процесс и потерять
// сессионную куку без явного Max-Age, а сервер — удалить данные сессии по gc_maxlifetime).
$__sess_lifetime=60*60*24*30; // 30 дней
ini_set('session.gc_maxlifetime',(string)$__sess_lifetime);
session_set_cookie_params(['lifetime'=>$__sess_lifetime,'httponly'=>true,'samesite'=>'Lax','secure'=>$__https]); session_start();
require __DIR__.'/lib.php';
set_exception_handler(function($e){
error_log('Необработанное исключение: '.$e->getMessage().' в '.$e->getFile().':'.$e->getLine());
if(!headers_sent()) http_response_code(500);
echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Техническая ошибка</title></head><body style="font-family:sans-serif;max-width:560px;margin:80px auto;text-align:center;color:#1E2128"><h1>Что-то пошло не так</h1><p>Произошла техническая ошибка на сервере. Мы уже знаем о проблеме — попробуйте обновить страницу или зайти позже.</p></body></html>';
exit;
});
set_error_handler(function($errno,$errstr,$errfile,$errline){
if(!(error_reporting() & $errno)) return false;
throw new ErrorException($errstr,0,$errno,$errfile,$errline);
});
init_schema();
$p=$_GET['p']??''; $method=$_SERVER['REQUEST_METHOD'];
function role_home($u){ return $u['role']==='admin' ? '?p=news_admin' : '?p='.$u['role']; }
// ========== БОКОВАЯ ПАНЕЛЬ БЫСТРОГО ДОСТУПА ==========
function sidebar_items($u){
if(!$u){
return [
['О нас','?p=about'],
['Новости','?p=news'],
['Вопросы и ответы','?p=faq'],
['Материалы','?p=materials'],
['Контакты','?p=contacts'],
['Регистрация','?p=register'],
['Вход','?p=login'],
];
}
if($u['role']==='admin') return [['Новости сайта','?p=news_admin']];
if($u['role']==='operator') return [
['Активные','?p=operator'],
['Архив','?p=operator&tab=archive'],
['Модерация заявителей','?p=operator&tab=moderation'],
['Реестр спикеров','?p=operator&tab=speakers'],
['Пользователи','?p=users'],
['Районы','?p=districts'],
['Календарь','?p=calendar'],
['Материалы','?p=materials'],
['Проверка готовности к занятиям','?p=lesson_readiness_admin'],
['Новости сайта','?p=news_admin'],
['Журнал действий','?p=audit'],
['Экспорт заявок (CSV)','?p=export&type=requests'],
['Экспорт спикеров (CSV)','?p=export&type=speakers'],
['Отчёт для департамента (CSV)','?p=export&type=report'],
];
if($u['role']==='fund') return [
['Активные','?p=fund'],
['Архив','?p=fund&tab=archive'],
['Спикеры Фонда','?p=fund&tab=speakers'],
['Управление спикерами','?p=users&role=speaker'],
['Календарь','?p=calendar'],
['Материалы','?p=materials'],
['Журнал действий','?p=audit&user_role=speaker'],
['Экспорт в CSV','?p=export'],
];
if($u['role']==='school') return [
['Кабинет','?p=school'],
['Подать заявку','?p=school&tab=new'],
['Сотрудники','?p=users&role=school'],
['Календарь','?p=calendar'],
['Материалы','?p=materials'],
['Экспорт в CSV','?p=export'],
];
if($u['role']==='speaker') return [
['Кабинет','?p=speaker'],
['Анкеты','?p=speaker_anketas'],
['Моя статистика','?p=speaker_stats'],
['Календарь','?p=calendar'],
['Материалы','?p=materials'],
];
return [];
}
function render_sidebar($u){
$items=sidebar_items($u);
if(!$items) return '';
$curP=$_GET['p']??''; $curTab=$_GET['tab']??'';
$html='<nav class="sidebar-nav">';
foreach($items as [$label,$href]){
parse_str(parse_url($href,PHP_URL_QUERY)?:'',$hq);
$active=(($hq['p']??'')===$curP) && (($hq['tab']??'')===$curTab);
$html.='<a href="'.esc($href).'"'.($active?' class="active"':'').'>'.esc($label).'</a>';
}
$html.='</nav>';
return $html;
}
// ========== ОБРАБОТЧИКИ ДЕЙСТВИЙ ==========
if($method==='POST'){
csrf_check(); $a=$_POST['action']??'';
if($a==='login'){
$loginTry=trim($_POST['login']??'');
$lockedUntil=login_lock_until($loginTry);
if($lockedUntil){ flash_set('Слишком много неудачных попыток входа. Попробуйте снова после '.date('H:i',strtotime($lockedUntil)).'.'); redirect('?p=login'); }
[$ok,$res]=verify_credentials($loginTry,$_POST['pass']??'');
if(!$ok){ record_login_failure($loginTry); flash_set($res); redirect('?p=login'); }
reset_login_failures($loginTry);
unset($_SESSION['uid']); $_SESSION['pending_uid']=$res['id'];
$sent=send_login_otp($res);
flash_set($sent?'Мы отправили код подтверждения на ваш email.':'Не удалось отправить код на email. Попробуйте ещё раз или запросите код повторно.');
redirect('?p=verify_otp');
}
if($a==='resend_otp'){
$pid=(int)($_SESSION['pending_uid']??0); $pu=$pid?find_user_by_id($pid):null;
if(!$pu){ unset($_SESSION['pending_uid']); flash_set('Сессия входа истекла. Войдите заново.'); redirect('?p=login'); }
if(!otp_resend_allowed($pu['id'])){ flash_set('Код уже отправлялся недавно. Подождите немного перед повторной отправкой.'); redirect('?p=verify_otp'); }
$sent=send_login_otp($pu);
flash_set($sent?'Код отправлен повторно.':'Не удалось отправить код. Попробуйте позже.');
redirect('?p=verify_otp');
}
if($a==='verify_otp'){
$pid=(int)($_SESSION['pending_uid']??0); $pu=$pid?find_user_by_id($pid):null;
if(!$pu){ unset($_SESSION['pending_uid']); flash_set('Сессия входа истекла. Войдите заново.'); redirect('?p=login'); }
[$ok,$err]=verify_login_otp($pid,trim($_POST['code']??''));
if(!$ok){ flash_set($err); redirect('?p=verify_otp'); }
unset($_SESSION['pending_uid']);
finalize_login($pu);
redirect(role_home($pu));
}
if($a==='register'){
$role=$_POST['role']??''; $login=trim($_POST['login']??''); $pass=$_POST['pass']??''; $email=trim($_POST['email']??'');
if(strlen($login)<3||strlen($pass)<10){ fail_redirect('Логин от 3 символов, пароль от 10 символов.','?p=register'); }
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ fail_redirect('Укажите корректный email.','?p=register'); }
if(empty($_POST['consent'])){ fail_redirect('Необходимо согласие на обработку персональных данных.','?p=register'); }
$dsel=$_POST['district']??''; $district=($dsel==='__other__')?ensure_district(trim($_POST['district_other']??'')):ensure_district($dsel);
if($district===''){ fail_redirect('Укажите район Тюменской области.','?p=register'); }
$c_name=trim($_POST['contact_name']??''); $c_pos=trim($_POST['position']??''); $c_phone=normalize_phone($_POST['contact_phone']??'');
if($role!=='speaker'){
if($c_name===''||$c_pos===''){ fail_redirect('Заполните ФИО контактного лица и должность.','?p=register'); }
if($c_phone===null){ fail_redirect('Укажите телефон в формате +7XXXXXXXXXX (10 цифр после +7).','?p=register'); }
}
if($role==='school'){
$schoolType=($_POST['school_type']??'owner')==='member'?'member':'owner';
if($schoolType==='member'){
$parentId=(int)($_POST['parent_school_id']??0);
$parentSchool=$parentId?find_user_by_id($parentId):null;
if(!$parentSchool||$parentSchool['role']!=='school'||!$parentSchool['active']||!empty($parentSchool['parent_school_id'])){ fail_redirect('Выберите организацию из списка.','?p=register'); }
[$ok,$res]=create_user('school',$login,$pass,['org_name'=>'','level'=>$parentSchool['level'],'display_name'=>$c_name,'email'=>$email,'district'=>$district,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone,'address'=>'','building'=>'','op_group'=>$parentSchool['op_group']??'','parent_school_id'=>$parentSchool['id']]);
} else {
$c_addr=trim($_POST['address']??''); $c_build=trim($_POST['building']??'');
if(empty($_POST['org_name'])||empty($_POST['level'])){ fail_redirect('Заполните наименование организации и уровень.','?p=register'); }
if($c_addr===''||$c_build===''){ fail_redirect('Укажите адрес местонахождения и номер корпуса.','?p=register'); }
$schoolOg=$_POST['school_op_group']??''; if(!in_array($schoolOg,['school','region'],true)){ fail_redirect('Выберите департамент, к которому относится организация.','?p=register'); }
if(dept_school_reg_limit_reached($schoolOg)){ fail_redirect('Департамент уже получил максимум заявок на регистрацию организаций за сегодня (5). Попробуйте зарегистрироваться завтра.','?p=register'); }
$orgNameIn=trim($_POST['org_name']);
$similar=find_similar_school_org($orgNameIn);
if($similar){
$ackKey='org_warn_'.normalize_org_name($orgNameIn);
if(empty($_SESSION[$ackKey])){
$_SESSION[$ackKey]=1;
fail_redirect('Похоже, организация «'.$similar['org_name'].'» уже зарегистрирована на сайте. Если это ваша организация — вернитесь назад и выберите «сотрудник существующей организации», её сотрудник подтвердит вам доступ. Если это другая, отдельная организация — просто нажмите «Зарегистрироваться» ещё раз.','?p=register');
}
unset($_SESSION[$ackKey]);
}
[$ok,$res]=create_user('school',$login,$pass,['org_name'=>$orgNameIn,'level'=>$_POST['level'],'display_name'=>$orgNameIn,'email'=>$email,'district'=>$district,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone,'address'=>$c_addr,'building'=>$c_build,'op_group'=>$schoolOg]);
}
}
elseif($role==='speaker'){
if(empty($_POST['display_name'])){ fail_redirect('Укажите ФИО.','?p=register'); }
$phone=normalize_phone($_POST['phone']??''); if($phone===null){ fail_redirect('Укажите телефон в формате +7XXXXXXXXXX (10 цифр после +7).','?p=register'); }
$fid=(int)($_POST['fund_user_id']??0); if(!$fid || !find_user_by_id($fid) || find_user_by_id($fid)['role']!=='fund'){ fail_redirect('Выберите фонд.','?p=register'); }
if(fund_speaker_reg_limit_reached($fid)){ fail_redirect('Этот фонд уже получил максимум заявок от новых спикеров за сегодня (4). Попробуйте зарегистрироваться завтра.','?p=register'); }
$groups=implode(',',array_keys($_POST['groups']??[])); if($groups===''){ fail_redirect('Выберите хотя бы одну возрастную группу.','?p=register'); }
$crimAnswer=$_POST['criminal_record']??''; if(!in_array($crimAnswer,['yes','no'],true)){ fail_redirect('Ответьте на вопрос о судимости.','?p=register'); }
$crimCertDate=trim($_POST['criminal_cert_date']??'');
if($crimAnswer==='no'){
if($crimCertDate===''){ fail_redirect('Укажите дату справки о несудимости.','?p=register'); }
if(empty($_FILES['criminal_cert']['name'])){ fail_redirect('Загрузите справку о несудимости.','?p=register'); }
}
[$certOk,$certErr,$crimCertPath]=validate_uploaded_criminal_cert($_FILES['criminal_cert']??[]);
if(!$certOk){ fail_redirect($certErr,'?p=register'); }
[$ok,$res]=create_user('speaker',$login,$pass,['display_name'=>trim($_POST['display_name']),'groups'=>$groups,'email'=>$email,'district'=>$district,'phone'=>$phone,'fund_user_id'=>$fid]);
if($ok){
set_speaker_criminal_record(['id'=>$res],$crimAnswer,$crimCertDate,$crimCertPath);
if($crimAnswer==='yes' && !empty($email)) @send_smtp_email($email,trim($_POST['display_name']),'[Уроки мужества 72] Уведомление о допуске к работе с детьми',criminal_record_notice());
}
}
elseif($role==='fund'){ $fdn=trim($_POST['fund_display_name']??''); if($fdn===''){ fail_redirect('Укажите наименование Фонда.','?p=register'); } $ftype=$_POST['fund_type']??'head'; if($ftype==='sub'){ $pfid=(int)($_POST['parent_fund_id']??0); $pf=$pfid?find_user_by_id($pfid):null; if(!$pf || $pf['role']!=='fund'){ fail_redirect('Подразделение должно указать головной фонд.','?p=register'); } [$ok,$res]=create_user('fund',$login,$pass,['display_name'=>$fdn,'email'=>$email,'district'=>$district,'parent_fund_id'=>$pfid,'active'=>0,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone]); } else { if(($_POST['fund_code']??'')!==cfg()['fund_signup_code']){ fail_redirect('Неверный код регистрации Фонда. Если у вас его нет, напишите на '.cfg()['support_email'].'.','?p=register'); } [$ok,$res]=create_user('fund',$login,$pass,['display_name'=>$fdn,'email'=>$email,'district'=>$district,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone]); } }
elseif($role==='operator'){ $odn=trim($_POST['op_display_name']??''); if($odn===''){ fail_redirect('Укажите наименование оператора.','?p=register'); } if(($_POST['op_code']??'')!==cfg()['operator_signup_code']){ fail_redirect('Неверный код регистрации оператора. Если у вас его нет, напишите на '.cfg()['support_email'].'.','?p=register'); } $og=$_POST['op_group']??''; if(!in_array($og,['school','region'])){ fail_redirect('Выберите департамент.','?p=register'); } [$ok,$res]=create_user('operator',$login,$pass,['display_name'=>$odn,'email'=>$email,'op_group'=>$og,'district'=>$district,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone]); }
else { fail_redirect('Выберите роль.','?p=register'); }
if(!$ok){ fail_redirect($res,'?p=register'); }
flash_set($role==='speaker'?'Регистрация принята. Доступ откроется после подтверждения вашим фондом.':($role==='school'?($schoolType==='member'?'Регистрация принята. Доступ откроется после подтверждения представителем вашей организации.':'Регистрация принята. Доступ откроется после подтверждения профильным департаментом.'):($role==='fund'&&($_POST['fund_type']??'head')==='sub'?'Регистрация подразделения принята. Доступ откроется после подтверждения головным фондом.':'Регистрация успешна. Войдите.'))); redirect('?p=login');
}
if($a==='logout'){ session_destroy(); redirect('?p=login'); }
if($a==='impersonate_start'){
require_login(); $u=current_user();
if(is_impersonating()||!can_impersonate($u)){ http_response_code(403); exit('Нет доступа.'); }
$target=find_user_by_id((int)($_POST['target_id']??0));
if(!$target){ flash_set('Пользователь не найден.'); redirect('?p=impersonate'); }
if((int)$target['id']===(int)$u['id']){ flash_set('Это ваша учётная запись.'); redirect('?p=impersonate'); }
if(can_impersonate($target)){ flash_set('Нельзя войти под другим суперпользователем.'); redirect('?p=impersonate'); }
$_SESSION['impersonator_id']=$u['id'];
$_SESSION['uid']=$target['id'];
session_regenerate_id(true);
log_action($u['id'],$u['role'],'impersonate_start','users',$target['id'],'Вход от имени пользователя: '.($target['display_name']?:$target['org_name']?:$target['login']).' ('.$target['role'].')');
redirect(role_home($target));
}
if($a==='impersonate_stop'){
if(!is_impersonating()){ redirect('?p=login'); }
$adminId=(int)$_SESSION['impersonator_id']; $targetId=(int)($_SESSION['uid']??0);
unset($_SESSION['impersonator_id']); $_SESSION['uid']=$adminId;
session_regenerate_id(true);
log_action($adminId,'operator','impersonate_stop','users',$targetId,'Возврат из режима «войти как»');
redirect('?p=impersonate');
}
if($a==='update_profile'){
require_login(); $u=current_user();
$isSchoolMember=$u['role']==='school' && !empty($u['parent_school_id']);
$dn=trim($_POST['display_name']??''); $em=trim($_POST['email']??''); $np=$_POST['newpass']??'';
if(!$isSchoolMember && $dn===''){ flash_set('Укажите наименование / ФИО.'); redirect('?p=profile'); }
if(!filter_var($em,FILTER_VALIDATE_EMAIL)){ flash_set('Укажите корректный email.'); redirect('?p=profile'); }
$set=['email=?']; $par=[$em];
if(!$isSchoolMember){ $set[]='display_name=?'; $par[]=$dn; }
$dsel=$_POST['district']??''; $dist=($dsel==='__other__')?ensure_district(trim($_POST['district_other']??'')):ensure_district($dsel);
if($dist!==''){ $set[]='district=?'; $par[]=$dist; }
if($u['role']==='speaker'){ $spPhone=normalize_phone($_POST['phone']??''); if($spPhone===null){ flash_set('Укажите телефон в формате +7XXXXXXXXXX (10 цифр после +7).'); redirect('?p=profile'); } $set[]='phone=?'; $par[]=$spPhone; }
if($u['role']==='speaker' && isset($_POST['fund_user_id'])){ $fid=(int)$_POST['fund_user_id']; if($fid>0){ $f=find_user_by_id($fid); if(!$f || $f['role']!=='fund'){ flash_set('Фонд не найден.'); redirect('?p=profile'); } $set[]='fund_user_id=?'; $par[]=$fid; } }
if($u['role']==='speaker'){
$set[]='sp_rank=?'; $par[]=trim($_POST['sp_rank']??'');
$set[]='sp_branch=?'; $par[]=trim($_POST['sp_branch']??'');
$set[]='sp_awards=?'; $par[]=trim($_POST['sp_awards']??'');
$set[]='sp_achievements=?'; $par[]=trim($_POST['sp_achievements']??'');
$set[]='sp_hobbies=?'; $par[]=trim($_POST['sp_hobbies']??'');
$set[]='sp_bio=?'; $par[]=trim($_POST['sp_bio']??'');
if(!empty($_FILES['photo']['name'])){
if($_FILES['photo']['error']!==UPLOAD_ERR_OK){ flash_set('Ошибка загрузки фото.'); redirect('?p=profile'); }
if($_FILES['photo']['size']>PHOTO_MAX_BYTES){ flash_set('Фото слишком большое (максимум 8 МБ).'); redirect('?p=profile'); }
$ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
if(!in_array($ext,['jpg','jpeg','png','webp'],true)){ flash_set('Фото должно быть в формате JPG, PNG или WEBP.'); redirect('?p=profile'); }
$imginfo=@getimagesize($_FILES['photo']['tmp_name']);
if(!$imginfo||!in_array($imginfo['mime'],['image/jpeg','image/png','image/webp'],true)){ flash_set('Файл повреждён или не является настоящим изображением JPG/PNG/WEBP.'); redirect('?p=profile'); }
$stored=bin2hex(random_bytes(16)).'.'.$ext;
$dir=__DIR__.'/uploads/photos'; if(!is_dir($dir)) mkdir($dir,0755,true);
if(!move_uploaded_file($_FILES['photo']['tmp_name'],$dir.'/'.$stored)){ flash_set('Не удалось сохранить фото.'); redirect('?p=profile'); }
if(!empty($u['photo_path'])){ $old=__DIR__.'/uploads/photos/'.$u['photo_path']; if(is_file($old)) @unlink($old); }
$set[]='photo_path=?'; $par[]=$stored;
}
$crimAnswer=$_POST['criminal_record']??'';
if(in_array($crimAnswer,['yes','no'],true)){
[$certOk,$certErr,$crimCertPath]=validate_uploaded_criminal_cert($_FILES['criminal_cert']??[]);
if(!$certOk){ flash_set($certErr); redirect('?p=profile'); }
$prevCrim=$u['criminal_record']??'';
[$crimOk,$crimErr]=set_speaker_criminal_record($u,$crimAnswer,$_POST['criminal_cert_date']??'',$crimCertPath);
if(!$crimOk){ flash_set($crimErr); redirect('?p=profile'); }
if($crimAnswer==='yes' && $prevCrim!=='yes' && !empty($em)) @send_smtp_email($em,$dn?:'спикер','[Уроки мужества 72] Уведомление о допуске к работе с детьми',criminal_record_notice());
}
}
if(in_array($u['role'],['school','fund','operator'],true)){
$cn=trim($_POST['contact_name']??''); $cp=trim($_POST['position']??''); $ph=normalize_phone($_POST['phone']??'');
if($cn===''||$cp===''){ flash_set('Заполните ФИО контактного лица и должность.'); redirect('?p=profile'); }
if($ph===null){ flash_set('Укажите телефон в формате +7XXXXXXXXXX (10 цифр после +7).'); redirect('?p=profile'); }
$set[]='contact_name=?'; $par[]=$cn; $set[]='position=?'; $par[]=$cp; $set[]='phone=?'; $par[]=$ph;
}
if($u['role']==='school' && !$isSchoolMember){
$set[]='org_name=?'; $par[]=$dn;
$addr=trim($_POST['address']??''); $build=trim($_POST['building']??'');
if($addr===''||$build===''){ flash_set('Укажите адрес местонахождения и номер корпуса.'); redirect('?p=profile'); }
$set[]='address=?'; $par[]=$addr; $set[]='building=?'; $par[]=$build;
$schoolOg=$_POST['school_op_group']??''; if(!in_array($schoolOg,['school','region'],true)){ flash_set('Выберите департамент.'); redirect('?p=profile'); }
$set[]='op_group=?'; $par[]=$schoolOg;
}
if(strlen($np)>=10){ $set[]='pass_hash=?'; $par[]=password_hash($np,PASSWORD_DEFAULT); }
elseif($np!==''){ flash_set('Пароль от 10 символов.'); redirect('?p=profile'); }
$par[]=$u['id'];
db()->prepare("UPDATE users SET ".implode(',',$set)." WHERE id=?")->execute($par);
log_action($u['id'],$u['role'],'update_profile','users',$u['id'],'Обновлены данные профиля');
flash_set('Данные сохранены.'.(strlen($np)>=5?' Пароль изменён.':'')); redirect('?p=profile');
}
if($a==='push_subscribe'){
require_login(); $u=current_user();
$endpoint=trim($_POST['endpoint']??''); $p256dh=trim($_POST['p256dh']??''); $auth=trim($_POST['auth']??'');
if($endpoint!=='' && $p256dh!=='' && $auth!==''){ save_push_subscription($u['id'],$endpoint,$p256dh,$auth); }
redirect('?p=profile');
}
if($a==='push_unsubscribe'){
require_login();
$endpoint=trim($_POST['endpoint']??'');
if($endpoint!=='') remove_push_subscription($endpoint);
redirect('?p=profile');
}
if($a==='request_reset'){
$email=trim($_POST['email']??'');
// Ответ одинаковый независимо от того, найден email или нет, и был ли запрос
// отклонён лимитом частоты — иначе по разнице сообщений можно перебором
// узнать, какие email зарегистрированы в системе.
if($email!=='' && reset_request_allowed($email)){
[$ok,$data]=create_reset_token($email);
if($ok){
$u=$data['user']; $token=$data['token'];
$site=cfg()['site_title'];
$resetUrl="https://урокмужества72.рф?p=reset_password&token=$token";
$body="Здравствуйте, {$u['display_name']}!\r\n\r\nВы запросили сброс пароля в системе \"$site\".\r\n\r\nПерейдите по ссылке (действует 1 час):\r\n$resetUrl\r\n\r\nЕсли вы не запрашивали сброс — проигнорируйте это письмо.\r\n";
send_smtp_email($u['email'],$u['display_name'],"[$site] Сброс пароля",$body);
}
}
flash_set('Если такой email зарегистрирован в системе, письмо с инструкцией по сбросу пароля отправлено.');
redirect('?p=login');
}
if($a==='reset_password'){
$token=$_POST['token']??''; $newpass=$_POST['newpass']??''; $newpass2=$_POST['newpass2']??'';
if(strlen($newpass)<10){ flash_set('Пароль от 10 символов.'); redirect('?p=reset_password&token='.urlencode($token)); }
if($newpass!==$newpass2){ flash_set('Пароли не совпадают.'); redirect('?p=reset_password&token='.urlencode($token)); }
[$ok,$msg]=reset_password($token,$newpass);
flash_set($msg); redirect('?p=login');
}
if($a==='admin_reset_password'){
require_login(); $u=current_user();
$target_id=(int)($_POST['target_id']??0); $newpass=$_POST['newpass']??'';
if(strlen($newpass)<10){ flash_set('Пароль от 10 символов.'); redirect('?p=users'); }
[$ok,$msg]=admin_reset_password($u,$target_id,$newpass);
flash_set($msg); redirect('?p=users');
}
if($a==='delete_user'){
require_login(); $u=current_user();
[$ok,$msg]=delete_user($u,(int)($_POST['target_id']??0));
flash_set($msg); redirect('?p=users');
}
if($a==='create_admin_account'){
require_role('operator'); $u=current_user();
$login=trim($_POST['login']??''); $pass=$_POST['pass']??''; $dn=trim($_POST['display_name']??''); $email=trim($_POST['email']??'');
if(strlen($login)<3||strlen($pass)<10){ flash_set('Логин от 3 символов, пароль от 10 символов.'); redirect('?p=users'); }
if($dn===''){ flash_set('Укажите ФИО.'); redirect('?p=users'); }
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ flash_set('Укажите корректный email.'); redirect('?p=users'); }
[$ok,$res]=create_user('admin',$login,$pass,['display_name'=>$dn,'email'=>$email,'active'=>1]);
if(!$ok){ flash_set($res); redirect('?p=users'); }
flash_set('Учётная запись администратора сайта создана.'); redirect('?p=users&role=admin');
}
if($a==='save_district_neighbors'){ require_role('operator'); update_district_neighbors(trim($_POST['name']??''), explode(',',$_POST['neighbors']??'')); flash_set('Соседние районы сохранены.'); redirect('?p=districts'); }
if($a==='add_district_admin'){ require_role('operator'); $n=ensure_district(trim($_POST['name']??'')); flash_set($n?('Район добавлен: '.$n):'Укажите название района.'); redirect('?p=districts'); }
require_login(); $u=current_user();
if($a==='submit_request'){
require_role('school');
if(empty($u['active'])){ flash_set('Ваша учётная запись ещё не подтверждена.'); redirect('?p=school'); }
$eff=school_effective($u);
$f=['level'=>$eff['level'],'contact_name'=>trim($_POST['contact_name']??''),'phone'=>normalize_phone($_POST['phone']??''),'email'=>trim($_POST['email']??''),
'udate'=>$_POST['udate']??'','utime'=>$_POST['utime']??'','utime_end'=>$_POST['utime_end']??'','age_group'=>$_POST['age_group']??'','participants'=>trim($_POST['participants']??''),
'speakers_needed'=>(int)($_POST['speakers_needed']??1),'topic'=>trim($_POST['topic']??''),'notes'=>trim($_POST['notes']??'')];
if(!$f['contact_name']||!$f['udate']||!$f['utime']||!$f['utime_end']||!$f['age_group']||empty($_POST['consent'])){ fail_redirect('Заполните обязательные поля и согласие.','?p=school&tab=new'); }
if($f['phone']===null){ fail_redirect('Укажите телефон в формате +7XXXXXXXXXX (10 цифр после +7).','?p=school&tab=new'); }
if($f['udate']<min_create_date()){ fail_redirect('Дату урока можно выбрать не ранее чем через 10 дней.','?p=school&tab=new'); }
if(!valid_slot($f['utime'])||!valid_slot($f['utime_end'])){ fail_redirect('Время — с 8:00 до 18:00, минуты кратны 10.','?p=school&tab=new'); }
if(tmin($f['utime_end'])<=tmin($f['utime'])){ fail_redirect('Время окончания должно быть позже времени начала.','?p=school&tab=new'); }
if($f['topic']!=='' && !in_array($f['topic'],material_topics(),true)){ fail_redirect('Выберите тему из списка.','?p=school&tab=new'); }
if(school_daily_limit_reached($u)){ fail_redirect('Ваша организация уже подала максимум заявок на сегодня (6). Попробуйте завтра.','?p=school&tab=new'); }
$dsid=(int)($_POST['desired_speaker_id']??0);
if($dsid){ $dsp=find_user_by_id($dsid); if(!$dsp||$dsp['role']!=='speaker'||!$dsp['active']) $dsid=0; }
$f['desired_speaker_id']=$dsid;
$id=create_request($eff,$f); flash_set('Заявка отправлена всем фондам. Как только фонд назначит спикера, вы увидите его здесь.'); redirect('?p=request&id='.$id);
}
// ========== РЕДАКТИРОВАНИЕ ЗАЯВКИ ==========
if($a==='update_request'){
require_role('school');
$rid=(int)($_POST['id']??0); $r=$rid?get_request($rid):null;
if(!$r){ flash_set('Заявка не найдена.'); redirect('?p=school'); }
if(!school_owns_request($u,$r)){ flash_set('Нет доступа.'); redirect('?p=school'); }
if(!in_array($r['status'],['new','chosen','confirmed'])){ flash_set('Редактирование доступно только на этапах «Новая», «Спикеры выбраны» и «Состав подтверждён Фондом».'); redirect('?p=request&id='.$rid); }
[$ok,$msg]=update_request($r,$_POST,$u);
flash_set($msg); redirect('?p=request&id='.$rid);
}
// ========== РЕДАКТИРОВАНИЕ ЗАЯВКИ ФОНДОМ ==========
if($a==='fund_edit_request'){
require_role('fund');
$rid=(int)($_POST['id']??0); $r=$rid?get_request($rid):null;
if(!$r){ flash_set('Заявка не найдена.'); redirect('?p=fund'); }
[$ok,$msg]=fund_edit_request($u,$r,$_POST);
flash_set($msg);
redirect('?p=request&id='.$rid);
}
if($a==='approve_subfund'){ require_role('fund'); db()->prepare("UPDATE users SET active=1 WHERE id=? AND role='fund' AND parent_fund_id=?")->execute([(int)($_POST['uid']??0),$u['id']]); flash_set('Подразделение подтверждено.'); redirect('?p=fund'); }
if($a==='approve_speaker'){ require_role('fund'); db()->prepare("UPDATE users SET active=1, age_groups=? WHERE id=? AND role='speaker' AND fund_user_id=?")->execute([implode(',',array_keys($_POST['groups']??[])),(int)$_POST['uid'],$u['id']]); flash_set('Спикер подтверждён.'); redirect('?p=fund&tab=speakers'); }
if($a==='reject_speaker'){ require_role('fund'); [$ok,$msg]=reject_speaker($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=fund&tab=speakers'); }
if($a==='approve_applicant'){ require_role('operator'); [$ok,$msg]=approve_applicant($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=operator&tab=moderation'); }
if($a==='reject_applicant'){ require_role('operator'); [$ok,$msg]=reject_applicant($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=operator&tab=moderation'); }
if($a==='approve_school_member'){ require_role('school'); [$ok,$msg]=approve_school_member($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=school'); }
if($a==='reject_school_member'){ require_role('school'); [$ok,$msg]=reject_school_member($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=school'); }
if($a==='transfer_school_head'){ require_role('school'); [$ok,$msg]=transfer_school_head($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=users&role=school'); }
if($a==='add_unavailable'){ require_role('speaker'); [$ok,$msg]=add_unavailable_period($u,$_POST['date_from']??'',$_POST['date_to']??'',$_POST['reason']??''); flash_set($msg); redirect('?p=speaker'); }
if($a==='remove_unavailable'){ require_role('speaker'); [$ok,$msg]=remove_unavailable_period($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=speaker'); }
if($a==='submit_readiness'){
require_role('speaker');
$blockKey=$_POST['block_key']??'';
[$ok,$res]=submit_readiness($u,$blockKey,$_POST['answers']??[],$_POST['final_answer']??'');
if(!$ok){ flash_set($res); redirect('?p=speaker'); }
$meta=readiness_status_meta($res['status']);
flash_set('Анкета отправлена: '.$res['score'].' из 20 — «'.esc($meta['label']).'». '.esc($meta['desc']));
redirect('?p=speaker');
}
if($a==='submit_psych_readiness'){
require_role('speaker');
$ageGroup=$_POST['age_group']??'';
[$ok,$res]=submit_psych_readiness($u,$ageGroup,$_POST['answers']??[]);
if(!$ok){ flash_set($res); redirect('?p=speaker'); }
$meta=psych_readiness_status_meta($res['status']);
flash_set('Анкета отправлена: '.$res['score'].' из 60 — «'.esc($meta['label']).'». Результат виден фонду как ориентир, решение о допуске остаётся за ним.');
redirect('?p=speaker');
}
if($a==='save_lesson_readiness_question'){
require_role('operator');
$topic=trim($_POST['topic']??''); $dimension=$_POST['dimension']??'';
$question=trim($_POST['question']??'');
$opts=['a'=>trim($_POST['option_a']??''),'b'=>trim($_POST['option_b']??''),'c'=>trim($_POST['option_c']??''),'d'=>trim($_POST['option_d']??'')];
$correct=$_POST['correct_option']??'';
$back='?p=lesson_readiness_admin&topic='.urlencode($topic);
if($topic===''){ flash_set('Укажите тему.'); redirect('?p=lesson_readiness_admin'); }
if($question===''){ flash_set('Укажите текст вопроса.'); redirect($back); }
if($opts['a']===''||$opts['b']===''||$opts['c']===''||$opts['d']===''){ flash_set('Заполните все 4 варианта ответа.'); redirect($back); }
if(!in_array($correct,['a','b','c','d'],true)){ flash_set('Отметьте правильный вариант ответа.'); redirect($back); }
[$ok,$msg]=set_lesson_readiness_question($topic,$dimension,$question,$opts,$correct,$u['id']);
flash_set($msg); redirect($back);
}
if($a==='submit_lesson_readiness'){
require_role('speaker');
$rid=(int)($_POST['request_id']??0); $r=$rid?get_request($rid):null;
if(!$r){ flash_set('Заявка не найдена.'); redirect('?p=speaker'); }
$row=get_rs_row($rid,$u['id']);
if(!$row||!$row['confirmed']){ flash_set('Вы не входите в утверждённый состав по этой заявке.'); redirect('?p=speaker'); }
$topic=trim((string)($r['topic']??''));
[$ok,$res]=submit_lesson_readiness($u,$topic,$rid,$_POST['answers']??[]);
if(!$ok){ flash_set($res); redirect('?p=request&id='.$rid); }
flash_set($res['passed']
? 'Проверка готовности пройдена: '.$res['score'].' из '.$res['total'].'.'
: 'Проверка готовности не пройдена: '.$res['score'].' из '.$res['total'].'. Изучите материалы ещё раз и попробуйте снова.');
redirect('?p=request&id='.$rid);
}
if($a==='set_criminal_record'){
require_role('speaker');
$answer=$_POST['criminal_record']??'';
$certDate=$_POST['criminal_cert_date']??'';
[$certOk,$certErr,$certPath]=validate_uploaded_criminal_cert($_FILES['criminal_cert']??[]);
if(!$certOk){ flash_set($certErr); redirect('?p=speaker_criminal_check'); }
[$ok,$res]=set_speaker_criminal_record($u,$answer,$certDate,$certPath);
if(!$ok){ flash_set($res); redirect('?p=speaker_criminal_check'); }
if($answer==='yes'){
if(!empty($u['email'])) @send_smtp_email($u['email'],$u['display_name']?:'спикер','[Уроки мужества 72] Уведомление о допуске к работе с детьми',criminal_record_notice());
flash_set('Сведения сохранены — подробности ниже.');
} else {
flash_set('Спасибо, сведения сохранены.');
}
redirect('?p=speaker');
}
if($a==='set_speaker_certification'){
require_role(['fund','operator']);
$sp=find_user_by_id((int)($_POST['speaker_id']??0));
$backId=(int)($_POST['speaker_id']??0);
if(!$sp || $sp['role']!=='speaker'){ flash_set('Спикер не найден.'); redirect('?p=speaker_view&id='.$backId); }
[$ok,$msg]=set_speaker_certification($u,$sp,$_POST['age_group']??'',!empty($_POST['on']));
flash_set($msg);
redirect('?p=speaker_view&id='.$backId);
}
if($a==='add_material'){
require_role('operator');
$title=trim($_POST['title']??''); $desc=trim($_POST['description']??''); $type=$_POST['type']??''; $topic=trim($_POST['topic']??''); $link=trim($_POST['link']??'');
if($title===''){ flash_set('Укажите название материала.'); redirect('?p=materials'); }
if(!array_key_exists($type,material_types())){ flash_set('Выберите вид материала.'); redirect('?p=materials'); }
if($topic===''){ flash_set('Укажите тему материала.'); redirect('?p=materials'); }
$filePath=null; $fileName=null; $fileSize=null;
if(!empty($_FILES['file']['name'])){
if($_FILES['file']['error']!==UPLOAD_ERR_OK){ flash_set('Ошибка загрузки файла (возможно, превышен допустимый размер).'); redirect('?p=materials'); }
if($_FILES['file']['size']>MATERIAL_MAX_BYTES){ flash_set('Файл слишком большой (максимум 100 МБ). Оставьте ссылку на файл вместо загрузки.'); redirect('?p=materials'); }
$ext=strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION));
if(!in_array($ext,material_allowed_extensions(),true)){ flash_set('Недопустимый тип файла. Разрешены: '.implode(', ',material_allowed_extensions()).'. Для другого типа — оставьте ссылку.'); redirect('?p=materials'); }
$stored=bin2hex(random_bytes(16)).".$ext";
$dir=__DIR__.'/uploads/materials'; if(!is_dir($dir)) mkdir($dir,0755,true);
if(!move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$stored)){ flash_set('Не удалось сохранить файл.'); redirect('?p=materials'); }
$filePath=$stored; $fileName=$_FILES['file']['name']; $fileSize=$_FILES['file']['size'];
}
if(!$filePath && $link===''){ flash_set('Прикрепите файл или укажите ссылку на материал.'); redirect('?p=materials'); }
add_material($title,$desc,$type,$topic,$link,$filePath,$fileName,$fileSize,$u['id']);
flash_set('Материал добавлен.'); redirect('?p=materials');
}
if($a==='update_material'){
require_role('operator');
$mid=(int)($_POST['id']??0); $m=$mid?get_material($mid):null;
if(!$m){ flash_set('Материал не найден.'); redirect('?p=materials'); }
$title=trim($_POST['title']??''); $desc=trim($_POST['description']??''); $type=$_POST['type']??''; $topic=trim($_POST['topic']??''); $link=trim($_POST['link']??'');
if($title===''){ flash_set('Укажите название материала.'); redirect('?p=material_edit&id='.$mid); }
if(!array_key_exists($type,material_types())){ flash_set('Выберите вид материала.'); redirect('?p=material_edit&id='.$mid); }
if($topic===''){ flash_set('Укажите тему материала.'); redirect('?p=material_edit&id='.$mid); }
$filePath=null; $fileName=null; $fileSize=null;
if(!empty($_FILES['file']['name'])){
if($_FILES['file']['error']!==UPLOAD_ERR_OK){ flash_set('Ошибка загрузки файла.'); redirect('?p=material_edit&id='.$mid); }
if($_FILES['file']['size']>MATERIAL_MAX_BYTES){ flash_set('Файл слишком большой (максимум 100 МБ). Оставьте ссылку на файл вместо загрузки.'); redirect('?p=material_edit&id='.$mid); }
$ext=strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION));
if(!in_array($ext,material_allowed_extensions(),true)){ flash_set('Недопустимый тип файла. Разрешены: '.implode(', ',material_allowed_extensions()).'. Для другого типа — оставьте ссылку.'); redirect('?p=material_edit&id='.$mid); }
$stored=bin2hex(random_bytes(16)).".$ext";
$dir=__DIR__.'/uploads/materials'; if(!is_dir($dir)) mkdir($dir,0755,true);
if(!move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$stored)){ flash_set('Не удалось сохранить файл.'); redirect('?p=material_edit&id='.$mid); }
if(!empty($m['file_path'])){ $old=__DIR__.'/uploads/materials/'.$m['file_path']; if(is_file($old)) @unlink($old); }
$filePath=$stored; $fileName=$_FILES['file']['name']; $fileSize=$_FILES['file']['size'];
}
if(!$filePath && empty($m['file_path']) && $link===''){ flash_set('Прикрепите файл или укажите ссылку на материал.'); redirect('?p=material_edit&id='.$mid); }
update_material($mid,$title,$desc,$type,$topic,$link,$filePath,$fileName,$fileSize);
flash_set('Материал обновлён.'); redirect('?p=materials');
}
if($a==='delete_material'){
require_role('operator');
[$ok,$msg]=delete_material((int)($_POST['id']??0));
flash_set($msg); redirect('?p=materials');
}
if($a==='add_news'){
require_role(['operator','admin']); $u=current_user();
$title=trim($_POST['title']??''); $bodyTxt=trim($_POST['body']??'');
if($title===''||$bodyTxt===''){ flash_set('Заполните заголовок и текст новости.'); redirect('?p=news_edit'); }
$schoolPostUrl=trim($_POST['school_post_url']??'');
if($schoolPostUrl!==''&&!preg_match('~^https?://~i',$schoolPostUrl)){ flash_set('Ссылка на публикацию школы должна начинаться с http:// или https://.'); redirect('?p=news_edit'); }
$newImages=normalize_multi_files($_FILES['images']??[]);
$stored=[];
foreach($newImages as $f){
[$imgOk,$imgErr,$imgStored]=validate_uploaded_news_image($f);
if(!$imgOk){ flash_set($imgErr); redirect('?p=news_edit'); }
$stored[]=$imgStored;
}
[$ok,$newId]=add_news($title,$bodyTxt,$schoolPostUrl,!empty($_POST['published']),$u['id']);
foreach($stored as $path) add_news_image($newId,$path);
flash_set('Новость добавлена.'); redirect('?p=news_admin');
}
if($a==='update_news'){
require_role(['operator','admin']); $u=current_user();
$id=(int)($_POST['id']??0); $n=$id?get_news($id):null;
if(!$n){ flash_set('Новость не найдена.'); redirect('?p=news_admin'); }
$title=trim($_POST['title']??''); $bodyTxt=trim($_POST['body']??'');
if($title===''||$bodyTxt===''){ flash_set('Заполните заголовок и текст новости.'); redirect('?p=news_edit&id='.$id); }
$schoolPostUrl=trim($_POST['school_post_url']??'');
if($schoolPostUrl!==''&&!preg_match('~^https?://~i',$schoolPostUrl)){ flash_set('Ссылка на публикацию школы должна начинаться с http:// или https://.'); redirect('?p=news_edit&id='.$id); }
foreach((array)($_POST['delete_image']??[]) as $imgId) delete_news_image((int)$imgId,$u['id']);
$newImages=normalize_multi_files($_FILES['images']??[]);
$stored=[];
foreach($newImages as $f){
[$imgOk,$imgErr,$imgStored]=validate_uploaded_news_image($f);
if(!$imgOk){ flash_set($imgErr); redirect('?p=news_edit&id='.$id); }
$stored[]=$imgStored;
}
foreach($stored as $path) add_news_image($id,$path);
update_news($id,$title,$bodyTxt,$schoolPostUrl,!empty($_POST['published']),$u['id']);
flash_set('Новость обновлена.'); redirect('?p=news_admin');
}
if($a==='delete_news'){
require_role(['operator','admin']); $u=current_user();
[$ok,$msg]=delete_news((int)($_POST['id']??0),$u['id']);
flash_set($msg); redirect('?p=news_admin');
}
if($a==='delete_request'){
require_role('school');
$rid=(int)($_POST['id']??0); $r=$rid?get_request($rid):null;
if(!$r){ flash_set('Заявка не найдена.'); redirect('?p=school'); }
[$ok,$msg]=delete_request($r,$u);
flash_set($msg); redirect('?p=school');
}
$rid=(int)($_POST['id']??0); $r=$rid?get_request($rid):null;
if($r){
$msg=null; $sid=(int)($_POST['sid']??0);
if($a==='fund_reject_candidate'){ require_role('fund'); $reasonRaw=$_POST['reason']??''; $reasonOther=trim($_POST['reason_other']??''); $reason=($reasonRaw==='__other__')?$reasonOther:$reasonRaw; [$ok,$msg]=fund_reject_candidate($u,$r,$sid,$reason); }
elseif($a==='fund_assign_speaker'){ require_role('fund'); [$ok,$msg]=fund_assign_speaker($u,$r,(int)($_POST['speaker_user_id']??0)); }
elseif($a==='fund_finalize'){ require_role('fund'); [$ok,$msg]=fund_finalize($u,$r); }
elseif($a==='claim_request'){ require_role('fund'); [$ok,$msg]=claim_request($u,$r); }
elseif($a==='release_request'){ require_role('fund'); [$ok,$msg]=release_request($u,$r); }
elseif($a==='fund_return_to_processing'){ require_role('fund'); [$ok,$msg]=fund_return_to_processing($u,$r,$_POST['reason']??''); }
elseif($a==='confirm_prep'){ require_role('school'); if(!school_owns_request($u,$r)) exit('Нет доступа.'); [$ok,$msg]=school_set_prep($r,isset($_POST['parents'])?1:0,isset($_POST['venue'])?1:0,isset($_POST['escort'])?1:0); }
elseif($a==='fund_recommend'){ require_role('fund'); [$ok,$msg]=fund_recommend($u,$r,(int)($_POST['sid']??0)); }
elseif($a==='fund_confirm_speaker'){ require_role('fund'); [$ok,$msg]=fund_confirm_speaker($u,$r,(int)($_POST['sid']??0)); }
elseif($a==='fund_briefing'){ require_role('fund'); [$ok,$msg]=fund_briefing($u,$r,$_POST['bdate']??''); }
elseif($a==='mark_done'){ require_role('school'); if(!school_owns_request($u,$r)) exit('Нет доступа.'); [$ok,$msg]=mark_done($r); }
elseif($a==='not_held'){ require_role('school'); if(!school_owns_request($u,$r)) exit('Нет доступа.'); $reasonRaw=$_POST['reason']??''; $reasonOther=trim($_POST['reason_comment']??''); $reason=($reasonRaw==='__other__')?$reasonOther:$reasonRaw; [$ok,$msg]=mark_not_held($r,$reason,$reasonRaw==='__other__'?'':$reasonOther); }
elseif($a==='close'){ require_role('operator'); [$ok,$msg]=close_request($r); }
elseif($a==='close_cancelled'){ require_role('operator'); [$ok,$msg]=close_cancelled_request($r); }
elseif($a==='step_back'){ require_role('operator'); step_back($r); $msg='Возврат на шаг назад.'; }
elseif($a==='fund_replace'){ require_role('fund'); $reasonRaw=$_POST['reason']??''; $reasonOther=trim($_POST['reason_other']??''); $reason=($reasonRaw==='__other__')?$reasonOther:$reasonRaw; [$ok,$msg]=fund_replace_speaker($u,$r,(int)($_POST['old_sid']??0),(int)($_POST['new_sid']??0),$reason); }
elseif($a==='speaker_decline'){ require_role('speaker'); [$ok,$msg]=speaker_decline($r,$u['id']); }
elseif($a==='feedback'){
$comment=trim($_POST['comment']??'');
$postUrl=trim($_POST['post_url']??'');
if($comment==='') $msg='Напишите отзыв.';
elseif(st_index($r['status'])<st_index('done')) $msg='Отзыв можно оставить после проведения.';
elseif($u['role']==='school' && school_owns_request($u,$r)){
if($postUrl==='') $msg='Укажите ссылку на пост о проведённом занятии — без неё заявка не закрывается.';
elseif(!preg_match('~^https?://~i',$postUrl)) $msg='Ссылка на пост должна начинаться с http:// или https://.';
else { upd($rid,['fb_app_comment'=>$comment,'fb_post_url'=>$postUrl,'fb_app_at'=>now()]); $msg='Спасибо! Отзыв и ссылка на пост сохранены.'; }
}
elseif($u['role']==='speaker'){ $row=get_rs_row($rid,$u['id']); if($row&&$row['confirmed']){ set_vet_feedback($rid,$u['id'],$comment); $msg='Спасибо! Отзыв сохранён.'; } else $msg='Нет доступа.'; }
else $msg='Нет доступа.';
}
if($msg!==null) flash_set($msg);
redirect('?p=request&id='.$rid);
}
redirect('?p=login');
}
// ========== GET-ДЕЙСТВИЯ ==========
$u=current_user();
if(!$u && $p==='login') unset($_SESSION['pending_uid']);
if(!$u && !empty($_SESSION['pending_uid']) && !in_array($p,['login','verify_otp'])) redirect('?p=verify_otp');
if(!$u && $p==='') $p='home';
if(!$u && !in_array($p,['login','register','reset_password','reset_request','privacy','guide','verify_otp','demo','home','news','news_image','about','faq','contacts'])) $p='login';
if($u && ($p===''||$p==='login'||$p==='register'||$p==='home')) $p=($u['role']==='admin')?'news_admin':$u['role'];
// Спикер, зарегистрированный до появления этого поля, обязан ответить на вопрос о судимости
// при первом же следующем входе — до этого никакие другие страницы кабинета не открываются.
if($u && speaker_criminal_answer_needed($u) && $p!=='speaker_criminal_check') $p='speaker_criminal_check';
if($p==='export' && $u && in_array($u['role'],['fund','school'])){
require_login();
$rows = ($u['role']==='fund') ? fund_dashboard_requests($u) : (function() use($u){
$ids=school_scope_ids($u); $in=implode(',',array_fill(0,count($ids),'?'));
$st=db()->prepare("SELECT * FROM requests WHERE school_user_id IN ($in) AND (archived=0 OR archived IS NULL) ORDER BY id DESC"); $st->execute($ids); return $st->fetchAll();
})();
$csv=export_requests_csv($rows);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="requests_'.date('Y-m-d').'.csv"');
echo $csv; exit;
}
if($p==='export' && $u && $u['role']==='operator'){
require_login();
$type=$_GET['type']??'requests';
if($type==='speakers'){
$csv=export_speakers_csv();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="speakers_'.date('Y-m-d').'.csv"');
echo $csv; exit;
} elseif($type==='report'){
$filters=[];
$xog=$u['op_group']??''; $xdept=($xog==='school')?'school':(in_array($_GET['dept']??'',['school','region'])?$_GET['dept']:'');
if($xdept!=='') $filters['op_group']=$xdept;
if(!empty($_GET['from_date'])) $filters['from_date']=$_GET['from_date'];
if(!empty($_GET['to_date'])) $filters['to_date']=$_GET['to_date'];
if(!empty($_GET['status'])) $filters['status']=$_GET['status'];
$csv=export_report_csv($filters);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_'.date('Y-m-d').'.csv"');
echo $csv; exit;
} else {
$filters=[];
$xog=$u['op_group']??''; $xdept=($xog==='school')?'school':(in_array($_GET['dept']??'',['school','region'])?$_GET['dept']:'');
if($xdept!=='') $filters['op_group']=$xdept;
if(!empty($_GET['status'])) $filters['status']=$_GET['status'];
if(!empty($_GET['org'])) $filters['org']=$_GET['org'];
if(!empty($_GET['from_date'])) $filters['from_date']=$_GET['from_date'];
if(!empty($_GET['to_date'])) $filters['to_date']=$_GET['to_date'];
$filters['archived']=isset($_GET['archived'])?1:0;
$rows=get_requests_list($filters);
$csv=export_requests_csv($rows);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="requests_'.date('Y-m-d').'.csv"');
echo $csv; exit;
}
}
if($p==='archive_run' && $u && $u['role']==='operator'){
$n=archive_old_requests(5);
flash_set("Архивировано заявок: $n");
redirect('?p=operator&tab=archive');
}
function layout($title,$body){
$u=current_user(); $c=cfg(); $sess='';
$fullLabel='';
if(!$u) $sess='<a class="btn-login-ring" href="?p=login">Вход</a>';
if($u){ $nm=($u['role']==='school')?(school_effective($u)['org_name']?:$u['display_name']):$u['display_name']; $fullLabel=trim((string)$nm);
$sess=($fullLabel!==''?'<span class="rolebadge" title="'.esc($fullLabel).'">'.esc($fullLabel).'</span> ':'').(can_impersonate($u)&&!is_impersonating()?'<a class="rolebadge" href="?p=impersonate" style="text-decoration:none">🔑 Войти как…</a> ':'').'<a class="rolebadge" href="?p=profile" style="text-decoration:none">Профиль</a> <form method="post" style="display:inline"><input type="hidden" name="action" value="logout">'.csrf_field().'<button class="logout">Выйти</button></form>'; }
$f=flash_get();
$missing=profile_missing_fields($u);
echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>'.esc($title).'</title><link rel="icon" href="favicon.ico"><link rel="icon" type="image/png" href="favicon.png"><link rel="apple-touch-icon" href="icon-192.png"><link rel="manifest" href="manifest.json"><meta name="theme-color" content="#1A56C4"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="УМ 72"><link rel="stylesheet" href="style.css?v='.(file_exists(__DIR__.'/style.css')?filemtime(__DIR__.'/style.css'):time()).'"></head><body>';
echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("sw.js").catch(function(){});});}</script>';
if(is_impersonating()){
$admin=find_user_by_id((int)$_SESSION['impersonator_id']);
echo '<div style="background:#b23b2c;color:#fff;text-align:center;padding:9px 14px;font-size:13.5px;position:sticky;top:0;z-index:1000">⚠ Вы вошли как <b>'.esc($u['display_name']?:$u['org_name']?:$u['login']).'</b> ('.esc($u['role']).') от имени <b>'.esc($admin['login']??'?').'</b>. <form method="post" style="display:inline;margin-left:8px">'.csrf_field().'<input type="hidden" name="action" value="impersonate_stop"><button type="submit" style="background:#fff;color:#b23b2c;border:none;border-radius:6px;padding:5px 12px;cursor:pointer;font-weight:600;font-size:13px">Вернуться к своей учётке</button></form></div>';
}
echo '<header class="top"><div class="topbar"><div class="mark"><img src="favicon.png" alt="" style="width:100%;height:100%;object-fit:contain"></div><div class="brand"><h1>Урок Мужества</h1><p class="brand-full">Проект разработан в рамках региональной программы «Боевой кадровый резерв Тюменской области»</p><p class="brand-short">В рамках региональной программы «БКР ТО»</p></div><div class="session">'.$sess.'</div></div>'.($fullLabel!==''?'<div class="rolebadge-full-mobile">'.esc($fullLabel).'</div>':'').'</header>';
$sideNav=render_sidebar($u);
echo '<div class="shell">';
if($sideNav) echo '<aside class="sidebar">'.$sideNav.'</aside>';
echo '<main class="wrap">'; if($f) echo '<div class="flash">'.esc($f).'</div>';
if($missing) echo '<div class="hint-warn"><b>⚠ Заполните профиль:</b> не указано — '.esc(implode(', ',$missing)).'. <a class="btn primary sm" href="?p=profile" style="margin-left:8px">Перейти в профиль →</a></div>';
echo $body.'</main>';
echo '</div>';
echo '<footer style="text-align:center;padding:20px;color:var(--muted);font-size:12px"><a href="?p=privacy">Политика обработки персональных данных (152-ФЗ)</a></footer>';
echo '<script>(function(){var n=0;document.querySelectorAll("label.lbl").forEach(function(lb){if(lb.getAttribute("for")||lb.id)return;var el=lb.nextElementSibling;while(el&&!/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)&&!(el.tagName==="DIV"&&el.querySelector("input,select,textarea")))el=el.nextElementSibling;if(!el)return;if(/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)){if(!el.id)el.id="lblauto_"+(++n);lb.setAttribute("for",el.id);}else{if(!lb.id)lb.id="lblgrp_"+(++n);el.setAttribute("role","group");el.setAttribute("aria-labelledby",lb.id);}});})();</script>';
echo '<script>(function(){function fmt(el){var d=el.value.replace(/\D+/g,"");if(d.length===11&&(d[0]==="7"||d[0]==="8"))d=d.slice(1);if(d.length===10)el.value="+7"+d;}document.querySelectorAll(\'input[name="phone"],input[name="contact_phone"]\').forEach(function(el){el.setAttribute("type","tel");el.setAttribute("placeholder","+7XXXXXXXXXX");el.setAttribute("pattern","\\\\+7[0-9]{10}");el.setAttribute("title","Формат: +7XXXXXXXXXX (10 цифр после +7)");el.setAttribute("maxlength","12");el.addEventListener("blur",function(){fmt(el);});});})();</script>';
echo '<script>(function(){function initResizable(table){var ths=table.querySelectorAll("thead th");ths.forEach(function(th){var handle=document.createElement("span");handle.className="col-resize-handle";th.appendChild(handle);handle.addEventListener("mousedown",function(e){e.preventDefault();e.stopPropagation();if(table.style.tableLayout!=="fixed"){ths.forEach(function(t){t.style.width=t.getBoundingClientRect().width+"px";});table.style.tableLayout="fixed";}handle.classList.add("active");var startX=e.pageX;var startW=th.getBoundingClientRect().width;function onMove(ev){var w=Math.max(50,startW+(ev.pageX-startX));th.style.width=w+"px";}function onUp(){handle.classList.remove("active");document.removeEventListener("mousemove",onMove);document.removeEventListener("mouseup",onUp);}document.addEventListener("mousemove",onMove);document.addEventListener("mouseup",onUp);});});}document.querySelectorAll("table.resizable").forEach(initResizable);})();</script>';
echo '<script>(function(){document.addEventListener("click",function(e){var gal=e.target.closest(".news-gallery");if(!gal)return;var prev=e.target.closest(".gallery-prev"),next=e.target.closest(".gallery-next"),dot=e.target.closest(".dot");if(!prev&&!next&&!dot)return;var imgs=gal.querySelectorAll(".news-gallery-img"),dots=gal.querySelectorAll(".dot"),n=imgs.length,cur=parseInt(gal.getAttribute("data-current")||"0",10);if(prev)cur=(cur-1+n)%n;else if(next)cur=(cur+1)%n;else if(dot)cur=parseInt(dot.getAttribute("data-index"),10);gal.setAttribute("data-current",cur);imgs.forEach(function(img,i){img.classList.toggle("active",i===cur);});dots.forEach(function(d,i){d.classList.toggle("active",i===cur);});});})();</script>';
if(empty($_COOKIE['um72_cookie_consent'])){
echo '<div id="um72-cookie" role="dialog" aria-live="polite" aria-label="Уведомление об использовании файлов cookie">'
.'<div class="um72ck-inner">'
.'<p class="um72ck-text">Мы используем файлы cookie. Продолжая использование, вы соглашаетесь с <a href="?p=privacy">Политикой обработки персональных данных</a>.</p>'
.'<div class="um72ck-act"><button type="button" id="um72ck-ok" class="um72ck-btn">Принять</button></div>'
.'</div></div>'
.'<style>'
.'#um72-cookie{position:fixed;right:16px;bottom:16px;left:auto;z-index:9999;max-width:320px;background:#1A56C4;color:#fff;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.25);animation:um72ckUp .35s ease}'
.'@keyframes um72ckUp{from{transform:translateY(12px);opacity:0}to{transform:translateY(0);opacity:1}}'
.'.um72ck-inner{padding:14px 16px;display:flex;flex-direction:column;gap:10px}'
.'.um72ck-text{margin:0;font-size:13px;line-height:1.45;color:#fff}'
.'.um72ck-text a{color:#cfe0f3;text-decoration:underline}'
.'.um72ck-btn{background:#fff;color:#1A56C4;border:0;cursor:pointer;font-weight:600;font-size:14px;padding:13px 18px;border-radius:6px;align-self:flex-start;min-height:44px}'
.'.um72ck-btn:hover{background:#e8eef6}'
.'@media(max-width:520px){#um72-cookie{left:12px;right:12px;bottom:12px;max-width:none}.um72ck-btn{width:100%;padding:11px;align-self:stretch}}'
.'</style>'
.'<script>(function(){var b=document.getElementById("um72-cookie"),k=document.getElementById("um72ck-ok");if(!b||!k)return;k.addEventListener("click",function(){var d=new Date();d.setFullYear(d.getFullYear()+1);var s=location.protocol==="https:"?"; Secure":"";document.cookie="um72_cookie_consent=1; expires="+d.toUTCString()+"; path=/; SameSite=Lax"+s;b.style.display="none";});})();</script>';
}
echo '</body></html>';
}
function pill($k){ $m=st_meta($k); if($k==='cancelled') return '<span class="pill" style="background:#fdecea;color:#b23b2c">'.esc($m['label']).'</span>'; return '<span class="pill '.$m['color'].'">'.esc($m['label']).'</span>'; }
function speaker_bio_card($sp){
$g=array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$sp['age_groups']??'')));
$h='<div class="card pad" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px">';
if(!empty($sp['photo_path'])) $h.='<img src="?p=speaker_photo&id='.$sp['id'].'" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:10px;border:1px solid var(--line);flex:0 0 auto">';
$h.='<div style="flex:1 1 240px"><b style="font-size:16px">'.esc($sp['display_name']).'</b>';
if(speaker_org_name($sp['id'])!=='') $h.=' <span class="muted">· '.esc(speaker_org_name($sp['id'])).'</span>';
$h.='<div class="muted" style="margin-top:2px">'.esc(implode(', ',$g)?:'').'</div>';
if(!empty($sp['sp_rank'])||!empty($sp['sp_branch'])) $h.='<div style="margin-top:6px">'.esc(trim($sp['sp_rank'].(($sp['sp_rank']&&$sp['sp_branch'])?', ':'').$sp['sp_branch'])).'</div>';
if(!empty($sp['sp_awards'])) $h.='<div style="margin-top:6px"><b>Награды:</b> '.nl2br(esc($sp['sp_awards'])).'</div>';
if(!empty($sp['sp_achievements'])) $h.='<div style="margin-top:6px"><b>Достижения:</b> '.nl2br(esc($sp['sp_achievements'])).'</div>';
if(!empty($sp['sp_hobbies'])) $h.='<div style="margin-top:6px"><b>Увлечения:</b> '.esc($sp['sp_hobbies']).'</div>';
if(!empty($sp['sp_bio'])) $h.='<div style="margin-top:6px">'.nl2br(esc($sp['sp_bio'])).'</div>';
$h.='</div></div>';
return $h;
}
function stepper($cur){ if($cur==='cancelled'){ return '<div class="stepper"><div class="st" style="color:#b23b2c"><span class="mk" style="background:#b23b2c;color:#fff">✕</span>Урок не состоялся</div></div>'; }
$idx=st_index($cur); $h='<div class="stepper">';
foreach(statuses() as $i=>$s){ if($i<=$idx){ $cls='done'; $mk='✓'; } elseif($i===$idx+1){ $cls='cur'; $mk=$i+1; } else { $cls=''; $mk=$i+1; } $h.='<div class="st '.$cls.'"><span class="mk">'.$mk.'</span>'.esc($s['label']).'</div>'; }
return $h.'</div>'; }
function fbline($role,$comment){ $comment=trim((string)$comment); if($comment==='') return '<div class="fbsum"><b>'.esc($role).'</b> <span class="muted">— отзыв не оставлен</span></div>'; return '<div class="fbsum"><b>'.esc($role).'</b><div class="cmt">«'.esc($comment).'»</div></div>'; }
function dt($r){ $t=$r['utime']?(' '.esc($r['utime']).(!empty($r['utime_end'])?('–'.esc($r['utime_end'])):'')):''; return esc($r['udate']).$t; }
function sort_th($label,$key){
$cur=$_GET['sort']??''; $curDir=$_GET['dir']??'asc';
$newDir=($cur===$key && $curDir==='asc')?'desc':'asc';
$q=$_GET; $q['sort']=$key; $q['dir']=$newDir;
$arrow=$cur===$key?($curDir==='asc'?' ▲':' ▼'):'';
return '<th><a href="?'.esc(http_build_query($q)).'" class="sort-th">'.esc($label).$arrow.'</a></th>';
}
function render_reason_select($name,$reasons,$withOther=true){
$o='<option value="">— выберите причину —</option>';
foreach($reasons as $rs) $o.='<option value="'.esc($rs).'">'.esc($rs).'</option>';
if($withOther) $o.='<option value="__other__">Другое (указать ниже)</option>';
$html='<select name="'.$name.'" required onchange="var w=this.parentNode.querySelector(\'.reason-other-wrap\');if(w)w.style.display=this.value===\'__other__\'?\'block\':\'none\';">'.$o.'</select>';
if($withOther) $html.='<div class="reason-other-wrap" style="display:none;margin-top:6px"><input name="'.$name.'_other" placeholder="Укажите причину"></div>';
return $html;
}
// ========== КАК ЭТО РАБОТАЕТ (презентация по ролям) ==========
if($p==='guide'){
$gc=guide_content();
$roleKeys=['school','speaker','fund','operator'];
$cur=in_array($_GET['role']??'',$roleKeys,true)?$_GET['role']:'school';
$tabs=''; foreach($roleKeys as $rk){ $g=$gc[$rk]; $tabs.='<button type="button" class="guide-tab'.($rk===$cur?' active':'').'" data-role="'.$rk.'" onclick="gsw(\''.$rk.'\')">'.esc($g['label']).'</button>'; }
$panels='';
foreach($roleKeys as $rk){ $g=$gc[$rk];
$steps=''; $n=1; foreach($g['steps'] as $s){ $steps.='<div class="guide-step"><div class="guide-num">'.$n.'</div><div class="guide-step-body"><b>'.esc($s['t']).'</b><p>'.esc($s['d']).'</p></div></div>'; $n++; }
$panels.='<div class="guide-panel" data-role="'.$rk.'" style="display:'.($rk===$cur?'block':'none').'">'
.'<div class="head"><h2>'.esc($g['label']).' <span class="muted" style="font-weight:400;font-size:15px">— '.esc($g['subtitle']).'</span></h2>'
.'<a class="btn primary" href="?p=register&role='.$rk.'">Зарегистрироваться →</a></div>'
.'<div class="guide-steps">'.$steps.'</div>'
.'</div>';
}
$body='<div class="head"><h2>Как это работает</h2><a class="btn ghost" href="?p=login">← ко входу</a></div>';
$body.='<p class="muted" style="max-width:680px">Платформа связывает четыре роли: образовательные организации подают заявки, Фонды направляют спикеров-ветеранов, департаменты образования модерируют и контролируют процесс. Ниже — интерактивный путь одной заявки от подачи до закрытия с указанием, чья сейчас очередь действовать. Регистрация и подтверждение всех участников (заявителя — департаментом, спикера — Фондом) считаются уже пройденными.</p>';
$body.='<p style="margin:4px 0 18px"><a class="btn ghost sm" href="?p=demo">🖱️ Или пройдите то же самое настоящими экранами сайта, шаг за шагом →</a></p>';
// ---- интерактивная презентация: путь заявки от подачи до закрытия ----
$jroles=journey_roles(); $journey=guide_journey(); $jtotal=count($journey);
$legend=''; foreach($jroles as $rv){ $legend.='<span><i style="background:'.esc($rv['color']).'"></i>'.esc($rv['label']).'</span>'; }
$jdots=''; $jpanels='';
foreach($journey as $i=>$st){
$col=$jroles[$st['role']]['color'];
$jdots.='<button type="button" class="journey-dot'.($i===0?' active':'').'" data-i="'.$i.'" data-color="'.esc($col).'" onclick="jgo('.$i.')"><span class="jd-mk"'.($i===0?' style="background:'.esc($col).';border-color:'.esc($col).'"':'').'>'.($i+1).'</span><span class="jd-lbl">'.esc($st['short']??$st['t']).'</span></button>';
$roleBadge='<span class="journey-role" style="background:'.esc($col).'">'.esc($jroles[$st['role']]['label']).'</span>';
if(!empty($st['role2'])){ $col2=$jroles[$st['role2']]['color']; $roleBadge.='<span class="journey-role j-role2" style="background:'.esc($col2).'">'.esc($jroles[$st['role2']]['label']).'</span>'; }
$note=!empty($st['note'])?'<div class="journey-note">💡 '.esc($st['note']).'</div>':'';
$jpanels.='<div class="journey-panel" data-i="'.$i.'" style="display:'.($i===0?'block':'none').'"><div class="journey-card">'.$roleBadge
.'<p class="journey-status">Статус заявки: <b>'.esc($st['status']).'</b></p>'
.'<h3>'.$st['emoji'].' '.esc($st['t']).'</h3><p>'.esc($st['d']).'</p>'.$note.'</div></div>';
}
$body.='<div class="journey"><div class="journey-legend">'.$legend.'</div><div class="journey-track">'.$jdots.'</div>'.$jpanels
.'<div class="journey-nav"><button type="button" class="btn ghost sm" id="journeyPrev" onclick="jprev()" disabled>← Назад</button><span class="journey-progress" id="journeyProgress">1 из '.$jtotal.'</span><button type="button" class="btn primary sm" id="journeyNext" onclick="jnext()">Далее →</button></div></div>';
$body.='<script>
var JOURNEY_TOTAL='.$jtotal.'; window.__jidx=0;
function jgo(i){
document.querySelectorAll(".journey-panel").forEach(function(p){ p.style.display=(+p.dataset.i===i)?"block":"none"; });
document.querySelectorAll(".journey-dot").forEach(function(d){
var active=(+d.dataset.i===i); d.classList.toggle("active",active);
var mk=d.querySelector(".jd-mk");
if(active){ mk.style.background=d.dataset.color; mk.style.borderColor=d.dataset.color; } else { mk.style.background=""; mk.style.borderColor=""; }
});
document.getElementById("journeyProgress").textContent=(i+1)+" из "+JOURNEY_TOTAL;
document.getElementById("journeyPrev").disabled=(i===0);
document.getElementById("journeyNext").disabled=(i===JOURNEY_TOTAL-1);
window.__jidx=i;
var ad=document.querySelector(".journey-dot.active"); if(ad&&ad.scrollIntoView) ad.scrollIntoView({inline:"center",block:"nearest",behavior:"smooth"});
}
function jnext(){ if(window.__jidx<JOURNEY_TOTAL-1) jgo(window.__jidx+1); }
function jprev(){ if(window.__jidx>0) jgo(window.__jidx-1); }
document.addEventListener("keydown",function(e){
if(!document.querySelector(".journey")) return;
if(e.key==="ArrowRight") jnext(); else if(e.key==="ArrowLeft") jprev();
});
</script>';
$body.='<div class="guide-tabs">'.$tabs.'</div>'.$panels;
$body.='<script>function gsw(r){document.querySelectorAll(".guide-tab").forEach(function(b){b.classList.toggle("active",b.dataset.role===r);});document.querySelectorAll(".guide-panel").forEach(function(p){p.style.display=(p.dataset.role===r)?"block":"none";});if(history.replaceState) history.replaceState(null,"","?p=guide&role="+r);}</script>';
layout('Как это работает',$body); exit;
}
// ========== ДЕМО-РЕЖИМ (интерактивный аналог сайта на выдуманных данных) ==========
if($p==='demo'){
$dp=demo_profile(); $total=demo_total_steps();
$step=(int)($_GET['step']??0);
$peek=$_GET['peek']??'';
$fakeR=['udate'=>$dp['udate'],'utime'=>$dp['utime'],'utime_end'=>$dp['utime_end']];

if($step<1||$step>8){
$jr=journey_roles(); $legend='';
foreach($jr as $rv) $legend.='<span class="demo-role-chip" style="background:'.esc($rv['color']).'">'.esc($rv['label']).'</span> ';
$body='<div class="head"><h2>Демо-режим</h2><a class="btn ghost" href="?p=guide">← к «Как это работает»</a></div>';
$body.='<div class="card pad" style="max-width:720px"><p>Это интерактивный аналог сайта на выдуманных данных — организация, спикер, фонд и заявка ниже не настоящие, ничего не сохраняется. Вы пройдёте путь одной заявки от подачи до закрытия теми же экранами, что видят настоящие пользователи, — по очереди оказываясь на месте каждой роли.</p>'
.'<p style="margin-top:14px">'.$legend.'</p>'
.'<a class="btn primary" href="?p=demo&step=1" style="margin-top:16px;display:inline-block">Начать демо →</a></div>';
layout('Демо-режим',$body); exit;
}

if($step===2 && $peek==='school'){
$body=demo_banner('school',2,$total);
$body.='<div class="head"><h2>'.esc($dp['org']).'</h2></div>';
$body.='<div class="kpis"><div class="kpi"><div class="n">1</div><div class="l">Всего заявок</div></div><div class="kpi"><div class="n">1</div><div class="l">Ожидают фонда</div></div><div class="kpi"><div class="n">0</div><div class="l">В работе</div></div><div class="kpi"><div class="n">0</div><div class="l">Проведено</div></div></div>';
$body.='<table class="card-view"><thead><tr><th>Код</th><th>Дата и время</th><th>Аудитория</th><th>Спикеры</th><th>Статус</th></tr></thead><tbody>';
$body.='<tr><td class="id">'.esc($dp['code']).'</td><td>'.dt($fakeR).'</td><td>'.esc($dp['age_group']).'</td><td>не выбран</td><td>'.pill('new').'</td></tr>';
$body.='</tbody></table>';
$body.='<div class="block" style="margin-top:14px"><p class="muted">Заявка передана всем фондам — заявитель просто ждёт, пока один из них назначит спикера. Искать и выбирать спикера вручную не нужно.</p></div>';
$body.='<a class="btn ghost sm" href="?p=demo&step=2">← Вернуться к сценарию (Фонд)</a>';
layout('Демо-режим',$body); exit;
}

switch($step){
case 1:
$body=demo_banner('school',1,$total);
$body.='<div class="head"><h2>Подать заявку</h2></div>';
$body.='<p class="muted">Организация: <b>'.esc($dp['org']).'</b> · оператор: '.esc($dp['operator']).'</p>';
$body.='<p class="muted">После отправки заявка сразу станет доступна всем фондам — они сами подберут и назначат спикера.</p>';
$body.='<form class="card pad" onsubmit="return false">'
.'<label class="lbl">Возрастная группа</label><select><option selected>'.esc($dp['age_group']).'</option></select>'
.'<label class="lbl">Сколько спикеров нужно</label><select><option selected>1</option></select>'
.'<label class="lbl">Контактное лицо</label><input value="'.esc($dp['contact_name']).'">'
.'<label class="lbl">Телефон</label><input value="'.esc($dp['contact_phone']).'">'
.'<label class="lbl">Желаемая дата</label><input type="date" value="'.esc($dp['udate']).'">'
.'<label class="lbl">Время начала</label><select><option selected>'.esc($dp['utime']).'</option></select>'
.'<label class="lbl">Время окончания</label><select><option selected>'.esc($dp['utime_end']).'</option></select>'
.'<label class="lbl">Число участников</label><input value="'.esc((string)$dp['participants']).'">'
.'<label class="lbl">Тема занятия</label><select><option selected>'.esc($dp['topic']).'</option></select>'
.'<label class="chk" style="margin-top:8px"><input type="checkbox" checked disabled> Согласие на обработку ПДн</label>'
.'<a class="btn primary" style="margin-top:12px;display:inline-block" href="?p=demo&step=2">Отправить заявку →</a>'
.'</form>';
break;
case 2:
$body=demo_banner('fund',2,$total);
$body.='<div class="head"><h2>'.esc($dp['fund']).'</h2></div>';
$body.='<div class="kpis"><div class="kpi"><div class="n">1</div><div class="l">Новых заявок</div></div><div class="kpi"><div class="n">0</div><div class="l">В работе</div></div><div class="kpi"><div class="n">37</div><div class="l">Проведено (демо)</div></div></div>';
$body.='<table class="card-view"><thead><tr><th>Код</th><th>Организация</th><th>Дата и время</th><th>Статус</th></tr></thead><tbody>';
$body.='<tr><td class="id">'.esc($dp['code']).'</td><td>'.esc($dp['org']).'</td><td>'.dt($fakeR).'</td><td>'.pill('new').'</td></tr>';
$body.='</tbody></table>';
$body.='<div class="block" style="margin-top:16px"><h4>Заявка '.esc($dp['code']).'</h4>'.stepper('new')
.'<p style="margin-top:10px"><span class="pill green">В обработке вашего фонда</span></p>'
.'<div style="margin-top:12px;border-top:1px solid #eee;padding-top:12px"><h4>Назначить спикера</h4><select><option selected>'.esc($dp['speaker']).' ('.esc($dp['speaker_rank']).')</option></select> <a class="btn primary sm" href="?p=demo&step=3" style="margin-left:6px">Назначить спикера</a></div>'
.'</div>';
$body.='<p class="muted" style="margin-top:14px">👀 Тем временем <a href="?p=demo&step=2&peek=school">заявитель видит заявку в статусе «Новая» →</a></p>';
break;
case 3:
$body=demo_banner('fund',3,$total);
$body.='<div class="head"><h2>Заявка '.esc($dp['code']).' '.pill('chosen').'</h2></div>';
$body.='<div class="card pad">'.stepper('chosen')
.'<div class="block"><h4>Согласование спикеров (0 из 1 подтверждено)</h4>'
.'<div class="row2" style="margin-bottom:6px"><span><b>'.esc($dp['speaker']).'</b> <span class="muted">(Тюмень, тел.: +7900...)</span></span><span class="pill gray">кандидат</span></div>'
.'<a class="btn primary sm" href="?p=demo&step=4">Подтвердить окончательно →</a>'
.'<p class="muted" style="margin-top:10px">Заявка не может перейти дальше, пока не подтверждён хотя бы один спикер — это встроенное ограничение.</p>'
.'</div></div>';
break;
case 4:
$body=demo_banner('school',4,$total);
$body.='<div class="head"><h2>Заявка '.esc($dp['code']).' '.pill('confirmed').'</h2></div>';
$body.='<div class="card pad">'.stepper('confirmed')
.'<div class="block"><h4>Состав (1 из 1)</h4><div class="fbsum"><b>'.esc($dp['speaker']).'</b> <span class="muted">— '.esc($dp['speaker_rank']).'</span></div></div>'
.'<div class="block"><h4>Готовность к проведению</h4><p class="muted">Отметьте все пункты — готовность зафиксируется автоматически.</p>'
.'<label class="chk"><input type="checkbox" checked disabled> площадка готова</label> <label class="chk"><input type="checkbox" checked disabled> сопровождение назначено</label>'
.'<p style="margin-top:10px"><span class="pill green">✓ Готовность подтверждена</span></p>'
.'<a class="btn primary sm" style="margin-top:10px;display:inline-block" href="?p=demo&step=5">Далее →</a></div></div>';
break;
case 5:
$body=demo_banner('school',5,$total);
$body.='<div class="head"><h2>Заявка '.esc($dp['code']).' '.pill('prep').'</h2></div>';
$body.='<div class="card pad">'.stepper('prep')
.'<div class="block"><h4>После урока</h4><p class="muted">Урок «'.esc($dp['topic']).'» состоялся '.esc($dp['udate']).' в '.esc($dp['utime']).'. Отметьте, как он прошёл.</p>'
.'<a class="btn primary sm" href="?p=demo&step=6">Урок проведён</a> '
.'<button class="btn ghost sm" disabled title="В демо доступен только успешный сценарий">Урок не состоялся</button>'
.'</div></div>';
break;
case 6:
$tab=($_GET['as']??'school')==='speaker'?'speaker':'school';
$tabs='<div class="guide-tabs" style="margin:16px 0"><button type="button" class="guide-tab'.($tab==='school'?' active':'').'" onclick="location=\'?p=demo&step=6&as=school\'">Заявитель</button><button type="button" class="guide-tab'.($tab==='speaker'?' active':'').'" onclick="location=\'?p=demo&step=6&as=speaker\'">Спикер</button></div>';
$body=demo_banner($tab,6,$total);
$body.='<div class="head"><h2>Заявка '.esc($dp['code']).' '.pill('done').'</h2></div>';
$body.=$tabs;
if($tab==='school') $body.='<div class="card pad"><h4>Ваш отзыв</h4><textarea readonly>Спикер отлично провёл занятие, дети слушали с большим интересом, спасибо!</textarea><a class="btn primary sm" style="margin-top:10px;display:inline-block" href="?p=demo&step=6&as=speaker">Далее — отзыв спикера →</a></div>';
else $body.='<div class="card pad"><h4>Ваш отзыв о занятии</h4><textarea readonly>Организация всё подготовила заранее, ребята — большие молодцы, задавали много вопросов.</textarea><a class="btn primary sm" style="margin-top:10px;display:inline-block" href="?p=demo&step=7">Далее →</a></div>';
break;
case 7:
$body=demo_banner('operator',7,$total);
$body.='<div class="head"><h2>Заявка '.esc($dp['code']).' '.pill('done').'</h2></div>';
$body.='<div class="card pad">'.stepper('done')
.'<div class="block"><h4>Состав (1 из 1)</h4><div class="fbsum"><b>'.esc($dp['speaker']).'</b></div><p class="muted">Подтверждение Фондом: <b>есть</b> · Инструктаж: <b>проведён</b></p></div>'
.'<div class="block"><h4>Обратная связь</h4>'.fbline('Заявитель','Спикер отлично провёл занятие, дети слушали с большим интересом, спасибо!').fbline('Спикер: '.$dp['speaker'],'Организация всё подготовила заранее, ребята — большие молодцы, задавали много вопросов.').'</div>'
.'<div class="block"><a class="btn primary sm" href="?p=demo&step=8">Закрыть заявку →</a></div></div>';
break;
case 8:
$body='<div class="head"><h2>Демо завершено 🎉</h2></div>';
$body.='<div class="card pad" style="max-width:720px">'.stepper('closed')
.'<p style="margin-top:14px">Заявка '.esc($dp['code']).' прошла весь путь: от подачи заявителем до закрытия оператором, с участием фонда и спикера — теми же экранами, что видят настоящие пользователи.</p>'
.'<div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">'
.'<a class="btn primary" href="?p=demo&step=1">↻ Пройти демо заново</a>'
.'<a class="btn ghost" href="?p=guide">📋 Текстовая версия «Как это работает»</a>'
.'<a class="btn ghost" href="?p=register">Зарегистрироваться →</a>'
.'</div></div>';
break;
}
layout('Демо-режим',$body); exit;
}
// ========== СТРАНИЦА ВХОДА ==========
if($p==='login'){
$body='<div class="auth"><h2>Вход</h2><form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="login"><label class="lbl">Логин</label><input name="login" required><label class="lbl">Пароль</label><input type="password" name="pass" required><button class="btn primary" style="margin-top:12px">Войти</button></form><p class="muted"><a href="?p=reset_request">Забыли пароль?</a> · <a href="?p=register">Зарегистрироваться</a> · <a href="?p=guide">Как это работает?</a> · <a href="?p=demo">Демо-режим →</a></p><p class="muted" style="font-size:12.5px">Не приходит код подтверждения или не получается войти? Напишите на <a href="mailto:'.esc(cfg()['support_email']).'">'.esc(cfg()['support_email']).'</a>.</p></div>';
layout('Вход',$body); exit;
}
if($p==='verify_otp'){
$pid=(int)($_SESSION['pending_uid']??0); $pu=$pid?find_user_by_id($pid):null;
if(!$pu){ unset($_SESSION['pending_uid']); redirect('?p=login'); }
$masked=preg_replace('/^(.).*(@.*)$/u','$1***$2',(string)$pu['email']);
$body='<div class="auth"><h2>Подтверждение входа</h2><p class="muted">Мы отправили код подтверждения на '.esc($masked).'. Введите его ниже — код действует 10 минут.</p>'
.'<form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="verify_otp"><label class="lbl">Код из письма</label><input name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus required><button class="btn primary" style="margin-top:12px">Подтвердить</button></form>'
.'<div style="display:flex;gap:10px;margin-top:10px"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="resend_otp"><button class="btn ghost sm">Отправить код повторно</button></form><a class="btn ghost sm" href="?p=login">Отмена</a></div>'
.'<p class="muted" style="font-size:12.5px;margin-top:10px">Код не приходит? Проверьте папку «Спам» или напишите на <a href="mailto:'.esc(cfg()['support_email']).'">'.esc(cfg()['support_email']).'</a>.</p></div>';
layout('Подтверждение входа',$body); exit;
}
if($p==='reset_request'){
$body='<div class="auth"><h2>Сброс пароля</h2><form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="request_reset"><label class="lbl">Email</label><input type="email" name="email" required><button class="btn primary" style="margin-top:12px">Отправить ссылку</button></form><p class="muted"><a href="?p=login">← ко входу</a></p></div>';
layout('Сброс пароля',$body); exit;
}
if($p==='reset_password'){
$token=$_GET['token']??'';
[$ok,$row]=verify_reset_token($token);
if(!$ok){ layout('Сброс пароля','<div class="auth card pad"><h2>Ссылка недействительна</h2><p class="muted">'.$row.'</p><p><a href="?p=reset_request">Запросить новую ссылку</a></p></div>'); exit; }
$body='<div class="auth"><h2>Новый пароль</h2><form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="reset_password"><input type="hidden" name="token" value="'.esc($token).'"><label class="lbl">Новый пароль (от 10 символов)</label><input type="password" name="newpass" required><label class="lbl">Повторите пароль</label><input type="password" name="newpass2" required><button class="btn primary" style="margin-top:12px">Сменить пароль</button></form></div>';
layout('Новый пароль',$body); exit;
}
if($p==='privacy'){
$body = <<<'HTML'
<div class="card pad" style="max-width:860px;margin:0 auto;line-height:1.6">
<style>
.pdoc h4{margin:22px 0 8px}
.pdoc p{text-align:justify;margin:8px 0}
.pdoc ul{margin:6px 0 6px 4px;padding-left:22px}
.pdoc li{margin:4px 0}
.pdoc table{width:100%;border-collapse:collapse;margin:10px 0;font-size:14px}
.pdoc th,.pdoc td{border:1px solid #d8dee8;padding:7px 9px;text-align:left;vertical-align:top}
.pdoc th{background:#1A56C4;color:#fff}
.pdoc .ph{color:#b23b2c;font-weight:600}
</style>
<div class="pdoc">
<h2>Политика обработки персональных данных</h2>
<p class="muted">Информационная система «урокмужества72.рф». В соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных». Редакция от 31.08.2026.</p>
<h4>1. Общие положения</h4>
<p>1.1. Настоящая Политика определяет порядок обработки персональных данных пользователей информационной системы «урокмужества72.рф» (далее — Сайт, Система), меры по защите персональных данных, права и обязанности Операторов и субъектов персональных данных.</p>
<p>1.2. Обработка персональных данных в Системе осуществляется совместно несколькими Операторами, каждый из которых действует в пределах своей зоны ответственности:</p>
<table><thead><tr><th>Оператор</th><th>Роль</th><th>Зона ответственности</th></tr></thead><tbody>
<tr><td>Один или несколько региональных фондов поддержки участников СВО (далее — Фонд, Фонды)</td><td>Координатор спикеров</td><td>Обработка данных спикеров (ветеранов), подбор состава, инструктажи</td></tr>
<tr><td>Департамент образования Администрации города Тюмени (ДОА г. Тюмени)</td><td>Оператор для школ</td><td>Приём заявок от школ, координация уроков в школах города Тюмени</td></tr>
<tr><td>Департамент образования и науки Тюменской области (ДОиН ТО)</td><td>Оператор для СПО и вузов</td><td>Приём заявок от СПО и вузов, координация уроков на уровне области</td></tr>
</tbody></table>
<p>1.3. Порядок совместной обработки, распределение обязанностей и ответственности между Операторами и условия обмена данными определяются Соглашением о порядке совместной обработки персональных данных, заключаемым между Операторами.</p>
<p>1.4. Каждый Оператор до начала обработки направляет в Роскомнадзор уведомление об обработке персональных данных и поддерживает актуальность сведений в реестре операторов (ст. 22 № 152-ФЗ).</p>
<p>1.5. Политика разработана в соответствии с Конституцией РФ, Федеральным законом № 152-ФЗ (в действующей редакции, с учётом Федеральных законов от 24.06.2025 № 156-ФЗ и от 30.11.2024 № 420-ФЗ), Федеральным законом от 28.03.1998 № 53-ФЗ, Постановлением Правительства РФ от 01.11.2012 № 1119 и иными нормативными актами РФ и Тюменской области.</p>
<h4>2. Основные понятия</h4>
<p>2.1. Персональные данные — любая информация, относящаяся к прямо или косвенно определённому или определяемому физическому лицу.</p>
<p>2.2. Обработка — любое действие с персональными данными (сбор, хранение, использование, передача, обезличивание, удаление и др.).</p>
<p>2.3. Согласие — конкретное, предметное, информированное, сознательное и однозначное волеизъявление субъекта, оформленное отдельным документом (отдельно от иных подписываемых субъектом документов).</p>
<p>2.4. Совместная обработка — обработка, осуществляемая несколькими Операторами на условиях Соглашения о совместной обработке.</p>
<h4>3. Разграничение данных по операторам</h4>
<p><b>3.1. Фонды — данные спикеров:</b> ФИО; контактный телефон; email; сведения об участии в СВО (при согласии субъекта); возрастные группы для работы; фотография и биографическая справка (при предоставлении); данные о проведённых уроках и отзывах. Правовое основание — согласие спикера, оформленное отдельным документом при регистрации, и договор о сотрудничестве.</p>
<p><b>3.2. ДОА г. Тюмени — данные по школам:</b> наименование, адрес и уровень организации; ФИО контактного лица; телефон и email; данные заявки (дата, время, возрастная группа, число участников, тематика); данные о готовности площадки; отзывы. Правовое основание — согласие контактного лица (отдельный документ) и полномочия Департамента.</p>
<p><b>3.3. ДОиН ТО — данные по СПО и вузам:</b> состав данных аналогичен п. 3.2. Правовое основание — согласие контактного лица (отдельный документ) и полномочия Департамента.</p>
<p><b>3.4. Данные обучающихся.</b> Система не обрабатывает персональные данные обучающихся (ФИО учеников, студентов). Обрабатываются только обезличенные сведения: возрастная группа и число участников. Обработка данных несовершеннолетних, если она потребуется в будущем, допускается только с согласия законных представителей.</p>
<p><b>3.5. Обмен данными между Операторами</b> осуществляется в объёме и на условиях Соглашения о совместной обработке, исключительно для проведения Уроков мужества: заявка (школа/СПО/вуз → Фонд) для подбора спикеров; ФИО и контакты утверждённых спикеров (Фонд → заявитель); данные о проведённых уроках и готовности (→ Департаменты) для отчётности и координации. Передача третьим лицам, не указанным в Политике, не производится, кроме случаев, предусмотренных законом.</p>
<h4>4. Сведения об участии в СВО</h4>
<p>4.1. В Системе могут обрабатываться сведения об участии спикеров-ветеранов в специальной военной операции (факт участия и, при согласии субъекта, краткая биографическая справка) — только с явного согласия, оформленного отдельным документом, с повышенными мерами защиты.</p>
<p>4.2. Специальные категории персональных данных по ст. 10 № 152-ФЗ (раса, национальность, политические взгляды, религиозные убеждения, состояние здоровья, интимная жизнь) в Системе не обрабатываются. В частности, не собираются сведения о ранениях, состоянии здоровья и инвалидности спикеров.</p>
<h4>5. Автоматически собираемые данные и cookie</h4>
<p>5.1. Автоматически собираются технические данные: IP-адрес; сведения о браузере и ОС; дата и время доступа; адреса запрашиваемых страниц; файлы cookie.</p>
<p>5.2. При первом посещении отображается уведомление (баннер) об использовании cookie. Продолжение использования Сайта означает согласие на их обработку. Данные используются только для функционирования Сайта и не передаются третьим лицам.</p>
<h4>6. Сроки хранения</h4>
<table><thead><tr><th>Категория</th><th>Срок</th><th>Оператор</th></tr></thead><tbody>
<tr><td>Данные спикеров</td><td>5 лет с момента последнего урока</td><td>Фонд</td></tr>
<tr><td>Данные заявок школ</td><td>3 года с момента закрытия заявки</td><td>ДОА г. Тюмени</td></tr>
<tr><td>Данные заявок СПО / вузов</td><td>3 года с момента закрытия заявки</td><td>ДОиН ТО</td></tr>
<tr><td>Технические данные (логи)</td><td>1 год</td><td>Все Операторы</td></tr>
<tr><td>Данные отзывов</td><td>5 лет</td><td>Все Операторы</td></tr>
</tbody></table>
<p>По истечении сроков данные уничтожаются или обезличиваются. Закрытые заявки переносятся в архив с ограниченным доступом через 5 рабочих дней после закрытия.</p>
<h4>7. Меры защиты</h4>
<p>Организационные: ответственные лица у каждого Оператора; разграничение доступа по ролям; журнал действий (audit log); обучение работников. Технические: HTTPS (SSL/TLS); хранение паролей в хэшированном виде (bcrypt); защита от CSRF; ежедневное резервное копирование; антивирусная защита серверов.</p>
<p><b>При инциденте (утечке)</b> Оператор обязан: незамедлительно прекратить неправомерную обработку; уведомить Роскомнадзор в течение 24 часов; в течение 72 часов представить сведения о результатах расследования; уведомить субъектов в установленный законом срок; устранить последствия.</p>
<h4>8. Права субъекта</h4>
<p>Субъект вправе: получать информацию об обработке; получать доступ к своим данным; требовать уточнения, блокирования, уничтожения; отзывать согласие; обжаловать действия Оператора в Роскомнадзор или в суд. Отзыв согласия направляется Оператору, обрабатывающему соответствующие данные; обращения рассматриваются в течение 30 дней.</p>
<h4>9. Локализация и трансграничная передача</h4>
<p>Обработка персональных данных граждан РФ ведётся с использованием баз данных на территории Российской Федерации. Трансграничная передача не осуществляется.</p>
<h4>10. Заключительные положения</h4>
<p>Политика вступает в силу с момента размещения на Сайте и действует бессрочно. Изменения вносятся с уведомлением пользователей не менее чем за 10 дней. Контролирующий орган — Роскомнадзор.</p>
<h4>Реквизиты операторов</h4>
<p class="muted">Перечень Фондов дополняется по мере включения новых организаций.</p>
<table><thead><tr><th>Оператор</th><th>Реквизиты</th><th>Контакт по ПДн</th></tr></thead><tbody>
<tr><td>Филиал Государственного фонда поддержки участников специальной военной операции «Защитники Отечества» в Тюменской области</td><td>ИНН головной организации 9704211491; адрес филиала: г. Тюмень, пер. Приисковый, д. 12</td><td>+7 (3452) 69-69-02, rf72@fzo.gov.ru</td></tr>
<tr><td>Тюменская региональная общественная организация участников специальной военной операции «Держава»</td><td>ОГРН 1247200018614; ИНН 7203582830; КПП 720301001; адрес: 625019, Тюменская область, г. Тюмень, ул. Республики, д. 266, офис 7</td><td>powerltd@mail.ru</td></tr>
<tr><td>ДОА г. Тюмени (Департамент образования Администрации города Тюмени)</td><td>ОГРН 1027200830920; ИНН 7202059338; КПП 720301001; ОКПО 02117657; юридический адрес: 625003, г. Тюмень, ул. Володарского, д. 13</td><td>+7 (3452) 56-13-40, education@tyumen-city.ru</td></tr>
<tr><td>ДОиН ТО (Департамент образования и науки Тюменской области)</td><td>ОГРН 1057200719762; ИНН 7202137498; КПП 720301001; ОКПО 00090813; ОКТМО 71701000001; юридический адрес: 625000, Тюменская область, г. Тюмень, ул. Володарского, д. 49</td><td>+7 (3452) 56-93-00; dep_obraz@72to.ru</td></tr>
</tbody></table>
<p style="margin-top:18px"><a href="?p=login">← ко входу</a></p>
</div></div>
HTML;
layout('Политика обработки персональных данных',$body); exit;
}
// ========== СТАРТОВАЯ СТРАНИЦА (для незалогиненных) ==========
if($p==='home'){
$stats=public_site_stats();
$newsItems=list_news(true,4);
$covers=news_cover_images(array_column($newsItems,'id'));
$body='<div class="pub-hero"><h1>Герои рассказывают — школьники слушают</h1><p>Ветераны специальной военной операции приходят в школы, колледжи и вузы Тюменской области, чтобы говорить с подростками о мужестве, долге и взаимовыручке. Платформа соединяет школы, фонды-координаторы и спикеров-ветеранов на одной платформе.</p>'
.'<div class="pub-stats"><div><b>'.$stats['lessons'].'</b> <span>уроков проведено</span></div><div><b>'.$stats['speakers'].'</b> <span>спикеров-ветеранов</span></div><div><b>'.$stats['schools'].'</b> <span>школ и колледжей</span></div></div></div>';
$body.='<div class="head"><h2>Новости</h2>'.($newsItems?'<a href="?p=news">Все новости →</a>':'').'</div>';
if(!$newsItems) $body.='<div class="card pad muted">Новостей пока нет.</div>';
else { $body.='<div class="news-grid">'; foreach($newsItems as $n){
$excerpt=mb_substr(trim(preg_replace('/\s+/u',' ',(string)$n['body'])),0,120);
$cov=$covers[$n['id']]??null;
$body.='<a href="?p=news&id='.$n['id'].'" class="news-card">'.($cov?'<img src="?p=news_image&id='.$cov['id'].'" alt="">':'<div class="news-card-ph"></div>').'<div class="news-card-body"><h4>'.esc($n['title']).'</h4><p>'.esc($excerpt).(mb_strlen((string)$n['body'])>120?'…':'').'</p></div></a>';
} $body.='</div>'; }
$body.='<div class="card pad" style="margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap"><span>Больше новостей о патриотическом воспитании — на сайте Роспатриотцентра.</span><a class="btn ghost sm" href="https://rospatriotcentr.ru/news/" target="_blank" rel="noopener">На сайт Роспатриотцентра →</a></div>';
$body.='<div class="pub-cta"><h3>Хотите стать частью программы?</h3><p>Подайте заявку на урок от имени школы или колледжа — либо зарегистрируйтесь как спикер-ветеран и делитесь своим опытом с подростками.</p><a class="btn primary btn-lg" href="?p=register">Зарегистрироваться</a></div>';
layout('Урок Мужества 72',$body); exit;
}
if($p==='news'){
$newsId=(int)($_GET['id']??0);
if($newsId){
$n=get_news($newsId);
if(!$n || !$n['published']){ layout('Новость','<div class="card pad">Новость не найдена. <a href="?p=news">Все новости</a></div>'); exit; }
$body='<div class="head"><h2>'.esc($n['title']).'</h2><a class="btn ghost" href="?p=news">← ко всем новостям</a></div>';
$body.='<div class="card pad">';
$imgs=list_news_images($newsId);
if(count($imgs)===1){
$body.='<img src="?p=news_image&id='.$imgs[0]['id'].'" alt="" style="display:block;width:100%;max-height:70vh;object-fit:contain;background:var(--bg);border-radius:10px;margin-bottom:14px">';
} elseif(count($imgs)>1){
$body.='<div class="news-gallery" data-current="0">';
$body.='<div class="news-gallery-track">';
foreach($imgs as $i=>$img) $body.='<img class="news-gallery-img'.($i===0?' active':'').'" src="?p=news_image&id='.$img['id'].'" alt="">';
$body.='</div>';
$body.='<button type="button" class="gallery-arrow gallery-prev" aria-label="Предыдущее фото">‹</button>';
$body.='<button type="button" class="gallery-arrow gallery-next" aria-label="Следующее фото">›</button>';
$body.='<div class="gallery-dots">'; foreach($imgs as $i=>$img) $body.='<span class="dot'.($i===0?' active':'').'" data-index="'.$i.'"></span>'; $body.='</div>';
$body.='</div>';
}
$body.='<p class="muted" style="margin:0 0 14px;font-size:13px">'.esc(substr((string)$n['created_at'],0,10)).($n['author']?' · '.esc($n['author']):'').'</p>';
$body.='<div style="white-space:pre-wrap;line-height:1.7">'.nl2br(esc($n['body'])).'</div>';
if(!empty($n['school_post_url'])) $body.='<p style="margin-top:14px"><a class="btn ghost sm" href="'.esc($n['school_post_url']).'" target="_blank" rel="noopener">🔗 Публикация школы</a></p>';
$body.='</div>';
$others=array_values(array_filter(list_news(true),fn($x)=>(int)$x['id']!==$newsId));
$others=array_slice($others,0,5);
if($others){
$oCovers=news_cover_images(array_column($others,'id'));
$body.='<div class="head" style="margin-top:28px"><h2>Другие новости</h2></div><div class="news-grid">';
foreach($others as $o){ $oc=$oCovers[$o['id']]??null; $body.='<a href="?p=news&id='.$o['id'].'" class="news-card">'.($oc?'<img src="?p=news_image&id='.$oc['id'].'" alt="">':'<div class="news-card-ph"></div>').'<div class="news-card-body"><h4>'.esc($o['title']).'</h4><time>'.esc(substr((string)$o['created_at'],0,10)).'</time></div></a>'; }
$body.='</div>';
}
layout($n['title'],$body); exit;
}
$items=list_news(true);
$covers=news_cover_images(array_column($items,'id'));
$body='<div class="head"><h2>Новости</h2><a class="btn ghost" href="'.esc($u?role_home($u):'?p=home').'">← назад</a></div>';
if(!$items) $body.='<div class="card pad muted">Новостей пока нет.</div>';
else { $body.='<div class="news-grid">'; foreach($items as $n){
$excerpt=mb_substr(trim(preg_replace('/\s+/u',' ',(string)$n['body'])),0,120);
$cov=$covers[$n['id']]??null;
$body.='<a href="?p=news&id='.$n['id'].'" class="news-card">'.($cov?'<img src="?p=news_image&id='.$cov['id'].'" alt="">':'<div class="news-card-ph"></div>').'<div class="news-card-body"><h4>'.esc($n['title']).'</h4><p>'.esc($excerpt).(mb_strlen((string)$n['body'])>120?'…':'').'</p><time>'.esc(substr((string)$n['created_at'],0,10)).'</time></div></a>';
} $body.='</div>'; }
layout('Новости',$body); exit;
}
if($p==='news_image'){
$img=get_news_image((int)($_GET['id']??0));
if(!$img){ http_response_code(404); exit; }
$path=__DIR__.'/uploads/news/'.$img['image_path'];
if(!is_file($path)){ http_response_code(404); exit; }
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
header('Content-Type: '.($ext==='png'?'image/png':($ext==='webp'?'image/webp':'image/jpeg')));
header('Cache-Control: public, max-age=86400');
header('Content-Length: '.filesize($path));
readfile($path); exit;
}
if($p==='about'){
$body='<div class="head"><h2>О проекте</h2><a class="btn ghost" href="'.esc($u?role_home($u):'?p=home').'">← назад</a></div>';
$body.='<div class="card pad" style="line-height:1.7">';
$body.='<p>«Урок Мужества» — платформа, которая соединяет школы и колледжи Тюменской области, фонды-координаторы и спикеров-ветеранов специальной военной операции для проведения уроков мужества.</p>';
$body.='<p>Проект реализуется в рамках региональной программы «Боевой кадровый резерв Тюменской области» при участии филиала Государственного фонда поддержки участников СВО «Защитники Отечества» в Тюменской области, ТРОО «Держава», Департамента образования Администрации города Тюмени и Департамента образования и науки Тюменской области.</p>';
$body.='<p>Как это работает: школа или колледж подаёт заявку на сайте — фонд-координатор подбирает и назначает спикера-ветерана с учётом района и возрастной группы аудитории — после урока школа и спикер оставляют отзывы о занятии.</p>';
$body.='<p>Цель проекта — живое общение подростков с ветеранами СВО: рассказы о мужестве, воинском долге и взаимовыручке, которые формируют уважение к защитникам Отечества.</p>';
$body.='</div>';
$body.='<div class="pub-cta" style="margin-top:20px"><h3>Хотите стать частью программы?</h3><p>Подайте заявку на урок от имени школы или колледжа — либо зарегистрируйтесь как спикер-ветеран.</p><a class="btn primary btn-lg" href="?p=register">Зарегистрироваться</a></div>';
layout('О проекте',$body); exit;
}
if($p==='faq'){
$faq=[
['Кто может подать заявку на Урок Мужества?','Школы, колледжи и вузы Тюменской области — заявку подаёт представитель организации после регистрации на сайте и подтверждения профильным департаментом образования.'],
['Как стать спикером-ветераном?','Зарегистрируйтесь на сайте с ролью «спикер», выберите фонд-координатор — он проверит и подтвердит вашу регистрацию, после чего вы сможете получать приглашения на уроки.'],
['Сколько стоит участие в программе?','Участие бесплатно для школ, колледжей и спикеров-ветеранов.'],
['Как быстро назначат спикера на заявку?','Заявка сразу становится видна всем фондам региона — как только один из них подберёт и назначит спикера, статус заявки обновится, а заявитель получит уведомление.'],
['Что делать, если по заявке долго нет ответа?','Обратитесь в фонд, который работает с вашим районом, либо напишите нам на '.esc(cfg()['support_email']).'.'],
['Как обрабатываются персональные данные?','В соответствии с 152-ФЗ — подробности в <a href="?p=privacy">Политике обработки персональных данных</a>.'],
['Куда обращаться по техническим вопросам?','Напишите на '.esc(cfg()['support_email']).' — опишите проблему и укажите номер заявки, если он есть.'],
];
$body='<div class="head"><h2>Вопросы и ответы</h2><a class="btn ghost" href="'.esc($u?role_home($u):'?p=home').'">← назад</a></div>';
foreach($faq as [$q,$a]) $body.='<details class="card pad" style="margin-bottom:10px"><summary style="cursor:pointer;font-weight:600">'.esc($q).'</summary><p style="margin:10px 0 0;color:var(--muted);line-height:1.6">'.$a.'</p></details>';
layout('Вопросы и ответы',$body); exit;
}
if($p==='contacts'){
$body='<div class="head"><h2>Контакты</h2><a class="btn ghost" href="'.esc($u?role_home($u):'?p=home').'">← назад</a></div>';
$body.='<div class="card pad" style="line-height:1.7">';
$body.='<p>По общим и техническим вопросам работы сайта — <a href="mailto:'.esc(cfg()['support_email']).'">'.esc(cfg()['support_email']).'</a>.</p>';
$body.='<p>По вопросам конкретной заявки — обратитесь к фонду или департаменту, который её обрабатывает (указан в карточке заявки).</p>';
$body.='<table><thead><tr><th>Оператор</th><th>Контакт</th></tr></thead><tbody>';
$body.='<tr><td>Филиал Государственного фонда поддержки участников СВО «Защитники Отечества» в Тюменской области</td><td>+7 (3452) 69-69-02, rf72@fzo.gov.ru</td></tr>';
$body.='<tr><td>ТРОО участников СВО «Держава»</td><td>powerltd@mail.ru</td></tr>';
$body.='<tr><td>ДОА г. Тюмени (Департамент образования Администрации города Тюмени)</td><td>+7 (3452) 56-13-40, education@tyumen-city.ru</td></tr>';
$body.='<tr><td>ДОиН ТО (Департамент образования и науки Тюменской области)</td><td>+7 (3452) 56-93-00, dep_obraz@72to.ru</td></tr>';
$body.='</tbody></table>';
$body.='<p class="muted" style="margin-top:14px">Полные реквизиты операторов — в <a href="?p=privacy">Политике обработки персональных данных</a>.</p>';
$body.='</div>';
layout('Контакты',$body); exit;
}
if($p==='news_admin'){
require_role(['operator','admin']);
$items=list_news(false);
$covers=news_cover_images(array_column($items,'id'));
$counts=news_image_counts(array_column($items,'id'));
$body='<div class="head"><h2>Новости сайта</h2><div style="display:flex;gap:8px">'.($u['role']==='operator'?'<a class="btn ghost" href="'.esc(role_home($u)).'">← в кабинет</a>':'').'<a class="btn primary" href="?p=news_edit">+ Добавить новость</a></div></div>';
if(!$items){
$body.='<div class="empty-state"><div class="empty-icon">📰</div><h3>Новостей пока нет</h3><p class="muted">Добавьте первую новость кнопкой выше — она появится на стартовой странице.</p></div>';
} else {
$body.='<table class="card-view"><thead><tr><th>Фото</th><th>Заголовок</th><th>Статус</th><th>Дата</th><th></th></tr></thead><tbody>';
foreach($items as $n){
$cov=$covers[$n['id']]??null; $cnt=$counts[$n['id']]??0;
$thumb=$cov?'<img src="?p=news_image&id='.$cov['id'].'" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:6px">'.($cnt>1?' <span class="muted" style="font-size:11px">+'.($cnt-1).'</span>':''):'<span class="muted">—</span>';
$body.='<tr><td data-label="Фото">'.$thumb.'</td><td class="id"><a href="?p=news_edit&id='.$n['id'].'">'.esc($n['title']).'</a></td><td>'.($n['published']?'<span class="pill green">опубликована</span>':'<span class="pill gray">черновик</span>').'</td><td class="muted">'.esc(substr((string)$n['created_at'],0,10)).'</td><td style="white-space:nowrap"><a class="btn ghost sm" href="?p=news_edit&id='.$n['id'].'">✏️</a> <form method="post" style="display:inline" onsubmit="return confirm(\'Удалить новость?\')">'.csrf_field().'<input type="hidden" name="action" value="delete_news"><input type="hidden" name="id" value="'.$n['id'].'"><button type="submit" class="btn-delete">Удалить</button></form></td></tr>';
}
$body.='</tbody></table>';
}
layout('Новости сайта',$body); exit;
}
if($p==='news_edit'){
require_role(['operator','admin']);
$id=(int)($_GET['id']??0); $n=$id?get_news($id):null;
if($id && !$n){ layout('Новость','<div class="card pad">Новость не найдена. <a href="?p=news_admin">Назад</a></div>'); exit; }
$existingImages=$id?list_news_images($id):[];
$body='<div class="head"><h2>'.($n?'Редактирование новости':'Добавить новость').'</h2><a class="btn ghost" href="?p=news_admin">← к новостям</a></div>';
$body.='<form method="post" class="upload-form" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="'.($n?'update_news':'add_news').'">'.($n?'<input type="hidden" name="id" value="'.$n['id'].'">':'');
$body.='<div class="form-group"><label class="form-label">Заголовок <span style="color:#d11">*</span></label><input class="form-input" name="title" required value="'.esc($n['title']??'').'"></div>';
$body.='<div class="form-group"><label class="form-label">Текст <span style="color:#d11">*</span></label><textarea class="form-textarea" name="body" required rows="8">'.esc($n['body']??'').'</textarea></div>';
if($existingImages){
$body.='<div class="form-group"><label class="form-label">Текущие фото</label><div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px">';
foreach($existingImages as $img) $body.='<div style="text-align:center"><img src="?p=news_image&id='.$img['id'].'" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:8px;display:block;margin-bottom:4px"><label class="chk" style="font-size:12px"><input type="checkbox" name="delete_image[]" value="'.$img['id'].'"> удалить</label></div>';
$body.='</div></div>';
}
$body.='<div class="form-group"><label class="form-label">Добавить фото (можно выбрать несколько)</label><div class="file-upload-wrapper"><input class="file-input" type="file" name="images[]" id="news_images" accept="image/*" multiple onchange="document.getElementById(\'news_images_name\').textContent=this.files.length?this.files.length+\' файл(ов) выбрано\':\'Файлы не выбраны\';"><label for="news_images" class="file-upload-label"><span>📎</span><span class="file-upload-text">Нажмите, чтобы выбрать фото</span><span class="file-name" id="news_images_name">Файлы не выбраны</span></label></div></div>';
$body.='<div class="form-group"><label class="form-label">Ссылка на публикацию школы</label><input class="form-input" type="url" name="school_post_url" value="'.esc($n['school_post_url']??'').'" placeholder="https://..."><p class="form-hint">Необязательно — например, ссылка на пост школы в соцсети об этом событии.</p></div>';
$body.='<div class="form-group"><label class="chk"><input type="checkbox" name="published" '.(($n['published']??1)?'checked':'').'> Опубликовано (видно на сайте)</label></div>';
$body.='<div class="form-actions"><button class="btn primary btn-lg">Сохранить</button></div></form>';
layout('Новость',$body); exit;
}
// ========== ПРОФИЛЬ СПИКЕРА (просмотр Фондом/Департаментом) ==========
if($p==='speaker_view'){
require_login();
$sp=find_user_by_id((int)($_GET['id']??0));
if(!$sp || $sp['role']!=='speaker'){ layout('Спикер','<div class="card pad">Спикер не найден.</div>'); exit; }
if($u['role']==='fund' && !in_array((int)($sp['fund_user_id']??0),fund_scope_ids($u),true)){ exit('Нет доступа.'); }
if(!in_array($u['role'],['fund','operator'],true)) exit('Нет доступа.');
$back=$u['role']==='fund'?'?p=fund&tab=speakers':'?p=operator&tab=speakers';
$g=array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$sp['age_groups'])));
$body='<div class="head"><h2>'.esc($sp['display_name']).'</h2><div><a class="btn ghost" href="?p=speaker_stats&id='.$sp['id'].'">📊 Статистика</a> <a class="btn ghost" href="'.esc($back).'">← К списку спикеров</a></div></div>';
if(speaker_has_criminal_record($sp)) $body.='<div class="hint-warn" style="background:#fdecea;border-left-color:#b23b2c;color:#7a2418"><b>⚠ Спикер указал наличие судимости.</b> В соответствии со ст. 351.1 ТК РФ он не может проводить занятия с детьми — недоступен для подбора ни по одной заявке.</div>';
$body.='<div class="card pad" style="max-width:640px;display:flex;gap:18px;flex-wrap:wrap">';
if(!empty($sp['photo_path'])) $body.='<img src="?p=speaker_photo&id='.$sp['id'].'" alt="" style="width:140px;height:140px;object-fit:cover;border-radius:12px;border:1px solid var(--line);flex:0 0 auto">';
else $body.='<div class="muted" style="width:140px;height:140px;border-radius:12px;border:1px dashed var(--line2);display:flex;align-items:center;justify-content:center;flex:0 0 auto">нет фото</div>';
$body.='<dl class="kv" style="flex:1 1 300px"><dt>Организация (Фонд)</dt><dd>'.esc(speaker_org_name($sp['id'])?:'—').'</dd><dt>Район</dt><dd>'.esc($sp['district']?:'—').'</dd><dt>Телефон</dt><dd>'.esc($sp['phone']?:'—').'</dd><dt>Возрастные группы</dt><dd>'.esc(implode(', ',$g)?:'—').'</dd><dt>Звание</dt><dd>'.esc($sp['sp_rank']?:'—').'</dd><dt>Род войск / где служил</dt><dd>'.esc($sp['sp_branch']?:'—').'</dd></dl>';
$body.='</div>';
if(!empty($sp['sp_awards'])) $body.='<div class="block"><h4>Награды</h4><p>'.nl2br(esc($sp['sp_awards'])).'</p></div>';
if(!empty($sp['sp_achievements'])) $body.='<div class="block"><h4>Достижения</h4><p>'.nl2br(esc($sp['sp_achievements'])).'</p></div>';
if(!empty($sp['sp_hobbies'])) $body.='<div class="block"><h4>Увлечения, хобби</h4><p>'.esc($sp['sp_hobbies']).'</p></div>';
if(!empty($sp['sp_bio'])) $body.='<div class="block"><h4>О себе</h4><p>'.nl2br(esc($sp['sp_bio'])).'</p></div>';
// ========== АНКЕТА САМООЦЕНКИ ПСИХОЛОГИЧЕСКОЙ ГОТОВНОСТИ + ДОПУСК К ВОЗРАСТНЫМ ГРУППАМ ==========
foreach(psych_readiness_blocks() as $pgk=>$pb){
$plast=speaker_psych_readiness_last($sp['id'],$pgk);
if($plast && !empty($plast['acute'])) $body.='<div class="hint-warn"><b>ⓘ Возможный признак острой реакции</b> — анкета психологической готовности, «'.esc($pb['label']).'» ('.esc(substr((string)$plast['created_at'],0,16)).'). '.esc(psych_readiness_acute_note()).'</div>';
}
$body.='<div class="block"><h4>🎓 Допуск к возрастным группам</h4><p class="muted">'.esc(psych_readiness_intro()['purpose']).' По результатам анкеты фонд/оператор принимает решение о допуске — переключатель становится активным после того, как спикер хотя бы раз заполнил анкету по этой группе.</p>';
$certified=speaker_certified_groups($sp);
$body.='<table class="card-view"><thead><tr><th>Группа</th><th>Анкета</th><th>Баллы</th><th>Допуск</th></tr></thead><tbody>';
foreach(psych_readiness_blocks() as $pgk=>$pb){
$plast=speaker_psych_readiness_last($sp['id'],$pgk);
$isCert=in_array($pgk,$certified,true);
if($plast){ $pmeta=psych_readiness_status_meta($plast['status']); $pStatusPill='<span class="pill '.esc($pmeta['color']).'">'.esc($pmeta['label']).'</span>'.(!empty($plast['acute'])?' <span class="pill" style="background:#fdecea;color:#b23b2c">возможная острая реакция</span>':''); $pScoreTxt=esc($plast['score']).' из 60 · '.esc(substr((string)$plast['created_at'],0,16)); }
else { $pStatusPill='<span class="muted">не заполнена</span>'; $pScoreTxt='—'; }
if($isCert){
$toggleCell='<span class="pill green">✓ Допущен</span> <form method="post" style="display:inline"><input type="hidden" name="action" value="set_speaker_certification"><input type="hidden" name="speaker_id" value="'.$sp['id'].'"><input type="hidden" name="age_group" value="'.esc($pgk).'">'.csrf_field().'<button class="btn ghost sm">Отключить</button></form>';
} elseif($plast){
$toggleCell='<span class="pill gray">не допущен</span> <form method="post" style="display:inline"><input type="hidden" name="action" value="set_speaker_certification"><input type="hidden" name="speaker_id" value="'.$sp['id'].'"><input type="hidden" name="age_group" value="'.esc($pgk).'"><input type="hidden" name="on" value="1">'.csrf_field().'<button class="btn primary sm">Включить допуск</button></form>';
} else {
$toggleCell='<span class="muted">сначала анкета</span>';
}
$body.='<tr><td data-label="Группа">'.esc($pb['label']).'</td><td data-label="Анкета">'.$pStatusPill.'</td><td data-label="Баллы">'.$pScoreTxt.'</td><td data-label="Допуск">'.$toggleCell.'</td></tr>';
}
$body.='</tbody></table>';
$pHistory=speaker_psych_readiness_history($sp['id']);
if($pHistory){
$body.='<details style="margin-top:12px"><summary style="cursor:pointer;font-weight:600;color:var(--navy)">Разбор по линиям и вся история заполнений ('.count($pHistory).')</summary><div style="margin-top:10px">';
$pLines=psych_readiness_lines();
foreach($pHistory as $patt){
$pmeta=psych_readiness_status_meta($patt['status']);
$blocks5=psych_readiness_blocks();
$pLabel=$blocks5[$patt['age_group']]['label']??$patt['age_group'];
$body.='<div class="card pad" style="margin-bottom:10px"><b>'.esc($pLabel).'</b> — '.esc($patt['score']).' из 60 · '.esc(substr((string)$patt['created_at'],0,16)).' <span class="pill '.esc($pmeta['color']).'">'.esc($pmeta['label']).'</span>'.(!empty($patt['acute'])?' <span class="pill" style="background:#fdecea;color:#b23b2c">возможная острая реакция</span>':'');
$sums=psych_readiness_line_breakdown($patt);
$body.='<ul style="margin:8px 0 0;padding-left:20px">';
foreach($pLines as $ln=>$lname) $body.='<li>Линия '.$ln.'. '.esc($lname).': <b>'.($sums[$ln]??0).' из 12</b></li>';
$body.='</ul>';
$body.='</div>';
}
$body.='</div></details>';
}
$body.='</div>';
// ========== АНКЕТА САМООЦЕНКИ ГОТОВНОСТИ ==========
$acuteSignals=speaker_readiness_acute_signals($sp['id']);
foreach($acuteSignals as $bk=>$acute){
$body.='<div class="hint-warn"><b>ⓘ Острая реакция на анкету самооценки</b> ('.esc(substr((string)$acute['created_at'],0,16)).', блок «'.esc(readiness_blocks()[$bk]['label']??$bk).'»). Это не означает «не готов» — мягко уточните состояние спикера лично, при необходимости порекомендуйте обращение к психологу. Решение о допуске к занятиям в этом случае лучше отложить до прояснения ситуации, а не выносить по баллам.</div>';
}
$body.='<div class="block"><h4>🧭 Анкета самооценки готовности</h4><p class="muted">Организационный ориентир для куратора, не автоматический допуск — заполняется спикером самостоятельно.</p>';
$body.='<table class="card-view"><thead><tr><th>Блок</th><th>Статус</th><th>Баллы</th><th>Дата</th></tr></thead><tbody>';
foreach(readiness_blocks() as $bk=>$b){
$last=speaker_readiness_last($sp['id'],$bk);
if($last){ $meta=readiness_status_meta($last['status']); $statusPill='<span class="pill '.esc($meta['color']).'">'.esc($meta['label']).'</span>'; $scoreTxt=esc($last['score']).' из 20'; $dateTxt=esc(substr((string)$last['created_at'],0,16)); }
else { $statusPill='<span class="muted">не заполнена</span>'; $scoreTxt='—'; $dateTxt='—'; }
$body.='<tr><td data-label="Блок">'.esc($b['label']).'</td><td data-label="Статус">'.$statusPill.'</td><td data-label="Баллы">'.$scoreTxt.'</td><td data-label="Дата">'.$dateTxt.'</td></tr>';
}
$body.='</tbody></table>';
$rHistory=speaker_readiness_history($sp['id']);
if($rHistory){
$body.='<details style="margin-top:12px"><summary style="cursor:pointer;font-weight:600;color:var(--navy)">Вся история заполнений и ответы ('.count($rHistory).')</summary><div style="margin-top:10px">';
foreach($rHistory as $att){
$meta=readiness_status_meta($att['status']);
$finalMeta=readiness_final_question()['options'][$att['final_answer']]??'';
$body.='<div class="card pad" style="margin-bottom:10px"><b>'.esc(readiness_blocks()[$att['block_key']]['label']??$att['block_key']).'</b> — '.esc($att['score']).' из 20 · '.esc(substr((string)$att['created_at'],0,16)).' <span class="pill '.esc($meta['color']).'">'.esc($meta['label']).'</span>';
$body.='<p class="muted" style="margin:6px 0 0">Итоговый вопрос: '.esc($finalMeta).($att['final_answer']==='D'?' <span class="pill" style="background:#fdecea;color:#b23b2c">острая реакция</span>':'').'</p>';
$detail=json_decode((string)$att['answers'],true)?:[];
if($detail){
$body.='<ol style="margin:8px 0 0;padding-left:20px">';
foreach($detail as $d) $body.='<li style="margin-bottom:4px">'.esc($d['question']).' <span class="muted">(ответ: '.esc($d['given_label']).')</span></li>';
$body.='</ol>';
}
$body.='</div>';
}
$body.='</div></details>';
}
$body.='</div>';
layout('Спикер: '.$sp['display_name'],$body); exit;
}
// ========== ПРОФИЛЬ ==========
if($p==='profile'){
require_login(); $u=current_user();
$isMember=$u['role']==='school' && !empty($u['parent_school_id']);
$dnLabel=['school'=>'Наименование организации','speaker'=>'ФИО','fund'=>'Название Фонда','operator'=>'Наименование (оператор / департамент)'][$u['role']]??'Отображаемое имя';
$cur=($u['role']==='school')?($u['org_name']?:$u['display_name']):$u['display_name'];
$body='<div class="head"><h2>Профиль</h2><a class="btn ghost" href="?p='.esc($u['role']).'">← в кабинет</a></div>';
if($isMember){ $effP=school_effective($u); $body.='<div class="card pad" style="max-width:560px;margin-bottom:12px"><p class="muted" style="margin:0">Вы — сотрудник организации</p><h3 style="margin:4px 0">'.esc($effP['org_name']).'</h3><p class="muted" style="margin:0">'.esc(level_label($effP['level'])).(!empty($effP['district'])?', '.esc($effP['district']):'').'</p><p class="muted" style="margin:8px 0 0">Название и адрес организации редактирует представитель, зарегистрировавший её.</p></div>'; }
$body.='<form method="post" enctype="multipart/form-data" class="card pad" style="max-width:560px">'.csrf_field().'<input type="hidden" name="action" value="update_profile">';
$body.='<p class="muted">Логин: <b>'.esc($u['login']).'</b> (не меняется).</p>';
if(!$isMember){ $body.='<label class="lbl">'.$dnLabel.' <span style="color:#d11">*</span></label><input name="display_name" required value="'.esc($cur).'">'; }
$body.='<label class="lbl">Email <span style="color:#d11">*</span></label><input type="email" name="email" required value="'.esc($u['email']).'">';
$distOptP=''; foreach(district_names() as $dn) $distOptP.='<option '.(($u['district']??'')===$dn?'selected':'').'>'.esc($dn).'</option>';
$body.='<label class="lbl">Район Тюменской области</label><select name="district" id="pdistrict_sel" onchange="pdsw()"><option value="">— выберите район —</option>'.$distOptP.'<option value="__other__">Другой (вписать вручную)</option></select>';
$body.='<div id="pdistrict_wrap" style="display:none"><label class="lbl">Впишите район</label><input name="district_other" placeholder="название района"></div>';
if($u['role']==='speaker'){ $body.='<label class="lbl">Телефон для связи <span style="color:#d11">*</span></label><input name="phone" required value="'.esc($u['phone']??'').'">';
$fo='<option value="">— выберите организацию (фонд) —</option>'; foreach(all_funds() as $ff){ $fo.='<option value="'.$ff['id'].'"'.((int)($u['fund_user_id']??0)===(int)$ff['id']?' selected':'').'>'.esc(fund_label($ff)).'</option>'; }
$body.='<label class="lbl">Организация (фонд)</label><select name="fund_user_id">'.$fo.'</select><p class="muted" style="margin:4px 0 0">Организация, которая направляет вас как спикера.</p>';
$body.='<label class="lbl" style="margin-top:14px">Фотография</label>';
if(!empty($u['photo_path'])) $body.='<div style="margin-bottom:8px"><img src="?p=speaker_photo&id='.$u['id'].'" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:10px;border:1px solid var(--line)"></div>';
$body.='<input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp"><p class="muted" style="margin:4px 0 8px">Фото используется в профиле, который видит Фонд. JPG/PNG/WEBP, до 8 МБ.</p>';
$body.='<h4 style="margin-top:16px">Информация о спикере</h4><p class="muted" style="margin:0 0 8px">Эти сведения увидит Фонд, а после подтверждения вас на урок — заявитель, чтобы правильно вас представить.</p>';
$body.='<label class="lbl">Звание</label><input name="sp_rank" value="'.esc($u['sp_rank']??'').'" placeholder="напр. старший лейтенант">';
$body.='<label class="lbl">Род войск / где служил</label><input name="sp_branch" value="'.esc($u['sp_branch']??'').'" placeholder="напр. ВДВ, мотострелковые войска">';
$body.='<label class="lbl">Награды</label><textarea name="sp_awards" placeholder="медали, ордена и т.п.">'.esc($u['sp_awards']??'').'</textarea>';
$body.='<label class="lbl">Достижения</label><textarea name="sp_achievements">'.esc($u['sp_achievements']??'').'</textarea>';
$body.='<label class="lbl">Увлечения, хобби</label><input name="sp_hobbies" value="'.esc($u['sp_hobbies']??'').'">';
$body.='<label class="lbl">Коротко о себе</label><textarea name="sp_bio" placeholder="как вас представить школьникам">'.esc($u['sp_bio']??'').'</textarea>';
$body.='<h4 style="margin-top:16px">Судимость</h4><p class="muted" style="margin:0 0 8px">Обязательно по закону (ст. 351.1 ТК РФ) для допуска к работе с детьми. Текущий ответ: <b>'.($u['criminal_record']==='yes'?'есть судимость':($u['criminal_record']==='no'?'судимости нет':'не указано')).'</b>.</p>';
$body.='<label class="lbl">Есть ли у Вас судимость (снятая или непогашенная)?</label><select name="criminal_record" id="pcrim_sel" onchange="pcrimSw()"><option value="">— не менять —</option><option value="no"'.(($u['criminal_record']??'')==='no'?' selected':'').'>Нет</option><option value="yes"'.(($u['criminal_record']??'')==='yes'?' selected':'').'>Да</option></select>';
$body.='<div id="pcrim_no_fields" style="display:none"><label class="lbl">Дата справки о несудимости</label><input type="date" name="criminal_cert_date" value="'.esc($u['criminal_cert_date']??'').'">';
if(!empty($u['criminal_cert_path'])) $body.='<p class="muted" style="margin:4px 0">Справка уже загружена (от '.esc($u['criminal_cert_date']??'—').'). Загрузите новый файл, только если нужно её заменить.</p>';
$body.='<label class="lbl">Справка о несудимости (PDF/JPG/PNG)</label><input type="file" name="criminal_cert" accept=".pdf,.jpg,.jpeg,.png"></div>';
$body.='<script>function pcrimSw(){var v=document.getElementById("pcrim_sel").value;document.getElementById("pcrim_no_fields").style.display=(v==="no")?"block":"none";}pcrimSw();</script>';
}
if($u['role']==='school' && !$isMember){
$curOg=school_dept_group($u);
$body.='<label class="lbl">Департамент <span style="color:#d11">*</span></label><select name="school_op_group" required><option value="school"'.($curOg==='school'?' selected':'').'>Департамент образования Администрации города Тюмени (школы)</option><option value="region"'.($curOg==='region'?' selected':'').'>Департамент образования и науки Тюменской области (СПО и вузы)</option></select><p class="muted" style="margin:2px 0 8px">Какой департамент модерирует ваши заявки. Если выбран неверно — поменяйте здесь.</p>';
$body.='<label class="lbl">Адрес местонахождения <span style="color:#d11">*</span></label><input name="address" required value="'.esc($u['address']??'').'" placeholder="г. Тюмень, ул. ..., д. ...">';
$body.='<label class="lbl">№ корпуса <span style="color:#d11">*</span></label><input name="building" required value="'.esc($u['building']??'').'" placeholder="напр. 1">';
$body.='<p class="muted" style="margin:2px 0 8px">Если у организации нет отдельных корпусов, укажите «1».</p>';
}
if(in_array($u['role'],['school','fund','operator'],true)){
$body.='<label class="lbl">ФИО контактного лица <span style="color:#d11">*</span></label><input name="contact_name" required value="'.esc($u['contact_name']??'').'">';
$body.='<label class="lbl">Должность <span style="color:#d11">*</span></label><input name="position" required value="'.esc($u['position']??'').'">';
$body.='<label class="lbl">Телефон <span style="color:#d11">*</span></label><input name="phone" required value="'.esc($u['phone']??'').'">';
}
$body.='<label class="lbl">Новый пароль (необязательно)</label><input type="password" name="newpass" placeholder="от 10 символов">';
$body.='<button class="btn primary" style="margin-top:12px">Сохранить</button></form>';
if($u['role']==='school' && !$isMember) $body.='<p class="muted" style="max-width:560px">При смене наименования ранее поданные заявки сохранят старое название.</p>';
$body.='<script>function pdsw(){var w=document.getElementById("pdistrict_wrap");if(w)w.style.display=(document.getElementById("pdistrict_sel").value==="__other__")?"block":"none";}</script>';
if(push_enabled()){
$body.='<div class="card pad" style="max-width:560px;margin-top:16px"><h4>Push-уведомления</h4><p class="muted" id="pushStatus">Проверка…</p><button type="button" class="btn ghost" id="pushBtn" style="display:none"></button></div>';
$body.='<script>(function(){
var VAPID_KEY="'.esc(vapid_public_key()).'";
function urlBase64ToUint8Array(base64String){
var padding="=".repeat((4-base64String.length%4)%4);
var base64=(base64String+padding).replace(/-/g,"+").replace(/_/g,"/");
var raw=atob(base64),out=new Uint8Array(raw.length);
for(var i=0;i<raw.length;i++) out[i]=raw.charCodeAt(i);
return out;
}
var statusEl=document.getElementById("pushStatus"),btn=document.getElementById("pushBtn");
if(!btn) return;
if(!("serviceWorker" in navigator) || !("PushManager" in window)){ statusEl.textContent="Ваш браузер не поддерживает push-уведомления."; return; }
function refresh(){
navigator.serviceWorker.ready.then(function(reg){
reg.pushManager.getSubscription().then(function(sub){
if(sub){ statusEl.textContent="Уведомления включены на этом устройстве."; btn.textContent="Отключить уведомления"; btn.dataset.mode="off"; }
else { statusEl.textContent="Получайте уведомления о новых заявках и назначениях прямо на телефон."; btn.textContent="Разрешить уведомления"; btn.dataset.mode="on"; }
btn.style.display="inline-block";
});
});
}
refresh();
function postCsrf(action,extra){
var f=document.createElement("form"); f.method="post"; f.style.display="none";
var csrf=document.querySelector(\'input[name="csrf"]\').value;
var fields=Object.assign({action:action,csrf:csrf},extra||{});
for(var k in fields){ var inp=document.createElement("input"); inp.name=k; inp.value=fields[k]; f.appendChild(inp); }
document.body.appendChild(f); f.submit();
}
btn.addEventListener("click",function(){
if(btn.dataset.mode==="off"){
navigator.serviceWorker.ready.then(function(reg){
reg.pushManager.getSubscription().then(function(sub){
if(!sub) return;
var endpoint=sub.endpoint;
sub.unsubscribe().then(function(){ postCsrf("push_unsubscribe",{endpoint:endpoint}); });
});
});
return;
}
Notification.requestPermission().then(function(perm){
if(perm!=="granted"){ statusEl.textContent="Уведомления не разрешены в браузере."; return; }
navigator.serviceWorker.ready.then(function(reg){
reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:urlBase64ToUint8Array(VAPID_KEY)}).then(function(sub){
var j=sub.toJSON();
postCsrf("push_subscribe",{endpoint:j.endpoint,p256dh:j.keys.p256dh,auth:j.keys.auth});
}).catch(function(err){ statusEl.textContent="Не удалось подписаться: "+err; });
});
});
});
})();</script>';
}
layout('Профиль',$body); exit;
}
// ========== РЕГИСТРАЦИЯ ==========
if($p==='register'){
$old=pull_old_input();
$ag=''; foreach(ages() as $k=>$v) $ag.='<label class="chk"><input type="checkbox" name="groups['.esc($k).']"'.ochk($old,'groups',$k).'> '.esc($v).'</label>';
$distOpt=''; foreach(district_names() as $dn) $distOpt.='<option'.osel($old,'district',$dn).'>'.esc($dn).'</option>';
$fundOpt='<option value="">— выберите фонд —</option>'; foreach(all_funds() as $ff) $fundOpt.='<option value="'.$ff['id'].'"'.osel($old,'fund_user_id',$ff['id']).'>'.esc(fund_label($ff)).'</option>';
$headFundOpt='<option value="">— выберите головной фонд —</option>'; foreach(head_funds() as $ff) $headFundOpt.='<option value="'.$ff['id'].'"'.osel($old,'parent_fund_id',$ff['id']).'>'.esc(fund_label($ff)).'</option>';
$schoolOpt='<option value="">— выберите организацию —</option>'; foreach(head_schools() as $sc) $schoolOpt.='<option value="'.$sc['id'].'"'.osel($old,'parent_school_id',$sc['id']).'>'.esc(school_label($sc)).'</option>';
$preRoleRaw=$old['role']??($_GET['role']??''); $preRole=in_array($preRoleRaw,['school','speaker','fund','operator'],true)?$preRoleRaw:'school';
$roleOpt=''; foreach(['school'=>'Образовательная организация (заявитель)','speaker'=>'Спикер (ветеран)','fund'=>'Фонд (направляет спикеров)','operator'=>'Оператор'] as $rk=>$rl) $roleOpt.='<option value="'.$rk.'"'.($rk===$preRole?' selected':'').'>'.esc($rl).'</option>';
$body='<div class="auth"><h2>Регистрация</h2><p class="muted" style="max-width:480px">Не уверены, что выбрать? Посмотрите <a href="?p=guide">как устроена работа по каждой роли</a> — от регистрации до завершения урока.</p><form method="post" enctype="multipart/form-data" class="card pad" id="regform">'.csrf_field().'<input type="hidden" name="action" value="register">
<label class="lbl">Кто вы?</label><select name="role" id="role" onchange="rsw()">'.$roleOpt.'</select><p class="muted" style="margin:2px 0 8px">От выбора роли зависит, какие поля нужно заполнить и что вы сможете делать в личном кабинете.</p>
<h4 style="margin-top:16px">Учётные данные</h4>
<label class="lbl">Логин <span style="color:#d11">*</span></label><input name="login" required value="'.ov($old,'login').'"><label class="lbl">Пароль (от 10 символов) <span style="color:#d11">*</span></label><input type="password" name="pass" required>
<label class="lbl">Email (для уведомлений) <span style="color:#d11">*</span></label><input type="email" name="email" required value="'.ov($old,'email').'"><p class="muted" style="margin:2px 0 8px">На этот адрес будут приходить уведомления об изменении статуса заявок.</p>
<label class="lbl">Район Тюменской области <span style="color:#d11">*</span></label><select name="district" id="district_sel" onchange="dsw()" required><option value="">— выберите район —</option>'.$distOpt.'<option value="__other__"'.osel($old,'district','__other__').'>Другой (вписать вручную)</option></select>
<div id="district_other_wrap" style="display:none"><label class="lbl">Впишите район <span style="color:#d11">*</span></label><input name="district_other" placeholder="название района" id="district_other" value="'.ov($old,'district_other').'"></div>
<div class="rf school"><label class="lbl">Тип регистрации</label><select name="school_type" id="school_type" onchange="sctsw()"><option value="owner"'.osel($old,'school_type','owner').'>Регистрирую организацию впервые</option><option value="member"'.osel($old,'school_type','member').'>Я сотрудник уже зарегистрированной организации</option></select><p class="muted" style="margin:2px 0 8px">Если организация уже зарегистрирована в системе (например, вашим коллегой), выберите второй вариант — не нужно создавать дубликат, и вы сразу увидите общие заявки организации.</p>
<div id="school_owner_fields"><p class="muted" style="margin:0 0 8px">После регистрации заявку проверит профильный департамент образования. Адрес и контакты нужны, чтобы Фонд и департамент могли согласовать визит спикера.</p><h4>Организация</h4><label class="lbl">Наименование организации <span style="color:#d11">*</span></label><input name="org_name" id="reg_org_name" data-req value="'.ov($old,'org_name').'"><label class="lbl">Уровень</label><select name="level" id="level_sel" onchange="lvlSync()"><option value="school"'.osel($old,'level','school').'>Школа</option><option value="spo"'.osel($old,'level','spo').'>Профессиональная образовательная организация</option><option value="vuz"'.osel($old,'level','vuz').'>Высшее учебное заведение</option><option value="other"'.osel($old,'level','other').'>Иное (спортивная школа, детский дом и т.п.)</option></select><label class="lbl">Департамент <span style="color:#d11">*</span></label><select name="school_op_group" id="school_og" data-req><option value="school"'.osel($old,'school_op_group','school').'>Департамент образования Администрации города Тюмени (школы)</option><option value="region"'.osel($old,'school_op_group','region').'>Департамент образования и науки Тюменской области (СПО и вузы)</option></select><p class="muted" style="margin:2px 0 8px">Определяет, какой департамент будет модерировать вашу регистрацию и заявки. Подставляется по уровню организации — при необходимости выберите вручную.</p><label class="lbl">Адрес местонахождения <span style="color:#d11">*</span></label><input name="address" id="reg_address" placeholder="г. Тюмень, ул. ..., д. ..." data-req value="'.ov($old,'address').'"><label class="lbl">№ корпуса <span style="color:#d11">*</span></label><input name="building" id="reg_building" placeholder="напр. 1" data-req value="'.ov($old,'building').'"><p class="muted" style="margin:2px 0 8px">Если у организации нет отдельных корпусов, укажите «1».</p></div>
<div id="school_member_fields" style="display:none"><label class="lbl">Организация <span style="color:#d11">*</span></label><select name="parent_school_id" id="parent_school_id">'.$schoolOpt.'</select><p class="muted" style="margin:2px 0 8px">Не нашли свою организацию? Значит, никто из коллег ещё не регистрировался — выберите «Регистрирую организацию впервые» выше.</p></div>
<h4>Контактное лицо</h4><label class="lbl">ФИО контактного лица <span style="color:#d11">*</span></label><input name="contact_name" data-req value="'.ov($old,'contact_name').'"><label class="lbl">Должность <span style="color:#d11">*</span></label><input name="position" data-req value="'.ov($old,'position').'"><label class="lbl">Телефон <span style="color:#d11">*</span></label><input name="contact_phone" data-req value="'.ov($old,'contact_phone').'"><p class="muted" style="margin:2px 0 0">Контакты для согласования даты, времени и деталей визита спикера.</p></div>
<div class="rf speaker" style="display:none"><p class="muted" style="margin:0 0 8px">Регистрацию подтверждает выбранный Фонд — он же будет направлять вас на уроки и согласовывать состав.</p><label class="lbl">ФИО <span style="color:#d11">*</span></label><input name="display_name" data-req value="'.ov($old,'display_name').'"><label class="lbl">Телефон для связи <span style="color:#d11">*</span></label><input name="phone" data-req value="'.ov($old,'phone').'"><label class="lbl">Фонд <span style="color:#d11">*</span></label><select name="fund_user_id" data-req>'.$fundOpt.'</select><label class="lbl">Возрастные группы <span style="color:#d11">*</span></label><div class="chks" id="groups_wrap">'.$ag.'</div><p class="muted" style="margin:6px 0 0">Определяет, для уроков с какой аудиторией вас будут предлагать заявителям.</p>
<label class="lbl">Есть ли у Вас судимость (снятая или непогашенная)? <span style="color:#d11">*</span></label><select name="criminal_record" id="crim_sel" data-req onchange="crimSw()"><option value="">— выберите —</option><option value="no"'.osel($old,'criminal_record','no').'>Нет</option><option value="yes"'.osel($old,'criminal_record','yes').'>Да</option></select>
<div id="crim_no_fields" style="display:none"><label class="lbl">Дата справки о несудимости <span style="color:#d11">*</span></label><input type="date" name="criminal_cert_date" id="crim_cert_date" value="'.ov($old,'criminal_cert_date').'"><label class="lbl">Справка о несудимости (PDF/JPG/PNG) <span style="color:#d11">*</span></label><input type="file" name="criminal_cert" id="crim_cert_file" accept=".pdf,.jpg,.jpeg,.png"></div>
<p class="muted" style="margin:6px 0 0;font-size:12.5px">Обязательно по закону (ст. 351.1 ТК РФ) для допуска к работе с детьми. При ответе «Да» регистрация не блокируется, но вы не будете предлагаться заявителям в качестве спикера.</p></div>
<div class="rf fund" style="display:none"><p class="muted" style="margin:0 0 8px">Фонд подбирает и подтверждает состав спикеров для заявок. Структурное подразделение работает под контролем головного фонда.</p><h4>Организация</h4><label class="lbl">Наименование Фонда <span style="color:#d11">*</span></label><input name="fund_display_name" data-req value="'.ov($old,'fund_display_name').'"><label class="lbl">Тип</label><select name="fund_type" id="fund_type" onchange="ftsw()"><option value="head"'.osel($old,'fund_type','head').'>Головной фонд</option><option value="sub"'.osel($old,'fund_type','sub').'>Структурное подразделение</option></select><div id="parentfund" style="display:none"><label class="lbl">Головной фонд <span style="color:#d11">*</span></label><select name="parent_fund_id" id="parent_fund_id">'.$headFundOpt.'</select><p class="muted" style="margin:2px 0 8px">Подразделение получит доступ после подтверждения головным фондом.</p></div><div id="fundcode"><label class="lbl">Код регистрации Фонда <span style="color:#d11">*</span></label><input name="fund_code" id="fund_code" value="'.ov($old,'fund_code').'"><p class="muted" style="margin:2px 0 8px">Код регистрации головного фонда выдаёт администратор платформы — он подтверждает право направлять спикеров. Нет кода? Напишите на <a href="mailto:'.esc(cfg()['support_email']).'">'.esc(cfg()['support_email']).'</a>.</p></div><h4>Контактное лицо</h4><label class="lbl">ФИО контактного лица <span style="color:#d11">*</span></label><input name="contact_name" data-req value="'.ov($old,'contact_name').'"><label class="lbl">Должность <span style="color:#d11">*</span></label><input name="position" data-req value="'.ov($old,'position').'"><label class="lbl">Телефон <span style="color:#d11">*</span></label><input name="contact_phone" data-req value="'.ov($old,'contact_phone').'"></div>
<div class="rf operator" style="display:none"><p class="muted" style="margin:0 0 8px">Оператор модерирует заявителей и контролирует ход заявок в пределах своего департамента.</p><h4>Организация</h4><label class="lbl">Наименование <span style="color:#d11">*</span></label><input name="op_display_name" data-req value="'.ov($old,'op_display_name').'"><label class="lbl">Департамент</label><select name="op_group"><option value="school"'.osel($old,'op_group','school').'>ДОА г. Тюмени (школы)</option><option value="region"'.osel($old,'op_group','region').'>ДОиН ТО (СПО и вузы)</option></select><p class="muted" style="margin:2px 0 8px">Определяет, заявки каких организаций вы будете видеть и модерировать.</p><label class="lbl">Код регистрации оператора <span style="color:#d11">*</span></label><input name="op_code" data-req value="'.ov($old,'op_code').'"><p class="muted" style="margin:2px 0 8px">Код выдаёт администратор платформы — он подтверждает право модерировать заявки. Нет кода? Напишите на <a href="mailto:'.esc(cfg()['support_email']).'">'.esc(cfg()['support_email']).'</a>.</p><h4>Контактное лицо</h4><label class="lbl">ФИО контактного лица <span style="color:#d11">*</span></label><input name="contact_name" data-req value="'.ov($old,'contact_name').'"><label class="lbl">Должность <span style="color:#d11">*</span></label><input name="position" data-req value="'.ov($old,'position').'"><label class="lbl">Телефон <span style="color:#d11">*</span></label><input name="contact_phone" data-req value="'.ov($old,'contact_phone').'"></div>
<label class="chk" style="margin-top:10px"><input type="checkbox" name="consent" required> Согласие на обработку ПДн <span style="color:#d11">*</span> (<a href="?p=privacy" target="_blank">политика</a>)</label>
<button class="btn primary" style="margin-top:12px">Зарегистрироваться</button></form><p class="muted"><a href="?p=login">← ко входу</a> · <a href="?p=guide">Как это работает?</a> · <a href="?p=demo">Демо-режим →</a></p></div>
<script>
function rsw(){
var r=document.getElementById("role").value;
document.querySelectorAll(".rf").forEach(function(e){ e.style.display="none"; e.querySelectorAll("input,select,textarea").forEach(function(inp){ inp.disabled=true; inp.required=false; }); });
document.getElementById("parent_fund_id").required=false;
document.getElementById("fund_code").required=false;
var b=document.querySelector(".rf."+r);
if(b){ b.style.display="block"; b.querySelectorAll("input,select,textarea").forEach(function(inp){ inp.disabled=false; }); b.querySelectorAll("[data-req]").forEach(function(inp){ inp.required=true; }); }
if(r==="fund") ftsw();
if(r==="school") sctsw();
if(r==="speaker") crimSw();
validateGroups();
}
function crimSw(){
var sel=document.getElementById("crim_sel"); if(!sel||sel.disabled) return;
var w=document.getElementById("crim_no_fields"); var show=(sel.value==="no");
w.style.display=show?"block":"none";
document.getElementById("crim_cert_date").required=show;
document.getElementById("crim_cert_file").required=show;
}
rsw();
lvlSync();
function lvlSync(){
var l=document.getElementById("level_sel").value; var og=document.getElementById("school_og");
if(og) og.value=(l==="spo"||l==="vuz")?"region":"school";
}
function ftsw(){
var t=document.getElementById("fund_type").value; var sub=(t==="sub");
document.getElementById("parentfund").style.display=sub?"block":"none";
document.getElementById("fundcode").style.display=sub?"none":"block";
document.getElementById("parent_fund_id").required=sub;
document.getElementById("fund_code").required=!sub;
}
function sctsw(){
var t=document.getElementById("school_type").value; var isMember=(t==="member");
document.getElementById("school_owner_fields").style.display=isMember?"none":"block";
document.getElementById("school_member_fields").style.display=isMember?"block":"none";
document.getElementById("reg_org_name").required=!isMember;
document.getElementById("school_og").required=!isMember;
document.getElementById("reg_address").required=!isMember;
document.getElementById("reg_building").required=!isMember;
document.getElementById("parent_school_id").required=isMember;
}
function dsw(){
var w=document.getElementById("district_other_wrap"); var isOther=(document.getElementById("district_sel").value==="__other__");
if(w)w.style.display=isOther?"block":"none";
document.getElementById("district_other").required=isOther;
}
function validateGroups(){
var boxes=document.querySelectorAll("#groups_wrap input[type=checkbox]"); if(!boxes.length)return;
var checked=Array.prototype.some.call(boxes,function(b){return b.checked;});
var needed=document.getElementById("role").value==="speaker";
boxes[0].setCustomValidity((needed&&!checked)?"Выберите хотя бы одну возрастную группу":"");
}
document.querySelectorAll("#groups_wrap input[type=checkbox]").forEach(function(b){ b.addEventListener("change",validateGroups); });
</script>';
layout('Регистрация',$body); exit;
}
require_login();
// ========== ВОЙТИ КАК (только для суперпользователя) ==========
if($p==='impersonate'){
if(is_impersonating()||!can_impersonate($u)){ http_response_code(403); exit('Нет доступа.'); }
$q=trim($_GET['q']??''); $roleFilter=$_GET['role']??'';
$where='1=1'; $params=[];
if($roleFilter){ $where.=' AND role=?'; $params[]=$roleFilter; }
if($q!==''){ $where.=' AND (login LIKE ? OR display_name LIKE ? OR org_name LIKE ? OR email LIKE ?)'; $qq='%'.$q.'%'; $params=array_merge($params,[$qq,$qq,$qq,$qq]); }
$st=db()->prepare("SELECT * FROM users WHERE $where ORDER BY role, display_name, org_name");
$st->execute($params); $list=$st->fetchAll();
$body='<div class="head"><h2>🔑 Войти как…</h2><a class="btn ghost" href="'.esc(role_home($u)).'">← в кабинет</a></div>';
$body.='<p class="muted">Этот раздел виден только вам. Каждый вход от чужого имени и возврат обратно фиксируются в журнале действий.</p>';
$body.='<form method="get" class="card pad" style="margin-bottom:14px;display:flex;gap:10px;align-items:end;flex-wrap:wrap"><input type="hidden" name="p" value="impersonate"><label class="lbl" style="margin:0">Роль</label><select name="role"><option value="">Все</option><option value="school" '.($roleFilter==='school'?'selected':'').'>Заявитель</option><option value="fund" '.($roleFilter==='fund'?'selected':'').'>Фонд</option><option value="speaker" '.($roleFilter==='speaker'?'selected':'').'>Спикер</option><option value="operator" '.($roleFilter==='operator'?'selected':'').'>Оператор</option></select><label class="lbl" style="margin:0">Поиск</label><input type="search" name="q" value="'.esc($q).'" placeholder="Логин, ФИО, организация, email"><button class="btn primary sm">Найти</button>'.(($q!==''||$roleFilter!=='')?' <a class="btn ghost sm" href="?p=impersonate">Сбросить</a>':'').'</form>';
$body.='<div class="table-scroll"><table class="card-view resizable"><thead><tr><th>Логин</th><th>ФИО/Организация</th><th>Роль</th><th>Статус</th><th></th></tr></thead><tbody>';
$shown=0;
foreach($list as $x){
if((int)$x['id']===(int)$u['id']) continue;
if(can_impersonate($x)) continue;
$shown++;
$status=$x['active']?'<span class="pill green">активен</span>':'<span class="pill gray">ожидает</span>';
$body.='<tr><td class="id">'.esc($x['login']).'</td><td data-label="ФИО/Организация">'.esc($x['display_name']?:$x['org_name']).'</td><td data-label="Роль">'.esc($x['role']).'</td><td data-label="Статус">'.$status.'</td><td data-label=""><form method="post">'.csrf_field().'<input type="hidden" name="action" value="impersonate_start"><input type="hidden" name="target_id" value="'.$x['id'].'"><button class="btn primary sm">Войти как →</button></form></td></tr>';
}
if(!$shown) $body.='<tr><td colspan="5" class="muted">Никого не найдено.</td></tr>';
$body.='</tbody></table></div>';
layout('Войти как',$body); exit;
}
// ========== УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ ==========
if($p==='users' && in_array($u['role'],['operator','fund','school'])){
$body='<div class="head"><h2>'.($u['role']==='school'?'Сотрудники организации':'Управление пользователями').'</h2><a class="btn ghost" href="?p='.$u['role'].'">← в кабинет</a></div>';
$role_filter=$_GET['role']??'';
$where="WHERE 1=1"; $params=[]; $superAdmin=false; $scopeNote='';
if($u['role']==='fund'){ $where.=" AND role='speaker' AND fund_user_id=?"; $params[]=$u['id']; $scopeNote='Только ваши спикеры.'; }
elseif($u['role']==='school'){ $schRoot=school_root_row($u); $where.=" AND role='school' AND parent_school_id=?"; $params[]=$schRoot['id']; $scopeNote='Сотрудники, зарегистрированные как участники организации «'.esc($schRoot['org_name']).'».'; }
else { $og=$u['op_group']??'';
if($og===''){ $superAdmin=true; if($role_filter){ $where.=" AND role=?"; $params[]=$role_filter; } }
else { $where.=" AND (role='fund' OR role='admin' OR (role='school' AND (op_group=? OR (op_group IS NULL OR op_group='') AND level IN (".implode(',',array_fill(0,count(applicant_group_levels($og)),'?')).'))))'; $params[]=$og; $params=array_merge($params,applicant_group_levels($og)); $scopeNote='Ваши организации, а также фонды и администраторы сайта — для сброса пароля.'; }
}
$q=trim($_GET['q']??'');
if($q!==''){ $where.=" AND (login LIKE ? OR display_name LIKE ? OR org_name LIKE ? OR email LIKE ?)"; $qq='%'.$q.'%'; $params=array_merge($params,[$qq,$qq,$qq,$qq]); }
$st=db()->prepare("SELECT * FROM users $where ORDER BY role, display_name");
$st->execute($params); $users=$st->fetchAll();
if(!empty($_GET['sort'])) $users=sort_users_rows($users,$_GET['sort'],$_GET['dir']??'asc');
$schoolIsRoot = $u['role']!=='school' || empty($u['parent_school_id']);
if($superAdmin) $body.='<div class="card pad"><form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><input type="hidden" name="p" value="users"><label class="lbl" style="margin:0">Фильтр по роли</label><select name="role" onchange="this.form.submit()"><option value="">Все</option><option value="school" '.($role_filter==='school'?'selected':'').'>Заявители</option><option value="speaker" '.($role_filter==='speaker'?'selected':'').'>Спикеры</option><option value="fund" '.($role_filter==='fund'?'selected':'').'>Фонд</option><option value="operator" '.($role_filter==='operator'?'selected':'').'>Оператор</option><option value="admin" '.($role_filter==='admin'?'selected':'').'>Администратор сайта</option></select><label class="lbl" style="margin:0">Поиск</label><input type="search" name="q" value="'.esc($q).'" placeholder="Логин, ФИО, организация, email"><button class="btn primary sm">Найти</button>'.($q!==''?' <a class="btn ghost sm" href="?p=users'.($role_filter?'&role='.esc($role_filter):'').'">Сбросить</a>':'').'</form></div>';
if(!$superAdmin){ if($scopeNote) $body.='<p class="muted">'.$scopeNote.'</p>'; $body.='<form method="get" class="card pad" style="margin-bottom:14px;display:flex;gap:10px;align-items:end;flex-wrap:wrap"><input type="hidden" name="p" value="users"><label class="lbl" style="margin:0">Поиск</label><input type="search" name="q" value="'.esc($q).'" placeholder="Логин, ФИО, организация, email"><button class="btn primary sm">Найти</button>'.($q!==''?' <a class="btn ghost sm" href="?p=users">Сбросить</a>':'').'</form>'; }
$body.='<div class="card pad" style="margin-top:12px;border-left:4px solid var(--rf-blue,var(--navy))"><h4 style="margin:0 0 4px">+ Создать учётную запись администратора сайта (новости)</h4>';
$body.='<p class="muted" style="margin:0 0 10px">Отдельная ограниченная учётная запись — доступ только к разделу «Новости сайта», без доступа к заявкам и данным пользователей.</p>';
$body.='<form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">'.csrf_field().'<input type="hidden" name="action" value="create_admin_account">';
$body.='<div><label class="lbl">Логин</label><input name="login" required minlength="3"></div>';
$body.='<div><label class="lbl">Пароль</label><input type="password" name="pass" required minlength="10"></div>';
$body.='<div><label class="lbl">ФИО</label><input name="display_name" required></div>';
$body.='<div><label class="lbl">Email</label><input type="email" name="email" required></div>';
$body.='<button class="btn primary sm">Создать</button></form></div>';
$body.='<div class="table-scroll"><table class="card-view resizable"><thead><tr><th>Логин</th>'.sort_th('ФИО/Орг','name').sort_th('Роль','role').sort_th('Email','email').sort_th('ФИО контактного лица','contact_name').sort_th('Должность','position').sort_th('Телефон','phone').sort_th('Адрес местонахождения','address').sort_th('№ корпуса','building').sort_th('Статус','status').'<th>Действия</th></tr></thead><tbody>';
foreach($users as $x){
$status=$x['active']?'<span class="pill green">активен</span>':'<span class="pill gray">ожидает</span>';
$actions='';
$canResetPw=can_reset_password($u,$x);
if($schoolIsRoot && $canResetPw) $actions.='<form method="post" style="display:inline">'.csrf_field().'<input type="hidden" name="action" value="admin_reset_password"><input type="hidden" name="target_id" value="'.$x['id'].'"><input type="password" name="newpass" placeholder="Новый пароль" required style="width:140px"><button class="btn ghost sm">Сбросить пароль</button></form>';
if($schoolIsRoot && can_manage($u,$x)) $actions.=' <form method="post" style="display:inline" onsubmit="return confirm(\'Удалить?\')">'.csrf_field().'<input type="hidden" name="action" value="delete_user"><input type="hidden" name="target_id" value="'.$x['id'].'"><button class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">Удалить</button></form>';
if($u['role']==='school' && $schoolIsRoot && $x['active']){ $actions.=' <form method="post" style="display:inline" onsubmit="return confirm(\'Передать права головного аккаунта организации сотруднику «'.esc($x['contact_name']).'»? Вы станете обычным сотрудником, а он сможет подтверждать новых сотрудников и редактировать данные организации.\')">'.csrf_field().'<input type="hidden" name="action" value="transfer_school_head"><input type="hidden" name="uid" value="'.$x['id'].'"><button class="btn ghost sm">Сделать головным</button></form>'; }
if($actions==='') $actions='<span class="muted">—</span>';
$body.='<tr><td class="id">'.esc($x['login']).'</td><td data-label="ФИО/Орг">'.esc($x['display_name']?:$x['org_name']).'</td><td data-label="Роль">'.esc($x['role']).'</td><td data-label="Email">'.esc($x['email']??'—').'</td><td data-label="ФИО контактного лица" class="muted">'.esc($x['contact_name']?:'—').'</td><td data-label="Должность" class="muted">'.esc($x['position']?:'—').'</td><td data-label="Телефон" class="muted">'.esc($x['phone']?:'—').'</td><td data-label="Адрес местонахождения" class="muted">'.esc($x['address']?:'—').'</td><td data-label="№ корпуса" class="muted">'.esc($x['building']?:'—').'</td><td data-label="Статус">'.$status.'</td><td data-label="Действия">'.$actions.'</td></tr>';
}
$body.='</tbody></table></div>';
layout('Пользователи',$body); exit;
}
if($p==='districts' && $u['role']==='operator'){
$body='<div class="head"><h2>Районы и соседства</h2><a class="btn ghost" href="?p=operator">← в кабинет</a></div>';
$body.='<p class="muted">Подбор спикеров идёт из района заявителя и соседних районов.</p>';
$body.='<form method="post" class="card pad" style="margin-bottom:12px">'.csrf_field().'<input type="hidden" name="action" value="add_district_admin"><label class="lbl">Новый район</label><input name="name" placeholder="название района"> <button class="btn primary sm" style="margin-top:8px">Добавить район</button></form>';
$body.='<table><thead><tr><th>Район</th><th>Соседние районы (через запятую)</th></tr></thead><tbody>';
foreach(districts_list() as $d){
$body.='<tr><td><b>'.esc($d['name']).'</b></td><td><form method="post" style="display:flex;gap:6px;align-items:center">'.csrf_field().'<input type="hidden" name="action" value="save_district_neighbors"><input type="hidden" name="name" value="'.esc($d['name']).'"><input name="neighbors" value="'.esc($d['neighbors']).'" style="min-width:320px"><button class="btn ghost sm">Сохранить</button></form></td></tr>';
}
$body.='</tbody></table>';
layout('Районы',$body); exit;
}
if($p==='audit' && $u){
$targetType=$_GET['target_type']??''; $targetId=(int)($_GET['target_id']??0);
if($targetType==='requests' && $targetId){
$rr=get_request($targetId);
if(!$rr){ layout('История','<div class="card pad">Заявка не найдена.</div>'); exit; }
if($u['role']==='school' && !school_owns_request($u,$rr)) exit('Нет доступа.');
if($u['role']==='speaker' && !is_candidate($targetId,$u['id'])) exit('Нет доступа.');
$logs=get_audit_log(['target_type'=>'requests','target_id'=>$targetId]);
$body='<div class="head"><h2>История заявки '.esc($rr['code']).'</h2><a class="btn ghost" href="?p=request&id='.$targetId.'">← К заявке</a></div>';
$body.='<table class="card-view"><thead><tr><th>Время</th><th>Пользователь</th><th>Действие</th><th>Детали</th></tr></thead><tbody>';
foreach($logs as $l){
$body.='<tr><td class="id" data-label="Время">'.esc($l['created_at']).'</td><td data-label="Пользователь">'.esc($l['display_name']?:$l['login']?:'система').' <span class="muted">('.esc($l['user_role']).')</span></td><td data-label="Действие">'.esc($l['action']).'</td><td data-label="Детали">'.esc($l['details']).'</td></tr>';
}
if(!$logs) $body.='<tr><td colspan="4" class="muted">Записей не найдено</td></tr>';
$body.='</tbody></table>';
layout('История заявки',$body); exit;
}
if($u['role']==='operator'){
$body='<div class="head"><h2>Журнал действий</h2><a class="btn ghost" href="?p='.$u['role'].'">← в кабинет</a></div>';
$filters=[];
if(!empty($_GET['target_type'])) $filters['target_type']=$_GET['target_type'];
if(!empty($_GET['target_id'])) $filters['target_id']=(int)$_GET['target_id'];
if(!empty($_GET['user_role'])) $filters['user_role']=$_GET['user_role'];
if(!empty($_GET['from_date'])) $filters['from_date']=$_GET['from_date'];
if(!empty($_GET['to_date'])) $filters['to_date']=$_GET['to_date'];
$logs=get_audit_log($filters);
$body.='<div class="card pad"><form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end"><input type="hidden" name="p" value="audit">
<label class="lbl" style="margin:0">Тип</label><select name="target_type"><option value="">Все</option><option value="requests" '.(!empty($_GET['target_type'])&&$_GET['target_type']==='requests'?'selected':'').'>Заявки</option><option value="users" '.(!empty($_GET['target_type'])&&$_GET['target_type']==='users'?'selected':'').'>Пользователи</option></select>
<label class="lbl" style="margin:0">Роль</label><select name="user_role"><option value="">Все</option><option value="school" '.(!empty($_GET['user_role'])&&$_GET['user_role']==='school'?'selected':'').'>Заявитель</option><option value="fund" '.(!empty($_GET['user_role'])&&$_GET['user_role']==='fund'?'selected':'').'>Фонд</option><option value="operator" '.(!empty($_GET['user_role'])&&$_GET['user_role']==='operator'?'selected':'').'>Оператор</option><option value="speaker" '.(!empty($_GET['user_role'])&&$_GET['user_role']==='speaker'?'selected':'').'>Спикер</option></select>
<label class="lbl" style="margin:0">С</label><input type="date" name="from_date" value="'.esc($_GET['from_date']??'').'">
<label class="lbl" style="margin:0">По</label><input type="date" name="to_date" value="'.esc($_GET['to_date']??'').'">
<button class="btn primary sm">Фильтр</button></form></div>';
$body.='<table class="card-view"><thead><tr><th>Время</th><th>Пользователь</th><th>Действие</th><th>Объект</th><th>Детали</th></tr></thead><tbody>';
foreach($logs as $l){
$body.='<tr><td class="id" data-label="Время">'.esc($l['created_at']).'</td><td data-label="Пользователь">'.esc($l['display_name']?:$l['login']?:'система').' <span class="muted">('.esc($l['user_role']).')</span></td><td data-label="Действие">'.esc($l['action']).'</td><td data-label="Объект">'.esc($l['target_type']).'#'.$l['target_id'].'</td><td data-label="Детали">'.esc($l['details']).'</td></tr>';
}
if(!$logs) $body.='<tr><td colspan="5" class="muted">Записей не найдено</td></tr>';
$body.='</tbody></table>';
layout('Журнал действий',$body); exit;
}
if($u['role']==='fund'){
$scope=fund_scope_ids($u);
$in=implode(',',array_fill(0,count($scope),'?'));
$spSt=db()->prepare("SELECT id FROM users WHERE role='speaker' AND fund_user_id IN ($in)"); $spSt->execute($scope);
$scopeIds=array_merge($scope,$spSt->fetchAll(PDO::FETCH_COLUMN));
$filters=[]; if(!empty($_GET['from_date'])) $filters['from_date']=$_GET['from_date']; if(!empty($_GET['to_date'])) $filters['to_date']=$_GET['to_date'];
$logs=get_audit_log($filters,$scopeIds);
$body='<div class="head"><h2>Журнал действий</h2><a class="btn ghost" href="?p=fund">← в кабинет</a></div><p class="muted">Действия вашего фонда и ваших спикеров.</p>';
$body.='<table class="card-view"><thead><tr><th>Время</th><th>Пользователь</th><th>Действие</th><th>Объект</th><th>Детали</th></tr></thead><tbody>';
foreach($logs as $l){
$body.='<tr><td class="id" data-label="Время">'.esc($l['created_at']).'</td><td data-label="Пользователь">'.esc($l['display_name']?:$l['login']?:'система').' <span class="muted">('.esc($l['user_role']).')</span></td><td data-label="Действие">'.esc($l['action']).'</td><td data-label="Объект">'.esc($l['target_type']).'#'.$l['target_id'].'</td><td data-label="Детали">'.esc($l['details']).'</td></tr>';
}
if(!$logs) $body.='<tr><td colspan="5" class="muted">Записей не найдено</td></tr>';
$body.='</tbody></table>';
layout('Журнал действий',$body); exit;
}
exit('Нет доступа.');
}
if($p==='calendar' && $u){
$month=$_GET['month']??date('m'); $year=$_GET['year']??date('Y');
$events=get_calendar_events($month,$year,$u);
$month_name=['','Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'][(int)$month];
$body='<div class="head"><h2>Календарь: '.$month_name.' '.$year.'</h2><div><a class="btn ghost" href="?p=calendar&month='.str_pad($month-1,2,'0',STR_PAD_LEFT).'&year='.$year.'">← Пред.</a> <a class="btn ghost" href="?p=calendar">Сегодня</a> <a class="btn ghost" href="?p=calendar&month='.str_pad($month+1,2,'0',STR_PAD_LEFT).'&year='.($month==12?$year+1:$year).'">След. →</a> <a class="btn ghost" href="?p='.$u['role'].'">← в кабинет</a></div></div>';
if(!$events){ $body.='<div class="card pad muted">В этом месяце уроков нет.</div>'; }
else {
$by_date=[];
foreach($events as $e){ $by_date[$e['udate']][]=$e; }
ksort($by_date);
foreach($by_date as $date=>$items){
$is_today=$date===date('Y-m-d');
$body.='<div class="card pad" style="margin-bottom:12px'.($is_today?';border-left:4px solid var(--amber)':'').'"><h4 style="margin:0 0 10px">'.esc($date).($is_today?' <span class="pill amber">сегодня</span>':'').'</h4>';
foreach($items as $e){
$body.='<div style="padding:8px;border-bottom:1px solid var(--line)"><a href="?p=request&id='.$e['id'].'" style="font-weight:600">'.esc($e['code']).'</a> — '.esc($e['org']).' <span class="muted">('.esc($e['utime']).(!empty($e['utime_end'])?'–'.esc($e['utime_end']):'').')</span> '.pill($e['status']);
if($e['speakers']) $body.=' <span class="muted">· спикеры: '.esc($e['speakers']).'</span>';
$body.='</div>';
}
$body.='</div>';
}
}
layout('Календарь',$body); exit;
}
// ========== КАБИНЕТ ОПЕРАТОРА ==========
if($p==='operator'){
require_role('operator'); $tab=$_GET['tab']??'list';
if($tab==='speakers'){
$body='<div class="head"><h2>Реестр спикеров</h2><a class="btn ghost" href="?p=operator">← в кабинет</a></div>';
$allsp=db()->query("SELECT * FROM users WHERE role='speaker' ORDER BY display_name")->fetchAll();
if(!empty($_GET['sort'])) $allsp=sort_users_rows($allsp,$_GET['sort'],$_GET['dir']??'asc');
$body.='<div class="table-scroll"><table class="card-view resizable"><thead><tr>'.sort_th('Спикер','name').sort_th('Организация','fund_name').sort_th('Район','district').'<th>Возрастные группы</th>'.sort_th('Телефон','phone').sort_th('Статус','status').'</tr></thead><tbody>';
foreach($allsp as $s){ $g=array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$s['age_groups']))); $f=$s['fund_user_id']?find_user_by_id((int)$s['fund_user_id']):null; $fname=($f && $f['display_name']!=='')?esc($f['display_name']):'<span class="muted">не указана</span>'; $stp=$s['active']?'<span class="pill green">активен</span>':'<span class="pill gray">на подтверждении</span>'; if(speaker_has_criminal_record($s)) $stp.=' <span class="pill" style="background:#fdecea;color:#b23b2c">⚠ судимость</span>'; $body.='<tr onclick="location=\'?p=speaker_view&id='.$s['id'].'\'"><td class="id" data-label="Спикер"><a href="?p=speaker_view&id='.$s['id'].'">'.esc($s['display_name']).'</a></td><td data-label="Организация">'.$fname.'</td><td data-label="Район" class="muted">'.esc($s['district']?:'—').'</td><td data-label="Группы" class="muted">'.esc(implode(', ',$g)).'</td><td data-label="Телефон" class="muted">'.esc($s['phone']?:'—').'</td><td data-label="Статус">'.$stp.'</td></tr>'; }
if(!$allsp) $body.='<tr><td colspan="6" class="muted">Спикеров пока нет.</td></tr>'; $body.='</tbody></table></div>';
$pend=pending_speakers(); if($pend) $body.='<p class="muted" style="margin-top:12px">Ожидают подтверждения Фондом: '.count($pend).'.</p>';
layout('Реестр спикеров',$body); exit;
}
if($tab==='archive'){
$afil=['archived'=>1]; if(($u['op_group']??'')==='school') $afil['op_group']='school';
if(!empty($_GET['org'])) $afil['org']=$_GET['org'];
if(!empty($_GET['from_date'])) $afil['from_date']=$_GET['from_date'];
if(!empty($_GET['to_date'])) $afil['to_date']=$_GET['to_date'];
$rows=get_requests_list($afil);
if(!empty($_GET['sort'])) $rows=sort_requests($rows,$_GET['sort'],$_GET['dir']??'asc');
$body='<div class="head"><h2>Архив заявок</h2><a class="btn ghost" href="?p=operator">← в кабинет</a></div>';
$body.='<div class="guide-tabs" style="margin:-6px 0 14px"><a class="guide-tab" href="?p=operator">Активные</a> <a class="guide-tab active" href="?p=operator&tab=archive">Архив</a></div>';
$body.='<p class="muted">Закрытые заявки автоматически переносятся в архив через 5 рабочих дней после закрытия.</p>';
$body.='<form method="get" class="card pad" style="margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:end"><input type="hidden" name="p" value="operator"><input type="hidden" name="tab" value="archive">'
.'<label class="lbl" style="margin:0">Организация</label><input name="org" value="'.esc($_GET['org']??'').'" placeholder="Поиск">'
.'<label class="lbl" style="margin:0">С</label><input type="date" name="from_date" value="'.esc($_GET['from_date']??'').'">'
.'<label class="lbl" style="margin:0">По</label><input type="date" name="to_date" value="'.esc($_GET['to_date']??'').'">'
.'<button class="btn primary sm">Фильтр</button> <a class="btn ghost sm" href="?p=operator&tab=archive">Сбросить</a></form>';
$labels=req_labels_bulk(array_column($rows,'id'));
$tr=''; foreach($rows as $r){ $tr.='<tr onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id"><a href="?p=request&id='.$r['id'].'">'.esc($r['code']).'</a></td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.esc($labels[$r['id']]??'не выбран').'</td><td>'.pill($r['status']).'</td><td class="muted">'.esc($r['archived_at']??'').'</td></tr>'; }
$hasFilter=!empty($_GET['org'])||!empty($_GET['from_date'])||!empty($_GET['to_date']);
$body.= $rows ? '<table class="card-view"><thead><tr>'.sort_th('Код','code').sort_th('Организация','org').sort_th('Дата','date').'<th>Спикеры</th>'.sort_th('Статус','status').'<th>Архивировано</th></tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">'.($hasFilter?'По заданным фильтрам ничего не найдено.':'Архив пуст.').'</div>';
layout('Архив заявок',$body); exit;
}
if($tab==='moderation'){
$pend=pending_applicants($u);
$dept=($u['op_group']??'')==='' ? 'Вы видите всех заявителей.' : 'Ваш департамент: '.esc(operator_label_for_group($u['op_group'])).'.';
$body='<div class="head"><h2>Модерация заявителей ('.count($pend).')</h2><a class="btn ghost" href="?p=operator">← в кабинет</a></div>';
$body.='<p class="muted">Подтвердите организации, которым открыть доступ к подаче заявок. '.$dept.'</p>';
if(!$pend) $body.='<div class="card pad muted">Новых заявок на регистрацию заявителей нет.</div>';
foreach($pend as $ap){
$body.='<div class="card pad" style="margin-bottom:10px"><b>'.esc($ap['org_name']).'</b> <span class="pill blue">'.esc(level_label($ap['level'])).'</span>'
.'<dl class="kv" style="margin-top:8px"><dt>Департамент</dt><dd>'.esc(operator_label_for_group(school_dept_group($ap))).'</dd><dt>Район</dt><dd>'.esc($ap['district']?:'—').'</dd>'
.'<dt>Адрес</dt><dd>'.esc($ap['address']?:'—').'</dd>'
.'<dt>№ корпуса</dt><dd>'.esc($ap['building']?:'—').'</dd>'
.'<dt>Контактное лицо</dt><dd>'.esc($ap['contact_name']?:'—').'</dd>'
.'<dt>Должность</dt><dd>'.esc($ap['position']?:'—').'</dd>'
.'<dt>Телефон</dt><dd>'.esc($ap['phone']?:'—').'</dd>'
.'<dt>Email</dt><dd>'.esc($ap['email']?:'—').'</dd>'
.'<dt>Логин</dt><dd>'.esc($ap['login']).'</dd>'
.'<dt>Подана</dt><dd>'.esc($ap['created_at']?:'—').'</dd></dl>'
.'<div style="margin-top:8px;display:flex;gap:8px">'
.'<form method="post" style="display:inline">'.csrf_field().'<input type="hidden" name="action" value="approve_applicant"><input type="hidden" name="uid" value="'.$ap['id'].'"><button class="btn primary sm">Подтвердить</button></form>'
.'<form method="post" style="display:inline" onsubmit="return confirm(\'Отклонить?\')">'.csrf_field().'<input type="hidden" name="action" value="reject_applicant"><input type="hidden" name="uid" value="'.$ap['id'].'"><button class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">Отклонить</button></form>'
.'</div></div>';
}
layout('Модерация заявителей',$body); exit;
}
$filters=['archived'=>0];
$opg=$u['op_group']??''; $locked=($opg==='school');
$deptFilter = $locked ? 'school' : (in_array($_GET['dept']??'',['school','region'])?$_GET['dept']:'');
if($deptFilter!=='') $filters['op_group']=$deptFilter;
if(!empty($_GET['status'])) $filters['status']=$_GET['status'];
if(!empty($_GET['org'])) $filters['org']=$_GET['org'];
if(!empty($_GET['from_date'])) $filters['from_date']=$_GET['from_date'];
if(!empty($_GET['to_date'])) $filters['to_date']=$_GET['to_date'];
$rows=get_requests_list($filters);
if(!empty($_GET['sort'])) $rows=sort_requests($rows,$_GET['sort'],$_GET['dir']??'asc');
else $rows=sort_requests($rows,'date','asc');
$archivedCount=count(get_requests_list(array_merge($filters,['archived'=>1])));
$activeFilters=(!$locked && $deptFilter!=='')+(!empty($_GET['status']))+(!empty($_GET['org']))+(!empty($_GET['from_date']))+(!empty($_GET['to_date']));
$pendN=count(pending_applicants($u));
$action_req = get_action_required_requests($u);
$body = '';
if ($action_req) {
$body .= '<div class="action-block"><h4>⚠️ Требует вашего действия <span class="action-badge">'.count($action_req).'</span></h4><ul class="action-list">';
foreach ($action_req as $ar) {
$msg = whose_turn($ar)[1];
$urgent_cls = is_request_urgent($ar) ? ' <span class="muted">(Срок скоро!)</span>' : '';
$body .= '<li><span>'.esc($ar['code']).' — '.esc($ar['org']).' <span class="muted">('.dt($ar).')</span>'.$urgent_cls.'</span><a href="?p=request&id='.$ar['id'].'">'.$msg.' →</a></li>';
}
$body .= '</ul></div>';
}
$k=kpis($locked?'school':($deptFilter?:null)); $kp='';
foreach($k as $c){
$cls='kpi'.(($c[0]==='Не состоялось'&&(int)$c[1]>0)?' warn':'');
if(!empty($c[2])){ $kp.='<a class="'.$cls.'" href="'.esc($c[2]).'" style="text-decoration:none;color:inherit"><div class="n">'.esc($c[1]).'</div><div class="l">'.esc($c[0]).'</div></a>'; }
else { $kp.='<div class="'.$cls.'"><div class="n">'.esc($c[1]).'</div><div class="l">'.esc($c[0]).'</div></div>'; }
}
$deptSel='';
if(!$locked){ $deptSel='<label class="lbl" style="margin:0">Департамент</label><select name="dept"><option value="">Общие результаты</option><option value="school" '.($deptFilter==='school'?'selected':'').'>ДО г. Тюмени (школы)</option><option value="region" '.($deptFilter==='region'?'selected':'').'>ДОиН ТО (СПО и вузы)</option></select>'; }
$body.='<details class="card pad" style="margin-bottom:14px"'.($activeFilters?' open':'').'><summary style="cursor:pointer;font-weight:600;color:var(--rf-blue)">🔍 Фильтры и поиск'.($activeFilters?' <span class="action-badge" style="background:var(--rf-blue)">'.$activeFilters.'</span>':'').'</summary><div style="margin-top:12px">'.($locked?'<p class="muted" style="margin:0 0 8px">Вы видите заявки школ.</p>':'').'<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end"><input type="hidden" name="p" value="operator"><input type="hidden" name="tab" value="list">
'.$deptSel.'<label class="lbl" style="margin:0">Статус</label><select name="status"><option value="">Все</option>';
foreach(statuses() as $s) $body.='<option value="'.$s['k'].'" '.(!empty($_GET['status'])&&$_GET['status']===$s['k']?'selected':'').'>'.$s['label'].'</option>';
$body.='</select><label class="lbl" style="margin:0">Организация</label><input name="org" value="'.esc($_GET['org']??'').'" placeholder="Поиск">
<label class="lbl" style="margin:0">С</label><input type="date" name="from_date" value="'.esc($_GET['from_date']??'').'">
<label class="lbl" style="margin:0">По</label><input type="date" name="to_date" value="'.esc($_GET['to_date']??'').'">
<button class="btn primary sm">Фильтр</button> <a class="btn ghost sm" href="?p=operator">Сбросить</a></form></div></details>';
$body.='<div style="margin-bottom:14px"><a class="btn ghost" href="?p=operator_tools">🛠️ Инструменты'.($pendN?' <span class="pill" style="background:#fdecea;color:#b23b2c">'.$pendN.'</span>':'').'</a></div>';
$labels=req_labels_bulk(array_column($rows,'id'));
$tr=''; foreach($rows as $r){
$urgent_tr = is_request_urgent($r) ? 'urgent' : '';
[$turnWho,$turnWhat]=whose_turn($r);
$tr.='<tr class="'.$urgent_tr.'" onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id"><a href="?p=request&id='.$r['id'].'">'.esc($r['code']).'</a></td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.esc($labels[$r['id']]??'не выбран').'</td><td title="'.esc($turnWho.($turnWhat?' — '.$turnWhat:'')).'">'.pill($r['status']).'</td></tr>';
}
$body.='<div class="head"><h2>Реестр заявок</h2></div>';
$body.='<div class="guide-tabs" style="margin:-6px 0 14px"><a class="guide-tab active" href="?p=operator">Активные ('.count($rows).')</a> <a class="guide-tab" href="?p=operator&tab=archive">Архив'.($archivedCount?' ('.$archivedCount.')':'').'</a></div>';
$body.='<div class="kpis">'.$kp.'</div>';
$body.=month_stats_widget(operator_scope_requests($locked?'school':($deptFilter?:null)),'opStatMonth');
$body.= $rows ? '<table class="card-view"><thead><tr>'.sort_th('Код','code').sort_th('Организация','org').sort_th('Дата и время','date').'<th>Спикеры</th>'.sort_th('Статус','status').'</tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">Заявок не найдено.</div>';
layout('Кабинет оператора',$body); exit;
}
// ========== ОТЗЫВЫ ==========
if($p==='reviews'){
require_role(['operator','fund']);
$type=($_GET['type']??'')==='speaker'?'speaker':'app';
$isFund=$u['role']==='fund';
$opg=$u['op_group']??''; $dept=$opg==='school'?'school':(in_array($_GET['dept']??'',['school','region'])?$_GET['dept']:'');
$scopeOg=$opg==='school'?'school':($dept?:null);
$body='<div class="head"><h2>'.($type==='app'?'Отзывы заявителей':'Отзывы спикеров').'</h2><a class="btn ghost" href="?p='.esc($u['role']).'">← в кабинет</a></div>';
if($type==='app'){
$rows=$isFund?list_app_reviews_for_fund($u):list_app_reviews($scopeOg);
$body.='<table class="card-view"><thead><tr><th>Заявитель</th><th>Спикеры</th><th>Тема урока</th><th>Дата отзыва</th><th>Отзыв</th><th>Пост</th></tr></thead><tbody>';
foreach($rows as $r){
$speakers=implode(', ',array_map(fn($x)=>$x['display_name'],req_confirmed_rows($r['id'])));
$review=esc($r['fb_app_comment']??'');
$postUrl=trim((string)($r['fb_post_url']??''));
$postCell=$postUrl!==''?'<a href="'.esc($postUrl).'" target="_blank" rel="noopener" onclick="event.stopPropagation()">ссылка</a>':'<span class="muted">—</span>';
$body.='<tr onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id" data-label="Заявитель"><a href="?p=request&id='.$r['id'].'">'.esc($r['org']).'</a></td><td data-label="Спикеры" class="muted">'.esc($speakers?:'—').'</td><td data-label="Тема" class="muted">'.esc($r['topic']?:'—').'</td><td data-label="Дата" class="muted">'.esc($r['fb_app_at']?:'—').'</td><td data-label="Отзыв">'.$review.'</td><td data-label="Пост">'.$postCell.'</td></tr>';
}
if(!$rows) $body.='<tr><td colspan="6" class="muted">Отзывов заявителей пока нет.</td></tr>';
$body.='</tbody></table>';
} else {
$rows=$isFund?list_speaker_reviews_for_fund($u):list_speaker_reviews($scopeOg);
$body.='<table class="card-view"><thead><tr><th>Заявитель</th><th>Спикер</th><th>Тема урока</th><th>Дата отзыва</th><th>Отзыв</th></tr></thead><tbody>';
foreach($rows as $row){
$review=esc($row['vet_comment']??'');
$body.='<tr onclick="location=\'?p=request&id='.$row['request_id'].'\'"><td class="id" data-label="Заявитель"><a href="?p=request&id='.$row['request_id'].'">'.esc($row['org']).'</a></td><td data-label="Спикер" class="muted">'.esc($row['speaker_name']).'</td><td data-label="Тема" class="muted">'.esc($row['topic']?:'—').'</td><td data-label="Дата" class="muted">'.esc($row['vet_at']?:'—').'</td><td data-label="Отзыв">'.$review.'</td></tr>';
}
if(!$rows) $body.='<tr><td colspan="5" class="muted">Отзывов спикеров пока нет.</td></tr>';
$body.='</tbody></table>';
}
layout($type==='app'?'Отзывы заявителей':'Отзывы спикеров',$body); exit;
}
// ========== ИНСТРУМЕНТЫ ОПЕРАТОРА ==========
if($p==='operator_tools'){
require_role('operator');
$pendN=count(pending_applicants($u));
$deptFilter=(($u['op_group']??'')==='school')?'school':'';
$body='<div class="head"><h2>Инструменты</h2><a class="btn ghost" href="?p=operator">← в кабинет</a></div>';
$body.='<div style="display:flex;gap:8px;flex-wrap:wrap">
<a class="btn ghost" href="?p=export&type=requests&dept='.esc($deptFilter).'">📤 Экспорт заявок (CSV)</a>
<a class="btn ghost" href="?p=export&type=speakers">📥 Экспорт спикеров (CSV)</a>
<a class="btn ghost" href="?p=export&type=report&dept='.esc($deptFilter).'">📊 Отчёт для департамента (CSV)</a>
<a class="btn ghost" href="?p=operator&tab=moderation">👤 Модерация заявителей'.($pendN?' ('.$pendN.')':'').'</a>
<a class="btn ghost" href="?p=users">👥 Пользователи</a>
<a class="btn ghost" href="?p=districts">🗺️ Районы</a>
<a class="btn ghost" href="?p=audit">📋 Журнал действий</a>
<a class="btn ghost" href="?p=calendar">📅 Календарь</a>
<a class="btn ghost" href="?p=operator&tab=archive">🗄️ Архив</a>
<form method="get" style="display:inline"><input type="hidden" name="p" value="archive_run"><button class="btn ghost" onclick="return confirm(\'Архивировать?\')">🗄️ Запустить архивацию</button></form>
<a class="btn ghost" href="?p=operator&tab=speakers">Реестр спикеров</a>
<a class="btn ghost" href="?p=materials">🗂️ Материалы</a>
<a class="btn ghost" href="?p=lesson_readiness_admin">📘 Проверка готовности к занятиям</a>
<a class="btn ghost" href="?p=news_admin">📰 Новости сайта</a>
</div>';
layout('Инструменты',$body); exit;
}
// ========== ФОТО СПИКЕРА ==========
if($p==='speaker_photo'){
require_login();
$sp=find_user_by_id((int)($_GET['id']??0));
if(!$sp || $sp['role']!=='speaker' || empty($sp['photo_path'])){ http_response_code(404); exit; }
$path=__DIR__.'/uploads/photos/'.$sp['photo_path'];
if(!is_file($path)){ http_response_code(404); exit; }
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
header('Content-Type: '.($ext==='png'?'image/png':($ext==='webp'?'image/webp':'image/jpeg')));
header('Cache-Control: private, max-age=86400');
header('Content-Length: '.filesize($path));
readfile($path); exit;
}
// ========== МАТЕРИАЛЫ ==========
function material_size_label($bytes){ if(!$bytes) return ''; $mb=$bytes/1048576; return $mb>=1?round($mb,1).' МБ':round($bytes/1024,1).' КБ'; }
if($p==='material_download'){
$m=get_material((int)($_GET['id']??0));
if(!$m || empty($m['file_path'])){ http_response_code(404); exit('Файл не найден.'); }
$path=__DIR__.'/uploads/materials/'.$m['file_path'];
if(!is_file($path)){ http_response_code(404); exit('Файл не найден.'); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.rawurlencode($m['file_name']?:$m['file_path']).'"');
header('Content-Length: '.filesize($path));
readfile($path); exit;
}
if($p==='materials'){
$typeFilter=$_GET['type']??''; $topicParam=$_GET['topic']??null;
$addHref='?p=material_edit'.($topicParam?'&topic='.urlencode($topicParam):'');
$body='<div class="head"><h2>Материалы</h2><div style="display:flex;gap:8px"><a class="btn ghost" href="'.esc(role_home($u)).'">← в кабинет</a>'.($u['role']==='operator'?'<a class="btn primary" href="'.esc($addHref).'">+ Добавить материал</a>':'').'</div></div>';
if($topicParam===null && $typeFilter==='' && empty($_GET['all'])){
// ---- Папки по темам: техкарты, ролики и документы по одной теме лежат вместе ----
$counts=material_topic_counts(); $topics=material_topics_all();
if(!$topics && empty($counts[''])){
$body.='<div class="empty-state"><div class="empty-icon">🗂️</div><h3>Материалов пока нет</h3><p class="muted">'.($u['role']==='operator'?'Загрузите первый материал кнопкой выше.':'Департамент ещё не добавил ни одного материала.').'</p></div>';
} else {
$body.='<p class="muted">Материалы сгруппированы по темам занятий — в каждой папке техкарта, ролики и документы по одной теме вместе. <a href="?p=materials&all=1">Показать все материалы одним списком</a></p>';
$body.='<div class="folders-grid">';
foreach($topics as $t){ $c=$counts[$t]??0; $body.='<a class="folder-card" href="?p=materials&topic='.urlencode($t).'"><div class="folder-icon">📁</div><div class="folder-info"><div class="folder-title">'.esc($t).'</div><div class="folder-count">'.$c.' '.ru_plural($c,'материал','материала','материалов').'</div></div></a>'; }
if(!empty($counts[''])){ $c=$counts['']; $body.='<a class="folder-card" href="?p=materials&topic=" style="border-style:dashed"><div class="folder-icon">🗂️</div><div class="folder-info"><div class="folder-title">Без темы</div><div class="folder-count">'.$c.' '.ru_plural($c,'материал','материала','материалов').'</div></div></a>'; }
$body.='</div>';
}
layout('Материалы',$body); exit;
}
// ---- Внутри папки (или общий список по виду материала) ----
$topicFilter=$topicParam??'';
$rows=list_materials($typeFilter?:null,$topicParam);
if($topicParam!==null) $body.='<div class="hint-ok" style="margin-bottom:10px">Папка: <b>'.($topicFilter!==''?esc($topicFilter):'без темы').'</b>. <a href="?p=materials">← ко всем темам</a></div>';
$body.='<form method="get" style="margin-bottom:14px"><input type="hidden" name="p" value="materials">'.($topicParam!==null?'<input type="hidden" name="topic" value="'.esc($topicFilter).'">':'').'<label class="lbl" style="margin:0">Вид материала</label><select name="type" class="form-select" style="max-width:320px;display:inline-block;width:auto" onchange="this.form.submit()"><option value="">Все</option>';
foreach(material_types() as $k=>$v) $body.='<option value="'.esc($k).'" '.($typeFilter===$k?'selected':'').'>'.esc($v).'</option>';
$body.='</select></form>';
if(!$rows){
$body.='<div class="empty-state"><div class="empty-icon">🗂️</div><h3>Материалов пока нет</h3><p class="muted">'.($u['role']==='operator'?'Загрузите первый материал кнопкой выше.':'Департамент ещё не добавил ни одного материала.').'</p></div>';
} else {
$body.='<div class="materials-grid">';
foreach($rows as $m){
$body.='<div class="material-card"><div class="material-header"><div class="material-icon">'.material_type_icon($m['type']).'</div><div class="material-info"><div class="material-title">'.esc($m['title']).'</div><div class="material-meta"><span class="material-type">'.esc(material_types()[$m['type']]??$m['type']).'</span><span>тема: '.esc($m['topic']?:'—').'</span></div></div></div>';
$body.='<div class="material-body">';
if(!empty($m['description'])) $body.='<div class="material-desc">'.nl2br(esc($m['description'])).'</div>';
$body.='<div class="material-actions">';
if(!empty($m['file_path'])) $body.='<a class="btn-download" href="?p=material_download&id='.$m['id'].'">⬇ Скачать'.($m['file_size']?' ('.material_size_label($m['file_size']).')':'').'</a>';
if(!empty($m['link'])) $body.='<a class="btn ghost sm" href="'.esc($m['link']).'" target="_blank" rel="noopener">🔗 Ссылка</a>';
if($u['role']==='operator') $body.='<a class="btn ghost sm" href="?p=material_edit&id='.$m['id'].'">✏️</a><form method="post" style="display:inline" onsubmit="return confirm(\'Удалить материал?\')">'.csrf_field().'<input type="hidden" name="action" value="delete_material"><input type="hidden" name="id" value="'.$m['id'].'"><button type="submit" class="btn-delete">Удалить</button></form>';
$body.='</div><div class="muted" style="font-size:12px;margin-top:10px">загрузил: '.esc($m['uploader']?:'—').' · '.esc(substr((string)$m['created_at'],0,10)).'</div>';
$body.='</div></div>';
}
$body.='</div>';
}
layout('Материалы',$body); exit;
}
if($p==='material_edit'){
require_role('operator');
$id=(int)($_GET['id']??0); $m=$id?get_material($id):null;
if($id && !$m){ layout('Материал','<div class="card pad">Материал не найден. <a href="?p=materials">Назад</a></div>'); exit; }
$backTopic=$m['topic']??trim($_GET['topic']??'');
$tOpt=''; foreach(material_types() as $k=>$v) $tOpt.='<option value="'.esc($k).'" '.(($m['type']??'')===$k?'selected':'').'>'.esc($v).'</option>';
$backHref=$backTopic!==''?'?p=materials&topic='.urlencode($backTopic):'?p=materials';
$body='<div class="head"><h2>'.($m?'Редактирование материала':'Добавить материал').'</h2><a class="btn ghost" href="'.esc($backHref).'">← к материалам</a></div>';
$body.='<form method="post" class="upload-form" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="'.($m?'update_material':'add_material').'">'.($m?'<input type="hidden" name="id" value="'.$m['id'].'">':'');
$body.='<div class="form-group"><label class="form-label">Название <span style="color:#d11">*</span></label><input class="form-input" name="title" required value="'.esc($m['title']??'').'"></div>';
$body.='<div class="form-group"><label class="form-label">Тема <span style="color:#d11">*</span></label><input class="form-input" name="topic" required list="topics_dl" value="'.esc($m['topic']??$backTopic).'"><p class="form-hint">Материалы с одинаковой темой попадают в одну папку — впишите новую тему или выберите существующую из подсказок.</p><datalist id="topics_dl">'; foreach(material_topics_all() as $t) $body.='<option value="'.esc($t).'">'; $body.='</datalist></div>';
$body.='<div class="form-group"><label class="form-label">Вид материала <span style="color:#d11">*</span></label><select class="form-select" name="type" required><option value="">— выберите —</option>'.$tOpt.'</select></div>';
$body.='<div class="form-group"><label class="form-label">Описание</label><textarea class="form-textarea" name="description">'.esc($m['description']??'').'</textarea></div>';
$body.='<div class="form-group"><label class="form-label">Файл (до 100 МБ)</label><div class="file-upload-wrapper"><input class="file-input" type="file" name="file" id="material_file" onchange="document.getElementById(\'material_file_name\').textContent=this.files.length?this.files[0].name:\'Файл не выбран\';"><label for="material_file" class="file-upload-label"><span>📎</span><span class="file-upload-text">Нажмите, чтобы выбрать файл</span><span class="file-name" id="material_file_name">'.($m&&!empty($m['file_path'])?esc($m['file_name']):'Файл не выбран').'</span></label></div></div>';
$body.='<div class="form-group"><label class="form-label">Или ссылка на файл</label><input class="form-input" name="link" value="'.esc($m['link']??'').'" placeholder="https://..."><p class="form-hint">Нужно указать файл или ссылку (можно оба).</p></div>';
$body.='<div class="form-actions"><button class="btn primary btn-lg">Сохранить</button></div></form>';
layout('Материал',$body); exit;
}
// ========== КАБИНЕТ ФОНДА ==========
if($p==='fund'){
require_role('fund'); $tab=$_GET['tab']??'queue';
if(empty($u['active'])){ layout('Кабинет фонда','<div class="head"><h2>Подразделение на проверке</h2></div><div class="card pad"><p>Регистрация подразделения отправлена на подтверждение головному фонду.</p><p class="muted">После подтверждения откроется доступ.</p></div>'); exit; }
if($tab==='speakers'){
$body='<div class="head"><h2>Спикеры Фонда</h2><a class="btn ghost" href="?p=fund">← в кабинет</a></div><h4 style="margin-top:6px">На подтверждении</h4>';
$pend=pending_speakers($u); if(!$pend) $body.='<div class="card pad muted" style="margin-bottom:14px">Новых заявок на регистрацию спикеров нет.</div>';
foreach($pend as $s){ $ag=''; foreach(ages() as $k=>$v) $ag.='<label class="chk"><input type="checkbox" name="groups['.esc($k).']" '.(in_array($k,explode(',',$s['age_groups']))?'checked':'').'> '.esc($v).'</label>';
$body.='<div class="card pad" style="margin-bottom:10px"><b>'.esc($s['display_name']).'</b> <span class="muted">(логин: '.esc($s['login']).' · район: '.esc($s['district']?:'—').' · тел.: '.esc($s['phone']?:'—').')</span>'
.'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="approve_speaker"><input type="hidden" name="uid" value="'.$s['id'].'"><div class="chks" style="margin:8px 0">'.$ag.'</div><div style="display:flex;gap:8px"><button class="btn primary">Подтвердить и дать доступ</button></div></form>'
.'<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Отклонить регистрацию спикера?\')">'.csrf_field().'<input type="hidden" name="action" value="reject_speaker"><input type="hidden" name="uid" value="'.$s['id'].'"><button class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">Отклонить</button></form>'
.'</div>'; }
$act=active_speakers($u); $body.='<h4>Подтверждённые спикеры</h4><table class="card-view"><thead><tr><th>Спикер</th><th>Район</th><th>Телефон</th><th>Возрастные группы</th><th>Статус</th></tr></thead><tbody>';
foreach($act as $s){ $g=array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$s['age_groups']))); $crimBadge=speaker_has_criminal_record($s)?'<span class="pill" style="background:#fdecea;color:#b23b2c">⚠ судимость</span>':'<span class="muted">—</span>'; $body.='<tr onclick="location=\'?p=speaker_view&id='.$s['id'].'\'"><td class="id"><a href="?p=speaker_view&id='.$s['id'].'">'.esc($s['display_name']).'</a></td><td class="muted">'.esc($s['district']?:'—').'</td><td class="muted">'.esc($s['phone']?:'—').'</td><td class="muted">'.esc(implode(', ',$g)).'</td><td data-label="Статус">'.$crimBadge.'</td></tr>'; }
if(!$act) $body.='<tr><td colspan="5" class="muted">Пока нет.</td></tr>'; $body.='</tbody></table>';
$body.='<div style="margin-top:14px;display:flex;gap:8px"><a class="btn ghost" href="?p=users&role=speaker">👥 Управление спикерами</a><a class="btn ghost" href="?p=audit&user_role=speaker">📋 Журнал</a><a class="btn ghost" href="?p=calendar">📅 Календарь</a></div>';
layout('Спикеры Фонда',$body); exit;
}
if($tab==='archive'){
$fIds=fund_scope_ids($u);
$afil=['archived'=>1,'fund_ids'=>$fIds];
if(!empty($_GET['org'])) $afil['org']=$_GET['org'];
if(!empty($_GET['from_date'])) $afil['from_date']=$_GET['from_date'];
if(!empty($_GET['to_date'])) $afil['to_date']=$_GET['to_date'];
$rows=get_requests_list($afil);
if(!empty($_GET['sort'])) $rows=sort_requests($rows,$_GET['sort'],$_GET['dir']??'asc');
$body='<div class="head"><h2>Архив заявок</h2></div>';
$body.='<div class="guide-tabs" style="margin:-6px 0 14px"><a class="guide-tab" href="?p=fund">Активные</a> <a class="guide-tab active" href="?p=fund&tab=archive">Архив</a></div>';
$body.='<p class="muted">Закрытые заявки автоматически переносятся сюда через 5 рабочих дней после закрытия.</p>';
$body.='<form method="get" class="card pad" style="margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:end"><input type="hidden" name="p" value="fund"><input type="hidden" name="tab" value="archive">'
.'<label class="lbl" style="margin:0">Организация</label><input name="org" value="'.esc($_GET['org']??'').'" placeholder="Поиск">'
.'<label class="lbl" style="margin:0">С</label><input type="date" name="from_date" value="'.esc($_GET['from_date']??'').'">'
.'<label class="lbl" style="margin:0">По</label><input type="date" name="to_date" value="'.esc($_GET['to_date']??'').'">'
.'<button class="btn primary sm">Фильтр</button> <a class="btn ghost sm" href="?p=fund&tab=archive">Сбросить</a></form>';
$labels=req_labels_bulk(array_column($rows,'id'),false);
$tr=''; foreach($rows as $r){ $tr.='<tr onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id"><a href="?p=request&id='.$r['id'].'">'.esc($r['code']).'</a></td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.esc($labels[$r['id']]??'не выбран').'</td><td>'.pill($r['status']).'</td><td class="muted">'.esc($r['archived_at']??'').'</td></tr>'; }
$hasFilter=!empty($_GET['org'])||!empty($_GET['from_date'])||!empty($_GET['to_date']);
$body.= $rows ? '<table class="card-view"><thead><tr>'.sort_th('Код','code').sort_th('Организация','org').sort_th('Дата','date').'<th>Спикеры</th>'.sort_th('Статус','status').'<th>Архивировано</th></tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">'.($hasFilter?'По заданным фильтрам ничего не найдено.':'Архив пуст.').'</div>';
layout('Архив заявок',$body); exit;
}
$rows=fund_dashboard_requests($u);
if(!empty($_GET['sort'])) $rows=sort_requests($rows,$_GET['sort'],$_GET['dir']??'asc');
else $rows=sort_requests($rows,'date','asc');
$archivedCount=fund_archived_count($u);
$myScope=fund_scope_ids($u);
$action_req = get_action_required_requests($u);
$k=fund_kpis($u); $kp='';
foreach($k as $c){
$cls='kpi'.(($c[0]==='Не состоялось'&&(int)$c[1]>0)?' warn':'');
if(!empty($c[2])){ $kp.='<a class="'.$cls.'" href="'.esc($c[2]).'" style="text-decoration:none;color:inherit"><div class="n">'.esc($c[1]).'</div><div class="l">'.esc($c[0]).'</div></a>'; }
else { $kp.='<div class="'.$cls.'"><div class="n">'.esc($c[1]).'</div><div class="l">'.esc($c[0]).'</div></div>'; }
}
$body = '<div class="kpis">'.$kp.'</div>';
$body.=month_stats_widget(fund_scope_requests($u),'fundStatMonth');
$subpend=pending_subfunds($u);
if($subpend){ $body.='<div class="action-block"><h4>🏢 Подразделения на подтверждении <span class="action-badge">'.count($subpend).'</span></h4>'; foreach($subpend as $sf){ $body.='<form method="post" style="margin:6px 0">'.csrf_field().'<input type="hidden" name="action" value="approve_subfund"><input type="hidden" name="uid" value="'.$sf['id'].'"><b>'.esc($sf['display_name']).'</b> <span class="muted">(район: '.esc($sf['district']?:'—').', логин: '.esc($sf['login']).')</span> <button class="btn primary sm">Подтвердить подразделение</button></form>'; } $body.='</div>'; }
if ($action_req) {
$body .= '<div class="action-block"><h4>⚠️ Требует вашего действия <span class="action-badge">'.count($action_req).'</span></h4><ul class="action-list">';
foreach ($action_req as $ar) {
$msg = whose_turn($ar)[1];
$urgent_cls = is_request_urgent($ar) ? ' <span class="muted">(Срок скоро!)</span>' : '';
$body .= '<li><span>'.esc($ar['code']).' — '.esc($ar['org']).' <span class="muted">('.dt($ar).')</span>'.$urgent_cls.'</span><a href="?p=request&id='.$ar['id'].'">'.$msg.' →</a></li>';
}
$body .= '</ul></div>';
}
$labels=req_labels_bulk(array_column($rows,'id'),false);
$confirmedCounts=count_confirmed_bulk(array_column($rows,'id'));
$tr=''; foreach($rows as $r){
$mine=!empty($r['claimed_fund_id']) && in_array((int)$r['claimed_fund_id'],$myScope,true);
$rowCls=is_request_urgent($r)?'urgent':'';
$claimBadge=$mine?((($confirmedCounts[$r['id']]??0)>=1)?' <span class="pill green">✓ подтверждён</span>':' <span class="pill gray">🔧 в обработке</span>'):'';
$need=in_array($r['status'],['chosen','confirmed'])?' <span class="pill amber">нужно действие</span>':'';
[$turnWho,$turnWhat]=whose_turn($r);
$tr.='<tr class="'.$rowCls.'" onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id"><a href="?p=request&id='.$r['id'].'">'.esc($r['code']).'</a></td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.esc($labels[$r['id']]??'не выбран').'</td><td title="'.esc($turnWho.($turnWhat?' — '.$turnWhat:'')).'">'.pill($r['status']).$need.$claimBadge.'</td></tr>';
}
$body.='<div class="head"><h2>Реестр заявок</h2><div><a class="btn ghost" href="?p=fund&tab=speakers">👥 Спикеры Фонда</a> <a class="btn ghost" href="?p=calendar">📅 Календарь</a> <a class="btn ghost" href="?p=materials">🗂️ Материалы</a> <a class="btn ghost" href="?p=export">⬇ Экспорт в CSV</a></div></div>';
$body.='<div class="guide-tabs" style="margin:-6px 0 14px"><a class="guide-tab active" href="?p=fund">Активные ('.count($rows).')</a> <a class="guide-tab" href="?p=fund&tab=archive">Архив'.($archivedCount?' ('.$archivedCount.')':'').'</a></div>';
$body.='<p class="muted">Новые заявки без спикера доступны всем фондам (в статусе — «свободна», не закреплена). Пометка <span class="pill gray">🔧 в обработке</span> / <span class="pill green">✓ подтверждён</span> — заявки, взятые в работу вашим фондом. <span style="background:#fff3e0;padding:2px 8px;border-radius:6px">Персиковым фоном</span> подсвечены заявки со скорым сроком урока.</p>';
$body.= $rows ? '<table class="card-view"><thead><tr>'.sort_th('Код','code').sort_th('Организация','org').sort_th('Дата и время','date').sort_th('Спикеры','speakers').sort_th('Статус','status').'</tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">Заявок пока нет.</div>';
layout('Кабинет Фонда',$body); exit;
}
// ========== КАБИНЕТ ЗАЯВИТЕЛЯ ==========
if($p==='school'){
require_role('school'); $tab=$_GET['tab']??'mine';
$eff=school_effective($u);
if(empty($u['active'])){
if(!empty($u['parent_school_id'])){ layout('Кабинет заявителя','<div class="head"><h2>Ваша учётная запись на проверке</h2></div><div class="card pad"><p>Ваша заявка на присоединение к организации <b>'.esc($eff['org_name']).'</b> отправлена представителю этой организации.</p><p class="muted">После подтверждения вы сможете подавать заявки.</p></div>'); exit; }
layout('Кабинет заявителя','<div class="head"><h2>Ваша учётная запись на проверке</h2></div><div class="card pad"><p>Заявка на регистрацию организации <b>'.esc($u['org_name']).'</b> отправлена на подтверждение в профильный департамент ('.esc(operator_of($u['level'])).').</p><p class="muted">После подтверждения вы сможете подавать заявки.</p></div>'); exit; }
if($tab==='new'){
$old=pull_old_input();
$src=null; if(!empty($_GET['copy'])){ $c0=get_request((int)$_GET['copy']); if($c0 && school_owns_request($u,$c0)) $src=$c0; }
if($old) $src=array_merge($src?:[],$old);
$sg=$src['age_group']??''; $opt=''; foreach(level_ages($eff['level']) as $g) $opt.='<option value="'.esc($g).'" '.($sg===$g?'selected':'').'>'.esc(ages()[$g]).'</option>';
$sn=(int)($src['speakers_needed']??0); $nopt=''; for($i=1;$i<=5;$i++) $nopt.='<option value="'.$i.'" '.($sn===$i?'selected':'').'>'.$i.'</option>';
$body='<div class="head"><h2>Подать заявку</h2><a class="btn ghost" href="?p=school&tab=mine">Мои заявки</a></div>
'.(!empty($src['code'])?'<div class="card pad" style="margin-bottom:10px;border-left:4px solid var(--rf-blue,#1A56C4)">Поля заполнены по образцу заявки <b>'.esc($src['code']).'</b>. Проверьте данные и укажите новую дату.</div>':'').'
<form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="submit_request">
<p class="muted">Организация: <b>'.esc($eff['org_name']).'</b> · оператор: '.esc(operator_of($eff['level'])).'</p>
<p class="muted">После отправки заявка сразу станет доступна всем фондам — они сами подберут и назначат спикера(ов). Вы можете указать желаемого спикера ниже — это пожелание, окончательное решение за фондом.</p>
<label class="lbl">Возрастная группа <span style="color:#d11">*</span></label><select name="age_group" required>'.$opt.'</select>
<label class="lbl">Сколько спикеров нужно <span style="color:#d11">*</span></label><select name="speakers_needed" id="speakers_needed_sel" onchange="snHint()">'.$nopt.'</select>
<div id="sn_hint" class="hint-warn" style="display:none"><b>⚠ Подсказка:</b> заявка сразу становится доступна всем фондам — они сами подберут и назначат нужное число спикеров.</div>
<label class="lbl">Контактное лицо <span style="color:#d11">*</span></label><input name="contact_name" required value="'.esc($src['contact_name']??'').'">
<label class="lbl">Телефон <span style="color:#d11">*</span></label><input name="phone" required value="'.esc($src['phone']??'').'">
<label class="lbl">Желаемая дата <span style="color:#d11">*</span> (не ранее чем через 10 дней)</label><input type="date" name="udate" min="'.min_create_date().'" required value="'.esc(substr((string)($src['udate']??''),0,10)).'">
<label class="lbl">Время начала <span style="color:#d11">*</span></label><select name="utime" required><option value="">—</option>'.time_options($src['utime']??'').'</select>
<label class="lbl">Время окончания <span style="color:#d11">*</span></label><select name="utime_end" required><option value="">—</option>'.time_options($src['utime_end']??'').'</select>
<label class="lbl">Число участников</label><input name="participants" value="'.esc($src['participants']??'').'">
<label class="lbl">Тема занятия</label>'.(function() use($src){
$cur=$src['topic']??''; $topics=material_topics(); $known=in_array($cur,$topics,true);
$o='<option value="">— выберите тему —</option>';
if($cur!==''&&!$known) $o.='<option value="'.esc($cur).'" selected>'.esc($cur).' (старая свободная тема)</option>';
foreach($topics as $t){ $o.='<option value="'.esc($t).'"'.($cur===$t?' selected':'').'>'.esc($t).'</option>'; }
return '<select name="topic">'.$o.'</select>';
})().'
<label class="lbl">Желаемый спикер (необязательно)</label>'.(function() use($src){
$cur=(int)($src['desired_speaker_id']??0);
$o='<option value="">— без предпочтения —</option>';
foreach(active_speakers() as $s){ $ags=array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$s['age_groups']))); $o.='<option value="'.$s['id'].'"'.($cur===(int)$s['id']?' selected':'').'>'.esc($s['display_name']).' — '.esc($s['district']?:'район не указан').' ('.esc(implode(', ',$ags)).')</option>'; }
return '<select name="desired_speaker_id">'.$o.'</select><p class="muted" style="margin:4px 0 0">Это пожелание для фонда, не гарантия назначения именно этого спикера.</p>';
})().'
<label class="lbl">Особые условия</label><textarea name="notes">'.esc($src['notes']??'').'</textarea>
<label class="chk" style="margin-top:8px"><input type="checkbox" name="consent" required> Согласие на обработку ПДн <span style="color:#d11">*</span> (<a href="?p=privacy" target="_blank">политика 152-ФЗ</a>)</label>
<button class="btn primary" style="margin-top:12px">Отправить заявку</button></form>
<script>function snHint(){var n=parseInt(document.getElementById("speakers_needed_sel").value);var h=document.getElementById("sn_hint");if(h)h.style.display=(n>=2)?"block":"none";}snHint();</script>';
layout('Подать заявку',$body); exit;
}
$scopeIds=school_scope_ids($u); $scopeIn=implode(',',array_fill(0,count($scopeIds),'?'));
$st=db()->prepare("SELECT * FROM requests WHERE school_user_id IN ($scopeIn) AND (archived=0 OR archived IS NULL) ORDER BY id DESC"); $st->execute($scopeIds); $rows=$st->fetchAll();
if(!empty($_GET['sort'])) $rows=sort_requests($rows,$_GET['sort'],$_GET['dir']??'asc');
else $rows=sort_requests($rows,'date','asc');
$action_req = get_action_required_requests($u);
$body = '';
if(empty($u['parent_school_id'])){
$pendMembers=pending_school_members($u);
if($pendMembers){ $body.='<div class="action-block"><h4>👥 Сотрудники на подтверждении <span class="action-badge">'.count($pendMembers).'</span></h4>';
foreach($pendMembers as $pm){
$body.='<div class="card pad" style="margin-bottom:10px;background:#fff">'
.'<dl class="kv"><dt>ФИО</dt><dd>'.esc($pm['contact_name']?:'—').'</dd>'
.'<dt>Должность</dt><dd>'.esc($pm['position']?:'—').'</dd>'
.'<dt>Телефон</dt><dd>'.esc($pm['phone']?:'—').'</dd>'
.'<dt>Email</dt><dd>'.esc($pm['email']?:'—').'</dd>'
.'<dt>Район</dt><dd>'.esc($pm['district']?:'—').'</dd>'
.'<dt>Логин</dt><dd>'.esc($pm['login']).'</dd>'
.'<dt>Подана</dt><dd>'.esc($pm['created_at']?:'—').'</dd></dl>'
.'<div style="margin-top:8px;display:flex;gap:8px">'
.'<form method="post" style="display:inline">'.csrf_field().'<input type="hidden" name="action" value="approve_school_member"><input type="hidden" name="uid" value="'.$pm['id'].'"><button class="btn primary sm">Подтвердить</button></form>'
.'<form method="post" style="display:inline" onsubmit="return confirm(\'Отклонить сотрудника?\')">'.csrf_field().'<input type="hidden" name="action" value="reject_school_member"><input type="hidden" name="uid" value="'.$pm['id'].'"><button class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">Отклонить</button></form>'
.'</div></div>';
}
$body.='</div>'; }
}
if ($action_req) {
$body .= '<div class="action-block"><h4>⚠️ Требует вашего действия <span class="action-badge">'.count($action_req).'</span></h4><ul class="action-list">';
foreach ($action_req as $ar) {
$msg = whose_turn($ar)[1];
$urgent_cls = is_request_urgent($ar) ? ' <span class="muted">(Срок скоро!)</span>' : '';
$body .= '<li><span>'.esc($ar['code']).' — '.esc($ar['org']).' <span class="muted">('.dt($ar).')</span>'.$urgent_cls.'</span><a href="?p=request&id='.$ar['id'].'">'.$msg.' →</a></li>';
}
$body .= '</ul></div>';
}
$labels=req_labels_bulk(array_column($rows,'id'));
$tr=''; foreach($rows as $r){
$urgent_tr = is_request_urgent($r) ? 'urgent' : '';
[$turnWho,$turnWhat]=whose_turn($r);
$tr.='<tr class="'.$urgent_tr.'" onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id"><a href="?p=request&id='.$r['id'].'">'.esc($r['code']).'</a></td><td>'.dt($r).'</td><td>'.esc(ages()[$r['age_group']]??'').'</td><td>'.esc($labels[$r['id']]??'не выбран').'</td><td title="'.esc($turnWho.($turnWhat?' — '.$turnWhat:'')).'">'.pill($r['status']).'</td></tr>';
}
$body.='<div class="head"><h2>'.esc($eff['org_name']).'</h2><div><a class="btn primary" href="?p=school&tab=new">+ Подать заявку</a> <a class="btn ghost" href="?p=calendar">📅 Календарь</a> <a class="btn ghost" href="?p=materials">🗂️ Материалы</a> <a class="btn ghost" href="?p=users&role=school">👥 Сотрудники</a> <a class="btn ghost" href="?p=export">⬇ Экспорт в CSV</a></div></div>';
$sch_total=count($rows);
$sch_waiting=count(array_filter($rows,fn($r)=>$r['status']==='new'));
$sch_inwork=count(array_filter($rows,fn($r)=>in_array($r['status'],['chosen','confirmed','prep'],true)));
$sch_done=count(array_filter($rows,fn($r)=>in_array($r['status'],['done','closed'],true)));
$body.='<div class="kpis"><div class="kpi"><div class="n">'.$sch_total.'</div><div class="l">Всего заявок</div></div><div class="kpi"><div class="n">'.$sch_waiting.'</div><div class="l">Ожидают фонда</div></div><div class="kpi"><div class="n">'.$sch_inwork.'</div><div class="l">В работе</div></div><div class="kpi"><div class="n">'.$sch_done.'</div><div class="l">Проведено</div></div></div>';
$body.=school_month_report(school_requests_for_stats($u),'schMonth');
$body.= $rows ? '<table class="card-view"><thead><tr>'.sort_th('Код','code').sort_th('Дата и время','date').'<th>Аудитория</th><th>Спикеры</th>'.sort_th('Статус','status').'</tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="empty-state"><div class="empty-icon">📋</div><h3>Заявок пока нет</h3><p class="muted">Нажмите «Подать заявку», чтобы отправить первую заявку на Урок Мужества.</p></div>';
layout('Кабинет заявителя',$body); exit;
}
// ========== КАБИНЕТ СПИКЕРА ==========
if($p==='speaker'){
require_role('speaker');
if(!$u['active']){ layout('Кабинет спикера','<div class="card pad"><h2>Ожидает подтверждения</h2><p class="muted">Ваша учётная запись ожидает подтверждения Фондом.</p></div>'); exit; }
$rows=speaker_lessons($u['id']);
if(!empty($_GET['sort'])) $rows=sort_requests($rows,$_GET['sort'],$_GET['dir']??'asc');
else $rows=sort_requests($rows,'date','asc');
$daily=speaker_daily_count($u['id'],date('Y-m-d'));
$weekly=speaker_weekly_count($u['id'],date('Y-m-d'));
$tr=''; foreach($rows as $r){ $mark=$r['cfd']?'<span style="color:var(--green);font-weight:600">в составе</span>':(st_index($r['status'])>=st_index('confirmed')?'<span class="muted">не выбран</span>':'<span class="muted">кандидат</span>'); [$turnWho,$turnWhat]=whose_turn($r); $tr.='<tr onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id"><a href="?p=request&id='.$r['id'].'">'.esc($r['code']).'</a></td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.$mark.'</td><td title="'.esc($turnWho.($turnWhat?' — '.$turnWhat:'')).'">'.pill($r['status']).'</td></tr>'; }
$body='<div class="head"><h2>Мои уроки</h2><div><a class="btn ghost" href="?p=speaker_anketas">📋 Анкеты</a> <a class="btn ghost" href="?p=speaker_stats">📊 Моя статистика</a> <a class="btn ghost" href="?p=calendar">📅 Календарь</a> <a class="btn ghost" href="?p=materials">🗂️ Материалы</a></div></div>';
if(speaker_has_criminal_record($u)) $body.='<div class="hint-warn">'.nl2br(esc(criminal_record_notice())).'</div>';
$dPct=$daily/2; $wPct=$weekly/7;
$dCls='kpi'.($dPct>=1?' warn':($dPct>=0.8?' caution':''));
$wCls='kpi'.($wPct>=1?' warn':($wPct>=0.8?' caution':''));
$body.='<div class="kpis"><div class="'.$dCls.'"><div class="n">'.$daily.' / 2</div><div class="l">Загрузка сегодня</div></div><div class="'.$wCls.'"><div class="n">'.$weekly.' / 7</div><div class="l">Загрузка на неделе</div></div><div class="kpi"><div class="n">'.count($rows).'</div><div class="l">Всего назначено</div></div></div>';
$body.='<p class="muted" style="margin-top:-8px;font-size:12.5px">Загрузка — активные уроки в пределах лимита (2 в день / 7 в неделю). Проведённые уроки и статистика — в разделе «Моя статистика».</p>';
if($todayExempt=speaker_load_exempt_label(date('Y-m-d'))) $body.='<div class="hint-ok" style="margin-top:8px">📅 Сегодня — «'.esc($todayExempt).'». Ограничение по нагрузке (2 урока в день / 7 в неделю) в этот день не действует.</div>';
$body.= $rows ? '<table class="card-view"><thead><tr>'.sort_th('Код','code').sort_th('Организация','org').sort_th('Дата и время','date').'<th>Роль на уроке</th>'.sort_th('Статус','status').'</tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">Назначенных уроков пока нет.</div>';
$pool=fund_pool_requests_for_speaker($u);
if($pool){ $body.='<div class="block" style="margin-top:20px"><h4>📋 Заявки вашего фонда в обработке</h4><p class="muted">Ваш Фонд взял эти заявки в работу и подбирает спикера. Как только он назначит конкретного спикера, заявка появится в разделе «Мои уроки» у него.</p>';
$body.='<table class="card-view"><thead><tr><th>Код</th><th>Организация</th><th>Дата и время</th></tr></thead><tbody>';
foreach($pool as $pr) $body.='<tr><td class="id">'.esc($pr['code']).'</td><td>'.esc($pr['org']).'</td><td>'.dt($pr).'</td></tr>';
$body.='</tbody></table></div>'; }
// ========== КАЛЕНДАРЬ НЕДОСТУПНОСТИ ==========
$body.='<div class="card pad" style="margin-top:20px"><h4>📅 Календарь недоступности</h4>';
$body.='<p class="muted">Укажите периоды, когда вы не можете проводить занятия (отпуск, больничный и т.п.). В эти даты вы не будете попадать в список доступных спикеров.</p>';
$body.='<form method="post" class="card pad" style="margin-bottom:15px;background:#f9fafb">'.csrf_field().'<input type="hidden" name="action" value="add_unavailable">';
$body.='<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">';
$body.='<div style="flex:1;min-width:150px"><label class="lbl">С даты <span style="color:#d11">*</span></label><input type="date" name="date_from" required min="'.date('Y-m-d').'" class="form-input"></div>';
$body.='<div style="flex:1;min-width:150px"><label class="lbl">По дату <span style="color:#d11">*</span></label><input type="date" name="date_to" required min="'.date('Y-m-d').'" class="form-input"></div>';
$body.='<div style="flex:1;min-width:200px"><label class="lbl">Причина <span style="color:#d11">*</span></label><select name="reason" required class="form-select"><option value="">— выберите —</option><option value="Отпуск">Отпуск</option><option value="Больничный">Больничный</option><option value="Командировка">Командировка</option><option value="Личные обстоятельства">Личные обстоятельства</option><option value="Другое">Другое</option></select></div>';
$body.='<button type="submit" class="btn primary">Добавить период</button>';
$body.='</div></form>';
$unavailable=list_unavailable($u['id']);
if($unavailable){
$body.='<h4 style="margin-top:15px">Ваши закрытые периоды</h4>';
$body.='<table class="card-view"><thead><tr><th>Период</th><th>Дней</th><th>Причина</th><th>Действия</th></tr></thead><tbody>';
foreach($unavailable as $un){
$days=max(1,(strtotime($un['date_to'])-strtotime($un['date_from']))/86400+1);
$reason=esc($un['reason']?:'—');
if($un['date_to']<date('Y-m-d')) $reason=' <span class="muted">(прошло)</span> '.$reason;
$body.='<tr><td class="id" data-label="Период">'.esc($un['date_from']).' — '.esc($un['date_to']).'</td><td data-label="Дней">'.(int)$days.'</td><td data-label="Причина">'.$reason.'</td><td data-label="Действия"><form method="post" style="display:inline" onsubmit="return confirm(\'Удалить период?\')">'.csrf_field().'<input type="hidden" name="action" value="remove_unavailable"><input type="hidden" name="uid" value="'.$un['id'].'"><button type="submit" class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">Удалить</button></form></td></tr>';
}
$body.='</tbody></table>';
} else {
$body.='<p class="muted" style="margin-top:10px">У вас пока нет закрытых периодов.</p>';
}
$body.='</div>';
layout('Кабинет спикера',$body); exit;
}
// ========== АНКЕТЫ СПИКЕРА (отдельный раздел) ==========
if($p==='speaker_anketas'){
require_role('speaker');
if(!$u['active']){ redirect('?p=speaker'); }
$body='<div class="head"><h2>Анкеты</h2><a class="btn ghost" href="?p=speaker">← в кабинет</a></div>';
$body.='<div class="block"><h4>🎓 Допуск к возрастным группам</h4><p class="muted">На заявки этой группы вас предложат фонду, только если группа допущена. Заполните анкету по нужной группе — фонд примет решение о допуске по её результатам. Заполнить можно в любой момент, повторно — тоже.</p>';
$pOverview=speaker_psych_readiness_overview($u);
$body.='<div class="materials-grid">';
foreach($pOverview as $gk=>$pov){
$body.='<div class="material-card"><div class="material-header"><div class="material-info"><div class="material-title">'.esc($pov['label']).'</div></div></div><div class="material-body">';
if($pov['last']){ $pmeta=psych_readiness_status_meta($pov['last']['status']); $body.='<span class="pill '.esc($pmeta['color']).'">'.esc($pmeta['label']).'</span><p class="muted" style="margin:8px 0 0;font-size:12.5px">'.(int)$pov['last']['score'].' из 60 ('.esc(substr((string)$pov['last']['created_at'],0,10)).')</p>'; }
else $body.='<span class="pill gray">Не заполнена</span>';
$body.='<div style="margin-top:10px"><a class="btn ghost sm" href="?p=speaker_psych_readiness&group='.esc($gk).'">'.($pov['last']?'Заполнить заново':'Заполнить анкету').'</a></div>';
$body.='</div></div>';
}
$body.='</div></div>';
$body.='<div class="block" style="margin-top:20px"><h4>🧭 Анкета самооценки готовности</h4><p class="muted">Помогает вам и куратору понять, насколько вы сейчас готовы говорить с детьми конкретного возраста о своём опыте. Честный ответ не снижает ваш статус в программе — он лишь подсказывает следующий шаг. Заполнить можно в любой момент, повторно — тоже.</p>';
$rOverview=speaker_readiness_overview($u);
$body.='<div class="materials-grid">';
foreach($rOverview as $bk=>$ov){
$body.='<div class="material-card"><div class="material-header"><div class="material-info"><div class="material-title">'.esc($ov['label']).'</div></div></div><div class="material-body">';
if($ov['last']){ $meta=readiness_status_meta($ov['last']['status']); $body.='<span class="pill '.esc($meta['color']).'">'.esc($meta['label']).'</span><p class="muted" style="margin:8px 0 0;font-size:12.5px">'.(int)$ov['last']['score'].' из 20 ('.esc(substr((string)$ov['last']['created_at'],0,10)).')</p>'; }
else $body.='<span class="pill gray">Не заполнена</span>';
$body.='<div style="margin-top:10px"><a class="btn ghost sm" href="?p=speaker_readiness&block='.esc($bk).'">'.($ov['last']?'Заполнить заново':'Заполнить анкету').'</a></div>';
$body.='</div></div>';
}
$body.='</div></div>';
layout('Анкеты',$body); exit;
}
// ========== СТАТИСТИКА СПИКЕРА (отдельный раздел, видит спикер и фонд) ==========
if($p==='speaker_stats'){
require_login();
$sid=(int)($_GET['id']??0);
if($u['role']==='speaker'){ if($sid && $sid!==(int)$u['id']) exit('Нет доступа.'); $sp=$u; $back='?p=speaker'; }
elseif(in_array($u['role'],['fund','operator'],true)){
$sp=$sid?find_user_by_id($sid):null;
if(!$sp||$sp['role']!=='speaker'){ layout('Статистика','<div class="card pad">Спикер не найден.</div>'); exit; }
if($u['role']==='fund' && !in_array((int)($sp['fund_user_id']??0),fund_scope_ids($u),true)){ exit('Нет доступа.'); }
$back='?p=speaker_view&id='.$sp['id'];
} else { exit('Нет доступа.'); }
$stats=speaker_stats($sp['id']);
$statCard=function($period,$lessons,$kids){
return '<div class="stat-card"><div class="stat-period">'.esc($period).'</div><div class="stat-row"><span class="n">'.$lessons.'</span><span class="l">'.($lessons==1?'урок':($lessons>=2&&$lessons<=4?'урока':'уроков')).'</span></div><div class="stat-row"><span class="n">'.$kids.'</span><span class="l">детей</span></div></div>';
};
$title=$u['role']==='speaker'?'Моя статистика':'Статистика: '.esc($sp['display_name']);
$body='<div class="head"><h2>'.$title.'</h2><a class="btn ghost" href="'.esc($back).'">← назад</a></div>';
$body.='<h4 style="margin-top:0">Проведено уроков и охват детей</h4>';
$body.='<div class="stat-grid">'.$statCard('Сегодня',$stats['day'],$stats['day_kids']).$statCard('За неделю',$stats['week'],$stats['week_kids']).$statCard('За месяц',$stats['month'],$stats['month_kids']).$statCard('Всего',$stats['total'],$stats['total_kids']).'</div>';
if($stats['by_month']){
$body.='<details class="card pad" style="margin-top:18px" open><summary style="cursor:pointer;font-weight:600;color:var(--navy)">📅 Разбивка по месяцам</summary><table class="card-view" style="margin-top:12px"><thead><tr><th>Месяц</th><th>Уроков</th><th>Детей</th></tr></thead><tbody>';
foreach($stats['by_month'] as $mk=>$mv) $body.='<tr><td class="id" data-label="Месяц">'.esc(month_label($mk)).'</td><td data-label="Уроков">'.$mv['lessons'].'</td><td data-label="Детей">'.$mv['kids'].'</td></tr>';
$body.='</tbody></table></details>';
} else {
$body.='<p class="muted" style="margin-top:14px">Проведённых уроков пока нет.</p>';
}
layout($u['role']==='speaker'?'Моя статистика':'Статистика спикера',$body); exit;
}
// ========== ТЕСТ ДЛЯ ДОПУСКА К ВОЗРАСТНОЙ ГРУППЕ ==========
// ========== АНКЕТА САМООЦЕНКИ ГОТОВНОСТИ ==========
if($p==='speaker_readiness'){
require_role('speaker');
if(!$u['active']){ redirect('?p=speaker'); }
$blockKey=$_GET['block']??'';
$blocks=readiness_blocks();
if(!array_key_exists($blockKey,$blocks)){ layout('Анкета','<div class="card pad">Блок анкеты не найден. <a href="?p=speaker">Назад</a></div>'); exit; }
$block=$blocks[$blockKey];
$body='<div class="head"><h2>Анкета самооценки: «'.esc($block['label']).'»</h2><a class="btn ghost" href="?p=speaker">← в кабинет</a></div>';
$body.='<p class="muted">Честный ответ не снижает ваш статус в программе — он определяет, с какой аудиторией и в каком формате комфортнее начать. Результат видят фонд и оператор как рабочий ориентир, а не автоматический запрет.</p>';
$body.='<form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="submit_readiness"><input type="hidden" name="block_key" value="'.esc($blockKey).'">';
$shuffledQ=readiness_questions_shuffled($block);
foreach($shuffledQ as $pos=>$sq){
$i=$sq['orig_index'];
$body.='<div style="margin-bottom:18px'.($pos>0?';padding-top:16px;border-top:1px solid var(--line)':'').'"><p style="font-weight:600;margin:0 0 8px">'.($pos+1).'. '.esc($sq['text']).'</p>';
foreach($sq['options'] as $ok=>$opt){
$body.='<label class="chk" style="display:block;margin:4px 0"><input type="radio" name="answers['.$i.']" value="'.$ok.'" required> '.esc($opt['label']).'</label>';
}
$body.='</div>';
}
$fq=readiness_final_question_shuffled();
$body.='<div style="margin-bottom:8px;padding-top:16px;border-top:2px solid var(--amber)"><p style="font-weight:600;margin:0 0 8px">Последний вопрос. '.esc($fq['text']).'</p>';
foreach($fq['options'] as $ok=>$label) $body.='<label class="chk" style="display:block;margin:4px 0"><input type="radio" name="final_answer" value="'.$ok.'" required> '.esc($label).'</label>';
$body.='<p class="muted" style="margin-top:6px;font-size:12.5px">Этот вопрос не влияет на баллы по возрастному блоку — он только помогает куратору при необходимости мягко поддержать вас.</p></div>';
$body.='<button class="btn primary" style="margin-top:8px">Отправить анкету</button></form>';
layout('Анкета готовности',$body); exit;
}
// ========== АНКЕТА САМООЦЕНКИ ПСИХОЛОГИЧЕСКОЙ ГОТОВНОСТИ (по возрастной группе) ==========
if($p==='speaker_psych_readiness'){
require_role('speaker');
if(!$u['active']){ redirect('?p=speaker'); }
$ageGroup=$_GET['group']??'';
$blocks=psych_readiness_blocks();
if(!array_key_exists($ageGroup,$blocks)){ layout('Анкета','<div class="card pad">Возрастная группа не найдена. <a href="?p=speaker">Назад</a></div>'); exit; }
$block=$blocks[$ageGroup]; $intro=psych_readiness_intro();
$body='<div class="head"><h2>Анкета самооценки психологической готовности: «'.esc($block['label']).'»</h2><a class="btn ghost" href="?p=speaker">← в кабинет</a></div>';
$body.='<div class="card pad" style="margin-bottom:14px"><p style="margin-top:0">'.esc($intro['purpose']).'</p><p>'.esc($intro['howto']).'</p><p class="muted" style="font-size:12.5px;margin-bottom:0">'.esc($intro['why3']).'</p></div>';
$body.='<div class="hint-warn"><b>Особенность аудитории.</b> '.esc($block['audience']).'</div>';
$body.='<form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="submit_psych_readiness"><input type="hidden" name="age_group" value="'.esc($ageGroup).'">';
$lines=psych_readiness_lines();
$shuffledQ=psych_readiness_questions_shuffled($block);
$curLine=null; $qn=0;
foreach($shuffledQ as $sq){
$i=$sq['orig_index'];
if($sq['line']!==$curLine){ $curLine=$sq['line']; $body.='<h4 style="margin:'.($qn>0?'22px':'0').' 0 4px;color:var(--navy)">Линия '.$curLine.'. '.esc($lines[$curLine]).'</h4>'; }
$qn++;
$body.='<div style="margin-bottom:16px;padding-top:12px;border-top:1px solid var(--line)"><p style="font-weight:600;margin:0 0 8px">'.esc($sq['text']).'</p>';
foreach($sq['options'] as $ok=>$opt){
$body.='<label class="chk" style="display:block;margin:4px 0"><input type="radio" name="answers['.$i.']" value="'.$ok.'" required> '.esc($opt['label']).'</label>';
}
$body.='</div>';
}
$body.='<button class="btn primary" style="margin-top:8px">Отправить анкету</button></form>';
$body.='<p class="muted" style="font-size:12px;margin-top:14px">'.esc($intro['footer']).'</p>';
layout('Анкета психологической готовности',$body); exit;
}
// ========== СУДИМОСТЬ СПИКЕРА (обязательный экран для тех, кто ещё не отвечал) ==========
if($p==='speaker_criminal_check'){
require_role('speaker');
$body='<div class="head"><h2>Сведения о судимости</h2></div>';
$body.='<div class="card pad" style="max-width:560px"><p style="margin-top:0">Это обязательное поле для всех спикеров программы «Уроки мужества» — занятия проводятся с детьми и подростками. Ответ сохраняется один раз, но при необходимости вы сможете изменить его позже в профиле.</p>';
$body.='<form method="post" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="set_criminal_record">';
$body.='<label class="lbl">Есть ли у Вас судимость (снятая или непогашенная)? <span style="color:#d11">*</span></label><select name="criminal_record" id="crim_sel" onchange="crimSw()" required><option value="">— выберите —</option><option value="no">Нет</option><option value="yes">Да</option></select>';
$body.='<div id="crim_no_fields" style="display:none;margin-top:10px"><label class="lbl">Дата справки о несудимости <span style="color:#d11">*</span></label><input type="date" name="criminal_cert_date"><label class="lbl">Справка о несудимости (PDF/JPG/PNG) <span style="color:#d11">*</span></label><input type="file" name="criminal_cert" accept=".pdf,.jpg,.jpeg,.png"></div>';
$body.='<p class="muted" style="font-size:12.5px;margin-top:10px">При ответе «Да» ваша учётная запись не блокируется — вы сможете пользоваться другими функциями платформы, но не будете предложены заявителям в качестве спикера. Подробности будут показаны после сохранения и продублированы на вашу почту.</p>';
$body.='<button class="btn primary" style="margin-top:12px">Сохранить</button></form></div>';
$body.='<script>function crimSw(){var v=document.getElementById("crim_sel").value;var w=document.getElementById("crim_no_fields");var show=(v==="no");w.style.display=show?"block":"none";w.querySelectorAll("input").forEach(function(i){i.required=show;});}crimSw();</script>';
layout('Сведения о судимости',$body); exit;
}
// ========== ПРОВЕРКА ГОТОВНОСТИ К ЗАНЯТИЯМ: БАНК ВОПРОСОВ ПО ТЕМЕ (ОПЕРАТОР) ==========
if($p==='lesson_readiness_admin'){
require_role('operator');
$topics=lesson_readiness_all_topics();
$cur=trim($_GET['topic']??''); if($cur===''&&$topics) $cur=$topics[0];
$body='<div class="head"><h2>Проверка готовности к занятиям</h2><a class="btn ghost" href="?p=operator_tools">← в инструменты</a></div>';
$body.='<p class="muted">Банк вопросов по темам занятий — 4 вопроса на тему (тема, цели урока, материальная база, методика проведения). Пока по теме не заполнены все 4 — проверка для неё не запрашивается у спикеров.</p>';
if($topics){
$body.='<div class="guide-tabs" style="margin:14px 0">';
foreach($topics as $t){ $qs=lesson_readiness_questions_for_topic($t); $c=count($qs); $body.='<a class="guide-tab'.($t===$cur?' active':'').'" href="?p=lesson_readiness_admin&topic='.urlencode($t).'" style="text-decoration:none;display:inline-block">'.esc($t).' <span class="pill '.($c>=4?'green':'gray').'" style="margin-left:4px">'.$c.'/4</span></a>'; }
$body.='</div>';
}
$body.='<form method="get" class="card pad" style="max-width:480px;margin-bottom:14px"><input type="hidden" name="p" value="lesson_readiness_admin"><label class="lbl">Добавить тему (если её нет в списке)</label><input name="topic" placeholder="точное название темы, как в заявке"><button class="btn ghost sm" style="margin-top:8px">Открыть/создать</button></form>';
if($cur!==''){
$qs=lesson_readiness_questions_for_topic($cur);
$body.='<h3>Тема: «'.esc($cur).'»</h3>';
foreach(lesson_readiness_dimensions() as $dk=>$dl){
$q=$qs[$dk]??null;
$body.='<form method="post" class="card pad" style="max-width:640px;margin-bottom:14px">'.csrf_field().'<input type="hidden" name="action" value="save_lesson_readiness_question"><input type="hidden" name="topic" value="'.esc($cur).'"><input type="hidden" name="dimension" value="'.esc($dk).'">';
$body.='<h4 style="margin-top:0">'.esc($dl).'</h4>';
$body.='<label class="lbl">Текст вопроса <span style="color:#d11">*</span></label><textarea name="question" required>'.esc($q['question']??'').'</textarea>';
foreach(['a'=>'А','b'=>'Б','c'=>'В','d'=>'Г'] as $ok=>$ol){
$checked=($q&&$q['correct_option']===$ok)?'checked':'';
$body.='<label class="lbl" style="margin-top:10px">Вариант '.$ol.' <span style="color:#d11">*</span></label><div style="display:flex;gap:8px;align-items:center"><input type="radio" name="correct_option" value="'.$ok.'" '.$checked.' required style="width:auto;flex:none"><input name="option_'.$ok.'" required value="'.esc($q['option_'.$ok]??'').'" style="flex:1"></div>';
}
$body.='<p class="muted" style="margin-top:6px;font-size:12.5px">Отметьте радиокнопкой правильный вариант.</p>';
$body.='<button class="btn primary sm" style="margin-top:10px">Сохранить</button></form>';
}
}
layout('Проверка готовности к занятиям',$body); exit;
}
// ========== ПРОВЕРКА ГОТОВНОСТИ СПИКЕРА К ЗАНЯТИЮ (по конкретной заявке) ==========
if($p==='lesson_readiness_quiz'){
require_role('speaker');
$rid=(int)($_GET['request_id']??0); $r=$rid?get_request($rid):null;
if(!$r){ layout('Проверка готовности','<div class="card pad">Заявка не найдена. <a href="?p=speaker">Назад</a></div>'); exit; }
$row=get_rs_row($rid,$u['id']);
if(!$row||!$row['confirmed']){ layout('Проверка готовности','<div class="card pad">Вы не входите в утверждённый состав по этой заявке. <a href="?p=request&id='.$rid.'">Назад</a></div>'); exit; }
$topic=trim((string)($r['topic']??''));
if($topic===''||!lesson_readiness_ready($topic)){ layout('Проверка готовности','<div class="card pad">По этой теме проверка готовности пока не настроена. <a href="?p=request&id='.$rid.'">Назад</a></div>'); exit; }
if(speaker_topic_ready($u['id'],$topic)){ layout('Проверка готовности','<div class="card pad">Вы уже подтвердили готовность по теме «'.esc($topic).'» — повторное прохождение не требуется (действует 14 дней). <a href="?p=request&id='.$rid.'">← к заявке</a></div>'); exit; }
$body='<div class="head"><h2>Проверка готовности: «'.esc($topic).'»</h2><a class="btn ghost" href="?p=request&id='.$rid.'">← к заявке</a></div>';
$body.='<div class="hint-warn" style="margin-bottom:14px"><b>Перед прохождением:</b> скачайте и изучите технологическую карту и все материалы по теме «'.esc($topic).'» — <a href="?p=materials&topic='.urlencode($topic).'" target="_blank">открыть материалы по теме →</a></div>';
$body.='<p class="muted">Ответьте на 4 вопроса — по теме, целям урока, материальной базе и методике проведения занятия.</p>';
$body.='<form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="submit_lesson_readiness"><input type="hidden" name="request_id" value="'.$rid.'">';
foreach(lesson_readiness_questions_shuffled($topic) as $sq){
$body.='<div style="margin-bottom:18px;padding-top:12px;border-top:1px solid var(--line)"><p class="muted" style="margin:0 0 4px;text-transform:uppercase;font-size:11.5px;letter-spacing:.03em">'.esc($sq['label']).'</p><p style="font-weight:600;margin:0 0 8px">'.esc($sq['text']).'</p>';
foreach($sq['options'] as $ok=>$otext) $body.='<label class="chk" style="display:block;margin:4px 0"><input type="radio" name="answers['.$sq['dimension'].']" value="'.$ok.'" required> '.esc($otext).'</label>';
$body.='</div>';
}
$body.='<button class="btn primary" style="margin-top:8px">Отправить</button></form>';
layout('Проверка готовности',$body); exit;
}
// ========== РЕДАКТИРОВАНИЕ ЗАЯВКИ (страница) ==========
if($p==='request_edit'){
require_role('school');
$r=get_request((int)($_GET['id']??0));
if(!$r){ layout('Заявка','<div class="card pad">Заявка не найдена. <a href="?p=school">Назад</a></div>'); exit; }
if(!school_owns_request($u,$r)){ layout('Заявка','<div class="card pad">Нет доступа. <a href="?p=school">Назад</a></div>'); exit; }
if(!in_array($r['status'],['new','chosen','confirmed'])){ layout('Редактирование заявки','<div class="card pad"><h2>Редактирование недоступно</h2><p class="muted">Редактирование доступно только на этапах «Новая», «Спикеры выбраны» и «Состав подтверждён Фондом».</p><p><a href="?p=request&id='.$r['id'].'">← К заявке</a></p></div>'); exit; }
$curTopic=$r['topic']??''; $topics=material_topics(); $known=in_array($curTopic,$topics,true);
$tOpt='<option value="">— выберите тему —</option>';
if($curTopic!==''&&!$known) $tOpt.='<option value="'.esc($curTopic).'" selected>'.esc($curTopic).' (старая свободная тема)</option>';
foreach($topics as $t) $tOpt.='<option value="'.esc($t).'"'.($curTopic===$t?' selected':'').'>'.esc($t).'</option>';
$agOpt=''; foreach(level_ages($u['level']) as $g) $agOpt.='<option value="'.esc($g).'" '.($r['age_group']===$g?'selected':'').'>'.esc(ages()[$g]).'</option>';
$snOpt=''; for($i=1;$i<=5;$i++) $snOpt.='<option value="'.$i.'" '.((int)$r['speakers_needed']===$i?'selected':'').'>'.$i.'</option>';
$body='<div class="head"><h2>Редактирование заявки '.esc($r['code']).'</h2><a class="btn ghost" href="?p=request&id='.$r['id'].'">← К заявке</a></div>';
$body.='<form method="post" class="card pad" style="max-width:640px">'.csrf_field().'<input type="hidden" name="action" value="update_request"><input type="hidden" name="id" value="'.$r['id'].'">';
$body.='<p class="muted">Организация: <b>'.esc($r['org']).'</b> · Оператор: '.esc($r['operator']).'</p>';
if(reschedulable_status($r)){
$body.='<div class="hint-warn"><b>ⓘ</b> Изменение даты/времени будет проверено на конфликты с другими уроками уже подтверждённых спикеров.</div>';
$body.='<label class="lbl">Дата урока <span style="color:#d11">*</span></label><input type="date" name="udate" min="'.date('Y-m-d').'" required value="'.esc(substr((string)$r['udate'],0,10)).'">';
$body.='<label class="lbl">Время начала <span style="color:#d11">*</span></label><select name="utime" required><option value="">—</option>'.time_options($r['utime']??'').'</select>';
$body.='<label class="lbl">Время окончания <span style="color:#d11">*</span></label><select name="utime_end" required><option value="">—</option>'.time_options($r['utime_end']??'').'</select>';
} else {
$body.='<p class="muted">Дата и время: <b>'.esc($r['udate']).' '.esc($r['utime']).(!empty($r['utime_end'])?'–'.esc($r['utime_end']):'').'</b> <span class="pill amber">не изменяется</span></p>';
}
$body.='<label class="lbl">Возрастная группа</label><select name="age_group" required>'.$agOpt.'</select>';
$body.='<label class="lbl">Сколько спикеров нужно</label><select name="speakers_needed">'.$snOpt.'</select>';
$body.='<label class="lbl">Контактное лицо <span style="color:#d11">*</span></label><input name="contact_name" required value="'.esc($r['contact_name']).'">';
$body.='<label class="lbl">Телефон <span style="color:#d11">*</span></label><input name="phone" required value="'.esc($r['phone']).'">';
$body.='<label class="lbl">Число участников</label><input name="participants" value="'.esc($r['participants']??'').'">';
$body.='<label class="lbl">Тема занятия</label><select name="topic">'.$tOpt.'</select>';
if($r['status']==='new'){
$dsOpt='<option value="">— без предпочтения —</option>';
foreach(active_speakers() as $s){ $ags=array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$s['age_groups']))); $dsOpt.='<option value="'.$s['id'].'"'.((int)($r['desired_speaker_id']??0)===(int)$s['id']?' selected':'').'>'.esc($s['display_name']).' — '.esc($s['district']?:'район не указан').' ('.esc(implode(', ',$ags)).')</option>'; }
$body.='<label class="lbl">Желаемый спикер (необязательно)</label><select name="desired_speaker_id">'.$dsOpt.'</select>';
}
$body.='<label class="lbl">Особые условия</label><textarea name="notes">'.esc($r['notes']??'').'</textarea>';
$body.='<button class="btn primary" style="margin-top:12px">Сохранить изменения</button></form>';
layout('Редактирование заявки',$body); exit;
}
// ========== РЕДАКТИРОВАНИЕ ЗАЯВКИ ФОНДОМ (страница) ==========
if($p==='fund_request_edit'){
require_role('fund');
$r=get_request((int)($_GET['id']??0));
if(!$r){ layout('Заявка','<div class="card pad">Заявка не найдена. <a href="?p=fund">Назад</a></div>'); exit; }
if(!empty($r['claimed_fund_id']) && !in_array((int)$r['claimed_fund_id'],fund_scope_ids($u),true)){ layout('Заявка','<div class="card pad">Заявка закреплена за другим фондом. <a href="?p=request&id='.$r['id'].'">← К заявке</a></div>'); exit; }
if(!reschedulable_status($r)){ layout('Редактирование заявки','<div class="card pad"><h2>Редактирование недоступно</h2><p class="muted">Редактирование доступно на этапах «Спикеры выбраны» и «Состав подтверждён Фондом».</p><p><a href="?p=request&id='.$r['id'].'">← К заявке</a></p></div>'); exit; }
$curTopic=$r['topic']??''; $topics=material_topics(); $known=in_array($curTopic,$topics,true);
$tOpt='<option value="">— выберите тему —</option>';
if($curTopic!==''&&!$known) $tOpt.='<option value="'.esc($curTopic).'" selected>'.esc($curTopic).' (старая свободная тема)</option>';
foreach($topics as $t) $tOpt.='<option value="'.esc($t).'"'.($curTopic===$t?' selected':'').'>'.esc($t).'</option>';
$agOpt=''; foreach(level_ages($r['level']) as $g) $agOpt.='<option value="'.esc($g).'" '.($r['age_group']===$g?'selected':'').'>'.esc(ages()[$g]).'</option>';
$body='<div class="head"><h2>Редактирование заявки '.esc($r['code']).'</h2><a class="btn ghost" href="?p=request&id='.$r['id'].'">← К заявке</a></div>';
$body.='<div class="hint-warn"><b>ⓘ</b> Изменение даты/времени будет проверено на конфликты с другими уроками уже подтверждённых спикеров.</div>';
$body.='<form method="post" class="card pad" style="max-width:640px">'.csrf_field().'<input type="hidden" name="action" value="fund_edit_request"><input type="hidden" name="id" value="'.$r['id'].'">';
$body.='<p class="muted">Организация: <b>'.esc($r['org']).'</b> · Оператор: '.esc($r['operator']).'</p>';
$body.='<label class="lbl">Дата урока <span style="color:#d11">*</span></label><input type="date" name="udate" min="'.date('Y-m-d').'" required value="'.esc(substr((string)$r['udate'],0,10)).'">';
$body.='<label class="lbl">Время начала <span style="color:#d11">*</span></label><select name="utime" required><option value="">—</option>'.time_options($r['utime']??'').'</select>';
$body.='<label class="lbl">Время окончания <span style="color:#d11">*</span></label><select name="utime_end" required><option value="">—</option>'.time_options($r['utime_end']??'').'</select>';
$body.='<label class="lbl">Возрастная группа</label><select name="age_group" required>'.$agOpt.'</select>';
$body.='<label class="lbl">Контактное лицо <span style="color:#d11">*</span></label><input name="contact_name" required value="'.esc($r['contact_name']).'">';
$body.='<label class="lbl">Телефон <span style="color:#d11">*</span></label><input name="phone" required value="'.esc($r['phone']).'">';
$body.='<label class="lbl">Число участников</label><input name="participants" value="'.esc($r['participants']??'').'">';
$body.='<label class="lbl">Тема занятия</label><select name="topic">'.$tOpt.'</select>';
$body.='<label class="lbl">Особые условия</label><textarea name="notes">'.esc($r['notes']??'').'</textarea>';
$body.='<button class="btn primary" style="margin-top:12px">Сохранить изменения</button></form>';
layout('Редактирование заявки',$body); exit;
}
// ========== СТРАНИЦА ЗАЯВКИ ==========
if($p==='request'){
$r=get_request((int)($_GET['id']??0)); if(!$r){ layout('Заявка','<div class="card pad">Заявка не найдена. <a href="?p='.$u['role'].'">Назад</a></div>'); exit; }
if($u['role']==='school' && !school_owns_request($u,$r)) exit('Нет доступа.');
if($u['role']==='speaker' && !is_candidate($r['id'],$u['id'])) exit('Нет доступа.');
$rows=req_speaker_rows($r['id']); $confN=count_confirmed($r['id']); $need=(int)$r['speakers_needed'];
$appl=find_user_by_id($r['school_user_id']);
$cName=(($appl['contact_name']??'')!=='')?$appl['contact_name']:$r['contact_name'];
$cPos=$appl['position']??''; $cPhone=(($appl['phone']??'')!=='')?$appl['phone']:$r['phone'];
$cLine=($cName!==''?esc($cName):'—').($cPos!==''?' <span class="muted">('.esc($cPos).')</span>':'').($cPhone!==''?' · <a href="tel:'.esc(preg_replace('/[^0-9+]/','',$cPhone)).'">'.esc($cPhone).'</a>':'');
$info='<dl class="kv"><dt>Код</dt><dd class="id">'.esc($r['code']).'</dd><dt>Организация</dt><dd>'.esc($r['org']).'</dd><dt>Оператор</dt><dd>'.esc($r['operator']).'</dd><dt>Аудитория</dt><dd>'.esc(ages()[$r['age_group']]??'').'</dd><dt>Дата и время</dt><dd>'.dt($r).'</dd><dt>Число участников</dt><dd>'.esc($r['participants']?:'—').'</dd><dt>Нужно спикеров</dt><dd>'.$need.'</dd>';
if(!empty($r['desired_speaker_id'])){ $ds=find_user_by_id($r['desired_speaker_id']); if($ds) $info.='<dt>Желаемый спикер</dt><dd>'.esc($ds['display_name']).($ds?' <span class="muted">(пожелание заявителя, не гарантия)</span>':'').'</dd>'; }
$info.='<dt>Контактное лицо</dt><dd>'.$cLine.'</dd>';
if(!empty($r['topic'])) $info.='<dt>Тема</dt><dd>'.esc($r['topic']).'</dd>';
if(!empty($r['notes'])) $info.='<dt>Особые условия</dt><dd style="background:#fffbea;padding:6px 10px;border-radius:6px;border-left:3px solid #C77700">'.nl2br(esc($r['notes'])).'</dd>';
$info.='</dl>';
[$turnWho,$turnAct]=whose_turn($r);
$turnMine=($u['role']==='school'&&$turnWho==='Заявитель')||($u['role']==='fund'&&$turnWho==='Фонд')||($u['role']==='operator'&&$turnWho==='Оператор');
$turnHtml=($turnAct && !in_array($r['status'],['closed','cancelled'])) ? '<div class="turn-banner '.($turnMine?'turn-you':'turn-other').'"><b>Сейчас ход за: '.esc($turnWho).'.</b> '.esc($turnAct).'</div>' : '';
$body='<div class="head"><h2>Заявка '.esc($r['code']).' '.pill($r['status']).'</h2><div><a class="btn ghost" href="?p=audit&target_type=requests&target_id='.$r['id'].'">📋 История</a> <a class="btn ghost" href="?p='.$u['role'].'">← в кабинет</a></div></div><div class="card pad">'.$turnHtml.$info.($r['status']==='cancelled' ? '<div class="block"><div style="background:#fdecea;color:#b23b2c;border:1px solid #f5c6c0;border-radius:8px;padding:12px"><b>Урок не состоялся.</b> Причина: '.esc($r['cancel_reason']?:'не указана').($r['cancelled_at']?' · '.esc($r['cancelled_at']):'').'</div></div>' : '<div class="block"><h4>Этапы</h4>'.stepper($r['status']).'</div>');
$listHtml='';
foreach($rows as $row){ $badge=$row['confirmed']?'<span class="pill green">в составе</span>':'<span class="pill gray">кандидат</span>'; $org=speaker_org_name($row['speaker_user_id']); $listHtml.='<div class="fbsum"><b>'.esc($row['display_name']).'</b>'.($org!==''?' <span class="muted">· '.esc($org).'</span>':'').' '.$badge; if(trim((string)$row['vet_comment'])!=='') $listHtml.='<div class="cmt">отзыв спикера: '.esc($row['vet_comment']).'</div>'; $listHtml.='</div>'; }
// ========== ГОТОВНОСТЬ СПИКЕРА К ЗАНЯТИЮ (проверка по теме) ==========
$reqTopic=trim((string)($r['topic']??''));
if($reqTopic!=='' && lesson_readiness_ready($reqTopic) && in_array($r['status'],['confirmed','brief','prep','done','closed'],true)){
$body.='<div class="block"><h4>📘 Готовность спикера к занятию</h4><p class="muted" style="margin-top:-4px">Перед занятием — <a href="?p=materials&topic='.urlencode($reqTopic).'" target="_blank">изучить технологическую карту и материалы по теме «'.esc($reqTopic).'» →</a></p><p class="muted" style="margin-top:-4px">Проверка готовности: 4 вопроса — по теме, целям урока, материальной базе и методике проведения. Пройденная проверка действительна 14 дней.</p>';
foreach(req_confirmed_rows($r['id']) as $row){
$ready=speaker_topic_ready($row['speaker_user_id'],$reqTopic);
$last=speaker_topic_last_pass($row['speaker_user_id'],$reqTopic);
$statusPill=$ready?'<span class="pill green">✓ подтверждена ('.esc(substr((string)$last['created_at'],0,10)).')</span>':'<span class="pill gray">не подтверждена</span>';
$body.='<div class="row2" style="margin-bottom:6px"><span>'.esc($row['display_name']).'</span>'.$statusPill.'</div>';
if($u['role']==='speaker' && (int)$u['id']===(int)$row['speaker_user_id'] && !$ready && in_array($r['status'],['confirmed','brief','prep'],true)) $body.='<div style="margin-top:6px"><a class="btn primary sm" href="?p=lesson_readiness_quiz&request_id='.$r['id'].'">Пройти проверку готовности</a></div>';
}
$body.='</div>';
}
if($u['role']==='school'){
$body.='<div class="block" style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn ghost sm" href="?p=school&tab=new&copy='.$r['id'].'">📋 Повторить заявку</a>';
if(in_array($r['status'],['new','chosen','confirmed'])) $body.='<a class="btn ghost sm" href="?p=request_edit&id='.$r['id'].'">✏️ Редактировать</a>';
if(request_deletable($r)) $body.='<form method="post" style="display:inline" onsubmit="return confirm(\'Удалить заявку? Действие необратимо.\')">'.csrf_field().'<input type="hidden" name="action" value="delete_request"><input type="hidden" name="id" value="'.$r['id'].'"><button type="submit" class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">🗑️ Удалить</button></form>';
$body.='</div>';
if(in_array($r['status'],['new','chosen'])){
$body.='<div class="block"><h4>Спикеры</h4>';
if($rows){ foreach($rows as $row){ $body.='<div class="row2" style="margin-bottom:6px"><span>'.esc($row['display_name']).'</span>'.($row['confirmed']?'<span class="pill green">в составе</span>':'<span class="pill gray">кандидат, ожидает решения Фонда</span>').'</div>'; } $body.='<p class="muted" style="margin-top:8px">Нужно спикеров: '.$need.', подтверждено: '.$confN.'.</p>'; }
else $body.='<p class="muted">Заявка передана всем фондам — ожидайте, пока один из них назначит спикера.</p>';
$body.='</div>';
} else {
$cl=''; foreach(req_confirmed_rows($r['id']) as $row){ $sp=find_user_by_id($row['speaker_user_id']); $cl.=$sp?speaker_bio_card($sp):('<div class="fbsum"><b>'.esc($row['display_name']).'</b></div>'); }
$body.='<div class="block"><h4>Состав ('.$confN.' из '.$need.')</h4><p class="muted" style="margin-top:-4px">Так вы можете представить спикера школьникам.</p>'.($cl?:'<p class="muted">—</p>').'</div>';
}
if(in_array($r['status'],['confirmed','brief','prep'])){
$cf=$r['prep_confirmed_at'];
$body.='<div class="block"><h4>Готовность к проведению</h4><p class="muted">Отметьте все пункты — готовность зафиксируется автоматически.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="confirm_prep"><input type="hidden" name="id" value="'.$r['id'].'">'.'<label class="chk"><input type="checkbox" name="venue" onchange="this.form.submit()" '.($r['prep_venue']?'checked':'').'> площадка готова</label> <label class="chk"><input type="checkbox" name="escort" onchange="this.form.submit()" '.($r['prep_escort']?'checked':'').'> сопровождение назначено</label></form>'.($cf?'<p style="margin-top:10px"><span class="pill green">✓ Готовность подтверждена</span> <span class="muted">'.esc($cf).'</span></p>':'<p class="muted" style="margin-top:8px">Отмечено не всё — готовность ещё не подтверждена.</p>').'</div>';
}
if($r['status']==='prep'){
$body.='<div class="block"><h4>После урока</h4><p class="muted">Отметьте, как прошёл урок. Если он не состоялся — выберите причину.</p>'
.'<form method="post" class="inline"><input type="hidden" name="action" value="mark_done">'.csrf_field().'<input type="hidden" name="id" value="'.$r['id'].'"><button class="btn primary sm">Урок проведён</button></form>'
.'<form method="post" style="margin-top:12px;border-top:1px solid #eee;padding-top:12px">'.csrf_field().'<input type="hidden" name="action" value="not_held"><input type="hidden" name="id" value="'.$r['id'].'"><label class="lbl">Урок не состоялся — причина</label>'.render_reason_select('reason',cancel_reasons()).'<label class="lbl" style="margin-top:8px">Комментарий (необязательно)</label><input name="reason_comment"><br><button class="btn ghost sm" style="margin-top:10px;color:#b23b2c;border-color:#f5c6c0">Урок не состоялся</button></form></div>';
}
if(st_index($r['status'])>=st_index('done')){
$hasFeedback=trim((string)$r['fb_app_comment'])!=='' && trim((string)($r['fb_post_url']??''))!=='';
$body.='<div class="block"><h4>Ваш отзыв</h4><p class="muted">Ссылка на пост о проведённом занятии обязательна — без неё заявка не закрывается.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="feedback"><input type="hidden" name="id" value="'.$r['id'].'"><label class="lbl">Комментарий</label><textarea name="comment" required>'.esc($r['fb_app_comment']).'</textarea><label class="lbl">Ссылка на пост о проведённом занятии <span style="color:#d11">*</span></label><input type="url" name="post_url" required placeholder="https://..." value="'.esc($r['fb_post_url']??'').'"><button class="btn primary sm" style="margin-top:10px">'.($hasFeedback?'Обновить отзыв':'Отправить отзыв').'</button></form></div>';
}
}
elseif($u['role']==='fund'){
$scope=fund_scope_ids($u);
$claimedByMe=!empty($r['claimed_fund_id']) && in_array((int)$r['claimed_fund_id'],$scope,true);
$claimedByOther=!empty($r['claimed_fund_id']) && !$claimedByMe;
if($claimedByOther){
$body.='<div class="hint-warn"><b>Заявка в обработке другого фонда:</b> '.esc(fund_name_by_id($r['claimed_fund_id'])?:'—').'. Вы можете наблюдать за ней, но назначать спикеров может только этот фонд.</div>';
} elseif($claimedByMe){
$body.='<div class="block" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><span class="pill green">В обработке вашего фонда</span>';
if($r['status']==='new' && !$rows) $body.='<form method="post" onsubmit="return confirm(\'Вернуть заявку в общий список? Другие фонды смогут её взять.\')">'.csrf_field().'<input type="hidden" name="action" value="release_request"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn ghost sm">↩️ Вернуть в обработку</button></form>';
$body.='</div>';
if(fund_returnable_status($r)) $body.='<div class="block" style="border-top:1px solid #eee;padding-top:12px"><form method="post" onsubmit="return confirm(\'Вернуть заявку в общую обработку? Этап сбросится на «Новая», назначенные спикеры будут сняты, и любой фонд сможет взять заявку заново. Продолжить?\')">'.csrf_field().'<input type="hidden" name="action" value="fund_return_to_processing"><input type="hidden" name="id" value="'.$r['id'].'"><label class="lbl">Вернуть в обработку — причина (необязательно)</label><input name="reason" placeholder="например: не удалось подобрать спикера"><button class="btn ghost sm" style="margin-top:8px;color:#b23b2c;border-color:#f5c6c0">↩️ Вернуть в обработку (сброс на этап «Новая»)</button></form></div>';
} elseif($r['status']==='new'){
$body.='<div class="block"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="claim_request"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn primary sm">📥 Взять в обработку</button></form><p class="muted" style="margin-top:6px">Заявка закрепится за вашим фондом, и вы сможете назначить спикера.</p></div>';
}
$mine=array_values(array_filter($rows,function($row) use($scope){ $sp=find_user_by_id($row['speaker_user_id']); return $sp && in_array((int)$sp['fund_user_id'],$scope,true); }));
$body.='<div class="block"><h4>Согласование спикеров ('.$confN.' из '.$need.' подтверждено)</h4>';
if(!$rows) $body.='<p class="muted">Заявитель ещё не выбрал спикеров.</p>';
elseif(!$mine) $body.='<p class="muted">Среди кандидатов нет ваших спикеров.</p>';
foreach($mine as $row){ $sp=find_user_by_id($row['speaker_user_id']); $act=candidate_action($u,$row);
$body.='<div class="row2" style="margin-bottom:6px"><span><b>'.esc($row['display_name']).'</b> <span class="muted">('.esc($sp['district']?:'—').', тел.: '.esc($sp['phone']?:'—').')</span> ';
if($row['confirmed']) $body.='<span class="pill green">в составе</span>'; elseif($row['recommended']) $body.='<span class="pill blue">рекомендован</span>';
$body.='</span>';
if($r['status']==='chosen'){
if($act==='recommend') $body.='<form method="post">'.csrf_field().'<input type="hidden" name="action" value="fund_recommend"><input type="hidden" name="id" value="'.$r['id'].'"><input type="hidden" name="sid" value="'.$row['speaker_user_id'].'"><button class="btn primary sm">Рекомендовать</button></form>';
elseif($act==='confirm') $body.='<form method="post">'.csrf_field().'<input type="hidden" name="action" value="fund_confirm_speaker"><input type="hidden" name="id" value="'.$r['id'].'"><input type="hidden" name="sid" value="'.$row['speaker_user_id'].'"><button class="btn primary sm">Подтвердить окончательно</button></form>';
elseif($act==='wait_recommend') $body.='<span class="muted">ожидает рекомендации подразделения</span>';
elseif($act==='wait_confirm') $body.='<span class="muted">ожидает подтверждения головного фонда</span>';
}
if($r['status']==='chosen' && empty($row['confirmed'])) $body.='<form method="post" style="display:inline;margin-left:6px">'.csrf_field().'<input type="hidden" name="action" value="fund_reject_candidate"><input type="hidden" name="id" value="'.$r['id'].'"><input type="hidden" name="sid" value="'.$row['speaker_user_id'].'">'.render_reason_select('reason',reject_reasons(),false).' <button class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0" onclick="return confirm(\'Отклонить этого спикера?\')">Отклонить</button></form>';
$body.='</div>'; }
// ========== КНОПКИ ДЛЯ ФОНДА НА ЭТАПАХ CHOSEN / CONFIRMED ==========
if(in_array($r['status'],['chosen','confirmed'])){
$body.='<div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">';
$body.='<a class="btn ghost sm" href="?p=fund_request_edit&id='.$r['id'].'">✏️ Редактировать заявку</a>';
if($r['status']==='chosen' && $confN>=1 && $confN<$need){
$body.='<form method="post" style="display:inline" onsubmit="return confirm(\'Закрыть состав с '.$confN.' спикером(ами) из '.$need.'? Заявка пойдёт дальше.\')">'.csrf_field().'<input type="hidden" name="action" value="fund_finalize"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn primary sm">Состав готов — закрыть и передать дальше</button></form>';
}
$body.='</div>';
if($r['status']==='chosen') $body.='<p class="muted" style="margin-top:8px">Как только нужное число спикеров подтверждено, состав закрывается автоматически. Если спикеров не хватает — можно закрыть состав вручную кнопкой выше.</p>';
}
if(!$claimedByOther && in_array($r['status'],['new','chosen'])){ $fassign=replacement_speakers($u,$r,0);
$body.='<div style="margin-top:10px;border-top:1px solid #eee;padding-top:10px"><h4>Назначить спикера</h4>';
if(!empty($r['desired_speaker_id'])){ $ds=find_user_by_id($r['desired_speaker_id']); if($ds) $body.='<p class="muted">Заявитель пожелал видеть: <b>'.esc($ds['display_name']).'</b>'.(speaker_org_name($ds['id'])!==''?' ('.esc(speaker_org_name($ds['id'])).')':'').'.</p>'; }
if(!$fassign) $body.='<p class="muted">Нет свободных подходящих спикеров вашей организации на эту дату.</p>';
else { $fo='<option value="">— выберите спикера —</option>'; foreach($fassign as $s){ $star=((int)($r['desired_speaker_id']??0)===(int)$s['id'])?'⭐ ':''; $fo.='<option value="'.$s['id'].'">'.$star.esc($s['display_name']).' ('.esc($s['district']?:'—').')</option>'; }
$body.='<form method="post">'.csrf_field().'<input type="hidden" name="action" value="fund_assign_speaker"><input type="hidden" name="id" value="'.$r['id'].'"><select name="speaker_user_id" required>'.$fo.'</select> <button class="btn primary sm">Назначить спикера</button></form><p class="muted" style="margin-top:6px">Назначенного спикера затем нужно рекомендовать/подтвердить в списке выше.</p>'; }
$body.='</div>'; }
if($r['fund_confirmed_at'] && !in_array($r['status'],['done','closed','cancelled'])) $body.='<form method="post" style="margin-top:8px">'.csrf_field().'<input type="hidden" name="action" value="fund_briefing"><input type="hidden" name="id" value="'.$r['id'].'"><label class="lbl">Инструктаж проведён (не позже даты урока '.esc($r['udate']).')</label><input type="date" name="bdate" max="'.esc(substr((string)$r['udate'],0,10)).'" value="'.esc($r['briefing_date']).'"> <button class="btn primary sm">'.($r['briefing_done']?'Обновить дату инструктажа':'Отметить инструктаж проведённым').'</button>'.($r['briefing_done']?' <span class="pill green">✓ проведён '.esc($r['briefing_date']).'</span>':'').'</form><p class="muted">Инструктаж не задерживает заявку — заявитель может подтверждать готовность параллельно.</p>';
elseif($r['briefing_done']) $body.='<p class="muted">Инструктаж проведён '.esc($r['briefing_date']).'.</p>';
if(replaceable_status($r)){ $confMine=array_values(array_filter($mine,fn($row)=>$row['confirmed']));
if($confMine){ $body.='<div style="margin-top:12px;border-top:1px solid #eee;padding-top:12px"><h4>Замена спикера</h4>';
$body.=$r['needs_replacement']?'<p><span class="pill" style="background:#fdecea;color:#b23b2c">Спикер сообщил, что не сможет провести — назначьте замену</span></p>':'<p class="muted">Если утверждённый спикер не может провести урок, назначьте другого.</p>';
foreach($confMine as $row){ $free=replacement_speakers($u,$r,$row['speaker_user_id']);
$body.='<form method="post" style="margin-bottom:10px">'.csrf_field().'<input type="hidden" name="action" value="fund_replace"><input type="hidden" name="id" value="'.$r['id'].'"><input type="hidden" name="old_sid" value="'.$row['speaker_user_id'].'"><div class="muted" style="margin-bottom:4px">Заменить: <b>'.esc($row['display_name']).'</b></div>';
if(!$free){ $body.='<p class="muted">Нет свободных подходящих спикеров для замены.</p></form>'; continue; }
$opt='<option value="">— выберите спикера —</option>'; foreach($free as $s){ $opt.='<option value="'.$s['id'].'">'.esc($s['display_name']).' ('.esc($s['district']?:'—').')</option>'; }
$body.='<select name="new_sid" required>'.$opt.'</select> '.render_reason_select('reason',replace_reasons()).' <button class="btn primary sm">Назначить замену</button></form>'; }
$body.='</div>'; } }
$body.='</div>';
}
elseif($u['role']==='operator'){
$yn=fn($v)=>$v?'✓ да':'— нет';
$body.='<div class="block"><h4>Состав ('.$confN.' из '.$need.')</h4>'.($listHtml?:'<p class="muted">спикеры не выбраны</p>').'<p class="muted">Подтверждение Фондом: <b>'.esc($r['fund_confirmed_at']?:'ожидается').'</b> · Инструктаж: <b>'.($r['briefing_done']?esc($r['briefing_date']?:'проведён'):'нет').'</b></p></div>';
$body.='<div class="block"><h4>Готовность (подтверждает заявитель)</h4><p class="muted">'.'Площадка: <b>'.$yn($r['prep_venue']).'</b> · Сопровождение: <b>'.$yn($r['prep_escort']).'</b><br>'.($r['prep_confirmed_at']?'Подтверждено: <b>'.esc($r['prep_confirmed_at']).'</b>':'ещё не подтверждено').'</p></div>';
$postUrl=trim((string)($r['fb_post_url']??''));
$body.='<div class="block"><h4>Обратная связь</h4>'.fbline('Заявитель',$r['fb_app_comment']).'<p class="muted" style="margin-top:-6px">Пост о занятии: '.($postUrl!==''?'<a href="'.esc($postUrl).'" target="_blank" rel="noopener">'.esc($postUrl).'</a>':'<span style="color:#b23b2c">не указан — заявка не закроется без ссылки</span>').'</p>'; foreach(req_confirmed_rows($r['id']) as $row) $body.=fbline('Спикер: '.$row['display_name'],$row['vet_comment']); $body.='</div>';
$body.='<div class="block">';
if($r['status']==='prep') $body.='<p class="muted">Ожидается отметка заявителя о проведении урока.</p>';
if($r['status']==='done') $body.='<form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="close"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn primary sm">Закрыть заявку</button></form>';
if($r['status']==='cancelled' && empty($r['archived'])) $body.='<form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="close_cancelled"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn primary sm">Закрыть заявку</button></form><p class="muted" style="margin-top:6px">Урок не состоялся — заявку можно сразу закрыть и перенести в архив, не дожидаясь автоматической архивации.</p>';
if(st_index($r['status'])>0) $body.='<form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="step_back"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn ghost sm">Шаг назад</button></form>';
$body.='</div>';
}
elseif($u['role']==='speaker'){
$row=get_rs_row($r['id'],$u['id']);
if($row['confirmed']) $part='<span class="pill green">вы в составе</span>';
elseif($r['status']==='chosen') $part='<span class="pill gray">вы кандидат — ожидает решения Фонда</span>';
else $part='<span class="muted">по этой заявке в состав вы не включены</span>';
$body.='<div class="block"><h4>Ваше участие</h4><p>'.$part.'</p></div>';
if(!empty($row['confirmed'])){
$staff=school_staff_contacts($r['school_user_id']);
if($staff){
$body.='<div class="block"><h4>Контакты организации-заявителя</h4><p class="muted">Если не получается связаться с указанным контактным лицом — вот другие сотрудники организации.</p><dl class="kv">';
foreach($staff as $stf){
$nm=$stf['contact_name']?:$stf['display_name'];
$phoneLine=$stf['phone']?' · <a href="tel:'.esc(preg_replace('/[^0-9+]/','',$stf['phone'])).'">'.esc($stf['phone']).'</a>':'';
$emailLine=$stf['email']?' · '.esc($stf['email']):'';
$body.='<dt>'.esc($nm?:'—').'</dt><dd>'.esc($stf['position']?:'—').$phoneLine.$emailLine.'</dd>';
}
$body.='</dl></div>';
}
}
if(!empty($row['confirmed']) && replaceable_status($r)){
$body.=$r['needs_replacement']
?'<div class="block"><p class="muted">Вы сообщили, что не сможете провести урок. Фонд подбирает замену.</p></div>'
:'<div class="block"><h4>Не сможете провести?</h4><p class="muted">Если вы не можете провести этот урок, сообщите — Фонд назначит другого спикера.</p><form method="post"><input type="hidden" name="action" value="speaker_decline">'.csrf_field().'<input type="hidden" name="id" value="'.$r['id'].'"><button class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">Не смогу провести</button></form></div>';
}
if($row['confirmed'] && st_index($r['status'])>=st_index('done')){
$body.='<div class="block"><h4>Ваш отзыв</h4><form method="post">'.csrf_field().'<input type="hidden" name="action" value="feedback"><input type="hidden" name="id" value="'.$r['id'].'"><label class="lbl">Комментарий</label><textarea name="comment" required>'.esc($row['vet_comment']).'</textarea><button class="btn primary sm" style="margin-top:10px">'.(trim((string)$row['vet_comment'])!==''?'Обновить отзыв':'Отправить отзыв').'</button></form></div>';
} elseif($row['confirmed']) { $body.='<div class="block muted">Отзыв можно оставить после проведения урока.</div>'; }
}
$body.='</div>'; layout('Заявка '.$r['code'],$body); exit;
}
layout('Уроки мужества 72','<div class="card pad">Раздел не найден. <a href="?p='.($u['role']??'login').'">На главную</a></div>');