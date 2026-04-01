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
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: #f5f5f5;
    min-height: 100vh;
    padding: 20px;
}
#app {
    max-width: 600px;
    margin: 0 auto;
}
/* Page 1 - Days List */
.page-title {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
    color: #333;
}
.date-header {
    text-align: center;
    font-size: 20px;
    margin-bottom: 20px;
    color: #333;
}
.day-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}
.day-card.today {
    border: 2px solid #e74c3c;
}
.day-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.day-date {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #2c3e50;
}
.day-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    text-align: center;
}
.stat-item {
    background: #f8f9fa;
    padding: 8px;
    border-radius: 6px;
}
.stat-label {
    font-size: 12px;
    color: #7f8c8d;
    margin-bottom: 4px;
}
.stat-value {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
}
.archive-divider {
    text-align: center;
    margin: 30px 0;
    position: relative;
}
.archive-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #bdc3c7;
}
.archive-divider span {
    background: #f5f5f5;
    padding: 0 15px;
    position: relative;
    color: #7f8c8d;
    font-size: 14px;
}
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
    font-size: 18px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
/* Page 2 - Day Detail */
.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: nowrap;
    gap: 5px;
}
.header-buttons {
    display: flex;
    gap: 5px;
    flex-shrink: 0;
}
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
    white-space: nowrap;
}
.btn-primary {
    background: #3498db;
    color: white;
}
.btn-primary:hover {
    background: #2980b9;
}
.btn-secondary {
    background: #95a5a6;
    color: white;
}
.btn-secondary:hover {
    background: #7f8c8d;
}
.meal-block {
    background: white;
    border-radius: 12px;
    padding: 35px 20px 20px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    position: relative;
}
.meal-title {
    font-size: 18px;
    font-weight: 600;
    padding: 5px 20px;
    background: #2c3e50;
    color: white;
    border-radius: 20px;
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    margin: 0;
}
.meal-content {
    margin-top: 0;
    padding-top: 0;
}
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
    font-size: 16px;
    flex-shrink: 0;
}
.dish-weight {
    width: 70px;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: center;
}
.dish-info {
    flex: 1;
}
.dish-name {
    font-weight: 600;
    margin-bottom: 4px;
}
.dish-nutrients {
    font-size: 12px;
    color: #7f8c8d;
}
.add-dish-btn {
    background: transparent;
    border: 2px dashed #3498db;
    color: #3498db;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 10px;
    transition: all 0.3s;
    display: inline-block;
}
.add-dish-btn:hover {
    background: #3498db;
    color: white;
}
.daily-total {
    background: #2c3e50;
    color: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.total-title {
    font-size: 20px;
    margin-bottom: 15px;
}
.total-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}
.total-item {
    text-align: center;
}
.total-label {
    font-size: 14px;
    opacity: 0.8;
    margin-bottom: 5px;
}
.total-value {
    font-size: 24px;
    font-weight: bold;
}
/* Page 3 - Add Dish Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
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
/* ✅ Вкладки всегда в 1 строку */
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
    transition: all 0.3s;
    text-align: center;
    white-space: nowrap;
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
.search-input:focus {
    outline: none;
    border-color: #3498db;
}
.search-results {
    max-height: 300px;
    overflow-y: auto;
}
.result-item {
    padding: 12px;
    border: 1px solid #ecf0f1;
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.3s;
}
.result-item:hover {
    background: #ecf0f1;
    border-color: #3498db;
}
.result-name {
    font-weight: 600;
    margin-bottom: 5px;
}
.result-nutrients {
    font-size: 12px;
    color: #7f8c8d;
}
/* Responsive Design */
@media (max-width: 600px) {
    body {
        padding: 10px;
    }
    /* ✅ УБРАНО: .tabs больше не column на мобильном */
    .dish-item {
        flex-wrap: wrap;
    }
    .dish-delete {
        order: 1;
    }
    .dish-info {
        width: 100%;
        order: 2;
    }
    .dish-weight {
        order: 3;
        width: 100%;
        margin-top: 10px;
    }
    .dish-nutrients {
        width: 100%;
        margin-top: 8px;
    }
    .day-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 5px;
    }
    .day-header .btn {
        padding: 6px 12px;
        font-size: 12px;
        white-space: nowrap;
    }
    /* ✅ Вкладки на мобильном тоже в 1 строку */
    .tabs {
        flex-direction: row;
        gap: 5px;
    }
    .tab {
        padding: 8px 5px;
        font-size: 13px;
    }
}
@media (min-width: 601px) {
    .day-stats,
    .total-stats {
        grid-template-columns: repeat(4, 1fr);
    }
}
.loading {
    text-align: center;
    padding: 40px;
    color: #7f8c8d;
}
.error {
    background: #e74c3c;
    color: white;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    text-align: center;
}
</style>
</head>
<body>
<div id="app"></div>
<script src="app.js"></script>
</body>
</html>