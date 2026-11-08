<?php
header('Content-Type: text/html; charset=utf-8');

$files = ['recipes.csv', 'cafe.csv', 'buy.csv'];

foreach ($files as $file) {
    // Проверяем оба расположения
    $paths = [$file, 'csv/' . $file];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            
            // Удаляем BOM
            if (substr($content, 0, 3) == "\xEF\xBB\xBF") {
                $content = substr($content, 3);
                file_put_contents($path, $content);
                echo "$path: ✅ BOM удалён<br>";
            } else {
                echo "$path: ✅ BOM не найден<br>";
            }
            break;
        }
    }
}
?>