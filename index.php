<?php
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); session_start();
require __DIR__.'/lib.php';
init_schema();
$p=$_GET['p']??''; $method=$_SERVER['REQUEST_METHOD'];
function role_home($u){ return '?p='.$u['role']; }
// ========== ОБРАБОТЧИКИ ДЕЙСТВИЙ ==========
if($method==='POST'){
csrf_check(); $a=$_POST['action']??'';
if($a==='login'){ [$ok,$res]=login_user(trim($_POST['login']??''),$_POST['pass']??''); if($ok) redirect(role_home($res)); flash_set($res); redirect('?p=login'); }
if($a==='register'){
$role=$_POST['role']??''; $login=trim($_POST['login']??''); $pass=$_POST['pass']??''; $email=trim($_POST['email']??'');
if(strlen($login)<3||strlen($pass)<5){ flash_set('Логин от 3 и пароль от 5 символов.'); redirect('?p=register'); }
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ flash_set('Укажите корректный email.'); redirect('?p=register'); }
if(empty($_POST['consent'])){ flash_set('Необходимо согласие на обработку персональных данных.'); redirect('?p=register'); }
$dsel=$_POST['district']??''; $district=($dsel==='__other__')?ensure_district(trim($_POST['district_other']??'')):ensure_district($dsel);
if($district===''){ flash_set('Укажите район Тюменской области.'); redirect('?p=register'); }
$c_name=trim($_POST['contact_name']??''); $c_pos=trim($_POST['position']??''); $c_phone=trim($_POST['contact_phone']??'');
if($role==='school'){
$c_addr=trim($_POST['address']??''); $c_build=trim($_POST['building']??'');
if(empty($_POST['org_name'])||empty($_POST['level'])){ flash_set('Заполните наименование организации и уровень.'); redirect('?p=register'); }
if($c_addr===''||$c_build===''){ flash_set('Укажите адрес местонахождения и номер корпуса.'); redirect('?p=register'); }
[$ok,$res]=create_user('school',$login,$pass,['org_name'=>trim($_POST['org_name']),'level'=>$_POST['level'],'display_name'=>trim($_POST['org_name']),'email'=>$email,'district'=>$district,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone,'address'=>$c_addr,'building'=>$c_build]);
}
elseif($role==='speaker'){ if(empty($_POST['display_name'])){ flash_set('Укажите ФИО.'); redirect('?p=register'); } $phone=trim($_POST['phone']??''); if($phone===''){ flash_set('Укажите телефон для связи.'); redirect('?p=register'); } $fid=(int)($_POST['fund_user_id']??0); if(!$fid || !find_user_by_id($fid) || find_user_by_id($fid)['role']!=='fund'){ flash_set('Выберите фонд.'); redirect('?p=register'); } [$ok,$res]=create_user('speaker',$login,$pass,['display_name'=>trim($_POST['display_name']),'groups'=>implode(',',array_keys($_POST['groups']??[])),'email'=>$email,'district'=>$district,'phone'=>$phone,'fund_user_id'=>$fid]); }
elseif($role==='fund'){ $ftype=$_POST['fund_type']??'head'; if($ftype==='sub'){ $pfid=(int)($_POST['parent_fund_id']??0); $pf=$pfid?find_user_by_id($pfid):null; if(!$pf || $pf['role']!=='fund'){ flash_set('Подразделение должно указать головной фонд.'); redirect('?p=register'); } [$ok,$res]=create_user('fund',$login,$pass,['display_name'=>trim($_POST['fund_display_name']??'Подразделение'),'email'=>$email,'district'=>$district,'parent_fund_id'=>$pfid,'active'=>0,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone]); } else { if(($_POST['fund_code']??'')!==cfg()['fund_signup_code']){ flash_set('Неверный код регистрации Фонда.'); redirect('?p=register'); } [$ok,$res]=create_user('fund',$login,$pass,['display_name'=>trim($_POST['fund_display_name']??'Фонд'),'email'=>$email,'district'=>$district,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone]); } }
elseif($role==='operator'){ if(($_POST['op_code']??'')!==cfg()['operator_signup_code']){ flash_set('Неверный код регистрации оператора.'); redirect('?p=register'); } $og=$_POST['op_group']??''; if(!in_array($og,['school','region'])){ flash_set('Выберите департамент.'); redirect('?p=register'); } [$ok,$res]=create_user('operator',$login,$pass,['display_name'=>trim($_POST['op_display_name']??'Оператор'),'email'=>$email,'op_group'=>$og,'district'=>$district,'contact_name'=>$c_name,'position'=>$c_pos,'phone'=>$c_phone]); }
else { flash_set('Выберите роль.'); redirect('?p=register'); }
if(!$ok){ flash_set($res); redirect('?p=register'); }
flash_set($role==='speaker'?'Регистрация принята. Доступ откроется после подтверждения вашим фондом.':($role==='school'?'Регистрация принята. Доступ откроется после подтверждения профильным департаментом.':($role==='fund'&&($_POST['fund_type']??'head')==='sub'?'Регистрация подразделения принята. Доступ откроется после подтверждения головным фондом.':'Регистрация успешна. Войдите.'))); redirect('?p=login');
}
if($a==='logout'){ session_destroy(); redirect('?p=login'); }
if($a==='update_profile'){
require_login(); $u=current_user();
$dn=trim($_POST['display_name']??''); $em=trim($_POST['email']??''); $np=$_POST['newpass']??'';
if($dn===''){ flash_set('Укажите наименование / ФИО.'); redirect('?p=profile'); }
if(!filter_var($em,FILTER_VALIDATE_EMAIL)){ flash_set('Укажите корректный email.'); redirect('?p=profile'); }
$set=['display_name=?','email=?']; $par=[$dn,$em];
$dsel=$_POST['district']??''; $dist=($dsel==='__other__')?ensure_district(trim($_POST['district_other']??'')):ensure_district($dsel);
if($dist!==''){ $set[]='district=?'; $par[]=$dist; }
if($u['role']==='speaker' && isset($_POST['phone'])){ $set[]='phone=?'; $par[]=trim($_POST['phone']); }
if($u['role']==='speaker' && isset($_POST['fund_user_id'])){ $fid=(int)$_POST['fund_user_id']; if($fid>0){ $f=find_user_by_id($fid); if(!$f || $f['role']!=='fund'){ flash_set('Фонд не найден.'); redirect('?p=profile'); } $set[]='fund_user_id=?'; $par[]=$fid; } }
if(in_array($u['role'],['school','fund','operator'],true)){ $set[]='contact_name=?'; $par[]=trim($_POST['contact_name']??''); $set[]='position=?'; $par[]=trim($_POST['position']??''); $set[]='phone=?'; $par[]=trim($_POST['phone']??''); }
if($u['role']==='school'){
$set[]='org_name=?'; $par[]=$dn;
$addr=trim($_POST['address']??''); $build=trim($_POST['building']??'');
if($addr===''||$build===''){ flash_set('Укажите адрес местонахождения и номер корпуса.'); redirect('?p=profile'); }
$set[]='address=?'; $par[]=$addr; $set[]='building=?'; $par[]=$build;
}
if(strlen($np)>=5){ $set[]='pass_hash=?'; $par[]=password_hash($np,PASSWORD_DEFAULT); }
elseif($np!==''){ flash_set('Пароль от 5 символов.'); redirect('?p=profile'); }
$par[]=$u['id'];
db()->prepare("UPDATE users SET ".implode(',',$set)." WHERE id=?")->execute($par);
log_action($u['id'],$u['role'],'update_profile','users',$u['id'],'Обновлены данные профиля');
flash_set('Данные сохранены.'.(strlen($np)>=5?' Пароль изменён.':'')); redirect('?p=profile');
}
if($a==='request_reset'){
$email=trim($_POST['email']??'');
[$ok,$data]=create_reset_token($email);
if($ok){
$u=$data['user']; $token=$data['token'];
$site=cfg()['site_title'];
$resetUrl="https://урокмужества72.рф?p=reset_password&token=$token";
$body="Здравствуйте, {$u['display_name']}!\r\n\r\nВы запросили сброс пароля в системе \"$site\".\r\n\r\nПерейдите по ссылке (действует 1 час):\r\n$resetUrl\r\n\r\nЕсли вы не запрашивали сброс — проигнорируйте это письмо.\r\n";
send_smtp_email($u['email'],$u['display_name'],"[$site] Сброс пароля",$body);
flash_set('Инструкция по сбросу пароля отправлена на email.');
} else { flash_set($data); }
redirect('?p=login');
}
if($a==='reset_password'){
$token=$_POST['token']??''; $newpass=$_POST['newpass']??''; $newpass2=$_POST['newpass2']??'';
if(strlen($newpass)<5){ flash_set('Пароль от 5 символов.'); redirect('?p=reset_password&token='.urlencode($token)); }
if($newpass!==$newpass2){ flash_set('Пароли не совпадают.'); redirect('?p=reset_password&token='.urlencode($token)); }
[$ok,$msg]=reset_password($token,$newpass);
flash_set($msg); redirect('?p=login');
}
if($a==='admin_reset_password'){
require_login(); $u=current_user();
$target_id=(int)($_POST['target_id']??0); $newpass=$_POST['newpass']??'';
if(strlen($newpass)<5){ flash_set('Пароль от 5 символов.'); redirect('?p=users'); }
[$ok,$msg]=admin_reset_password($u,$target_id,$newpass);
flash_set($msg); redirect('?p=users');
}
if($a==='delete_user'){
require_login(); $u=current_user();
[$ok,$msg]=delete_user($u,(int)($_POST['target_id']??0));
flash_set($msg); redirect('?p=users');
}
if($a==='save_district_neighbors'){ require_role('operator'); update_district_neighbors(trim($_POST['name']??''), explode(',',$_POST['neighbors']??'')); flash_set('Соседние районы сохранены.'); redirect('?p=districts'); }
if($a==='add_district_admin'){ require_role('operator'); $n=ensure_district(trim($_POST['name']??'')); flash_set($n?('Район добавлен: '.$n):'Укажите название района.'); redirect('?p=districts'); }
require_login(); $u=current_user();
if($a==='submit_request'){
require_role('school');
if(empty($u['active'])){ flash_set('Ваша учётная запись ещё не подтверждена.'); redirect('?p=school'); }
$f=['level'=>$u['level'],'contact_name'=>trim($_POST['contact_name']??''),'phone'=>trim($_POST['phone']??''),'email'=>trim($_POST['email']??''),
'udate'=>$_POST['udate']??'','utime'=>$_POST['utime']??'','utime_end'=>$_POST['utime_end']??'','age_group'=>$_POST['age_group']??'','participants'=>trim($_POST['participants']??''),
'speakers_needed'=>(int)($_POST['speakers_needed']??1),'topic'=>(($_POST['topic']??'')==='__other__'?trim($_POST['topic_other']??''):trim($_POST['topic']??'')),'notes'=>trim($_POST['notes']??'')];
if(!$f['contact_name']||!$f['phone']||!$f['udate']||!$f['utime']||!$f['utime_end']||!$f['age_group']||empty($_POST['consent'])){ flash_set('Заполните обязательные поля и согласие.'); redirect('?p=school&tab=new'); }
if($f['udate']<min_create_date()){ flash_set('Дату урока можно выбрать не ранее чем через 10 дней.'); redirect('?p=school&tab=new'); }
if(!valid_slot($f['utime'])||!valid_slot($f['utime_end'])){ flash_set('Время — с 8:00 до 18:00, минуты кратны 10.'); redirect('?p=school&tab=new'); }
if(tmin($f['utime_end'])<=tmin($f['utime'])){ flash_set('Время окончания должно быть позже времени начала.'); redirect('?p=school&tab=new'); }
$id=create_request($u,$f); flash_set('Заявка отправлена. Откройте её и выберите спикеров.'); redirect('?p=request&id='.$id);
}
// ========== РЕДАКТИРОВАНИЕ ЗАЯВКИ ==========
if($a==='update_request'){
require_role('school');
$rid=(int)($_POST['id']??0); $r=$rid?get_request($rid):null;
if(!$r){ flash_set('Заявка не найдена.'); redirect('?p=school'); }
if($r['school_user_id']!=$u['id']){ flash_set('Нет доступа.'); redirect('?p=school'); }
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
if($a==='approve_applicant'){ require_role('operator'); [$ok,$msg]=approve_applicant($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=operator&tab=moderation'); }
if($a==='reject_applicant'){ require_role('operator'); [$ok,$msg]=reject_applicant($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=operator&tab=moderation'); }
if($a==='add_unavailable'){ require_role('speaker'); [$ok,$msg]=add_unavailable_period($u,$_POST['date_from']??'',$_POST['date_to']??'',$_POST['reason']??''); flash_set($msg); redirect('?p=speaker'); }
if($a==='remove_unavailable'){ require_role('speaker'); [$ok,$msg]=remove_unavailable_period($u,(int)($_POST['uid']??0)); flash_set($msg); redirect('?p=speaker'); }
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
$stored=bin2hex(random_bytes(16)).($ext?".$ext":'');
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
$stored=bin2hex(random_bytes(16)).($ext?".$ext":'');
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
$rid=(int)($_POST['id']??0); $r=$rid?get_request($rid):null;
if($r){
$msg=null; $sid=(int)($_POST['sid']??0);
if($a==='add_candidate'){ require_role('school'); if($r['school_user_id']!=$u['id']) exit('Нет доступа.'); [$ok,$msg]=add_candidate($r,(int)($_POST['speaker_user_id']??0)); }
elseif($a==='auto_pick'){ require_role('school'); if($r['school_user_id']!=$u['id']) exit('Нет доступа.'); [$ok,$msg]=auto_pick_multiple($r); }
elseif($a==='remove_candidate'){ require_role('school'); if($r['school_user_id']!=$u['id']) exit('Нет доступа.'); [$ok,$msg]=remove_candidate($r,$sid); }
elseif($a==='fund_reject_candidate'){ require_role('fund'); $reasonRaw=$_POST['reason']??''; $reasonOther=trim($_POST['reason_other']??''); $reason=($reasonRaw==='__other__')?$reasonOther:$reasonRaw; [$ok,$msg]=fund_reject_candidate($u,$r,$sid,$reason); }
elseif($a==='fund_assign_speaker'){ require_role('fund'); [$ok,$msg]=fund_assign_speaker($u,$r,(int)($_POST['speaker_user_id']??0)); }
elseif($a==='fund_finalize'){ require_role('fund'); [$ok,$msg]=fund_finalize($u,$r); }
elseif($a==='confirm_prep'){ require_role('school'); if($r['school_user_id']!=$u['id']) exit('Нет доступа.'); [$ok,$msg]=school_set_prep($r,isset($_POST['parents'])?1:0,isset($_POST['venue'])?1:0,isset($_POST['escort'])?1:0); }
elseif($a==='fund_recommend'){ require_role('fund'); [$ok,$msg]=fund_recommend($u,$r,(int)($_POST['sid']??0)); }
elseif($a==='fund_confirm_speaker'){ require_role('fund'); [$ok,$msg]=fund_confirm_speaker($u,$r,(int)($_POST['sid']??0)); }
elseif($a==='fund_briefing'){ require_role('fund'); [$ok,$msg]=fund_briefing($r,$_POST['bdate']??''); }
elseif($a==='mark_done'){ require_role('school'); if($r['school_user_id']!=$u['id']) exit('Нет доступа.'); [$ok,$msg]=mark_done($r); }
elseif($a==='not_held'){ require_role('school'); if($r['school_user_id']!=$u['id']) exit('Нет доступа.'); $reasonRaw=$_POST['reason']??''; $reasonOther=trim($_POST['reason_comment']??''); $reason=($reasonRaw==='__other__')?$reasonOther:$reasonRaw; [$ok,$msg]=mark_not_held($r,$reason,$reasonRaw==='__other__'?'':$reasonOther); }
elseif($a==='close'){ require_role('operator'); [$ok,$msg]=close_request($r); }
elseif($a==='step_back'){ require_role('operator'); step_back($r); $msg='Возврат на шаг назад.'; }
elseif($a==='fund_replace'){ require_role('fund'); $reasonRaw=$_POST['reason']??''; $reasonOther=trim($_POST['reason_other']??''); $reason=($reasonRaw==='__other__')?$reasonOther:$reasonRaw; [$ok,$msg]=fund_replace_speaker($u,$r,(int)($_POST['old_sid']??0),(int)($_POST['new_sid']??0),$reason); }
elseif($a==='speaker_decline'){ require_role('speaker'); [$ok,$msg]=speaker_decline($r,$u['id']); }
elseif($a==='feedback'){
$comment=trim($_POST['comment']??'');
if($comment==='') $msg='Напишите отзыв.';
elseif(st_index($r['status'])<st_index('done')) $msg='Отзыв можно оставить после проведения.';
elseif($u['role']==='school' && $r['school_user_id']==$u['id']){ upd($rid,['fb_app_comment'=>$comment,'fb_app_at'=>now()]); $msg='Спасибо! Отзыв сохранён.'; }
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
if(!$u && !in_array($p,['login','register','reset_password','reset_request','privacy'])) $p='login';
if($u && ($p===''||$p==='login'||$p==='register')) $p=$u['role'];
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
$n=archive_old_requests(30);
flash_set("Архивировано заявок: $n");
redirect('?p=operator&tab=archive');
}
function layout($title,$body){
$u=current_user(); $c=cfg(); $sess='';
if($u){ $nm=($u['role']==='school')?($u['org_name']?:$u['display_name']):$u['display_name']; $roleWord=['operator'=>'Департамент','school'=>'Заявитель','speaker'=>'Спикер','fund'=>'Фонд'][$u['role']]??''; $label=$roleWord.(trim((string)$nm)!==''?': '.$nm:'');
$sess='<span class="rolebadge">'.esc($label).'</span> <a class="rolebadge" href="?p=profile" style="text-decoration:none">Профиль</a> <form method="post" style="display:inline"><input type="hidden" name="action" value="logout">'.csrf_field().'<button class="logout">Выйти</button></form>'; }
$f=flash_get();
echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title><link rel="icon" href="favicon.ico"><link rel="icon" type="image/png" href="favicon.png"><link rel="stylesheet" href="style.css"></head><body>';
echo '<header class="top"><div class="topbar"><div class="mark"><img src="favicon.png" alt="" style="width:100%;height:100%;object-fit:contain"></div><div class="brand"><h1>Урок Мужества</h1></div><div class="session">'.$sess.'</div></div></header>';
echo '<main class="wrap">'; if($f) echo '<div class="flash">'.esc($f).'</div>'; echo $body.'</main>';
echo '<footer style="text-align:center;padding:20px;color:var(--muted);font-size:12px"><a href="?p=privacy">Политика обработки персональных данных (152-ФЗ)</a></footer>';
if(empty($_COOKIE['um72_cookie_consent'])){
echo '<div id="um72-cookie" role="dialog" aria-live="polite" aria-label="Уведомление об использовании файлов cookie">'
.'<div class="um72ck-inner">'
.'<p class="um72ck-text">Мы используем файлы cookie. Продолжая использование, вы соглашаетесь с <a href="?p=privacy">Политикой обработки персональных данных</a>.</p>'
.'<div class="um72ck-act"><button type="button" id="um72ck-ok" class="um72ck-btn">Принять</button></div>'
.'</div></div>'
.'<style>'
.'#um72-cookie{position:fixed;left:0;right:0;bottom:0;z-index:9999;background:#0039A6;color:#fff;box-shadow:0 -2px 14px rgba(0,0,0,.25);animation:um72ckUp .35s ease}'
.'@keyframes um72ckUp{from{transform:translateY(100%)}to{transform:translateY(0)}}'
.'.um72ck-inner{max-width:1100px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:18px;flex-wrap:wrap}'
.'.um72ck-text{margin:0;flex:1 1 320px;font-size:14px;line-height:1.5;color:#fff}'
.'.um72ck-text a{color:#cfe0f3;text-decoration:underline}'
.'.um72ck-act{flex:0 0 auto}'
.'.um72ck-btn{background:#fff;color:#0039A6;border:0;cursor:pointer;font-weight:600;font-size:15px;padding:10px 26px;border-radius:6px}'
.'.um72ck-btn:hover{background:#e8eef6}'
.'@media(max-width:520px){.um72ck-btn{width:100%;padding:12px}.um72ck-act{width:100%}}'
.'</style>'
.'<script>(function(){var b=document.getElementById("um72-cookie"),k=document.getElementById("um72ck-ok");if(!b||!k)return;k.addEventListener("click",function(){var d=new Date();d.setFullYear(d.getFullYear()+1);var s=location.protocol==="https:"?"; Secure":"";document.cookie="um72_cookie_consent=1; expires="+d.toUTCString()+"; path=/; SameSite=Lax"+s;b.style.display="none";});})();</script>';
}
echo '</body></html>';
}
function pill($k){ $m=st_meta($k); if($k==='cancelled') return '<span class="pill" style="background:#fdecea;color:#b23b2c">'.esc($m['label']).'</span>'; return '<span class="pill '.$m['color'].'">'.esc($m['label']).'</span>'; }
function stepper($cur){ if($cur==='cancelled'){ return '<div class="stepper"><div class="st" style="color:#b23b2c"><span class="mk" style="background:#b23b2c;color:#fff">✕</span>Урок не состоялся</div></div>'; }
$idx=st_index($cur); $h='<div class="stepper">';
foreach(statuses() as $i=>$s){ if($i<=$idx){ $cls='done'; $mk='✓'; } elseif($i===$idx+1){ $cls='cur'; $mk=$i+1; } else { $cls=''; $mk=$i+1; } $h.='<div class="st '.$cls.'"><span class="mk">'.$mk.'</span>'.esc($s['label']).'</div>'; }
return $h.'</div>'; }
function fbline($role,$comment){ $comment=trim((string)$comment); if($comment==='') return '<div class="fbsum"><b>'.esc($role).'</b> <span class="muted">— отзыв не оставлен</span></div>'; return '<div class="fbsum"><b>'.esc($role).'</b><div class="cmt">«'.esc($comment).'»</div></div>'; }
function dt($r){ $t=$r['utime']?(' '.esc($r['utime']).(!empty($r['utime_end'])?('–'.esc($r['utime_end'])):'')):''; return esc($r['udate']).$t; }
function render_reason_select($name,$reasons,$withOther=true){
$o='<option value="">— выберите причину —</option>';
foreach($reasons as $rs) $o.='<option value="'.esc($rs).'">'.esc($rs).'</option>';
if($withOther) $o.='<option value="__other__">Другое (указать ниже)</option>';
$html='<select name="'.$name.'" required onchange="var w=this.parentNode.querySelector(\'.reason-other-wrap\');if(w)w.style.display=this.value===\'__other__\'?\'block\':\'none\';">'.$o.'</select>';
if($withOther) $html.='<div class="reason-other-wrap" style="display:none;margin-top:6px"><input name="'.$name.'_other" placeholder="Укажите причину"></div>';
return $html;
}
// ========== СТРАНИЦА ВХОДА ==========
if($p==='login'){
$body='<div class="auth"><h2>Вход</h2><form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="login"><label class="lbl">Логин</label><input name="login" required><label class="lbl">Пароль</label><input type="password" name="pass" required><button class="btn primary" style="margin-top:12px">Войти</button></form><p class="muted"><a href="?p=reset_request">Забыли пароль?</a> · <a href="?p=register">Зарегистрироваться</a></p></div>';
layout('Вход',$body); exit;
}
if($p==='reset_request'){
$body='<div class="auth"><h2>Сброс пароля</h2><form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="request_reset"><label class="lbl">Email</label><input type="email" name="email" required><button class="btn primary" style="margin-top:12px">Отправить ссылку</button></form><p class="muted"><a href="?p=login">← ко входу</a></p></div>';
layout('Сброс пароля',$body); exit;
}
if($p==='reset_password'){
$token=$_GET['token']??'';
[$ok,$row]=verify_reset_token($token);
if(!$ok){ layout('Сброс пароля','<div class="auth card pad"><h2>Ссылка недействительна</h2><p class="muted">'.$row.'</p><p><a href="?p=reset_request">Запросить новую ссылку</a></p></div>'); exit; }
$body='<div class="auth"><h2>Новый пароль</h2><form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="reset_password"><input type="hidden" name="token" value="'.esc($token).'"><label class="lbl">Новый пароль (от 5 символов)</label><input type="password" name="newpass" required><label class="lbl">Повторите пароль</label><input type="password" name="newpass2" required><button class="btn primary" style="margin-top:12px">Сменить пароль</button></form></div>';
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
.pdoc th{background:#0039A6;color:#fff}
.pdoc .ph{color:#b23b2c;font-weight:600}
</style>
<div class="pdoc">
<h2>Политика обработки персональных данных</h2>
<p class="muted">Информационная система «урокмужества72.рф». В соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных». Редакция от <span class="ph">[дата]</span> 2026 г.</p>
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
<p>По истечении сроков данные уничтожаются или обезличиваются. Закрытые заявки старше 30 дней переносятся в архив с ограниченным доступом.</p>
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
<p class="muted">Заполните плейсхолдеры <span class="ph">[●]</span> реальными реквизитами. Перечень Фондов дополняется по мере включения новых организаций.</p>
<table><thead><tr><th>Оператор</th><th>Реквизиты</th><th>Контакт по ПДн</th></tr></thead><tbody>
<tr><td>Региональный фонд «<span class="ph">[наименование]</span>»</td><td>ОГРН <span class="ph">[●]</span>; ИНН <span class="ph">[●]</span>; адрес <span class="ph">[●]</span></td><td><span class="ph">[email]</span></td></tr>
<tr><td>ДОА г. Тюмени</td><td>ОГРН <span class="ph">[●]</span>; ИНН <span class="ph">[●]</span>; адрес <span class="ph">[●]</span></td><td><span class="ph">[email]</span></td></tr>
<tr><td>ДОиН ТО</td><td>ОГРН <span class="ph">[●]</span>; ИНН <span class="ph">[●]</span>; адрес <span class="ph">[●]</span></td><td><span class="ph">[email]</span></td></tr>
</tbody></table>
<p style="margin-top:18px"><a href="?p=login">← ко входу</a></p>
</div></div>
HTML;
layout('Политика обработки персональных данных',$body); exit;
}
// ========== ПРОФИЛЬ ==========
if($p==='profile'){
require_login(); $u=current_user();
$dnLabel=['school'=>'Наименование организации','speaker'=>'ФИО','fund'=>'Название Фонда','operator'=>'Наименование (оператор / департамент)'][$u['role']]??'Отображаемое имя';
$cur=($u['role']==='school')?($u['org_name']?:$u['display_name']):$u['display_name'];
$body='<div class="head"><h2>Профиль</h2><a class="btn ghost" href="?p='.esc($u['role']).'">← Назад</a></div>';
$body.='<form method="post" class="card pad" style="max-width:560px">'.csrf_field().'<input type="hidden" name="action" value="update_profile">';
$body.='<p class="muted">Логин: <b>'.esc($u['login']).'</b> (не меняется).</p>';
$body.='<label class="lbl">'.$dnLabel.' <span style="color:#d11">*</span></label><input name="display_name" required value="'.esc($cur).'">';
$body.='<label class="lbl">Email <span style="color:#d11">*</span></label><input type="email" name="email" required value="'.esc($u['email']).'">';
$distOptP=''; foreach(district_names() as $dn) $distOptP.='<option '.(($u['district']??'')===$dn?'selected':'').'>'.esc($dn).'</option>';
$body.='<label class="lbl">Район Тюменской области</label><select name="district" id="pdistrict_sel" onchange="pdsw()"><option value="">— выберите район —</option>'.$distOptP.'<option value="__other__">Другой (вписать вручную)</option></select>';
$body.='<div id="pdistrict_wrap" style="display:none"><label class="lbl">Впишите район</label><input name="district_other" placeholder="название района"></div>';
if($u['role']==='speaker'){ $body.='<label class="lbl">Телефон для связи</label><input name="phone" value="'.esc($u['phone']??'').'">';
$fo='<option value="">— выберите организацию (фонд) —</option>'; foreach(all_funds() as $ff){ $fo.='<option value="'.$ff['id'].'"'.((int)($u['fund_user_id']??0)===(int)$ff['id']?' selected':'').'>'.esc(fund_label($ff)).'</option>'; }
$body.='<label class="lbl">Организация (фонд)</label><select name="fund_user_id">'.$fo.'</select><p class="muted" style="margin:4px 0 0">Организация, которая направляет вас как спикера.</p>';
}
if($u['role']==='school'){
$body.='<label class="lbl">Адрес местонахождения</label><input name="address" value="'.esc($u['address']??'').'" placeholder="г. Тюмень, ул. ..., д. ...">';
$body.='<label class="lbl">№ корпуса</label><input name="building" value="'.esc($u['building']??'').'" placeholder="напр. 1">';
$body.='<p class="muted" style="margin:2px 0 8px">Если у организации нет отдельных корпусов, укажите «1».</p>';
}
if(in_array($u['role'],['school','fund','operator'],true)){
$body.='<label class="lbl">ФИО контактного лица</label><input name="contact_name" value="'.esc($u['contact_name']??'').'">';
$body.='<label class="lbl">Должность</label><input name="position" value="'.esc($u['position']??'').'">';
$body.='<label class="lbl">Телефон</label><input name="phone" value="'.esc($u['phone']??'').'">';
}
$body.='<label class="lbl">Новый пароль (необязательно)</label><input type="password" name="newpass" placeholder="от 5 символов">';
$body.='<button class="btn primary" style="margin-top:12px">Сохранить</button></form>';
if($u['role']==='school') $body.='<p class="muted" style="max-width:560px">При смене наименования ранее поданные заявки сохранят старое название.</p>';
$body.='<script>function pdsw(){var w=document.getElementById("pdistrict_wrap");if(w)w.style.display=(document.getElementById("pdistrict_sel").value==="__other__")?"block":"none";}</script>';
layout('Профиль',$body); exit;
}
// ========== РЕГИСТРАЦИЯ ==========
if($p==='register'){
$ag=''; foreach(ages() as $k=>$v) $ag.='<label class="chk"><input type="checkbox" name="groups['.esc($k).']"> '.esc($v).'</label>';
$distOpt=''; foreach(district_names() as $dn) $distOpt.='<option>'.esc($dn).'</option>';
$fundOpt='<option value="">— выберите фонд —</option>'; foreach(all_funds() as $ff) $fundOpt.='<option value="'.$ff['id'].'">'.esc(fund_label($ff)).'</option>';
$headFundOpt='<option value="">— выберите головной фонд —</option>'; foreach(head_funds() as $ff) $headFundOpt.='<option value="'.$ff['id'].'">'.esc(fund_label($ff)).'</option>';
$body='<div class="auth"><h2>Регистрация</h2><form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="register">
<label class="lbl">Кто вы?</label><select name="role" id="role" onchange="rsw()"><option value="school">Образовательная организация (заявитель)</option><option value="speaker">Спикер (ветеран)</option><option value="fund">Фонд (направляет спикеров)</option><option value="operator">Оператор</option></select>
<label class="lbl">Логин <span style="color:#d11">*</span></label><input name="login" required><label class="lbl">Пароль (от 5 символов) <span style="color:#d11">*</span></label><input type="password" name="pass" required>
<label class="lbl">Email (для уведомлений) <span style="color:#d11">*</span></label><input type="email" name="email" required>
<label class="lbl">Район Тюменской области <span style="color:#d11">*</span></label><select name="district" id="district_sel" onchange="dsw()"><option value="">— выберите район —</option>'.$distOpt.'<option value="__other__">Другой (вписать вручную)</option></select>
<div id="district_other_wrap" style="display:none"><label class="lbl">Впишите район</label><input name="district_other" placeholder="название района"></div>
<div class="rf school"><label class="lbl">Наименование организации</label><input name="org_name"><label class="lbl">Уровень</label><select name="level"><option value="school">Школа</option><option value="spo">Профессиональная образовательная организация</option><option value="vuz">Высшее учебное заведение</option></select><label class="lbl">Адрес местонахождения</label><input name="address" placeholder="г. Тюмень, ул. ..., д. ..."><label class="lbl">№ корпуса</label><input name="building" placeholder="напр. 1"><p class="muted" style="margin:2px 0 8px">Если у организации нет отдельных корпусов, укажите «1».</p><label class="lbl">ФИО контактного лица</label><input name="contact_name"><label class="lbl">Должность</label><input name="position"><label class="lbl">Телефон</label><input name="contact_phone"></div>
<div class="rf speaker" style="display:none"><label class="lbl">ФИО</label><input name="display_name"><label class="lbl">Телефон для связи <span style="color:#d11">*</span></label><input name="phone"><label class="lbl">Фонд <span style="color:#d11">*</span></label><select name="fund_user_id">'.$fundOpt.'</select><label class="lbl">Возрастные группы</label><div class="chks">'.$ag.'</div></div>
<div class="rf fund" style="display:none"><label class="lbl">Наименование Фонда</label><input name="fund_display_name"><label class="lbl">Тип</label><select name="fund_type" id="fund_type" onchange="ftsw()"><option value="head">Головной фонд</option><option value="sub">Структурное подразделение</option></select><div id="parentfund" style="display:none"><label class="lbl">Головной фонд <span style="color:#d11">*</span></label><select name="parent_fund_id">'.$headFundOpt.'</select></div><div id="fundcode"><label class="lbl">Код регистрации Фонда</label><input name="fund_code"></div><label class="lbl">ФИО контактного лица</label><input name="contact_name"><label class="lbl">Должность</label><input name="position"><label class="lbl">Телефон</label><input name="contact_phone"></div>
<div class="rf operator" style="display:none"><label class="lbl">Наименование</label><input name="op_display_name"><label class="lbl">Департамент</label><select name="op_group"><option value="school">ДОА г. Тюмени (школы)</option><option value="region">ДОиН ТО (СПО и вузы)</option></select><label class="lbl">Код регистрации оператора</label><input name="op_code"><label class="lbl">ФИО контактного лица</label><input name="contact_name"><label class="lbl">Должность</label><input name="position"><label class="lbl">Телефон</label><input name="contact_phone"></div>
<label class="chk" style="margin-top:10px"><input type="checkbox" name="consent" required> Согласие на обработку ПДн <span style="color:#d11">*</span> (<a href="?p=privacy" target="_blank">политика</a>)</label>
<button class="btn primary" style="margin-top:12px">Зарегистрироваться</button></form><p class="muted"><a href="?p=login">← ко входу</a></p></div>
<script>function rsw(){var r=document.getElementById("role").value;document.querySelectorAll(".rf").forEach(function(e){e.style.display="none"});var b=document.querySelector(".rf."+r);if(b)b.style.display="block";}rsw();function ftsw(){var t=document.getElementById("fund_type").value;document.getElementById("parentfund").style.display=(t==="sub")?"block":"none";document.getElementById("fundcode").style.display=(t==="sub")?"none":"block";}function dsw(){var w=document.getElementById("district_other_wrap");if(w)w.style.display=(document.getElementById("district_sel").value==="__other__")?"block":"none";}</script>';
layout('Регистрация',$body); exit;
}
require_login();
// ========== УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ ==========
if($p==='users' && in_array($u['role'],['operator','fund'])){
$body='<div class="head"><h2>Управление пользователями</h2><a class="btn ghost" href="?p='.$u['role'].'">← в кабинет</a></div>';
$role_filter=$_GET['role']??'';
$where="WHERE 1=1"; $params=[]; $superAdmin=false; $scopeNote='';
if($u['role']==='fund'){ $where.=" AND role='speaker' AND fund_user_id=?"; $params[]=$u['id']; $scopeNote='Только ваши спикеры.'; }
else { $og=$u['op_group']??'';
if($og===''){ $superAdmin=true; if($role_filter){ $where.=" AND role=?"; $params[]=$role_filter; } }
else { $levels=applicant_group_levels($og); $in=implode(',',array_fill(0,count($levels),'?')); $where.=" AND role='school' AND level IN ($in)"; $params=array_merge($params,$levels); $scopeNote='Только ваши организации.'; }
}
$st=db()->prepare("SELECT * FROM users $where ORDER BY role, display_name");
$st->execute($params); $users=$st->fetchAll();
if($superAdmin) $body.='<div class="card pad"><form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><input type="hidden" name="p" value="users"><label class="lbl" style="margin:0">Фильтр по роли</label><select name="role" onchange="this.form.submit()"><option value="">Все</option><option value="school" '.($role_filter==='school'?'selected':'').'>Заявители</option><option value="speaker" '.($role_filter==='speaker'?'selected':'').'>Спикеры</option><option value="fund" '.($role_filter==='fund'?'selected':'').'>Фонд</option><option value="operator" '.($role_filter==='operator'?'selected':'').'>Оператор</option></select></form></div>';
elseif($scopeNote) $body.='<p class="muted">'.$scopeNote.'</p>';
$body.='<table class="card-view"><thead><tr><th>Логин</th><th>ФИО/Орг</th><th>Роль</th><th>Email</th><th>Статус</th><th>Действия</th></tr></thead><tbody>';
foreach($users as $x){
$status=$x['active']?'<span class="pill green">активен</span>':'<span class="pill gray">ожидает</span>';
$actions='<form method="post" style="display:inline">'.csrf_field().'<input type="hidden" name="action" value="admin_reset_password"><input type="hidden" name="target_id" value="'.$x['id'].'"><input type="password" name="newpass" placeholder="Новый пароль" required style="width:140px"><button class="btn ghost sm">Сбросить пароль</button></form>';
$actions.=' <form method="post" style="display:inline" onsubmit="return confirm(\'Удалить?\')">'.csrf_field().'<input type="hidden" name="action" value="delete_user"><input type="hidden" name="target_id" value="'.$x['id'].'"><button class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">Удалить</button></form>';
$body.='<tr><td class="id">'.esc($x['login']).'</td><td data-label="ФИО/Орг">'.esc($x['display_name']?:$x['org_name']).'</td><td data-label="Роль">'.esc($x['role']).'</td><td data-label="Email">'.esc($x['email']??'—').'</td><td data-label="Статус">'.$status.'</td><td data-label="Действия">'.$actions.'</td></tr>';
}
$body.='</tbody></table>';
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
if($p==='audit' && $u && $u['role']==='operator'){
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
$body='<div class="head"><h2>Реестр спикеров</h2><a class="btn ghost" href="?p=operator">← к заявкам</a></div>';
$allsp=db()->query("SELECT * FROM users WHERE role='speaker' ORDER BY display_name")->fetchAll();
$body.='<table class="card-view"><thead><tr><th>Спикер</th><th>Организация</th><th>Район</th><th>Возрастные группы</th><th>Телефон</th><th>Статус</th></tr></thead><tbody>';
foreach($allsp as $s){ $g=array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$s['age_groups']))); $f=$s['fund_user_id']?find_user_by_id((int)$s['fund_user_id']):null; $fname=($f && $f['display_name']!=='')?esc($f['display_name']):'<span class="muted">не указана</span>'; $stp=$s['active']?'<span class="pill green">активен</span>':'<span class="pill gray">на подтверждении</span>'; $body.='<tr><td class="id" data-label="Спикер">'.esc($s['display_name']).'</td><td data-label="Организация">'.$fname.'</td><td data-label="Район" class="muted">'.esc($s['district']?:'—').'</td><td data-label="Группы" class="muted">'.esc(implode(', ',$g)).'</td><td data-label="Телефон" class="muted">'.esc($s['phone']?:'—').'</td><td data-label="Статус">'.$stp.'</td></tr>'; }
if(!$allsp) $body.='<tr><td colspan="6" class="muted">Спикеров пока нет.</td></tr>'; $body.='</tbody></table>';
$pend=pending_speakers(); if($pend) $body.='<p class="muted" style="margin-top:12px">Ожидают подтверждения Фондом: '.count($pend).'.</p>';
layout('Реестр спикеров',$body); exit;
}
if($tab==='archive'){
$afil=['archived'=>1]; if(($u['op_group']??'')==='school') $afil['op_group']='school';
$rows=get_requests_list($afil);
$body='<div class="head"><h2>Архив заявок</h2><a class="btn ghost" href="?p=operator">← к заявкам</a></div>';
$body.='<p class="muted">Закрытые заявки старше 30 дней автоматически переносятся в архив.</p>';
$tr=''; foreach($rows as $r){ $tr.='<tr onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id">'.esc($r['code']).'</td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.esc(req_label($r['id'])).'</td><td>'.pill($r['status']).'</td><td class="muted">'.esc($r['archived_at']??'').'</td></tr>'; }
$body.= $rows ? '<table class="card-view"><thead><tr><th>Код</th><th>Организация</th><th>Дата</th><th>Спикеры</th><th>Статус</th><th>Архивировано</th></tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">Архив пуст.</div>';
layout('Архив заявок',$body); exit;
}
if($tab==='moderation'){
$pend=pending_applicants($u);
$dept=($u['op_group']??'')==='' ? 'Вы видите всех заявителей.' : 'Ваш департамент: '.esc(operator_of(($u['op_group']==='school')?'school':'spo')).'.';
$body='<div class="head"><h2>Модерация заявителей ('.count($pend).')</h2><a class="btn ghost" href="?p=operator">← к заявкам</a></div>';
$body.='<p class="muted">Подтвердите организации, которым открыть доступ к подаче заявок. '.$dept.'</p>';
if(!$pend) $body.='<div class="card pad muted">Новых заявок на регистрацию заявителей нет.</div>';
foreach($pend as $ap){
$body.='<div class="card pad" style="margin-bottom:10px"><b>'.esc($ap['org_name']).'</b> <span class="pill blue">'.esc(level_label($ap['level'])).'</span>'
.'<dl class="kv" style="margin-top:8px"><dt>Район</dt><dd>'.esc($ap['district']?:'—').'</dd>'
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
if(!empty($c[2])){ $kp.='<a class="kpi" href="'.esc($c[2]).'" style="text-decoration:none;color:inherit"><div class="n">'.esc($c[1]).'</div><div class="l">'.esc($c[0]).'</div></a>'; }
else { $kp.='<div class="kpi"><div class="n">'.esc($c[1]).'</div><div class="l">'.esc($c[0]).'</div></div>'; }
}
$deptSel='';
if(!$locked){ $deptSel='<label class="lbl" style="margin:0">Департамент</label><select name="dept"><option value="">Общие результаты</option><option value="school" '.($deptFilter==='school'?'selected':'').'>ДО г. Тюмени (школы)</option><option value="region" '.($deptFilter==='region'?'selected':'').'>ДОиН ТО (СПО и вузы)</option></select>'; }
$body.='<details class="card pad" style="margin-bottom:14px"><summary style="cursor:pointer;font-weight:600;color:var(--rf-blue)">🔍 Фильтры и поиск</summary><div style="margin-top:12px">'.($locked?'<p class="muted" style="margin:0 0 8px">Вы видите заявки школ.</p>':'').'<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end"><input type="hidden" name="p" value="operator"><input type="hidden" name="tab" value="list">
'.$deptSel.'<label class="lbl" style="margin:0">Статус</label><select name="status"><option value="">Все</option>';
foreach(statuses() as $s) $body.='<option value="'.$s['k'].'" '.(!empty($_GET['status'])&&$_GET['status']===$s['k']?'selected':'').'>'.$s['label'].'</option>';
$body.='</select><label class="lbl" style="margin:0">Организация</label><input name="org" value="'.esc($_GET['org']??'').'" placeholder="Поиск">
<label class="lbl" style="margin:0">С</label><input type="date" name="from_date" value="'.esc($_GET['from_date']??'').'">
<label class="lbl" style="margin:0">По</label><input type="date" name="to_date" value="'.esc($_GET['to_date']??'').'">
<button class="btn primary sm">Фильтр</button> <a class="btn ghost sm" href="?p=operator">Сбросить</a></form></div></details>';
$body.='<div style="margin-bottom:14px"><a class="btn ghost" href="?p=operator_tools">🛠️ Инструменты'.($pendN?' <span class="pill" style="background:#fdecea;color:#b23b2c">'.$pendN.'</span>':'').'</a></div>';
$tr=''; foreach($rows as $r){
$urgent_tr = is_request_urgent($r) ? 'urgent' : '';
$tr.='<tr class="'.$urgent_tr.'" onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id">'.esc($r['code']).'</td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.esc(req_label($r['id'])).'</td><td>'.pill($r['status']).'</td></tr>';
}
$body.='<div class="head"><h2>Реестр заявок ('.count($rows).')</h2></div><div class="kpis">'.$kp.'</div>';
$body.= $rows ? '<table class="card-view"><thead><tr><th>Код</th><th>Организация</th><th>Дата и время</th><th>Спикеры</th><th>Статус</th></tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">Заявок не найдено.</div>';
layout('Кабинет оператора',$body); exit;
}
// ========== ОТЗЫВЫ ==========
if($p==='reviews'){
require_role('operator');
$type=($_GET['type']??'')==='speaker'?'speaker':'app';
$opg=$u['op_group']??''; $dept=$opg==='school'?'school':(in_array($_GET['dept']??'',['school','region'])?$_GET['dept']:'');
$scopeOg=$opg==='school'?'school':($dept?:null);
$body='<div class="head"><h2>'.($type==='app'?'Отзывы заявителей':'Отзывы спикеров').'</h2><a class="btn ghost" href="?p=operator">← к заявкам</a></div>';
if($type==='app'){
$rows=list_app_reviews($scopeOg);
$body.='<table class="card-view"><thead><tr><th>Заявитель</th><th>Спикеры</th><th>Тема урока</th><th>Дата отзыва</th><th>Отзыв</th></tr></thead><tbody>';
foreach($rows as $r){
$speakers=implode(', ',array_map(fn($x)=>$x['display_name'],req_confirmed_rows($r['id'])));
$review=esc($r['fb_app_comment']??'');
$body.='<tr onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id" data-label="Заявитель">'.esc($r['org']).'</td><td data-label="Спикеры" class="muted">'.esc($speakers?:'—').'</td><td data-label="Тема" class="muted">'.esc($r['topic']?:'—').'</td><td data-label="Дата" class="muted">'.esc($r['fb_app_at']?:'—').'</td><td data-label="Отзыв">'.$review.'</td></tr>';
}
if(!$rows) $body.='<tr><td colspan="5" class="muted">Отзывов заявителей пока нет.</td></tr>';
$body.='</tbody></table>';
} else {
$rows=list_speaker_reviews($scopeOg);
$body.='<table class="card-view"><thead><tr><th>Заявитель</th><th>Спикер</th><th>Тема урока</th><th>Дата отзыва</th><th>Отзыв</th></tr></thead><tbody>';
foreach($rows as $row){
$review=esc($row['vet_comment']??'');
$body.='<tr onclick="location=\'?p=request&id='.$row['request_id'].'\'"><td class="id" data-label="Заявитель">'.esc($row['org']).'</td><td data-label="Спикер" class="muted">'.esc($row['speaker_name']).'</td><td data-label="Тема" class="muted">'.esc($row['topic']?:'—').'</td><td data-label="Дата" class="muted">'.esc($row['vet_at']?:'—').'</td><td data-label="Отзыв">'.$review.'</td></tr>';
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
$body='<div class="head"><h2>Инструменты</h2><a class="btn ghost" href="?p=operator">← к заявкам</a></div>';
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
</div>';
layout('Инструменты',$body); exit;
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
$typeFilter=$_GET['type']??'';
$rows=list_materials($typeFilter?:null);
$body='<div class="head"><h2>Материалы</h2>'.($u['role']==='operator'?'<a class="btn primary" href="?p=material_edit">+ Добавить материал</a>':'').'</div>';
$body.='<form method="get" style="margin-bottom:14px"><input type="hidden" name="p" value="materials"><label class="lbl" style="margin:0">Вид материала</label><select name="type" class="form-select" style="max-width:320px;display:inline-block;width:auto" onchange="this.form.submit()"><option value="">Все</option>';
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
$tOpt=''; foreach(material_types() as $k=>$v) $tOpt.='<option value="'.esc($k).'" '.(($m['type']??'')===$k?'selected':'').'>'.esc($v).'</option>';
$body='<div class="head"><h2>'.($m?'Редактирование материала':'Добавить материал').'</h2><a class="btn ghost" href="?p=materials">← к материалам</a></div>';
$body.='<form method="post" class="upload-form" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="'.($m?'update_material':'add_material').'">'.($m?'<input type="hidden" name="id" value="'.$m['id'].'">':'');
$body.='<div class="form-group"><label class="form-label">Название <span style="color:#d11">*</span></label><input class="form-input" name="title" required value="'.esc($m['title']??'').'"></div>';
$body.='<div class="form-group"><label class="form-label">Тема <span style="color:#d11">*</span></label><input class="form-input" name="topic" required list="topics_dl" value="'.esc($m['topic']??'').'"><datalist id="topics_dl">'; foreach(material_topics() as $t) $body.='<option value="'.esc($t).'">'; $body.='</datalist></div>';
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
$body='<div class="head"><h2>Спикеры Фонда</h2><a class="btn ghost" href="?p=fund">← к заявкам</a></div><h4 style="margin-top:6px">На подтверждении</h4>';
$pend=pending_speakers($u); if(!$pend) $body.='<div class="card pad muted" style="margin-bottom:14px">Новых заявок на регистрацию спикеров нет.</div>';
foreach($pend as $s){ $ag=''; foreach(ages() as $k=>$v) $ag.='<label class="chk"><input type="checkbox" name="groups['.esc($k).']" '.(in_array($k,explode(',',$s['age_groups']))?'checked':'').'> '.esc($v).'</label>';
$body.='<form method="post" class="card pad" style="margin-bottom:10px">'.csrf_field().'<input type="hidden" name="action" value="approve_speaker"><input type="hidden" name="uid" value="'.$s['id'].'"><b>'.esc($s['display_name']).'</b> <span class="muted">(логин: '.esc($s['login']).' · район: '.esc($s['district']?:'—').' · тел.: '.esc($s['phone']?:'—').')</span><div class="chks" style="margin:8px 0">'.$ag.'</div><button class="btn primary">Подтвердить и дать доступ</button></form>'; }
$act=active_speakers($u); $body.='<h4>Подтверждённые спикеры</h4><table class="card-view"><thead><tr><th>Спикер</th><th>Район</th><th>Телефон</th><th>Возрастные группы</th></tr></thead><tbody>';
foreach($act as $s){ $g=array_map(fn($k)=>ages()[$k]??$k,array_filter(explode(',',$s['age_groups']))); $body.='<tr><td class="id">'.esc($s['display_name']).'</td><td class="muted">'.esc($s['district']?:'—').'</td><td class="muted">'.esc($s['phone']?:'—').'</td><td class="muted">'.esc(implode(', ',$g)).'</td></tr>'; }
if(!$act) $body.='<tr><td colspan="4" class="muted">Пока нет.</td></tr>'; $body.='</tbody></table>';
$body.='<div style="margin-top:14px;display:flex;gap:8px"><a class="btn ghost" href="?p=users&role=speaker">👥 Управление спикерами</a><a class="btn ghost" href="?p=audit&user_role=speaker">📋 Журнал</a><a class="btn ghost" href="?p=calendar">📅 Календарь</a></div>';
layout('Спикеры Фонда',$body); exit;
}
$rows=fund_requests($u);
$action_req = get_action_required_requests($u);
$body = '';
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
$tr=''; foreach($rows as $r){
$urgent_tr = is_request_urgent($r) ? 'urgent' : '';
$need=in_array($r['status'],['chosen','confirmed'])?' <span class="pill amber">нужно действие</span>':'';
$tr.='<tr class="'.$urgent_tr.'" onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id">'.esc($r['code']).'</td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.esc(req_label($r['id'])).'</td><td>'.pill($r['status']).$need.'</td></tr>';
}
$body.='<div class="head"><h2>Заявки со спикерами</h2><div><a class="btn ghost" href="?p=fund&tab=speakers">Спикеры Фонда</a> <a class="btn ghost" href="?p=calendar">📅 Календарь</a></div></div>';
$body.= $rows ? '<table class="card-view"><thead><tr><th>Код</th><th>Организация</th><th>Дата и время</th><th>Спикеры</th><th>Статус</th></tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">Пока нет заявок с выбранными спикерами.</div>';
layout('Кабинет Фонда',$body); exit;
}
// ========== КАБИНЕТ ЗАЯВИТЕЛЯ ==========
if($p==='school'){
require_role('school'); $tab=$_GET['tab']??'mine';
if(empty($u['active'])){ layout('Кабинет заявителя','<div class="head"><h2>Ваша учётная запись на проверке</h2></div><div class="card pad"><p>Заявка на регистрацию организации <b>'.esc($u['org_name']).'</b> отправлена на подтверждение в профильный департамент ('.esc(operator_of($u['level'])).').</p><p class="muted">После подтверждения вы сможете подавать заявки.</p></div>'); exit; }
if($tab==='new'){
$src=null; if(!empty($_GET['copy'])){ $c0=get_request((int)$_GET['copy']); if($c0 && $c0['school_user_id']==$u['id']) $src=$c0; }
$sg=$src['age_group']??''; $opt=''; foreach(level_ages($u['level']) as $g) $opt.='<option value="'.esc($g).'" '.($sg===$g?'selected':'').'>'.esc(ages()[$g]).'</option>';
$sn=(int)($src['speakers_needed']??0); $nopt=''; for($i=1;$i<=5;$i++) $nopt.='<option value="'.$i.'" '.($sn===$i?'selected':'').'>'.$i.'</option>';
$body='<div class="head"><h2>Подать заявку</h2><a class="btn ghost" href="?p=school&tab=mine">Мои заявки</a></div>
'.($src?'<div class="card pad" style="margin-bottom:10px;border-left:4px solid var(--rf-blue,#0039A6)">Поля заполнены по образцу заявки <b>'.esc($src['code']).'</b>. Проверьте данные и укажите новую дату.</div>':'').'
<form method="post" class="card pad">'.csrf_field().'<input type="hidden" name="action" value="submit_request">
<p class="muted">Организация: <b>'.esc($u['org_name']).'</b> · оператор: '.esc(operator_of($u['level'])).'</p>
<label class="lbl">Возрастная группа <span style="color:#d11">*</span></label><select name="age_group" required>'.$opt.'</select>
<label class="lbl">Сколько спикеров нужно <span style="color:#d11">*</span></label><select name="speakers_needed" id="speakers_needed_sel" onchange="snHint()">'.$nopt.'</select>
<div id="sn_hint" class="hint-warn" style="display:none"><b>⚠ Подсказка:</b> система подбирает спикеров автоматически. Если свободных меньше, чем запрошено — вам покажут доступных, а недостающих можно назначить позже.</div>
<label class="lbl">Контактное лицо <span style="color:#d11">*</span></label><input name="contact_name" required value="'.esc($src['contact_name']??'').'">
<label class="lbl">Телефон <span style="color:#d11">*</span></label><input name="phone" required value="'.esc($src['phone']??'').'">
<label class="lbl">Желаемая дата <span style="color:#d11">*</span> (не ранее чем через 10 дней)</label><input type="date" name="udate" min="'.min_create_date().'" required>
<label class="lbl">Время начала <span style="color:#d11">*</span></label><select name="utime" required><option value="">—</option>'.time_options($src['utime']??'').'</select>
<label class="lbl">Время окончания <span style="color:#d11">*</span></label><select name="utime_end" required><option value="">—</option>'.time_options($src['utime_end']??'').'</select>
<label class="lbl">Число участников</label><input name="participants" value="'.esc($src['participants']??'').'">
<label class="lbl">Тема занятия</label>'.(function() use($src){
$cur=$src['topic']??''; $topics=material_topics(); $known=in_array($cur,$topics,true);
$o='<option value="">— выберите тему —</option>';
foreach($topics as $t){ $o.='<option value="'.esc($t).'"'.($cur===$t?' selected':'').'>'.esc($t).'</option>'; }
$o.='<option value="__other__"'.(($cur!==''&&!$known)?' selected':'').'>Другая тема (указать свою)</option>';
return '<select name="topic" onchange="var b=this.form.querySelector(\'.topic-other\');b.style.display=this.value===\'__other__\'?\'block\':\'none\';">'.$o.'</select>'
.'<input class="topic-other" name="topic_other" placeholder="Своя тема занятия" value="'.esc($known?'':$cur).'" style="margin-top:8px;display:'.(($cur!==''&&!$known)?'block':'none').'">';
})().'
<label class="lbl">Особые условия</label><textarea name="notes">'.esc($src['notes']??'').'</textarea>
<label class="chk" style="margin-top:8px"><input type="checkbox" name="consent" required> Согласие на обработку ПДн <span style="color:#d11">*</span> (<a href="?p=privacy" target="_blank">политика 152-ФЗ</a>)</label>
<button class="btn primary" style="margin-top:12px">Отправить заявку</button></form>
<script>function snHint(){var n=parseInt(document.getElementById("speakers_needed_sel").value);var h=document.getElementById("sn_hint");if(h)h.style.display=(n>=2)?"block":"none";}snHint();</script>';
layout('Подать заявку',$body); exit;
}
$st=db()->prepare('SELECT * FROM requests WHERE school_user_id=? AND (archived=0 OR archived IS NULL) ORDER BY id DESC'); $st->execute([$u['id']]); $rows=$st->fetchAll();
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
$tr=''; foreach($rows as $r){
$urgent_tr = is_request_urgent($r) ? 'urgent' : '';
$tr.='<tr class="'.$urgent_tr.'" onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id">'.esc($r['code']).'</td><td>'.dt($r).'</td><td>'.esc(ages()[$r['age_group']]??'').'</td><td>'.esc(req_label($r['id'])).'</td><td>'.pill($r['status']).'</td></tr>';
}
$body.='<div class="head"><h2>'.esc($u['org_name']).'</h2><div><a class="btn primary" href="?p=school&tab=new">+ Подать заявку</a> <a class="btn ghost" href="?p=calendar">📅 Календарь</a></div></div>';
$body.= $rows ? '<table class="card-view"><thead><tr><th>Код</th><th>Дата и время</th><th>Аудитория</th><th>Спикеры</th><th>Статус</th></tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">Заявок пока нет. Нажмите «Подать заявку».</div>';
layout('Кабинет заявителя',$body); exit;
}
// ========== КАБИНЕТ СПИКЕРА ==========
if($p==='speaker'){
require_role('speaker');
if(!$u['active']){ layout('Кабинет спикера','<div class="card pad"><h2>Ожидает подтверждения</h2><p class="muted">Ваша учётная запись ожидает подтверждения Фондом.</p></div>'); exit; }
$rows=speaker_lessons($u['id']);
$daily=speaker_daily_count($u['id'],date('Y-m-d'));
$weekly=speaker_weekly_count($u['id'],date('Y-m-d'));
$tr=''; foreach($rows as $r){ $mark=$r['cfd']?'<span class="pill green">в составе</span>':(st_index($r['status'])>=st_index('confirmed')?'<span class="pill gray">не выбран</span>':'<span class="pill gray">кандидат</span>'); $tr.='<tr onclick="location=\'?p=request&id='.$r['id'].'\'"><td class="id">'.esc($r['code']).'</td><td>'.esc($r['org']).'</td><td>'.dt($r).'</td><td>'.$mark.'</td><td>'.pill($r['status']).'</td></tr>'; }
$body='<div class="head"><h2>Мои уроки</h2><div><a class="btn ghost" href="?p=calendar">📅 Календарь</a></div></div>';
$body.='<div class="kpis"><div class="kpi"><div class="n">'.$daily.' / 2</div><div class="l">Уроков сегодня</div></div><div class="kpi"><div class="n">'.$weekly.' / 7</div><div class="l">Уроков на неделе</div></div><div class="kpi"><div class="n">'.count($rows).'</div><div class="l">Всего назначено</div></div></div>';
$body.= $rows ? '<table class="card-view"><thead><tr><th>Код</th><th>Организация</th><th>Дата и время</th><th>Вы</th><th>Статус</th></tr></thead><tbody>'.$tr.'</tbody></table>' : '<div class="card pad muted">Назначенных уроков пока нет.</div>';
// ========== КАЛЕНДАРЬ НЕДОСТУПНОСТИ ==========
$body.='<div class="block" style="margin-top:20px"><h4>📅 Календарь недоступности</h4>';
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
$body.='<table class="card-view"><thead><tr><th>Период</th><th>Дней</th><th>Причина</th><th></th></tr></thead><tbody>';
foreach($unavailable as $un){
$days=max(1,(strtotime($un['date_to'])-strtotime($un['date_from']))/86400+1);
$reason=esc($un['reason']?:'—');
if($un['date_to']<date('Y-m-d')) $reason=' <span class="muted">(прошло)</span> '.$reason;
$body.='<tr><td class="id" data-label="Период">'.esc($un['date_from']).' — '.esc($un['date_to']).'</td><td data-label="Дней">'.(int)$days.'</td><td data-label="Причина">'.$reason.'</td><td data-label=""><form method="post" style="display:inline" onsubmit="return confirm(\'Удалить период?\')">'.csrf_field().'<input type="hidden" name="action" value="remove_unavailable"><input type="hidden" name="uid" value="'.$un['id'].'"><button type="submit" class="btn ghost sm" style="color:#b23b2c;border-color:#f5c6c0">Удалить</button></form></td></tr>';
}
$body.='</tbody></table>';
} else {
$body.='<p class="muted" style="margin-top:10px">У вас пока нет закрытых периодов.</p>';
}
$body.='</div>';
layout('Кабинет спикера',$body); exit;
}
// ========== РЕДАКТИРОВАНИЕ ЗАЯВКИ (страница) ==========
if($p==='request_edit'){
require_role('school');
$r=get_request((int)($_GET['id']??0));
if(!$r){ layout('Заявка','<div class="card pad">Заявка не найдена. <a href="?p=school">Назад</a></div>'); exit; }
if($r['school_user_id']!=$u['id']){ layout('Заявка','<div class="card pad">Нет доступа. <a href="?p=school">Назад</a></div>'); exit; }
if(!in_array($r['status'],['new','chosen','confirmed'])){ layout('Редактирование заявки','<div class="card pad"><h2>Редактирование недоступно</h2><p class="muted">Редактирование доступно только на этапах «Новая», «Спикеры выбраны» и «Состав подтверждён Фондом».</p><p><a href="?p=request&id='.$r['id'].'">← К заявке</a></p></div>'); exit; }
$curTopic=$r['topic']??''; $topics=material_topics(); $known=in_array($curTopic,$topics,true);
$tOpt='<option value="">— выберите тему —</option>';
foreach($topics as $t) $tOpt.='<option value="'.esc($t).'"'.($curTopic===$t?' selected':'').'>'.esc($t).'</option>';
$tOpt.='<option value="__other__"'.(($curTopic!==''&&!$known)?' selected':'').'>Другая тема (указать свою)</option>';
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
$body.='<label class="lbl">Тема занятия</label><select name="topic" onchange="var b=this.form.querySelector(\'.topic-other\');b.style.display=this.value===\'__other__\'?\'block\':\'none\';">'.$tOpt.'</select>';
$body.='<input class="topic-other" name="topic_other" placeholder="Своя тема занятия" value="'.esc($known?'':$curTopic).'" style="margin-top:8px;display:'.(($curTopic!==''&&!$known)?'block':'none').'">';
$body.='<label class="lbl">Особые условия</label><textarea name="notes">'.esc($r['notes']??'').'</textarea>';
$body.='<button class="btn primary" style="margin-top:12px">Сохранить изменения</button></form>';
layout('Редактирование заявки',$body); exit;
}
// ========== РЕДАКТИРОВАНИЕ ЗАЯВКИ ФОНДОМ (страница) ==========
if($p==='fund_request_edit'){
require_role('fund');
$r=get_request((int)($_GET['id']??0));
if(!$r){ layout('Заявка','<div class="card pad">Заявка не найдена. <a href="?p=fund">Назад</a></div>'); exit; }
if(!reschedulable_status($r)){ layout('Редактирование заявки','<div class="card pad"><h2>Редактирование недоступно</h2><p class="muted">Редактирование доступно на этапах «Спикеры выбраны» и «Состав подтверждён Фондом».</p><p><a href="?p=request&id='.$r['id'].'">← К заявке</a></p></div>'); exit; }
$curTopic=$r['topic']??''; $topics=material_topics(); $known=in_array($curTopic,$topics,true);
$tOpt='<option value="">— выберите тему —</option>';
foreach($topics as $t) $tOpt.='<option value="'.esc($t).'"'.($curTopic===$t?' selected':'').'>'.esc($t).'</option>';
$tOpt.='<option value="__other__"'.(($curTopic!==''&&!$known)?' selected':'').'>Другая тема (указать свою)</option>';
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
$body.='<label class="lbl">Тема занятия</label><select name="topic" onchange="var b=this.form.querySelector(\'.topic-other\');b.style.display=this.value===\'__other__\'?\'block\':\'none\';">'.$tOpt.'</select>';
$body.='<input class="topic-other" name="topic_other" placeholder="Своя тема занятия" value="'.esc($known?'':$curTopic).'" style="margin-top:8px;display:'.(($curTopic!==''&&!$known)?'block':'none').'">';
$body.='<label class="lbl">Особые условия</label><textarea name="notes">'.esc($r['notes']??'').'</textarea>';
$body.='<button class="btn primary" style="margin-top:12px">Сохранить изменения</button></form>';
layout('Редактирование заявки',$body); exit;
}
// ========== СТРАНИЦА ЗАЯВКИ ==========
if($p==='request'){
$r=get_request((int)($_GET['id']??0)); if(!$r){ layout('Заявка','<div class="card pad">Заявка не найдена. <a href="?p='.$u['role'].'">Назад</a></div>'); exit; }
if($u['role']==='school' && $r['school_user_id']!=$u['id']) exit('Нет доступа.');
if($u['role']==='speaker' && !is_candidate($r['id'],$u['id'])) exit('Нет доступа.');
$rows=req_speaker_rows($r['id']); $confN=count_confirmed($r['id']); $need=(int)$r['speakers_needed'];
$appl=find_user_by_id($r['school_user_id']);
$cName=(($appl['contact_name']??'')!=='')?$appl['contact_name']:$r['contact_name'];
$cPos=$appl['position']??''; $cPhone=(($appl['phone']??'')!=='')?$appl['phone']:$r['phone'];
$cLine=($cName!==''?esc($cName):'—').($cPos!==''?' <span class="muted">('.esc($cPos).')</span>':'').($cPhone!==''?' · <a href="tel:'.esc(preg_replace('/[^0-9+]/','',$cPhone)).'">'.esc($cPhone).'</a>':'');
$info='<dl class="kv"><dt>Код</dt><dd class="id">'.esc($r['code']).'</dd><dt>Организация</dt><dd>'.esc($r['org']).'</dd><dt>Оператор</dt><dd>'.esc($r['operator']).'</dd><dt>Аудитория</dt><dd>'.esc(ages()[$r['age_group']]??'').'</dd><dt>Дата и время</dt><dd>'.dt($r).'</dd><dt>Нужно спикеров</dt><dd>'.$need.'</dd><dt>Контактное лицо</dt><dd>'.$cLine.'</dd>';
if(!empty($r['topic'])) $info.='<dt>Тема</dt><dd>'.esc($r['topic']).'</dd>';
if(!empty($r['notes'])) $info.='<dt>Особые условия</dt><dd style="background:#fffbea;padding:6px 10px;border-radius:6px;border-left:3px solid #C77700">'.nl2br(esc($r['notes'])).'</dd>';
$info.='</dl>';
[$turnWho,$turnAct]=whose_turn($r);
$turnMine=($u['role']==='school'&&$turnWho==='Заявитель')||($u['role']==='fund'&&$turnWho==='Фонд')||($u['role']==='operator'&&$turnWho==='Оператор');
$turnHtml=($turnAct && !in_array($r['status'],['closed','cancelled'])) ? '<div class="turn-banner '.($turnMine?'turn-you':'turn-other').'"><b>Сейчас ход за: '.esc($turnWho).'.</b> '.esc($turnAct).'</div>' : '';
$body='<div class="head"><h2>Заявка '.esc($r['code']).' '.pill($r['status']).'</h2><div><a class="btn ghost" href="?p=audit&target_type=requests&target_id='.$r['id'].'">📋 История</a> <a class="btn ghost" href="?p='.$u['role'].'">← Назад</a></div></div><div class="card pad">'.$turnHtml.$info.($r['status']==='cancelled' ? '<div class="block"><div style="background:#fdecea;color:#b23b2c;border:1px solid #f5c6c0;border-radius:8px;padding:12px"><b>Урок не состоялся.</b> Причина: '.esc($r['cancel_reason']?:'не указана').($r['cancelled_at']?' · '.esc($r['cancelled_at']):'').'</div></div>' : '<div class="block"><h4>Этапы</h4>'.stepper($r['status']).'</div>');
$listHtml='';
foreach($rows as $row){ $badge=$row['confirmed']?'<span class="pill green">в составе</span>':'<span class="pill gray">кандидат</span>'; $org=speaker_org_name($row['speaker_user_id']); $listHtml.='<div class="fbsum"><b>'.esc($row['display_name']).'</b>'.($org!==''?' <span class="muted">· '.esc($org).'</span>':'').' '.$badge; if(trim((string)$row['vet_comment'])!=='') $listHtml.='<div class="cmt">отзыв спикера: '.esc($row['vet_comment']).'</div>'; $listHtml.='</div>'; }
if($u['role']==='school'){
$body.='<div class="block" style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn ghost sm" href="?p=school&tab=new&copy='.$r['id'].'">📋 Повторить заявку</a>';
if(in_array($r['status'],['new','chosen','confirmed'])) $body.='<a class="btn ghost sm" href="?p=request_edit&id='.$r['id'].'">✏️ Редактировать</a>';
$body.='</div>';
if(in_array($r['status'],['new','chosen'])){
$free=free_speakers_for_request($r); $opt='<option value="">— выбрать свободного спикера —</option>';
foreach($free as $s){ $opt.='<option value="'.$s['id'].'">'.esc($s['display_name']).' — '.esc($s['district']?:'район не указан').'</option>'; }
$body.='<div class="block"><h4>Желаемые спикеры (нужно '.$need.', можно указать с запасом)</h4>';
if($r['status']==='chosen' && $confN<$need){
$left=$need-$confN;
$body.='<div class="hint-warn"><b>⚠ Подсказка:</b> сейчас в составе только '.$confN.' из '.$need.' спикеров. Вы можете добавить ещё '.($left>0?$left:'').', если найдутся свободные, либо Фонд подберёт замену. Если спикеров не хватит — заявка всё равно пойдёт дальше после подтверждения Фонда.</div>';
}
if($rows){ foreach($rows as $row){ $body.='<div class="row2" style="margin-bottom:6px"><span>'.esc($row['display_name']).'</span><form method="post">'.csrf_field().'<input type="hidden" name="action" value="remove_candidate"><input type="hidden" name="id" value="'.$r['id'].'"><input type="hidden" name="sid" value="'.$row['speaker_user_id'].'"><button class="btn ghost sm">Убрать</button></form></div>'; } }
else $body.='<p class="muted">Пока не выбрано ни одного спикера.</p>';
$body.= $free ? '<form method="post" style="margin-top:8px">'.csrf_field().'<input type="hidden" name="action" value="add_candidate"><input type="hidden" name="id" value="'.$r['id'].'"><select name="speaker_user_id">'.$opt.'</select> <button class="btn primary sm">Добавить спикера</button></form><form method="post" style="margin-top:8px">'.csrf_field().'<input type="hidden" name="action" value="auto_pick"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn ghost sm">✨ Подобрать спикеров автоматически (до '.$need.')</button></form><p class="muted">Показаны спикеры района <b>'.esc(request_district($r)?:'—').'</b> и соседних, свободные на '.dt($r).' (с учётом лимитов 2/день и 7/неделю, а также закрытых дат спикеров). «Подобрать автоматически» добавит свободных спикеров с лучшим рейтингом до запрошенного числа. Итоговый состав утверждает Фонд.</p>'
: '<p class="muted">Больше свободных спикеров на это время нет.</p>';
$body.='</div>';
} else {
$cl=''; foreach(req_confirmed_rows($r['id']) as $row){ $cl.='<div class="fbsum"><b>'.esc($row['display_name']).'</b> <span class="pill green">в составе</span>'.(trim((string)$row['vet_comment'])!==''?'<div class="cmt">отзыв спикера: '.esc($row['vet_comment']).'</div>':'').'</div>'; }
$body.='<div class="block"><h4>Состав ('.$confN.' из '.$need.')</h4>'.($cl?:'<p class="muted">—</p>').'</div>';
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
$body.='<div class="block"><h4>Ваш отзыв</h4><form method="post">'.csrf_field().'<input type="hidden" name="action" value="feedback"><input type="hidden" name="id" value="'.$r['id'].'"><label class="lbl">Комментарий</label><textarea name="comment" required>'.esc($r['fb_app_comment']).'</textarea><button class="btn primary sm" style="margin-top:10px">'.(trim((string)$r['fb_app_comment'])!==''?'Обновить отзыв':'Отправить отзыв').'</button></form></div>';
}
}
elseif($u['role']==='fund'){
$scope=fund_scope_ids($u);
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
if(in_array($r['status'],['new','chosen'])){ $fassign=replacement_speakers($u,$r,0);
$body.='<div style="margin-top:10px;border-top:1px solid #eee;padding-top:10px"><h4>Назначить другого спикера</h4>';
if(!$fassign) $body.='<p class="muted">Нет свободных подходящих спикеров вашей организации на эту дату.</p>';
else { $fo='<option value="">— выберите спикера —</option>'; foreach($fassign as $s){ $fo.='<option value="'.$s['id'].'">'.esc($s['display_name']).' ('.esc($s['district']?:'—').')</option>'; }
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
$body.='<div class="block"><h4>Обратная связь</h4>'.fbline('Заявитель',$r['fb_app_comment']); foreach(req_confirmed_rows($r['id']) as $row) $body.=fbline('Спикер: '.$row['display_name'],$row['vet_comment']); $body.='</div>';
$body.='<div class="block">';
if($r['status']==='prep') $body.='<p class="muted">Ожидается отметка заявителя о проведении урока.</p>';
if($r['status']==='done') $body.='<form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="close"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn primary sm">Закрыть заявку</button></form>';
if(st_index($r['status'])>0) $body.='<form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="step_back"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn ghost sm">Шаг назад</button></form>';
$body.='</div>';
}
elseif($u['role']==='speaker'){
$row=get_rs_row($r['id'],$u['id']);
if($row['confirmed']) $part='<span class="pill green">вы в составе</span>';
elseif($r['status']==='chosen') $part='<span class="pill gray">вы кандидат — ожидает решения Фонда</span>';
else $part='<span class="muted">по этой заявке в состав вы не включены</span>';
$body.='<div class="block"><h4>Ваше участие</h4><p>'.$part.'</p></div>';
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