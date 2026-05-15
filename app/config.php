<?php
/**
 * ViewPay v2.0 — Core Config, Security & Helpers
 * Developed by Jcode | Author: Obrempong
 */
defined('VP_BOOT') || die('Direct access not permitted.');

define('VP_VERSION', '2.0.0');
define('VP_AUTHOR',  'Jcode');
define('VP_CREDIT',  'Obrempong');

// Load .env
(static function(): void {
    foreach ([__DIR__.'/.env', __DIR__.'/../.env', __DIR__.'/../../.env'] as $p) {
        if (!is_readable($p)) { continue; }
        foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) { continue; }
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim(trim($v), '"\'');
        }
        break;
    }
})();

define('APP_NAME',  $_ENV['APP_NAME']  ?? 'ViewPay');
define('APP_URL',   rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'));
define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');

if (APP_DEBUG) { error_reporting(E_ALL); ini_set('display_errors','1'); }
else           { error_reporting(0);     ini_set('display_errors','0'); ini_set('log_errors','1'); }

// Session hardening
if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['lifetime'=>7200,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    session_name('_vp');
    session_start();
    if (!isset($_SESSION['_born']))              { $_SESSION['_born'] = time(); }
    elseif (time()-$_SESSION['_born'] > 1800)   { session_regenerate_id(true); $_SESSION['_born']=time(); }
}

// PDO singleton
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $h=$_ENV['DB_HOST']?? 'localhost'; $p=$_ENV['DB_PORT']?? '3306';
    $n=$_ENV['DB_DATABASE']?? ''; $u=$_ENV['DB_USERNAME']?? ''; $pw=$_ENV['DB_PASSWORD']?? '';
    if (!$n) { http_response_code(503); die('<p>ViewPay not configured. <a href="/install.php">Install</a></p>'); }
    try {
        $pdo = new PDO("mysql:host=$h;port=$p;dbname=$n;charset=utf8mb4",$u,$pw,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_TIMEOUT=>10,
        ]);
    } catch (PDOException $e) {
        error_log('[VP] DB: '.$e->getMessage());
        if (APP_DEBUG) die('<pre>DB: '.htmlspecialchars($e->getMessage()).'</pre>');
        http_response_code(503); die('<p>Database error. Check .env</p>');
    }
    return $pdo;
}

// Settings cache
function setting(string $k, string $d=''): string {
    static $c=[];
    if (array_key_exists($k,$c)) return $c[$k];
    try { $s=db()->prepare('SELECT `value` FROM settings WHERE `key`=? LIMIT 1'); $s->execute([$k]); $r=$s->fetch(); $c[$k]=($r&&$r['value']!==null)?(string)$r['value']:$d; }
    catch(Exception $e){ $c[$k]=$d; }
    return $c[$k];
}
function setting_set(string $k,string $v,string $g='general'): void {
    db()->prepare("INSERT INTO settings(`key`,`value`,`group`) VALUES(?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$k,$v,$g]);
}

// Auth
function auth(): ?array {
    if (empty($_SESSION['_uid'])) return null;
    static $u=null;
    if ($u!==null) return $u;
    try { $s=db()->prepare("SELECT * FROM users WHERE id=? AND status='active' AND deleted_at IS NULL LIMIT 1"); $s->execute([(int)$_SESSION['_uid']]); $u=$s->fetch()?:null; } catch(Exception $e){ $u=null; }
    return $u;
}
function is_admin(): bool { $u=auth(); return (bool)($u&&!empty($u['is_admin'])); }
function is_moderator(): bool { $u=auth(); return (bool)($u&&(!empty($u['is_admin'])||($u['role']??'')==='moderator')); }
function require_auth(): void { if(!auth()){ flash('info','Please sign in.'); redirect('/login'); } }
function require_admin(): void { require_auth(); if(!is_admin()){ http_response_code(403); die('<p>Admin access required.</p>'); } }
function require_moderator(): void { require_auth(); if(!is_moderator()){ http_response_code(403); die('<p>Moderator access required.</p>'); } }

function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['_uid']=$_SESSION['_born']=(int)$u['id'];
    $_SESSION['_born']=time();
    try { db()->prepare("UPDATE users SET last_seen_at=?,last_ip=?,updated_at=? WHERE id=?")->execute([now_ts(),vp_ip(),now_ts(),(int)$u['id']]); } catch(Exception $e){}
}
function logout_user(): void {
    $_SESSION=[];
    if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
    session_destroy();
}

// CSRF
function csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(24)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { $t=$_POST['_csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??''; if(!$t||!hash_equals(csrf_token(),$t)){ http_response_code(403); die('CSRF mismatch. <a href="javascript:history.back()">Back</a>'); } }

// Rate limit
function rate_limit(string $key,int $max,int $window=60): bool {
    $sk='_rl_'.md5($key); $now=time();
    if(!isset($_SESSION[$sk])||$now>$_SESSION[$sk]['reset']) $_SESSION[$sk]=['count'=>0,'reset'=>$now+$window];
    return ++$_SESSION[$sk]['count']<=$max;
}

// Audit log
function audit(string $action,string $desc='',?int $uid=null,?string $etype=null,?int $eid=null): void {
    try {
        $u=$uid??(auth()?(int)auth()['id']:null);
        db()->prepare("INSERT INTO audit_logs(user_id,action,description,entity_type,entity_id,ip_address,user_agent,created_at) VALUES(?,?,?,?,?,?,?,?)")
            ->execute([$u,$action,$desc,$etype,$eid,vp_ip(),mb_substr($_SERVER['HTTP_USER_AGENT']??'',0,250),now_ts()]);
    } catch(Exception $e){ error_log('[VP] Audit: '.$e->getMessage()); }
}

// Email verification
function send_verification_email(array $user): bool {
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    try {
        db()->prepare("DELETE FROM email_verifications WHERE user_id=?")->execute([$user['id']]);
        db()->prepare("INSERT INTO email_verifications(user_id,token,expires_at,created_at) VALUES(?,?,?,?)")
            ->execute([$user['id'], $hash, date('Y-m-d H:i:s', strtotime('+24 hours')), now_ts()]);
    } catch(Exception $e) { return false; }
    $link = url('verify-email?t='.urlencode($token).'&uid='.(int)$user['id']);
    $site = setting('site_name', APP_NAME);
    $from = setting('mail_from', setting('site_email','noreply@viewpay.com'));
    $pri  = setting('primary_color','#6366f1');
    $body = "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#f5f5fa;padding:30px'>"
          . "<div style='max-width:500px;margin:0 auto;background:#fff;border-radius:12px;padding:36px;box-shadow:0 2px 12px rgba(0,0,0,.08)'>"
          . "<div style='text-align:center;margin-bottom:24px'>"
          . "<h2 style='color:#111'>Verify Your ".e($site)." Account</h2></div>"
          . "<p style='color:#555'>Hello ".e($user['name']).",</p>"
          . "<p style='color:#555'>Click the button below to verify your email and start earning.</p>"
          . "<div style='text-align:center;margin:28px 0'>"
          . "<a href='".$link."' style='background:{$pri};color:#fff;padding:13px 32px;border-radius:8px;text-decoration:none;font-weight:700'>Verify Email</a>"
          . "</div><p style='color:#999;font-size:13px'>Link expires in 24 hours.</p>"
          . "<hr style='border:none;border-top:1px solid #eee;margin-top:20px'>"
          . "<p style='color:#bbb;font-size:12px'>".e($site)." · Developed by Jcode</p>"
          . "</div></body></html>";
    $headers  = "From: {$site} <{$from}>\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    return @mail($user['email'], 'Verify your '.$site.' account', $body, $headers);
}

function send_password_reset_email(array $user, string $token): bool {
    $link = url('reset-password?token='.$token.'&email='.urlencode($user['email']));
    $site = setting('site_name', APP_NAME);
    $from = setting('mail_from', setting('site_email','noreply@viewpay.com'));
    $pri  = setting('primary_color','#6366f1');
    $body = "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#f5f5fa;padding:30px'>"
          . "<div style='max-width:500px;margin:0 auto;background:#fff;border-radius:12px;padding:36px;box-shadow:0 2px 12px rgba(0,0,0,.08)'>"
          . "<div style='text-align:center;margin-bottom:24px'>"
          . "<h2 style='color:#111'>Reset Password</h2></div>"
          . "<p style='color:#555'>Hello ".e($user['name']).",</p>"
          . "<p style='color:#555'>You are receiving this email because we received a password reset request for your account.</p>"
          . "<div style='text-align:center;margin:28px 0'>"
          . "<a href='".$link."' style='background:{$pri};color:#fff;padding:13px 32px;border-radius:8px;text-decoration:none;font-weight:700'>Reset Password</a>"
          . "</div><p style='color:#999;font-size:13px'>If you did not request a password reset, no further action is required. Link expires in 1 hour.</p>"
          . "<hr style='border:none;border-top:1px solid #eee;margin-top:20px'>"
          . "<p style='color:#bbb;font-size:12px'>".e($site)." · Developed by Jcode</p>"
          . "</div></body></html>";
    $headers  = "From: {$site} <{$from}>\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    return @mail($user['email'], 'Reset your '.$site.' password', $body, $headers);
}

// Currency
function get_currency(): array {
    static $c=null;
    if ($c) return $c;
    $r=(float)(setting('usd_exchange_rate','14.5')?:'14.5');
    $c=['code'=>setting('currency_code','GHS'),'symbol'=>setting('currency_symbol','₵'),
        'name'=>setting('currency_name','Ghanaian Cedi'),'rate'=>max(0.0001,$r),'position'=>setting('currency_position','before')];
    return $c;
}
function money(float $usd,int $dec=2): string {
    $c=get_currency(); $v=number_format($usd*$c['rate'],$dec);
    return $c['position']==='after'?$v.$c['symbol']:$c['symbol'].$v;
}

// Ad time slots
function ad_current_period(): string {
    $tz=setting('timezone','UTC');
    $h=(int)(new DateTime('now',new DateTimeZone($tz)))->format('G');
    if ($h>=6&&$h<12) return 'morning';
    if ($h>=12&&$h<18) return 'afternoon';
    return 'night';
}
function ad_period_limit(string $period): int {
    return (int)(setting("ad_{$period}_limit",match($period){'morning'=>'10','afternoon'=>'10',default=>'15'}));
}
function ad_period_reward(string $period): float {
    return (float)(setting("ad_{$period}_reward",match($period){'morning'=>'0.002','afternoon'=>'0.002',default=>'0.003'}));
}
function ad_watches_period(int $uid, string $period): int {
    try {
        $tz=setting('timezone','UTC'); $today=(new DateTime('now',new DateTimeZone($tz)))->format('Y-m-d');
        $s=db()->prepare("SELECT COUNT(*) FROM ad_watches WHERE user_id=? AND period=? AND DATE(watched_at)=?");
        $s->execute([$uid,$period,$today]); return (int)$s->fetchColumn();
    } catch(Exception $e){ return 0; }
}
function ad_can_watch(int $uid): array {
    $period=ad_current_period(); $limit=ad_period_limit($period); $watched=ad_watches_period($uid,$period);
    return ['can'=>$watched<$limit,'period'=>$period,'limit'=>$limit,'watched'=>$watched,'left'=>max(0,$limit-$watched),'reward'=>ad_period_reward($period)];
}

// Utilities
function redirect(string $path): never { header('Location: '.APP_URL.'/'.ltrim($path,'/')); exit; }
function url(string $path=''): string  { return APP_URL.'/'.ltrim($path,'/'); }
function e(mixed $v): string           { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function flash(string $t,string $m): void     { $_SESSION['_flash']=compact('t','m'); $_SESSION['_flash']=['type'=>$t,'msg'=>$m]; }
function get_flash(): ?array           { $f=$_SESSION['_flash']??null; unset($_SESSION['_flash']); return $f; }
function now_ts(): string              { return date('Y-m-d H:i:s'); }
function hash_pw(string $pw): string   { return password_hash($pw,PASSWORD_BCRYPT,['cost'=>12]); }
function verify_pw(string $pw,string $h): bool { return password_verify($pw,$h); }
function gen_ref(string $pfx='REF'): string { return $pfx.'-'.strtoupper(bin2hex(random_bytes(6))); }
function vp_ip(): string {
    foreach(['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k){
        if(!empty($_SERVER[$k])){ $ip=filter_var(explode(',',$_SERVER[$k])[0],FILTER_VALIDATE_IP); if($ip) return $ip; }
    }
    return '0.0.0.0';
}
function vp_device_fp(): string { return substr(md5(($_SERVER['HTTP_USER_AGENT']??'').($_SERVER['HTTP_ACCEPT_LANGUAGE']??'')),0,32); }
function avatar_url(?string $file,string $name=''): string {
    if($file&&file_exists(__DIR__.'/uploads/'.$file)) return url('uploads/'.$file);
    return 'https://ui-avatars.com/api/?name='.urlencode($name?:'User').'&background=6366f1&color=fff&size=80&bold=true&format=svg';
}
function handle_upload(string $field,array $allowed=['jpg','jpeg','png','gif','webp','svg']): string|false {
    if(empty($_FILES[$field])||$_FILES[$field]['error']!==UPLOAD_ERR_OK) return false;
    $file=$_FILES[$field]; if($file['size']>5*1024*1024) return false;
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,$allowed,true)) return false;
    if(in_array($ext,['jpg','jpeg','png','gif','webp'],true)&&!@getimagesize($file['tmp_name'])) return false;
    $dir=__DIR__.'/uploads/'; if(!is_dir($dir)) mkdir($dir,0755,true);
    $name=bin2hex(random_bytes(16)).'.'.$ext;
    return move_uploaded_file($file['tmp_name'],$dir.$name)?$name:false;
}
function paginate(int $total,int $per,int $page,string $base): string {
    $pages=(int)ceil($total/$per); if($pages<=1) return '';
    $sep=str_contains($base,'?')?'&':'?';
    $h='<nav><ul class="pagination justify-content-center mb-0 flex-wrap">';
    if($page>1) $h.='<li class="page-item"><a class="page-link" href="'.$base.$sep.'page='.($page-1).'">‹</a></li>';
    for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++) $h.='<li class="page-item'.($i===$page?' active':'').'"><a class="page-link" href="'.$base.$sep.'page='.$i.'">'.$i.'</a></li>';
    if($page<$pages) $h.='<li class="page-item"><a class="page-link" href="'.$base.$sep.'page='.($page+1).'">›</a></li>';
    return $h.'</ul></nav>';
}
if(!headers_sent()){ header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN'); header('X-XSS-Protection: 1; mode=block'); header('Referrer-Policy: strict-origin-when-cross-origin'); header_remove('X-Powered-By'); }
