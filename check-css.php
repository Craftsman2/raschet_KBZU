<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>Проверка CSS</h1>";

if (file_exists('style.css')) {
    echo "<span style='color:green;'>✅ style.css существует</span><br>";
    echo "Размер: " . filesize('style.css') . " байт<br>";
    echo "Чтение: " . (is_readable('style.css') ? "✅ OK" : "❌ Нет прав") . "<br>";
    
    // Показываем первые 10 строк
    echo "<h3>Содержимое (первые 10 строк):</h3>";
    echo "<pre>";
    $lines = file('style.css');
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]);
    }
    echo "</pre>";
} else {
    echo "<span style='color:red;'>❌ style.css НЕ найден!</span><br>";
    echo "Создайте файл style.css в корне сайта<br>";
}

echo "<h2>Проверка index.php:</h2>";
if (file_exists('index.php')) {
    $content = file_get_contents('index.php');
    if (strpos($content, 'style.css') !== false) {
        echo "✅ style.css подключён в index.php<br>";
    } else {
        echo "❌ style.css НЕ подключён в index.php<br>";
    }
    
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
}
?>