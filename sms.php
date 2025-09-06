<?php
/**
 * sms.php — Forward SMS (JSON POST) → Telegram
 * Compatible with: android_income_sms_gateway_webhook
 * https://github.com/bogkonstantin/android_income_sms_gateway_webhook
 *
 * Input JSON:
 * {
 *   "from":"%from%",
 *   "text":"%text%",
 *   "sentStamp":%sentStamp%,        // epoch ms
 *   "receivedStamp":%receivedStamp%,// epoch ms
 *   "sim":"%sim%"
 * }
 */

//// CONFIG (via environment variables) //////////////////////////////
$BOT_TOKEN     = getenv('BOT_TOKEN')     ?: '';         // Telegram bot token
$CHAT_ID       = getenv('CHAT_ID')       ?: '';         // Telegram numeric chat ID
$SHARED_SECRET = getenv('SHARED_SECRET') ?: '';         // optional shared secret
$LOG_DIR       = __DIR__ . '/logs';                     // log directory
$LOCAL_TZ      = getenv('LOCAL_TZ')      ?: 'Asia/Baku';// local TZ for display
////////////////////////////////////////////////////////////////////////

date_default_timezone_set('UTC');
@mkdir($LOG_DIR, 0775, true);
$REQ_LOG_FILE = $LOG_DIR . '/sms-req-' . date('Ymd') . '.log';
$TG_LOG_FILE  = $LOG_DIR . '/sms-tg-'  . date('Ymd') . '.log';

function log_to($file, $line) { @file_put_contents($file, '['.date('H:i:s')."] $line\n", FILE_APPEND); }
function json_out($arr, $code = 200){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function json_fail($code,$msg,$detail=null){ $o=['ok'=>false,'error'=>$msg]; if($detail!==null)$o['detail']=$detail; json_out($o,$code); }

function get_all_headers_safe() {
  if (function_exists('getallheaders')) return getallheaders();
  $h=[]; foreach($_SERVER as $k=>$v){ if(strpos($k,'HTTP_')===0){ $name=str_replace(' ','-', ucwords(strtolower(str_replace('_',' ',substr($k,5))))); $h[$name]=$v; } }
  if (isset($_SERVER['CONTENT_TYPE']))   $h['Content-Type']   = $_SERVER['CONTENT_TYPE'];
  if (isset($_SERVER['CONTENT_LENGTH'])) $h['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
  return $h;
}
function epochms_to_dt($ms,$tz='UTC'){ if($ms===''||$ms===null) return ['','']; $ms=(float)$ms; if($ms>1e12) $ms/=1e6; $sec=(int)floor($ms/1000);
  $utc=(new DateTime("@$sec"))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  $loc=(new DateTime("@$sec"))->setTimezone(new DateTimeZone($tz))->format('Y-m-d H:i:s'); return [$utc,$loc]; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_fail(405,'Method Not Allowed');

$ct  = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input');
$headers = get_all_headers_safe();
$req_snapshot = [
  'time'       => gmdate('Y-m-d H:i:s'),
  'method'     => $_SERVER['REQUEST_METHOD'] ?? '',
  'uri'        => $_SERVER['REQUEST_URI'] ?? '',
  'query'      => $_GET ?? [],
  'headers'    => $headers,
  'raw_body'   => $raw,
  'post'       => $_POST ?? [],
  'files'      => $_FILES ?? [],
  'server_ip'  => $_SERVER['SERVER_ADDR'] ?? '',
  'client_ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
  'ua'         => $headers['User-Agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
];
log_to($REQ_LOG_FILE, json_encode($req_snapshot, JSON_UNESCAPED_UNICODE));

if (stripos($ct, 'application/json') === false || $raw === '') {
  json_fail(400, 'Expecting non-empty application/json body', ['content_type'=>$ct,'len'=>strlen($raw)]);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) json_fail(400, 'Bad Request: invalid JSON');

// Optional shared-secret: accept from body.secret OR header:X-Shared-Secret OR query:?secret=
if ($SHARED_SECRET !== '') {
  $secret = ($payload['secret'] ?? '') ?: ($headers['X-Shared-Secret'] ?? '') ?: ($_GET['secret'] ?? '');
  if ($secret !== $SHARED_SECRET) json_fail(401, 'Unauthorized');
}

// Extract
$from          = trim((string)($payload['from']          ?? ''));
$text          = trim((string)($payload['text']          ?? ''));
$sentStamp     = $payload['sentStamp']     ?? null;
$receivedStamp = $payload['receivedStamp'] ?? null;
$sim           = trim((string)($payload['sim']           ?? ''));

// Convert stamps
[$sentUTC,$sentLocal]         = epochms_to_dt($sentStamp, $LOCAL_TZ);
[$recvUTC,$recvLocal]         = epochms_to_dt($receivedStamp, $LOCAL_TZ);

// Build Telegram text
$h = fn($s)=>htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$lines=[];
$lines[] = '<b>📩 New SMS</b>';
if ($from!=='') $lines[] = '<b>From:</b> '.$h($from);
if ($text!=='') $lines[] = "<b>Message:</b>\n".$h($text);

$meta=[];
if ($sim!=='')       $meta[]='SIM: '.$h($sim);
if ($sentUTC!=='')   $meta[]='Sent(UTC): '.$h($sentUTC);
if ($sentLocal!=='') $meta[]='Sent(Local): '.$h($sentLocal);
if ($recvUTC!=='')   $meta[]='Recv(UTC): '.$h($recvUTC);
if ($recvLocal!=='') $meta[]='Recv(Local): '.$h($recvLocal);
if ($meta) $lines[]='<i>'.implode(' | ',$meta).'</i>';

$textOut = implode("\n\n",$lines);
if ($textOut==='') $textOut='📩 New SMS (empty body)';
if (mb_strlen($textOut,'UTF-8')>4000) $textOut=mb_substr($textOut,0,3990,'UTF-8')."<br>...(truncated)";

// Send to Telegram
if ($BOT_TOKEN === '' || $CHAT_ID === '') json_fail(500,'server_not_configured');

$tgUrl = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
$post  = ['chat_id'=>$CHAT_ID,'text'=>$textOut,'parse_mode'=>'HTML','disable_web_page_preview'=>1];

$ch = curl_init($tgUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $post,
  CURLOPT_TIMEOUT        => 20,
]);
$res  = curl_exec($ch);
$err  = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

log_to($TG_LOG_FILE, "HTTP=$http | CURL_ERR=" . ($err ?: '-') . " | RESP=" . $res);

if ($http >= 200 && $http < 300 && !$err) json_out(['ok'=>true], 200);
json_fail(500, 'telegram_failed', ['http'=>$http,'curl_error'=>$err,'telegram_response'=>$res]);
