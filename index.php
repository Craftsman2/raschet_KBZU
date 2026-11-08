<?php
/**
 * Расчет КБЖУ — версия для Yandex Cloud Functions (PHP).
 * Точка входа: index.handler
 *
 * Хранилище: Yandex Object Storage, бакет из переменной окружения S3_BUCKET
 *   data.json (в корне), csv/cafe.csv, csv/recipes.csv, csv/buy.csv
 *
 * Без внешних зависимостей (S3 через curl + AWS Signature V4 или IAM-токен).
 * Совместим с PHP 7.4+.
 */

define('S3_BUCKET', getenv('S3_BUCKET') ?: '');            // обязательная переменная окружения
define('S3_CSV_PREFIX', getenv('S3_CSV_PREFIX') !== false ? getenv('S3_CSV_PREFIX') : 'csv/'); // папка с CSV
define('S3_ENDPOINT', getenv('S3_ENDPOINT') ?: 'storage.yandexcloud.net');
define('S3_REGION', getenv('S3_REGION') ?: 'ru-central1');
define('DATA_KEY', getenv('S3_DATA_KEY') ?: 'data.json');

date_default_timezone_set(getenv('APP_TZ') ?: 'Europe/Moscow');

/* ===================== Точка входа ===================== */

function handler($event, $context = null)
{
    try {
        $query = (isset($event['queryStringParameters']) && is_array($event['queryStringParameters']))
            ? $event['queryStringParameters'] : array();

        // Статика: страница, стили, скрипт
        if (isset($query['asset'])) {
            return serveAsset($query['asset']);
        }
        if (!isset($query['action'])) {
            return serveAsset('index.html');
        }

        $input = parseBody($event);
        $result = route($query['action'], $query, $input);
        return jsonResponse($result);
    } catch (Throwable $e) {
        error_log('KBZU error: ' . $e->getMessage());
        return jsonResponse(array('error' => $e->getMessage()), 500);
    }
}

function route($action, $q, $in)
{
    switch ($action) {
        case 'getDays':
            return getDays();
        case 'getDay':
            return getDay(isset($q['date']) ? $q['date'] : date('Y-m-d'));
        case 'addDay':
            return addDay(isset($in['date']) ? $in['date'] : date('Y-m-d'));
        case 'deleteDay':
            return deleteDay(isset($in['date']) ? $in['date'] : '');
        case 'addDish':
            return addDish($in);
        case 'deleteDish':
            return deleteDish($in);
        case 'updateDishWeight':
            return updateDishWeight($in);
        case 'reorderDishes':
            return reorderDishes($in);
        case 'setDayWeight':
            return setDayWeight($in);
        case 'copyDay':
            return copyDay($in);
        case 'updateDate':
            return updateDate($in);
        case 'searchCafe':
            return searchCSV(S3_CSV_PREFIX . 'cafe.csv', isset($q['q']) ? $q['q'] : '');
        case 'searchRecipes':
            return searchCSV(S3_CSV_PREFIX . 'recipes.csv', isset($q['q']) ? $q['q'] : '');
        case 'searchBuy':
            return searchCSV(S3_CSV_PREFIX . 'buy.csv', isset($q['q']) ? $q['q'] : '');
        default:
            return array('error' => 'Invalid action', 'action' => $action);
    }
}

/* ===================== HTTP-утилиты ===================== */

function jsonResponse($data, $status = 200)
{
    return array(
        'statusCode' => $status,
        'headers' => array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ),
        'isBase64Encoded' => false,
        'body' => (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
    );
}

function serveAsset($name)
{
    $types = array(
        'index.html' => 'text/html; charset=utf-8',
        'style.css' => 'text/css; charset=utf-8',
        'app.js' => 'application/javascript; charset=utf-8',
    );
    if (!isset($types[$name])) {
        return array('statusCode' => 404, 'headers' => array('Content-Type' => 'text/plain'), 'body' => 'Not found');
    }
    $body = false;
    foreach (array(__DIR__ . '/static/' . $name, __DIR__ . '/' . $name) as $path) {
        if (is_file($path)) {
            $body = file_get_contents($path);
            break;
        }
    }
    if ($body === false) {
        error_log('KBZU: asset not found: ' . $name . ' in ' . __DIR__);
        return array(
            'statusCode' => 500,
            'headers' => array('Content-Type' => 'text/plain; charset=utf-8'),
            'isBase64Encoded' => false,
            'body' => 'Файл ' . $name . ' не найден в архиве функции (ожидается static/' . $name . ')',
        );
    }
    return array(
        'statusCode' => 200,
        'headers' => array('Content-Type' => $types[$name], 'Cache-Control' => 'no-cache'),
        'isBase64Encoded' => false,
        'body' => $body,
    );
}

function parseBody($event)
{
    $body = isset($event['body']) ? $event['body'] : '';
    if ($body === '' || $body === null) {
        return array();
    }
    if (!empty($event['isBase64Encoded'])) {
        $body = base64_decode($body);
    }
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    // запасной вариант: application/x-www-form-urlencoded
    $parsed = array();
    parse_str($body, $parsed);
    return $parsed;
}

/* ===================== Object Storage ===================== */

function getIamToken()
{
    static $token = null, $expires = 0;
    if ($token && time() < $expires - 60) {
        return $token;
    }
    $ch = curl_init('http://169.254.169.254/computeMetadata/v1/instance/service-accounts/default/token');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Metadata-Flavor: Google'),
        CURLOPT_TIMEOUT => 5,
    ));
    $resp = curl_exec($ch);
    curl_close($ch);
    $j = json_decode($resp, true);
    if (empty($j['access_token'])) {
        throw new Exception('Не удалось получить IAM-токен (назначьте функции сервисный аккаунт или задайте S3_ACCESS_KEY_ID/S3_SECRET_ACCESS_KEY)');
    }
    $token = $j['access_token'];
    $expires = time() + (isset($j['expires_in']) ? (int)$j['expires_in'] : 600);
    return $token;
}

/** Возвращает array(httpCode, body) */
function s3Request($method, $key, $body = '')
{
    if (S3_BUCKET === '') {
        throw new Exception('Не задана переменная окружения S3_BUCKET');
    }
    $host = S3_ENDPOINT;
    $path = '/' . S3_BUCKET . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    $amzDate = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    $payloadHash = hash('sha256', $body);

    $headers = array(
        'x-amz-content-sha256: ' . $payloadHash,
        'x-amz-date: ' . $amzDate,
    );

    $ak = getenv('S3_ACCESS_KEY_ID');
    $sk = getenv('S3_SECRET_ACCESS_KEY');

    if ($ak && $sk) {
        // AWS Signature V4 (статические ключи)
        $signed = 'host;x-amz-content-sha256;x-amz-date';
        $canonical = $method . "\n" . $path . "\n\n"
            . "host:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\n\n"
            . $signed . "\n" . $payloadHash;
        $scope = "$date/" . S3_REGION . "/s3/aws4_request";
        $toSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $canonical);
        $k = hash_hmac('sha256', $date, 'AWS4' . $sk, true);
        $k = hash_hmac('sha256', S3_REGION, $k, true);
        $k = hash_hmac('sha256', 's3', $k, true);
        $k = hash_hmac('sha256', 'aws4_request', $k, true);
        $sig = hash_hmac('sha256', $toSign, $k);
        $headers[] = "Authorization: AWS4-HMAC-SHA256 Credential=$ak/$scope, SignedHeaders=$signed, Signature=$sig";
    } else {
        // IAM-токен сервисного аккаунта функции
        $headers[] = 'X-YaCloud-SubjectToken: ' . getIamToken();
    }

    $ch = curl_init('https://' . $host . $path);
    $opts = array(
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    );
    if ($method === 'PUT') {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception('S3 request failed: ' . $err);
    }
    return array($code, $resp);
}

function s3Get($key)
{
    list($code, $body) = s3Request('GET', $key);
    if ($code === 404) {
        return null;
    }
    if ($code !== 200) {
        throw new Exception("S3 GET $key: HTTP $code");
    }
    return $body;
}

function s3Put($key, $body)
{
    list($code) = s3Request('PUT', $key, $body);
    if ($code < 200 || $code >= 300) {
        throw new Exception("S3 PUT $key: HTTP $code");
    }
}

/* ===================== Работа с data.json ===================== */

function emptyDay($date)
{
    return array(
        'date' => $date,
        'meals' => array('breakfast' => array(), 'lunch' => array(), 'dinner' => array()),
    );
}

function loadData()
{
    $content = s3Get(DATA_KEY);
    $decoded = $content ? json_decode($content, true) : null;
    if (!is_array($decoded) || !isset($decoded['days']) || !is_array($decoded['days'])) {
        $decoded = array('days' => array());
    }
    return $decoded;
}

function saveData($data)
{
    s3Put(DATA_KEY, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function validDate($d)
{
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

function getDays()
{
    $data = loadData();
    $today = date('Y-m-d');
    $changed = false;

    foreach (array(0, 1, 2) as $offset) {
        $d = date('Y-m-d', strtotime("+$offset day"));
        if (!isset($data['days'][$d])) {
            $data['days'][$d] = emptyDay($d);
            $changed = true;
        }
    }
    if ($changed) {
        saveData($data);
    }

    $future = array();
    $past = array();
    $current = null;
    foreach ($data['days'] as $date => $dayData) {
        if ($date === $today) {
            $current = array($date => $dayData);
        } elseif ($date > $today) {
            $future[$date] = $dayData;
        } else {
            $past[$date] = $dayData;
        }
    }
    ksort($future);
    krsort($past);

    return array('future' => $future, 'current' => $current, 'past' => $past);
}

function getDay($date)
{
    $data = loadData();
    return isset($data['days'][$date]) ? $data['days'][$date] : null;
}

function addDay($date)
{
    if (!validDate($date)) {
        return array('success' => false, 'error' => 'Invalid date');
    }
    $data = loadData();
    if (!isset($data['days'][$date])) {
        $data['days'][$date] = emptyDay($date);
        saveData($data);
    }
    return array('success' => true, 'date' => $date);
}

function deleteDay($date)
{
    $data = loadData();
    if (isset($data['days'][$date])) {
        unset($data['days'][$date]);
        saveData($data);
        return array('success' => true);
    }
    return array('success' => false);
}

function addDish($in)
{
    if (empty($in['date']) || empty($in['meal']) || empty($in['dish']) || !validDate($in['date'])) {
        return array('success' => false, 'error' => 'No data');
    }
    $date = $in['date'];
    $meal = $in['meal'];
    $dish = $in['dish'];
    if (!in_array($meal, array('breakfast', 'lunch', 'dinner'), true)) {
        return array('success' => false, 'error' => 'Invalid meal');
    }

    $data = loadData();
    if (!isset($data['days'][$date])) {
        $data['days'][$date] = emptyDay($date);
    }
    $dish['id'] = uniqid();
    $data['days'][$date]['meals'][$meal][] = $dish;
    saveData($data);

    return array('success' => true);
}

function deleteDish($in)
{
    if (empty($in['date']) || empty($in['meal']) || !isset($in['dishId'])) {
        return array('success' => false);
    }
    $data = loadData();
    $date = $in['date'];
    $meal = $in['meal'];
    if (isset($data['days'][$date]['meals'][$meal])) {
        $filtered = array();
        foreach ($data['days'][$date]['meals'][$meal] as $dish) {
            if ($dish['id'] !== $in['dishId']) {
                $filtered[] = $dish;
            }
        }
        $data['days'][$date]['meals'][$meal] = $filtered;
        saveData($data);
        return array('success' => true);
    }
    return array('success' => false);
}

function updateDishWeight($in)
{
    if (empty($in['date']) || empty($in['meal']) || !isset($in['dishId']) || !isset($in['weight'])) {
        return array('success' => false);
    }
    $date = $in['date'];
    $meal = $in['meal'];
    $weight = intval($in['weight']);

    $data = loadData();
    if (!isset($data['days'][$date]['meals'][$meal])) {
        return array('success' => false);
    }
    foreach ($data['days'][$date]['meals'][$meal] as &$dish) {
        if ($dish['id'] === $in['dishId']) {
            $dish['weight'] = $weight;
            if (isset($dish['source']) && $dish['source'] === 'recipes' && !empty($dish['total_weight'])) {
                $ratio = $weight / $dish['total_weight'];
            } else {
                $ratio = $weight / 100;
            }
            $dish['calories'] = round($dish['calories_per_100'] * $ratio);
            $dish['proteins'] = round($dish['proteins_per_100'] * $ratio, 1);
            $dish['fats'] = round($dish['fats_per_100'] * $ratio, 1);
            $dish['carbs'] = round($dish['carbs_per_100'] * $ratio, 1);
            break;
        }
    }
    unset($dish);
    saveData($data);
    return array('success' => true);
}

/** Новый порядок блюд внутри приёма пищи (drag & drop) */
function reorderDishes($in)
{
    if (empty($in['date']) || empty($in['meal']) || empty($in['order']) || !is_array($in['order'])) {
        return array('success' => false);
    }
    $date = $in['date'];
    $meal = $in['meal'];
    $data = loadData();
    if (!isset($data['days'][$date]['meals'][$meal])) {
        return array('success' => false);
    }

    $byId = array();
    foreach ($data['days'][$date]['meals'][$meal] as $dish) {
        $byId[$dish['id']] = $dish;
    }
    $result = array();
    foreach ($in['order'] as $id) {
        if (isset($byId[$id])) {
            $result[] = $byId[$id];
            unset($byId[$id]);
        }
    }
    foreach ($byId as $dish) { // блюда, которых не было в присланном порядке, остаются в конце
        $result[] = $dish;
    }
    $data['days'][$date]['meals'][$meal] = $result;
    saveData($data);
    return array('success' => true);
}

/** Вес за день (поле «Вес»); null/пусто — очистить */
function setDayWeight($in)
{
    if (empty($in['date']) || !validDate($in['date'])) {
        return array('success' => false);
    }
    $date = $in['date'];
    $data = loadData();
    if (!isset($data['days'][$date])) {
        $data['days'][$date] = emptyDay($date);
    }
    if (!isset($in['weight']) || $in['weight'] === '' || !is_numeric($in['weight'])) {
        unset($data['days'][$date]['weight']);
    } else {
        $data['days'][$date]['weight'] = round((float)$in['weight'], 2);
    }
    saveData($data);
    return array('success' => true);
}

function copyDay($in)
{
    if (empty($in['fromDate']) || empty($in['toDate']) || !validDate($in['toDate'])) {
        return array('success' => false);
    }
    $data = loadData();
    $from = $in['fromDate'];
    $to = $in['toDate'];
    if (!isset($data['days'][$from])) {
        return array('success' => false);
    }
    $copy = $data['days'][$from];
    $copy['date'] = $to;
    foreach ($copy['meals'] as $mealName => $dishes) {
        foreach ($dishes as $i => $dish) {
            $copy['meals'][$mealName][$i]['id'] = uniqid();
        }
    }
    $data['days'][$to] = $copy;
    saveData($data);
    return array('success' => true);
}

function updateDate($in)
{
    if (empty($in['oldDate']) || empty($in['newDate']) || !validDate($in['newDate'])) {
        return array('success' => false);
    }
    $data = loadData();
    $old = $in['oldDate'];
    $new = $in['newDate'];
    if (!isset($data['days'][$old])) {
        return array('success' => false);
    }
    $data['days'][$new] = $data['days'][$old];
    $data['days'][$new]['date'] = $new;
    if ($old !== $new) {
        unset($data['days'][$old]);
    }
    saveData($data);
    return array('success' => true, 'newDate' => $new);
}

/* ===================== CSV из Object Storage ===================== */

/** Кэш CSV на время жизни «тёплого» экземпляра функции */
function loadCsv($key)
{
    static $cache = array();
    $ttl = 300; // секунд
    if (isset($cache[$key]) && time() - $cache[$key]['t'] < $ttl) {
        return $cache[$key]['rows'];
    }

    $content = s3Get($key);
    if ($content === null) {
        throw new Exception('File not found in bucket: ' . $key);
    }
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = preg_split('/\r\n|\n|\r/', $content);

    $headers = null;
    $rows = array();
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $fields = array_map('trim', str_getcsv($line, ',', '"', ''));
        if ($headers === null) {
            $headers = $fields;
            continue;
        }
        if (count($fields) !== count($headers)) {
            continue;
        }
        $rows[] = array_combine($headers, $fields);
    }

    $cache[$key] = array('t' => time(), 'rows' => $rows);
    return $rows;
}

function pick($row, $names, $default = '')
{
    foreach ($names as $n) {
        if (isset($row[$n]) && $row[$n] !== '') {
            return $row[$n];
        }
    }
    return $default;
}

function searchCSV($key, $query)
{
    $results = array();
    $pattern = $query === '' ? null : '/' . preg_quote($query, '/') . '/iu';

    foreach (loadCsv($key) as $row) {
        $name = pick($row, array('Name', 'Название'));
        if ($name === '') {
            continue;
        }
        if ($pattern !== null && !preg_match($pattern, $name)) {
            continue;
        }
        $results[] = array(
            'name' => $name,
            'calories' => floatval(pick($row, array('Калории на 100гр', 'Калорий на 100гр'), 0)),
            'proteins' => floatval(pick($row, array('Белки на 100гр'), 0)),
            'fats' => floatval(pick($row, array('Жиры на 100гр'), 0)),
            'carbs' => floatval(pick($row, array('Углеводы на 100гр'), 0)),
            'total_weight' => floatval(pick($row, array('Итоговый вес'), 100)),
        );
    }
    return $results;
}
