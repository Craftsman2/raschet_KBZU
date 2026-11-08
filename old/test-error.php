<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Тест PHP ошибок</h1>";

try {
    echo "<h2>Тест 1: Простой JSON</h2>";
    $test = ['test' => 'ok', 'date' => date('Y-m-d')];
    echo json_encode($test, JSON_UNESCAPED_UNICODE);
    echo "<br><br>";
    
    echo "<h2>Тест 2: Чтение data.json</h2>";
    if (file_exists('data.json')) {
        echo "Файл существует<br>";
        $content = file_get_contents('data.json');
        echo "Содержимое: " . htmlspecialchars($content) . "<br>";
        $data = json_decode($content, true);
        echo "Decoded: " . print_r($data, true) . "<br>";
    } else {
        echo "Файл не существует, создаём...<br>";
        file_put_contents('data.json', json_encode(['days' => []], JSON_UNESCAPED_UNICODE));
        echo "Создан!<br>";
    }
    
    echo "<h2>Тест 3: Чтение CSV</h2>";
    $csvFile = 'csv/recipes.csv';
    if (file_exists($csvFile)) {
        echo "Файл $csvFile существует<br>";
        $file = fopen($csvFile, 'r');
        if ($file) {
            $headers = fgetcsv($file, 1000, ',');
            echo "Заголовки: " . print_r($headers, true) . "<br>";
            fclose($file);
        } else {
            echo "❌ Не удалось открыть файл<br>";
        }
    } else {
        echo "❌ Файл $csvFile не найден<br>";
    }
    
    echo "<h2>Тест 4: Вызов api.php</h2>";
    $apiResult = file_get_contents('api.php?action=getDays');
    echo "Результат: " . htmlspecialchars($apiResult) . "<br>";
    
} catch (Exception $e) {
    echo "<div style='color:red;'><strong>ОШИБКА:</strong> " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Информация о сервере:</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Directory: " . getcwd() . "<br>";
echo "Files in directory:<br>";
foreach (scandir('.') as $file) {
    echo "&nbsp;&nbsp;$file<br>";
}
?>