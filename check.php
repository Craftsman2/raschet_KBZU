<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Диагностика</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .ok { color: green; }
        .error { color: red; }
        pre { background: #f0f0f0; padding: 10px; }
    </style>
</head>
<body>
<h1>🔍 Диагностика</h1>

<h2>Файлы:</h2>
<?php
$files = array('api.php', 'index.php', 'style.css', 'app.js', 'data.json', 'recipes.csv', 'cafe.csv', 'buy.csv');
foreach ($files as $file) {
    echo "$file: " . (file_exists($file) ? "<span class='ok'>✅</span>" : "<span class='error'>❌</span>") . "<br>";
}
?>

<h2>Тест API:</h2>
<?php
$test = @file_get_contents('api.php?action=getDays');
if ($test) {
    echo "<span class='ok'>✅ Работает!</span><br>";
    echo "<pre>" . htmlspecialchars($test) . "</pre>";
} else {
    echo "<span class='error'>❌ Ошибка</span><br>";
}
?>

<h2>Тест поиска:</h2>
<?php
$test2 = @file_get_contents('api.php?action=searchRecipes&q=Сникерс');
if ($test2) {
    echo "<span class='ok'>✅ Работает!</span><br>";
    echo "<pre>" . htmlspecialchars($test2) . "</pre>";
} else {
    echo "<span class='error'>❌ Ошибка</span><br>";
}
?>

<h2><a href="index.php">🏠 Открыть приложение</a></h2>
</body>
</html>