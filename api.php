<?php
// НЕ выводим ошибки в API - только в JSON
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
$dataFile = 'data.json';
$csvDir = 'csv/'; // ✅ Исправлено: файлы CSV в корне, а не в папке csv/

// Инициализация файла данных
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode(array('days' => array()), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Обработка ошибок через try-catch
try {
    switch ($action) {
        case 'getDays':
            echo json_encode(getDays(), JSON_UNESCAPED_UNICODE);
            break;
        case 'getDay':
            $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
            echo json_encode(getDay($date), JSON_UNESCAPED_UNICODE);
            break;
        case 'addDay':
            $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
            echo json_encode(addDay($date), JSON_UNESCAPED_UNICODE);
            break;
        case 'deleteDay':
            $date = isset($_POST['date']) ? $_POST['date'] : '';
            echo json_encode(deleteDay($date), JSON_UNESCAPED_UNICODE);
            break;
        case 'addDish':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            echo json_encode(addDish($data), JSON_UNESCAPED_UNICODE);
            break;
        case 'deleteDish':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            echo json_encode(deleteDish($data), JSON_UNESCAPED_UNICODE);
            break;
        case 'updateDishWeight':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            echo json_encode(updateDishWeight($data), JSON_UNESCAPED_UNICODE);
            break;
        case 'copyDay':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            echo json_encode(copyDay($data), JSON_UNESCAPED_UNICODE);
            break;
        case 'updateDate':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            echo json_encode(updateDate($data), JSON_UNESCAPED_UNICODE);
            break;
        case 'searchCafe':
            $query = isset($_GET['q']) ? $_GET['q'] : '';
            echo json_encode(searchCSV($csvDir . 'cafe.csv', $query), JSON_UNESCAPED_UNICODE);
            break;
        case 'searchRecipes':
            $query = isset($_GET['q']) ? $_GET['q'] : '';
            echo json_encode(searchCSV($csvDir . 'recipes.csv', $query), JSON_UNESCAPED_UNICODE);
            break;
        case 'searchBuy':
            $query = isset($_GET['q']) ? $_GET['q'] : '';
            echo json_encode(searchCSV($csvDir . 'buy.csv', $query), JSON_UNESCAPED_UNICODE);
            break;
        default:
            echo json_encode(array('error' => 'Invalid action', 'action' => $action), JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}

function loadData() {
    global $dataFile;
    $content = @file_get_contents($dataFile);
    $decoded = json_decode($content, true);
    return $decoded ? $decoded : array('days' => array());
}

function saveData($data) {
    global $dataFile;
    @file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getDays() {
    $data = loadData();
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $dayAfter = date('Y-m-d', strtotime('+2 days'));
    
    foreach (array($today, $tomorrow, $dayAfter) as $date) {
        if (!isset($data['days'][$date])) {
            $data['days'][$date] = array(
                'date' => $date,
                'meals' => array('breakfast' => array(), 'lunch' => array(), 'dinner' => array())
            );
            saveData($data);
        }
    }
    
    $data = loadData();
    $days = isset($data['days']) ? $data['days'] : array();
    $future = array();
    $past = array();
    $current = null;
    
    foreach ($days as $date => $dayData) {
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

function getDay($date) {
    $data = loadData();
    return isset($data['days'][$date]) ? $data['days'][$date] : null;
}

function addDay($date) {
    $data = loadData();
    if (!isset($data['days'][$date])) {
        $data['days'][$date] = array(
            'date' => $date,
            'meals' => array('breakfast' => array(), 'lunch' => array(), 'dinner' => array())
        );
        saveData($data);
    }
    return array('success' => true, 'date' => $date);
}

function deleteDay($date) {
    $data = loadData();
    if (isset($data['days'][$date])) {
        unset($data['days'][$date]);
        saveData($data);
        return array('success' => true);
    }
    return array('success' => false);
}

function addDish($data) {
    if (!$data) return array('success' => false, 'error' => 'No data');
    $date = $data['date'];
    $meal = $data['meal'];
    $dish = $data['dish'];
    
    $jsonData = loadData();
    if (!isset($jsonData['days'][$date])) {
        addDay($date);
        $jsonData = loadData();
    }
    
    $dish['id'] = uniqid();
    $jsonData['days'][$date]['meals'][$meal][] = $dish;
    saveData($jsonData);
    
    return array('success' => true);
}

function deleteDish($data) {
    if (!$data) return array('success' => false);
    $date = $data['date'];
    $meal = $data['meal'];
    $dishId = $data['dishId'];
    
    $jsonData = loadData();
    if (isset($jsonData['days'][$date]['meals'][$meal])) {
        $filtered = array();
        foreach ($jsonData['days'][$date]['meals'][$meal] as $dish) {
            if ($dish['id'] !== $dishId) {
                $filtered[] = $dish;
            }
        }
        $jsonData['days'][$date]['meals'][$meal] = $filtered;
        saveData($jsonData);
        return array('success' => true);
    }
    return array('success' => false);
}

function updateDishWeight($data) {
    if (!$data) return array('success' => false);
    $date = $data['date'];
    $meal = $data['meal'];
    $dishId = $data['dishId'];
    $weight = intval($data['weight']);
    
    $jsonData = loadData();
    if (isset($jsonData['days'][$date]['meals'][$meal])) {
        foreach ($jsonData['days'][$date]['meals'][$meal] as &$dish) {
            if ($dish['id'] === $dishId) {
                $dish['weight'] = $weight;
                if ($dish['source'] === 'recipes') {
                    $ratio = $weight / $dish['total_weight'];
                    $dish['calories'] = round($dish['calories_per_100'] * $ratio);
                    $dish['proteins'] = round($dish['proteins_per_100'] * $ratio, 1);
                    $dish['fats'] = round($dish['fats_per_100'] * $ratio, 1);
                    $dish['carbs'] = round($dish['carbs_per_100'] * $ratio, 1);
                } else {
                    $ratio = $weight / 100;
                    $dish['calories'] = round($dish['calories_per_100'] * $ratio);
                    $dish['proteins'] = round($dish['proteins_per_100'] * $ratio, 1);
                    $dish['fats'] = round($dish['fats_per_100'] * $ratio, 1);
                    $dish['carbs'] = round($dish['carbs_per_100'] * $ratio, 1);
                }
                break;
            }
        }
        saveData($jsonData);
        return array('success' => true);
    }
    return array('success' => false);
}

function copyDay($data) {
    if (!$data) return array('success' => false);
    $fromDate = $data['fromDate'];
    $toDate = $data['toDate'];
    
    $jsonData = loadData();
    if (isset($jsonData['days'][$fromDate])) {
        $jsonData['days'][$toDate] = $jsonData['days'][$fromDate];
        $jsonData['days'][$toDate]['date'] = $toDate;
        foreach ($jsonData['days'][$toDate]['meals'] as &$meal) {
            foreach ($meal as &$dish) {
                $dish['id'] = uniqid();
            }
        }
        saveData($jsonData);
        return array('success' => true);
    }
    return array('success' => false);
}

function updateDate($data) {
    if (!$data) return array('success' => false);
    $oldDate = $data['oldDate'];
    $newDate = $data['newDate'];
    
    $jsonData = loadData();
    if (isset($jsonData['days'][$oldDate])) {
        $jsonData['days'][$newDate] = $jsonData['days'][$oldDate];
        $jsonData['days'][$newDate]['date'] = $newDate;
        unset($jsonData['days'][$oldDate]);
        saveData($jsonData);
        return array('success' => true, 'newDate' => $newDate);
    }
    return array('success' => false);
}

function searchCSV($filepath, $query) {
    $results = array();
    
    if (!file_exists($filepath)) {
        return array('error' => 'File not found: ' . $filepath, 'results' => array());
    }
    
    $file = @fopen($filepath, 'r');
    if (!$file) {
        return array('error' => 'Cannot open file', 'results' => array());
    }
    
    $headers = @fgetcsv($file, 1000, ',');
    if (!$headers) {
        @fclose($file);
        return array('error' => 'Cannot read headers', 'results' => array());
    }
    
    // Удаляем BOM из первого заголовка
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    $headers = array_map('trim', $headers);
    
    while (($row = @fgetcsv($file, 1000, ',')) !== FALSE) {
        $row = array_map('trim', $row);
        $data = @array_combine($headers, $row);
        if (!$data) continue;
        
        $name = isset($data['Name']) ? $data['Name'] : (isset($data['Название']) ? $data['Название'] : '');
        if (empty($name)) continue;
        
        // ✅ ИЗМЕНЕНО: mb_stripos вместо stripos для корректной работы с кириллицей
        if (empty($query) || mb_stripos($name, $query, 0, 'UTF-8') !== false) {
            $calories = floatval(isset($data['Калории на 100гр']) ? $data['Калории на 100гр'] : (isset($data['Калорий на 100гр']) ? $data['Калорий на 100гр'] : 0));
            $proteins = floatval(isset($data['Белки на 100гр']) ? $data['Белки на 100гр'] : 0);
            $fats = floatval(isset($data['Жиры на 100гр']) ? $data['Жиры на 100гр'] : 0);
            $carbs = floatval(isset($data['Углеводы на 100гр']) ? $data['Углеводы на 100гр'] : 0);
            $totalWeight = floatval(isset($data['Итоговый вес']) ? $data['Итоговый вес'] : 100);
            
            $results[] = array(
                'name' => $name,
                'calories' => $calories,
                'proteins' => $proteins,
                'fats' => $fats,
                'carbs' => $carbs,
                'total_weight' => $totalWeight
            );
        }
    }
    
    @fclose($file);
    return $results;
}
?>