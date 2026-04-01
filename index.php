<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расчет КБЖУ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        #app { max-width: 600px; margin: 0 auto; }
        .page-title { 
            text-align: center; 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 20px;
            color: #333;
        }
        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .date-header {
            text-align: center;
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin: 2px;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .meal-block {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #27ae60;
        }
        .meal-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding: 5px 15px;
            background: #2c3e50;
            color: white;
            border-radius: 20px;
            display: inline-block;
        }
        .add-dish-btn {
            background: transparent;
            border: 2px dashed #3498db;
            color: #3498db;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
        }
        .daily-total {
            background: #2c3e50;
            color: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .total-title { font-size: 20px; margin-bottom: 15px; }
        .total-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        .total-item { text-align: center; }
        .total-label { font-size: 14px; opacity: 0.8; margin-bottom: 5px; }
        .total-value { font-size: 24px; font-weight: bold; }
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 20px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab {
            flex: 1;
            padding: 10px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
        }
        .tab.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .search-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .search-results { max-height: 300px; overflow-y: auto; }
        .result-item {
            padding: 12px;
            border: 1px solid #ecf0f1;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
        }
        .result-item:hover {
            background: #ecf0f1;
            border-color: #3498db;
        }
        .result-name { font-weight: 600; margin-bottom: 5px; }
        .result-nutrients { font-size: 12px; color: #7f8c8d; }
        .dish-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            gap: 10px;
        }
        .dish-delete {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
        }
        .dish-weight {
            width: 70px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        .dish-info { flex: 1; }
        .dish-name { font-weight: 600; margin-bottom: 4px; }
        .dish-nutrients { font-size: 12px; color: #7f8c8d; }
        .day-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .day-card.today { border: 2px solid #e74c3c; }
        .day-date { font-size: 18px; font-weight: 600; margin-bottom: 10px; }
        .day-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            text-align: center;
        }
        .stat-item { background: #f8f9fa; padding: 8px; border-radius: 6px; }
        .stat-label { font-size: 12px; color: #7f8c8d; }
        .stat-value { font-size: 14px; font-weight: 600; }
        .delete-day-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
        }
        .archive-divider {
            text-align: center;
            margin: 30px 0;
        }
        .archive-divider span {
            background: #f5f5f5;
            padding: 0 15px;
            color: #7f8c8d;
        }
        .loading { text-align: center; padding: 40px; color: #7f8c8d; }
    </style>
</head>
<body>
    <div id="app"></div>
    <script src="app.js"></script>
</body>
</html>