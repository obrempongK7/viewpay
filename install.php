<?php
/**
 * ViewPay v2.0 — Installer
 * Developed by Jcode | Author: Obrempong
 * DELETE this file immediately after installation.
 */
session_start();
define('VP_VER', '2.0.0');

function vp_env_path(): string {
    foreach ([__DIR__.'/.env',__DIR__.'/../.env',__DIR__.'/../../.env'] as $p)
        if (is_writable(dirname($p))) return $p;
    return __DIR__.'/.env';
}
function vp_write_env(array $kv): bool {
    $lines=[];
    foreach ($kv as $k=>$v){ $v=(string)$v; if ($v!==''&&preg_match('/[\s#"\'\\\\]/',$v)) $v='"'.addslashes($v).'"'; $lines[]="$k=$v"; }
    return file_put_contents(vp_env_path(),implode("\n",$lines)."\n")!==false;
}
function vp_db(string $h,string $p,string $db,string $u,string $pw): PDO {
    return new PDO("mysql:host=$h;port=$p;dbname=$db;charset=utf8mb4",$u,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function vp_hash(string $pw): string { return password_hash($pw,PASSWORD_BCRYPT,['cost'=>12]); }
function vp_ref(): string  { return strtoupper(substr(md5(uniqid('',true)),0,8)); }
function vp_now(): string  { return date('Y-m-d H:i:s'); }
function vp_check(): array {
    return [
        ['PHP 8.1+',    version_compare(PHP_VERSION,'8.1.0','>='), PHP_VERSION,                  true],
        ['PDO MySQL',   extension_loaded('pdo_mysql'),             extension_loaded('pdo_mysql') ?'OK':'Missing', true],
        ['OpenSSL',     extension_loaded('openssl'),               extension_loaded('openssl')   ?'OK':'Missing', true],
        ['Mbstring',    extension_loaded('mbstring'),              extension_loaded('mbstring')  ?'OK':'Missing', true],
        ['JSON',        extension_loaded('json'),                  extension_loaded('json')      ?'OK':'Missing', true],
        ['cURL',        extension_loaded('curl'),                  extension_loaded('curl')      ?'OK':'Optional', false],
        ['.env writable',is_writable(dirname(vp_env_path())),      is_writable(dirname(vp_env_path()))?'OK':'Check permissions', false],
    ];
}

// Router
$errors=[];
$step=(int)($_GET['step']??$_SESSION['vs_step']??1);
$d=&$_SESSION['vs_d'];
if(!is_array($d)) $d=[];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $ps=(int)($_POST['ps']??0);
    foreach ($_POST as $k=>$v) if (!in_array($k,['ps','admin_pw','admin_pw2'])) $d[$k]=trim((string)$v);

    if ($ps===1) { $_SESSION['vs_step']=2; header('Location: install.php?step=2'); exit; }

    if ($ps===2) {
        $d['db_pass']=$_POST['db_pass']??'';
        if (empty($d['db_name'])||empty($d['db_user'])) { $errors[]='Database name and username required.'; $step=2; }
        else {
            try { $t=vp_db($d['db_host']??'localhost',$d['db_port']??'3306',$d['db_name'],$d['db_user'],$d['db_pass']); unset($t); $_SESSION['vs_db_ok']=true; $_SESSION['vs_step']=3; header('Location: install.php?step=3'); exit; }
            catch (Exception $e) { $errors[]='Connection failed: '.$e->getMessage(); $step=2; }
        }
    }

    if ($ps===3) {
        if (empty($d['site_name'])||empty($d['app_url'])) { $errors[]='Site name and URL required.'; $step=3; }
        else { $_SESSION['vs_step']=4; header('Location: install.php?step=4'); exit; }
    }

    if ($ps===4) {
        $ae=trim($d['admin_email']??''); $ap=$_POST['admin_pw']??''; $ap2=$_POST['admin_pw2']??'';
        if (!filter_var($ae,FILTER_VALIDATE_EMAIL)) { $errors[]='Valid email required.'; $step=4; }
        elseif (strlen($ap)<8) { $errors[]='Password must be 8+ characters.'; $step=4; }
        elseif ($ap!==$ap2)    { $errors[]='Passwords do not match.'; $step=4; }
        else { $_SESSION['vs_admin_email']=$ae; $_SESSION['vs_admin_pw']=$ap; $_SESSION['vs_admin_name']=trim($d['admin_name']??'Admin'); $_SESSION['vs_step']=5; header('Location: install.php?step=5'); exit; }
    }

    if ($ps===5) {
        if (empty($_SESSION['vs_db_ok'])||empty($_SESSION['vs_admin_email'])) { $errors[]='Session expired.'; $step=1; }
        else {
            $r=vp_run_install($d,$_SESSION);
            if ($r===true) { $_SESSION['vs_done']=true; header('Location: install.php?step=6'); exit; }
            else { $errors[]=$r; $step=5; }
        }
    }
}

if ($step>2&&empty($_SESSION['vs_db_ok']))      $step=2;
if ($step>4&&empty($_SESSION['vs_admin_email'])) $step=4;
if ($step===6&&empty($_SESSION['vs_done']))      $step=5;

function vp_run_install(array &$d, array &$s): string|bool {
    try { $pdo=vp_db($d['db_host'],$d['db_port'],$d['db_name'],$d['db_user'],$d['db_pass']); }
    catch (Exception $e) { return 'DB Error: '.$e->getMessage(); }
    try { vp_create_tables($pdo); }  catch (Exception $e) { return 'Migration Error: '.$e->getMessage(); }
    try { vp_seed_data($pdo,$d,$s); } catch (Exception $e) { return 'Seed Error: '.$e->getMessage(); }
    $key='base64:'.base64_encode(random_bytes(32));
    $env=['APP_NAME'=>$d['site_name']??'ViewPay','APP_ENV'=>'production','APP_KEY'=>$key,'APP_DEBUG'=>'false','APP_URL'=>rtrim($d['app_url']??'','/'),'DB_HOST'=>$d['db_host']??'127.0.0.1','DB_PORT'=>$d['db_port']??'3306','DB_DATABASE'=>$d['db_name']??'','DB_USERNAME'=>$d['db_user']??'','DB_PASSWORD'=>$d['db_pass']??''];
    if (!vp_write_env($env)) { $_SESSION['vs_env_warn']=true; $_SESSION['vs_env_txt']=implode("\n",array_map(fn($k,$v)=>"$k=$v",array_keys($env),array_values($env))); }
    $s['vs_app_key']=$key;
    return true;
}

function vp_create_tables(PDO $pdo): void {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    foreach ([
"CREATE TABLE IF NOT EXISTS `users`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`name` VARCHAR(100) NOT NULL,`username` VARCHAR(60) UNIQUE NULL,`email` VARCHAR(191) UNIQUE NOT NULL,`email_verified_at` TIMESTAMP NULL,`password` VARCHAR(255) NOT NULL,`avatar` VARCHAR(255) NULL,`phone` VARCHAR(30) NULL,`country` VARCHAR(100) NULL,`timezone` VARCHAR(60) DEFAULT 'UTC',`role` VARCHAR(20) DEFAULT 'user',`status` VARCHAR(20) DEFAULT 'active',`device_fingerprint` VARCHAR(64) NULL,`last_ip` VARCHAR(45) NULL,`register_ip` VARCHAR(45) NULL,`is_admin` TINYINT(1) DEFAULT 0,`wallet_balance` DECIMAL(18,8) DEFAULT 0,`total_earned` DECIMAL(18,8) DEFAULT 0,`total_deposited` DECIMAL(18,8) DEFAULT 0,`total_withdrawn` DECIMAL(18,8) DEFAULT 0,`total_tasks_completed` INT DEFAULT 0,`referral_code` VARCHAR(20) UNIQUE NULL,`referred_by` BIGINT UNSIGNED NULL,`last_seen_at` TIMESTAMP NULL,`last_task_at` TIMESTAMP NULL,`remember_token` VARCHAR(100) NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,`deleted_at` TIMESTAMP NULL,INDEX `idx_status`(`status`),INDEX `idx_ip`(`last_ip`),INDEX `idx_fp`(`device_fingerprint`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `settings`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`key` VARCHAR(100) UNIQUE NOT NULL,`value` TEXT NULL,`group` VARCHAR(50) DEFAULT 'general',`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `packages`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`name` VARCHAR(100) NOT NULL,`description` TEXT NULL,`type` VARCHAR(20) DEFAULT 'video',`price` DECIMAL(12,2) NOT NULL,`views_count` INT NOT NULL DEFAULT 0,`duration_days` INT DEFAULT 30,`cost_per_view` DECIMAL(10,6) DEFAULT 0,`features` JSON NULL,`badge` VARCHAR(50) NULL,`color` VARCHAR(20) NULL,`sort_order` INT DEFAULT 0,`is_active` TINYINT(1) DEFAULT 1,`is_featured` TINYINT(1) DEFAULT 0,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `tasks`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` BIGINT UNSIGNED NOT NULL,`package_id` BIGINT UNSIGNED NULL,`title` VARCHAR(255) NOT NULL,`description` TEXT NULL,`type` VARCHAR(20) DEFAULT 'video',`url` TEXT NOT NULL,`thumbnail` VARCHAR(255) NULL,`total_views_purchased` INT DEFAULT 0,`views_remaining` INT DEFAULT 0,`views_completed` INT DEFAULT 0,`min_watch_seconds` INT DEFAULT 30,`reward_per_view` DECIMAL(10,6) DEFAULT 0,`amount_paid` DECIMAL(12,2) DEFAULT 0,`status` VARCHAR(30) DEFAULT 'pending',`show_ads_before` TINYINT(1) DEFAULT 1,`is_visible` TINYINT(1) DEFAULT 1,`expires_at` TIMESTAMP NULL,`rejection_reason` TEXT NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,`deleted_at` TIMESTAMP NULL,INDEX `idx_status`(`status`,`type`),FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `task_views`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`task_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`status` VARCHAR(20) DEFAULT 'pending',`seconds_watched` INT DEFAULT 0,`reward_earned` DECIMAL(10,6) DEFAULT 0,`ip_address` VARCHAR(45) NULL,`device_fingerprint` VARCHAR(64) NULL,`started_at` TIMESTAMP NULL,`completed_at` TIMESTAMP NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY `uniq_task_user`(`task_id`,`user_id`),FOREIGN KEY(`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `ad_watches`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` BIGINT UNSIGNED NOT NULL,`period` ENUM('morning','afternoon','night') NOT NULL,`ad_network` VARCHAR(30) DEFAULT 'custom',`reward_earned` DECIMAL(10,8) DEFAULT 0,`ip_address` VARCHAR(45) NULL,`watched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX `idx_user_date`(`user_id`,`watched_at`),FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `email_verifications`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` BIGINT UNSIGNED NOT NULL,`token` VARCHAR(64) NOT NULL,`expires_at` TIMESTAMP NOT NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY `uniq_user`(`user_id`),FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `audit_logs`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` BIGINT UNSIGNED NULL,`action` VARCHAR(100) NOT NULL,`description` TEXT NULL,`entity_type` VARCHAR(50) NULL,`entity_id` BIGINT UNSIGNED NULL,`ip_address` VARCHAR(45) NULL,`user_agent` VARCHAR(300) NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX `idx_action`(`action`),INDEX `idx_user`(`user_id`),INDEX `idx_date`(`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `transactions`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` BIGINT UNSIGNED NOT NULL,`type` VARCHAR(30) NOT NULL,`amount` DECIMAL(18,8) NOT NULL,`fee` DECIMAL(18,8) DEFAULT 0,`net_amount` DECIMAL(18,8) NOT NULL,`currency` VARCHAR(10) DEFAULT 'GHS',`status` VARCHAR(20) DEFAULT 'pending',`gateway` VARCHAR(30) NULL,`gateway_transaction_id` VARCHAR(255) NULL,`note` TEXT NULL,`reference` VARCHAR(100) UNIQUE NOT NULL,`processed_at` TIMESTAMP NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `deposits`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` BIGINT UNSIGNED NOT NULL,`package_id` BIGINT UNSIGNED NULL,`amount` DECIMAL(12,2) NOT NULL,`fee` DECIMAL(12,2) DEFAULT 0,`currency` VARCHAR(10) DEFAULT 'GHS',`gateway` VARCHAR(30) NOT NULL,`gateway_transaction_id` VARCHAR(255) NULL,`status` VARCHAR(20) DEFAULT 'pending',`gateway_response` JSON NULL,`purpose` VARCHAR(30) DEFAULT 'package',`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `withdrawals`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` BIGINT UNSIGNED NOT NULL,`amount` DECIMAL(18,8) NOT NULL,`fee` DECIMAL(18,8) DEFAULT 0,`net_amount` DECIMAL(18,8) NOT NULL,`gateway` VARCHAR(30) NOT NULL,`payout_details` JSON NOT NULL,`status` VARCHAR(20) DEFAULT 'pending',`admin_note` TEXT NULL,`processed_by` BIGINT UNSIGNED NULL,`processed_at` TIMESTAMP NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `advertisements`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`title` VARCHAR(200) NOT NULL,`type` VARCHAR(30) NOT NULL,`position` VARCHAR(30) NOT NULL,`content` TEXT NULL,`target_url` VARCHAR(500) NULL,`duration_seconds` INT DEFAULT 5,`is_skippable` TINYINT(1) DEFAULT 1,`is_active` TINYINT(1) DEFAULT 1,`impressions` INT DEFAULT 0,`clicks` INT DEFAULT 0,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `pages`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`title` VARCHAR(200) NOT NULL,`slug` VARCHAR(191) UNIQUE NOT NULL,`content` LONGTEXT NOT NULL,`meta_title` VARCHAR(200) NULL,`meta_description` TEXT NULL,`is_active` TINYINT(1) DEFAULT 1,`show_in_footer` TINYINT(1) DEFAULT 0,`show_in_header` TINYINT(1) DEFAULT 0,`sort_order` INT DEFAULT 0,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `fraud_logs`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` BIGINT UNSIGNED NULL,`type` VARCHAR(50) NOT NULL,`ip_address` VARCHAR(45) NULL,`device_fingerprint` VARCHAR(64) NULL,`details` TEXT NULL,`action_taken` VARCHAR(100) NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `referrals`(`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`referrer_id` BIGINT UNSIGNED NOT NULL,`referee_id` BIGINT UNSIGNED NOT NULL,`commission_earned` DECIMAL(10,6) DEFAULT 0,`status` VARCHAR(20) DEFAULT 'active',`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,FOREIGN KEY(`referee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `password_reset_tokens`(`email` VARCHAR(191) PRIMARY KEY,`token` VARCHAR(255) NOT NULL,`created_at` TIMESTAMP NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS `cache`(`key` VARCHAR(255) PRIMARY KEY,`value` MEDIUMTEXT NOT NULL,`expiration` INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ] as $sql) $pdo->exec($sql);
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
}

function vp_seed_data(PDO $pdo, array &$d, array &$s): void {
    $now=$n=vp_now(); $name=$d['site_name']??'ViewPay'; $email=$d['admin_email']??'admin@viewpay.com'; $ip=$_SERVER['REMOTE_ADDR']??'127.0.0.1';

    // SETTINGS — 3 cols, 3 ?s, 3 params ✓
    $st=$pdo->prepare("INSERT INTO `settings`(`key`,`value`,`group`) VALUES(?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    foreach ([
        ['site_name',$name,'general'],['site_description','Earn money watching videos and visiting websites.','general'],['site_email',$email,'general'],['support_email',$email,'general'],['support_whatsapp','','general'],['timezone','UTC','general'],
        ['primary_color','#6366f1','appearance'],['logo','','appearance'],['favicon','','appearance'],
        ['pwa_splash_enabled','1','pwa'],['pwa_splash_bg','#6366f1','pwa'],['pwa_splash_text',$name,'pwa'],['pwa_theme_color','#6366f1','pwa'],['pwa_app_name',$name,'pwa'],['pwa_short_name','ViewPay','pwa'],
        ['currency_code','GHS','currency'],['currency_symbol','₵','currency'],['currency_name','Ghanaian Cedi','currency'],['usd_exchange_rate','14.5','currency'],['currency_position','before','currency'],
        ['reward_per_video_view','0.005','rewards'],['reward_per_website_visit','0.003','rewards'],['min_watch_seconds','30','rewards'],['min_withdrawal','5.00','rewards'],['max_withdrawal','500.00','rewards'],['withdrawal_fee_percent','3','rewards'],['referral_commission_percent','5','rewards'],
        ['max_accounts_per_ip','1','security'],['max_accounts_per_device','1','security'],['max_views_per_day','50','security'],
        ['email_verification_required','0','email'],
        ['momo_enabled','1','payments'],['paystack_enabled','1','payments'],['bank_transfer_enabled','1','payments'],['paypal_enabled','0','payments'],['stripe_enabled','0','payments'],['other_enabled','0','payments'],
        ['momo_number','','payments'],['momo_name','','payments'],['momo_provider','MTN MoMo','payments'],['bank_name','','payments'],['bank_account','','payments'],['bank_holder','','payments'],['bank_branch','','payments'],['paypal_email','','payments'],['paystack_public_key','','payments'],['paystack_secret_key','','payments'],['paystack_payment_link','','payments'],
        ['payout_paypal_enabled','1','payouts'],['payout_stripe_enabled','0','payouts'],['payout_skrill_enabled','0','payouts'],['payout_cashapp_enabled','0','payouts'],['payout_zelle_enabled','0','payouts'],['payout_bank_enabled','1','payouts'],
        ['ad_watching_enabled','1','ads'],['ad_network','custom','ads'],['ad_watch_code','','ads'],['ad_min_watch_seconds','10','ads'],['ad_morning_limit','10','ads'],['ad_afternoon_limit','10','ads'],['ad_night_limit','15','ads'],['ad_morning_reward','0.002','ads'],['ad_afternoon_reward','0.002','ads'],['ad_night_reward','0.003','ads'],
        ['google_login_enabled','0','social'],['facebook_login_enabled','0','social'],
        ['meta_title',$name.' — Earn Money Watching Videos','seo'],['meta_description','Join '.$name.' and earn real money watching videos and visiting websites.','seo'],['google_analytics_id','','seo'],['robots_meta','index,follow','seo'],['adsense_enabled','0','ads'],['adsense_client','','ads'],['adsense_slot','','ads'],
    ] as [$k,$v,$g]) $st->execute([$k,$v,$g]); // 3 params ✓

    // ADMIN USER — 13 cols, 13 ?s, 13 params ✓
    $pdo->prepare("INSERT IGNORE INTO `users`(`name`,`username`,`email`,`password`,`role`,`is_admin`,`status`,`email_verified_at`,`referral_code`,`register_ip`,`last_ip`,`created_at`,`updated_at`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$s['vs_admin_name']??'Admin','admin',$email,vp_hash($s['vs_admin_pw']??'Admin@12345'),'admin',1,'active',$now,vp_ref(),$ip,$ip,$now,$now]);

    // PACKAGES — 15 cols, 15 ?s, 15 params ✓
    $pkgQ=$pdo->prepare("INSERT IGNORE INTO `packages`(`name`,`description`,`type`,`price`,`views_count`,`duration_days`,`cost_per_view`,`features`,`badge`,`color`,`sort_order`,`is_active`,`is_featured`,`updated_at`,`created_at`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ([
        ['Starter Video','Perfect for new creators.','video',5.00,500,30,0.005,json_encode(['500 video views','30-day campaign','Basic analytics']),null,'#6366f1',1,1,0],
        ['Growth Video','Grow your channel fast.','video',15.00,2000,30,0.0045,json_encode(['2,000 video views','30-day campaign','Full analytics']),'Popular','#10b981',2,1,1],
        ['Pro Video','For serious creators.','video',35.00,5000,45,0.004,json_encode(['5,000 views','45-day campaign','Advanced analytics']),'','#f59e0b',3,1,0],
        ['Elite Video','Maximum exposure.','video',80.00,15000,60,0.0038,json_encode(['15,000 views','60-day campaign','Account manager']),'Best Value','#ef4444',4,1,1],
        ['Mega Video','Enterprise promo.','video',200.00,50000,90,0.003,json_encode(['50,000 views','90-day campaign']),'Enterprise','#8b5cf6',5,1,0],
        ['Blog Starter','First visitors.','website',4.00,500,30,0.003,json_encode(['500 website visits','30-day campaign']),'','#06b6d4',6,1,0],
        ['Blog Growth','Scale your blog.','website',12.00,2000,30,0.003,json_encode(['2,000 visits','30-day campaign']),'Popular','#10b981',7,1,1],
        ['Ultimate Bundle','Video + Website.','both',50.00,10000,60,0.0035,json_encode(['10,000 combined','60-day campaign']),'Best Deal','#6366f1',8,1,1],
    ] as $p) $pkgQ->execute([$p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$p[7],$p[8],$p[9],$p[10],$p[11],$p[12],$now,$now]); // 15 ✓

    // PAGES — 9 cols, 9 ?s, 9 params ✓
    $pgQ=$pdo->prepare("INSERT IGNORE INTO `pages`(`title`,`slug`,`content`,`show_in_footer`,`show_in_header`,`sort_order`,`is_active`,`created_at`,`updated_at`) VALUES(?,?,?,?,?,?,?,?,?)");
    foreach ([
        ['About Us','about',"<h2>About {$name}</h2><p>{$name} connects creators with real audiences while rewarding viewers for their attention.</p>",1,0,1],
        ['Privacy Policy','privacy',"<h2>Privacy Policy</h2><p>Your privacy matters. We collect only what is necessary to operate {$name} securely.</p>",1,0,2],
        ['Terms of Service','terms',"<h2>Terms of Service</h2><p>By using {$name} you agree to these terms. Bots and automation are strictly prohibited.</p>",1,0,3],
        ['FAQ','faq',"<h2>FAQ</h2><h4>How do I earn?</h4><p>Complete tasks and watch ads. Click Claim to receive rewards.</p><h4>How do I withdraw?</h4><p>Go to Wallet and submit a withdrawal request. Admin processes within 24-72 hours.</p>",1,1,4],
        ['Contact Us','contact',"<h2>Contact Us</h2><p><strong>Email:</strong> {$email}</p><p>We respond within 24 hours on business days.</p>",1,0,5],
    ] as [$t,$sl,$c,$f,$h,$o]) $pgQ->execute([$t,$sl,$c,$f,$h,$o,1,$now,$now]); // 9 ✓

    // AD — 9 cols, 9 ?s, 9 params ✓
    $pdo->prepare("INSERT IGNORE INTO `advertisements`(`title`,`type`,`position`,`content`,`duration_seconds`,`is_skippable`,`is_active`,`created_at`,`updated_at`) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute(['Welcome Ad','custom_html','before_task',"<div style='text-align:center;padding:30px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border-radius:12px'><h3>Welcome to {$name}</h3><p>Earn money watching videos and ads!</p></div>",5,1,1,$now,$now]); // 9 ✓
}

// UI
$reqs=$checks=vp_check();
$critOk=!in_array(false,array_map(fn($r)=>$r[3]?$r[1]:true,$reqs));
$fD=$d??[];
$sNames=[1=>'Requirements',2=>'Database',3=>'Site Config',4=>'Admin Account',5=>'Install',6=>'Done'];
$pct=[1=>10,2=>30,3=>52,4=>72,5=>88,6=>100];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ViewPay v<?= VP_VER ?> Installer</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--vp:#6366f1}
body{font-family:'Inter',sans-serif;background:#f3f4f8;min-height:100vh}
.sidebar{background:#0d0d12;min-height:100vh;width:230px;position:sticky;top:0;height:100vh;overflow-y:auto;flex-shrink:0}
.si{display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:8px;color:rgba(255,255,255,.55);font-size:.82rem;font-weight:500;margin-bottom:2px}
.si.done{color:#10b981}.si.active{color:#fff;background:var(--vp)}
.sn{width:21px;height:21px;border-radius:50%;border:2px solid currentColor;display:grid;place-items:center;font-size:.68rem;flex-shrink:0}
.si.done .sn{background:#10b981;border-color:#10b981;color:#fff}
.pbar{height:4px;background:#e5e7eb;border-radius:99px;overflow:hidden;margin-bottom:28px}
.pf{height:100%;background:linear-gradient(90deg,var(--vp),#8b5cf6);border-radius:99px;transition:width .5s}
.card{border-radius:.75rem}
.sfoot{margin-top:auto;color:rgba(255,255,255,.25);font-size:.62rem;padding:14px;border-top:1px solid rgba(255,255,255,.07);line-height:1.9}
.sfoot strong{color:rgba(255,255,255,.45)}
@media(max-width:640px){.sidebar{display:none}}
</style></head>
<body>
<div class="d-flex">
<aside class="sidebar p-3">
  <div class="d-flex align-items-center gap-2 mb-4 px-1 pt-1"><div style="width:28px;height:28px;background:var(--vp);border-radius:6px;display:grid;place-items:center;flex-shrink:0"><i class="bi bi-play-circle-fill text-white" style="font-size:.8rem"></i></div><span class="text-white fw-bold" style="font-size:.88rem">ViewPay <span style="opacity:.4;font-size:.7rem">v<?= VP_VER ?></span></span></div>
  <ul class="list-unstyled">
  <?php foreach($sNames as $n=>$lbl): ?>
  <li><div class="si <?= $n<$step?'done':($n===$step?'active':'') ?>"><span class="sn"><?= $n<$step?'✓':$n ?></span><?= $lbl ?></div></li>
  <?php endforeach; ?>
  </ul>
  <div class="sfoot"><div>PHP <?= PHP_VERSION ?></div><div>By <strong>Jcode</strong></div><div>Author: <strong>Obrempong</strong></div></div>
</aside>

<main class="flex-grow-1 p-4 p-lg-5" style="max-width:660px">
<div class="pbar"><div class="pf" style="width:<?= $pct[$step]??10 ?>%"></div></div>
<?php if($errors): ?><div class="alert alert-danger border-0 py-2 mb-3 d-flex gap-2"><i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i><div><?= implode('<br>',array_map('htmlspecialchars',$errors)) ?></div></div><?php endif; ?>

<?php if($step===1): ?>
<span class="badge bg-primary mb-2" style="font-size:.68rem">Step 1 of 5</span>
<h1 class="fw-800 mb-1 fs-2">Server Requirements</h1>
<p class="text-muted small mb-4">Checking your cPanel environment</p>
<div class="card border-0 shadow-sm overflow-hidden mb-4">
<?php foreach($reqs as [$rn,$rp,$rv,$rc]): ?>
<div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
  <div class="small fw-600 d-flex align-items-center gap-2"><i class="bi bi-<?= $rp?'check-circle-fill text-success':'exclamation-circle '.($rc?'text-danger':'text-warning') ?>"></i><?= htmlspecialchars($rn) ?><?php if($rc&&!$rp): ?><span class="badge bg-danger ms-1" style="font-size:.58rem">Required</span><?php endif; ?></div>
  <code class="text-muted small"><?= htmlspecialchars($rv) ?></code>
</div>
<?php endforeach; ?>
</div>
<form method="POST"><input type="hidden" name="ps" value="1"><button type="submit" class="btn btn-primary fw-700 px-5" <?= $critOk?'':'disabled' ?>>Continue <i class="bi bi-arrow-right ms-1"></i></button> <a href="?step=1" class="btn btn-outline-secondary">Re-check</a></form>

<?php elseif($step===2): ?>
<span class="badge bg-primary mb-2" style="font-size:.68rem">Step 2 of 5</span>
<h1 class="fw-800 mb-1 fs-2">Database</h1>
<p class="text-muted small mb-3">cPanel → MySQL Databases → Create DB → Create User → All Privileges</p>
<div class="alert alert-info border-0 small py-2 mb-3"><i class="bi bi-lightbulb me-1"></i>cPanel prefix format: <code>username_dbname</code></div>
<div class="card border-0 shadow-sm p-4">
<form method="POST"><input type="hidden" name="ps" value="2">
<div class="row g-3">
  <div class="col-8"><label class="form-label small fw-600">Host</label><input name="db_host" class="form-control" value="<?= htmlspecialchars($fD['db_host']??'localhost') ?>" required></div>
  <div class="col-4"><label class="form-label small fw-600">Port</label><input name="db_port" class="form-control" value="<?= htmlspecialchars($fD['db_port']??'3306') ?>"></div>
  <div class="col-12"><label class="form-label small fw-600">Database Name *</label><input name="db_name" class="form-control" value="<?= htmlspecialchars($fD['db_name']??'') ?>" placeholder="yourusername_viewpay" required></div>
  <div class="col-6"><label class="form-label small fw-600">Username *</label><input name="db_user" class="form-control" value="<?= htmlspecialchars($fD['db_user']??'') ?>" placeholder="yourusername_user" required></div>
  <div class="col-6"><label class="form-label small fw-600">Password</label><input type="password" name="db_pass" class="form-control" placeholder="••••••••"></div>
</div>
<div class="mt-4 d-flex gap-2"><a href="?step=1" class="btn btn-outline-secondary">← Back</a><button type="submit" class="btn btn-primary fw-700">Test &amp; Continue →</button></div>
</form>
</div>

<?php elseif($step===3): ?>
<span class="badge bg-primary mb-2" style="font-size:.68rem">Step 3 of 5</span>
<h1 class="fw-800 mb-1 fs-2">Site Configuration</h1>
<p class="text-muted small mb-4">Your site name and public URL</p>
<div class="card border-0 shadow-sm p-4">
<form method="POST"><input type="hidden" name="ps" value="3">
<div class="mb-3"><label class="form-label small fw-600">Site Name *</label><input name="site_name" class="form-control" value="<?= htmlspecialchars($fD['site_name']??'ViewPay') ?>" required></div>
<div class="mb-0"><label class="form-label small fw-600">Site URL *</label><input type="url" name="app_url" class="form-control" value="<?= htmlspecialchars($fD['app_url']??'https://'.($_SERVER['HTTP_HOST']??'yourdomain.com')) ?>" required><div class="form-text">No trailing slash. Use https:// if SSL is enabled.</div></div>
<div class="mt-4 d-flex gap-2"><a href="?step=2" class="btn btn-outline-secondary">← Back</a><button type="submit" class="btn btn-primary fw-700">Continue →</button></div>
</form>
</div>

<?php elseif($step===4): ?>
<span class="badge bg-primary mb-2" style="font-size:.68rem">Step 4 of 5</span>
<h1 class="fw-800 mb-1 fs-2">Admin Account</h1>
<p class="text-muted small mb-4">Save these credentials — they are NOT emailed to you</p>
<div class="card border-0 shadow-sm p-4">
<form method="POST"><input type="hidden" name="ps" value="4">
<div class="mb-3"><label class="form-label small fw-600">Full Name</label><input name="admin_name" class="form-control" value="<?= htmlspecialchars($fD['admin_name']??'Admin') ?>"></div>
<div class="mb-3"><label class="form-label small fw-600">Admin Email *</label><input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($fD['admin_email']??'') ?>" placeholder="admin@yourdomain.com" required></div>
<div class="row g-2 mb-3">
  <div class="col-6"><label class="form-label small fw-600">Password (8+ chars)</label><input type="password" name="admin_pw" class="form-control" placeholder="••••••••" minlength="8" required></div>
  <div class="col-6"><label class="form-label small fw-600">Confirm Password</label><input type="password" name="admin_pw2" class="form-control" placeholder="••••••••" required></div>
</div>
<div class="alert alert-warning border-0 py-2 small"><i class="bi bi-key-fill me-1"></i>Write these down now — cannot be recovered without database access.</div>
<div class="mt-3 d-flex gap-2"><a href="?step=3" class="btn btn-outline-secondary">← Back</a><button type="submit" class="btn btn-primary fw-700">Review →</button></div>
</form>
</div>

<?php elseif($step===5): ?>
<span class="badge bg-primary mb-2" style="font-size:.68rem">Step 5 of 5</span>
<h1 class="fw-800 mb-1 fs-2">Ready to Install</h1>
<p class="text-muted small mb-4">Click Install Now to create 18 tables and seed data</p>
<div class="card border-0 shadow-sm p-4 mb-3">
<?php foreach([['Site',$d['site_name']??'—'],['URL',$d['app_url']??'—'],['Database',($d['db_user']??'').'@'.($d['db_name']??'')],['Admin Email',$_SESSION['vs_admin_email']??'—'],['Tables','18 + email_verifications + audit_logs + ad_watches'],['New in v2','Ad Watching, Email Verification, Audit Logs, Moderator Roles']] as [$k,$v]): ?>
<div class="d-flex justify-content-between border-bottom py-2 small"><span class="text-muted"><?= $k ?></span><span class="fw-600 text-end"><?= htmlspecialchars($v) ?></span></div>
<?php endforeach; ?>
</div>
<form method="POST" onsubmit="document.getElementById('ib').disabled=true;document.getElementById('ib').innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Installing…'">
<input type="hidden" name="ps" value="5">
<div class="d-flex gap-2"><a href="?step=4" class="btn btn-outline-secondary">← Back</a><button id="ib" type="submit" class="btn btn-primary fw-700 px-5"><i class="bi bi-rocket-takeoff-fill me-2"></i>Install Now</button></div>
</form>

<?php elseif($step===6):
$siteUrl=rtrim($d['app_url']??'','/'); $adminUrl=$siteUrl.'/admin';
?>
<div class="text-center py-2">
<div style="width:72px;height:72px;background:#ecfdf5;border-radius:50%;display:grid;place-items:center;margin:0 auto 16px"><i class="bi bi-check-circle-fill text-success" style="font-size:2.2rem"></i></div>
<h1 class="fw-800 mb-1 fs-2">Installation Complete!</h1>
<p class="text-muted mb-4 small">ViewPay v<?= VP_VER ?> is installed and ready.</p>
<div class="card border-0 p-4 mb-3 text-start" style="background:#0d0d12;border-radius:.75rem">
<div class="small font-monospace text-muted mb-1">Website</div>
<div class="small font-monospace text-info mb-3"><?= htmlspecialchars($siteUrl) ?></div>
<div class="small font-monospace text-muted mb-1">Admin Panel</div>
<div class="small font-monospace text-info mb-3"><?= htmlspecialchars($adminUrl) ?></div>
<div class="small font-monospace text-muted mb-1">Admin Email</div>
<div class="small font-monospace text-success mb-3"><?= htmlspecialchars($_SESSION['vs_admin_email']??'') ?></div>
<div class="small font-monospace text-muted mb-1">Password</div>
<div class="small font-monospace text-success"><?= htmlspecialchars($_SESSION['vs_admin_pw']??'') ?></div>
</div>
<?php if(!empty($_SESSION['vs_env_warn'])): ?>
<div class="alert alert-warning small text-start py-2 mb-3"><i class="bi bi-exclamation-triangle me-1"></i><strong>Could not write .env file.</strong> Create <code>.env</code> in public_html and paste:<pre class="bg-dark text-light rounded p-2 mt-2 small"><?= htmlspecialchars($_SESSION['vs_env_txt']??'') ?></pre></div>
<?php endif; ?>
<div class="alert alert-danger fw-700 small py-2 mb-3"><i class="bi bi-shield-fill-exclamation me-1"></i>DELETE install.php NOW via cPanel File Manager!</div>
<div class="card border-0 shadow-sm p-4 mb-4 text-start small">
<strong>First-time setup checklist:</strong>
<ol class="mt-2 mb-0" style="line-height:2">
<li>Settings → <strong>Currency</strong> — update exchange rate</li>
<li>Settings → <strong>Payments</strong> — enter MoMo number &amp; Paystack keys</li>
<li>Settings → <strong>Ad Watching</strong> — configure ad network &amp; rewards</li>
<li>Settings → <strong>Email/SMTP</strong> — set up email (for verification)</li>
<li>Settings → <strong>Appearance &amp; PWA</strong> — upload logo &amp; splash screen</li>
</ol>
</div>
<div class="d-flex gap-3 justify-content-center">
<a href="<?= htmlspecialchars($adminUrl) ?>" class="btn btn-success fw-700 px-4"><i class="bi bi-speedometer2 me-2"></i>Open Admin Panel</a>
<a href="<?= htmlspecialchars($siteUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-globe me-1"></i>View Site</a>
</div>
<div class="mt-4 text-muted" style="font-size:.72rem">Developed by <strong>Jcode</strong> &nbsp;|&nbsp; Author: <strong>Obrempong</strong> &nbsp;|&nbsp; v<?= VP_VER ?></div>
</div>
<?php unset($_SESSION['vs_admin_pw'],$_SESSION['vs_db_ok'],$_SESSION['vs_done']); endif; ?>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
