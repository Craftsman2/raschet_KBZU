<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Прямой тест API</h1>";

// Подключаем api.php функции
require_once 'api.php';

echo "<h2>Тест 1: getDays</h2>";
try {
    $result = getDays();
    echo "<span style='color:green;'>✅ Работает!</span><br>";
    echo "<pre>" . htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre>";
} catch (Exception $e) {
    echo "<span style='color:red;'>❌ Ошибка: " . $e->getMessage() . "</span><br>";
}

echo "<h2>Тест 2: searchCSV (recipes)</h2>";
try {
    $result = searchCSV('recipes.csv', 'Сникерс');
    echo "<span style='color:green;'>✅ Работает!</span><br>";
    echo "<pre>" . htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre>";
} catch (Exception $e) {
    echo "<span style='color:red;'>❌ Ошибка: " . $e->getMessage() . "</span><br>";
}

echo "<h2>Тест 3: searchCSV (cafe)</h2>";
try {
    $result = searchCSV('cafe.csv', 'Кури');
    echo "<span style='color:green;'>✅ Работает!</span><br>";
    echo "<pre>" . htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre>";
} catch (Exception $e) {
    echo "<span style='color:red;'>❌ Ошибка: " . $e->getMessage() . "</span><br>";
}

echo "<h2>Тест 4: searchCSV (buy)</h2>";
try {
    $result = searchCSV('buy.csv', '');
    echo "<span style='color:green;'>✅ Работает!</span><br>";
    echo "<pre>" . htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre>";
} catch (Exception $e) {
    echo "<span style='color:red;'>❌ Ошибка: " . $e->getMessage() . "</span><br>";
}

echo "<h2>Тест 5: data.json</h2>";
if (file_exists('data.json')) {
    echo "<span style='color:green;'>✅ Существует</span><br>";
    $content = file_get_contents('data.json');
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
} else {
    echo "<span style='color:red;'>❌ Не создан</span><br>";
}
?>