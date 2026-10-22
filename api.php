<?php
/** Cloud City Training Log • private Synology PHP endpoint • same-origin only.
 * Edit TOKEN and DATA_DIR before installing. Do not expose publicly.
 */
declare(strict_types=1);
const TOKEN = 'REPLACE_WITH_A_LONG_RANDOM_SECRET_TOKEN';
const DATA_DIR = '/volume1/leadville-private'; // Must be writable by Web Station PHP and OUTSIDE the served website.
const MAX_BYTES = 12582912; // 12 MiB; stored OCR thumbnails can take space.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
// Old API versions encoded empty object maps as arrays. Repair only known maps;
// preserve actual lists (sessions, ingredients, etc.) exactly as supplied.
function normalizeStoreMaps(object $store): object {
    if (isset($store->daily) && is_array($store->daily) && count($store->daily) === 0) $store->daily = new stdClass();
    if (isset($store->nutrition)) {
        if (is_array($store->nutrition) && count($store->nutrition) === 0) $store->nutrition = new stdClass();
        if (is_object($store->nutrition)) {
            foreach (['foods', 'logs', 'days'] as $key) {
                if (isset($store->nutrition->$key) && is_array($store->nutrition->$key) && count($store->nutrition->$key) === 0) $store->nutrition->$key = new stdClass();
            }
            if (isset($store->nutrition->logs) && is_object($store->nutrition->logs)) {
                foreach ($store->nutrition->logs as $log) {
                    if (is_object($log) && isset($log->entries) && is_array($log->entries) && count($log->entries) === 0) $log->entries = new stdClass();
                }
            }
        }
    }
    return $store;
}
function respond(int $status, array $obj): never {
    http_response_code($status);
    echo json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (TOKEN === 'REPLACE_WITH_A_LONG_RANDOM_SECRET_TOKEN' || strlen(TOKEN)<24) {
    respond(503, ['error'=>'Administrator: set a unique, long TOKEN in api.php first.']);
}
if (!hash_equals(TOKEN, $_SERVER['HTTP_X_LEADVILLE_TOKEN'] ?? '')) {
    respond(401, ['error'=>'Unauthorized. Enter the same NAS token in NAS Setup on each device.']);
}
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'PUT'], true)) respond(405, ['error'=>'Use GET or PUT']);
if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0700, true) && !is_dir(DATA_DIR)) {
    respond(500, ['error'=>'NAS data folder could not be created. Create '.DATA_DIR.' and grant PHP read/write permission.']);
}
$file = DATA_DIR.'/training-log.json';
$lock = @fopen(DATA_DIR.'/training-log.lock', 'c');
if (!$lock) respond(500, ['error'=>'NAS data folder not writable by PHP.']);
if (!flock($lock, $method==='GET' ? LOCK_SH : LOCK_EX)) respond(500, ['error'=>'NAS lock failed']);
$current = is_file($file) ? @file_get_contents($file) : false;
if ($current === false) $current='{"theme":"copper","daily":{}}';
$revision = hash('sha256', $current);
if ($method==='GET') {
    $stored=json_decode($current,true);
    if (!is_array($stored) || !isset($stored['daily']) || !is_array($stored['daily'])) respond(500, ['error'=>'NAS data file is damaged. Restore from backup before saving.']);
    // Return the original JSON object types; associative decoding turns {} into [].
    $storedObject=json_decode($current);
    if (!is_object($storedObject)) respond(500, ['error'=>'NAS data file must contain a JSON object.']);
    $storedObject=normalizeStoreMaps($storedObject);
    respond(200,['revision'=>$revision,'store'=>$storedObject]);
}
$body=@file_get_contents('php://input',false,null,0,MAX_BYTES+1);
if ($body===false || strlen($body)>MAX_BYTES) respond(413,['error'=>'Backup too large for configured API size limit.']);
$payload=json_decode($body,true);
if (!is_array($payload) || !is_string($payload['revision']??null) || !isset($payload['store']) || !is_array($payload['store'])) respond(400,['error'=>'Invalid JSON or missing revision/store']);
if (!hash_equals($revision, $payload['revision'])) respond(409,['error'=>'NAS has changed on another device. Export local backup, refresh or merge to resolve.']);
$store=$payload['store'];
if (!isset($store['daily']) || !is_array($store['daily']) || count($store['daily'])>336) respond(400,['error'=>'Invalid daily workout object']);
foreach ($store['daily'] as $k=>$v) {
    if (!preg_match('/^(?:[1-9]|[1-3][0-9]|4[0-8])-(?:MON|TUE|WED|THU|FRI|SAT|SUN)$/',strval($k)) || !is_array($v)) respond(400,['error'=>'Invalid day key or log type']);
}
// Validation above uses associative arrays. Encode the original object tree to
// preserve empty food/log/day maps as {} and actual lists as [].
$payloadObject=json_decode($body);
if (!is_object($payloadObject) || !is_object($payloadObject->store ?? null)) respond(400, ['error'=>'Store must be a JSON object']);
$json=json_encode(normalizeStoreMaps($payloadObject->store),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
if ($json===false || strlen($json)>MAX_BYTES) respond(413,['error'=>'Training data is too large']);
$backups=DATA_DIR.'/backups';
if (!is_dir($backups) && !@mkdir($backups,0700,true) && !is_dir($backups)) respond(500,['error'=>'Cannot create backup folder']);
if (is_file($file)) {
    $backup=$backups.'/training-log-'.gmdate('Ymd-His').'-'.substr($revision,0,10).'.json';
    if (!@copy($file,$backup)) respond(500,['error'=>'NAS backup failed; refusing to overwrite current version']);
}
$tmp=@tempnam(DATA_DIR,'pending-');
if (!$tmp) respond(500,['error'=>'Cannot create temporary data file']);
if (file_put_contents($tmp,$json,LOCK_EX)===false || !@rename($tmp,$file)) {
    @unlink($tmp);respond(500,['error'=>'Could not commit NAS update']);
}
@chmod($file,0600);
respond(200,['revision'=>hash('sha256',$json),'saved'=>true,'entryCount'=>count($store['daily'])]);
