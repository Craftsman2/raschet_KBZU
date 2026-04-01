// Глобальные переменные
let currentRoute = {};
let currentMeal = '';
let currentDate = '';
let currentTab = ''; // ✅ По умолчанию пусто

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    console.log('App loaded');
    handleRoute();
    window.addEventListener('hashchange', handleRoute);
});

// Роутинг
function handleRoute() {
    var hash = window.location.hash.slice(1) || '/';
    var app = document.getElementById('app');
    app.innerHTML = '';
    
    if (hash === '/') {
        renderDaysList();
    } else if (hash.indexOf('/day/') === 0) {
        var date = hash.replace('/day/', '');
        renderDayDetail(date);
    }
}

// Страница 1: Список дней
function renderDaysList() {
    var app = document.getElementById('app');
    app.innerHTML = '<div class="loading">Загрузка...</div>';
    
    fetch('api.php?action=getDays')
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            console.log('Days data:', data);
            var today = new Date().toISOString().split('T')[0];
            
            var html = '<h1 class="page-title">Расчет КБЖУ</h1>';
            
            if (data.future) {
                for (var date in data.future) {
                    if (data.future.hasOwnProperty(date)) {
                        html += renderDayCard(date, data.future[date], today, false);
                    }
                }
            }
            
            if (data.current) {
                for (var date in data.current) {
                    if (data.current.hasOwnProperty(date)) {
                        html += renderDayCard(date, data.current[date], today, true);
                    }
                }
            }
            
            if (data.past && Object.keys(data.past).length > 0) {
                html += '<div class="archive-divider"><span>Архив</span></div>';
                for (var date in data.past) {
                    if (data.past.hasOwnProperty(date)) {
                        html += renderDayCard(date, data.past[date], today, false);
                    }
                }
            }
            
            app.innerHTML = html;
            attachDayListeners();
        })
        .catch(function(error) {
            console.error('Error:', error);
            app.innerHTML = '<div class="error">Ошибка загрузки: ' + error.message + '</div>';
        });
}

function renderDayCard(date, dayData, today, isToday) {
    var stats = calculateDayTotals(dayData);
    var dateObj = new Date(date);
    var dateString = dateObj.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
    
    return '<div class="day-card ' + (isToday ? 'today' : '') + '" data-date="' + date + '">' +
        '<button class="delete-day-btn" data-date="' + date + '" onclick="deleteDay(event, \'' + date + '\')">×</button>' +
        '<div class="day-date">' + dateString + '</div>' +
        '<div class="day-stats">' +
            '<div class="stat-item"><div class="stat-label">Кал</div><div class="stat-value">' + stats.calories + '</div></div>' +
            '<div class="stat-item"><div class="stat-label">Белки</div><div class="stat-value">' + stats.proteins + '</div></div>' +
            '<div class="stat-item"><div class="stat-label">Жиры</div><div class="stat-value">' + stats.fats + '</div></div>' +
            '<div class="stat-item"><div class="stat-label">Углеводы</div><div class="stat-value">' + stats.carbs + '</div></div>' +
        '</div>' +
    '</div>';
}

function attachDayListeners() {
    var cards = document.querySelectorAll('.day-card');
    for (var i = 0; i < cards.length; i++) {
        cards[i].addEventListener('click', function(e) {
            if (!e.target.classList.contains('delete-day-btn')) {
                var date = this.getAttribute('data-date');
                window.location.hash = '/day/' + date;
            }
        });
    }
}

function deleteDay(event, date) {
    event.stopPropagation();
    if (confirm('Удалить этот день?')) {
        var formData = new FormData();
        formData.append('date', date);
        fetch('api.php?action=deleteDay', {
            method: 'POST',
            body: formData
        })
        .then(function() {
            renderDaysList();
        });
    }
}

// Страница 2: Детали дня
function renderDayDetail(date) {
    currentDate = date;
    var app = document.getElementById('app');
    app.innerHTML = '<div class="loading">Загрузка...</div>';
    
    fetch('api.php?action=getDay&date=' + date)
        .then(function(response) {
            return response.json();
        })
        .then(function(dayData) {
            console.log('Day data:', dayData);
            
            if (!dayData) {
                app.innerHTML = '<div class="error">День не найден</div>';
                return;
            }
            
            var dateObj = new Date(date);
            var dateString = dateObj.toLocaleDateString('ru-RU', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
            
            var totals = calculateDayTotals(dayData);
            
            var html = '<div class="date-header" onclick="changeDate()" title="Нажмите для изменения даты">' + dateString + ' 📅</div>' +
                '<div class="day-header">' +
                    '<button class="btn btn-primary" onclick="window.location.hash=\'/\'">← Назад</button>' +
                    '<button class="btn btn-secondary" onclick="copyDay()">Копировать</button>' +
                '</div>';
            
            var meals = [
                {key: 'breakfast', title: 'Завтрак'},
                {key: 'lunch', title: 'Обед'},
                {key: 'dinner', title: 'Ужин'}
            ];
            
				for (var i = 0; i < meals.length; i++) {
					var meal = meals[i];
					html += '<div class="meal-block">' +
						'<div class="meal-title">' + meal.title + '</div>' +
						'<div class="meal-content">' +
							renderDishes(dayData.meals[meal.key] || [], date, meal.key) +
							'<button class="add-dish-btn" onclick="openAddDishModal(\'' + meal.key + '\')">добавить</button>' +
						'</div>' +
					'</div>';
				}
            
            html += '<div class="daily-total">' +
                '<div class="total-title">Итого КБЖУ за день</div>' +
                '<div class="total-stats">' +
                    '<div class="total-item"><div class="total-label">Кал</div><div class="total-value">' + totals.calories + '</div></div>' +
                    '<div class="total-item"><div class="total-label">Белки</div><div class="total-value">' + totals.proteins + '</div></div>' +
                    '<div class="total-item"><div class="total-label">Жиры</div><div class="total-value">' + totals.fats + '</div></div>' +
                    '<div class="total-item"><div class="total-label">Углеводы</div><div class="total-value">' + totals.carbs + '</div></div>' +
                '</div>' +
            '</div>';
            
            app.innerHTML = html;
            attachDishListeners(date);
        })
        .catch(function(error) {
            console.error('Error:', error);
            app.innerHTML = '<div class="error">Ошибка: ' + error.message + '</div>';
        });
}

function renderDishes(dishes, date, meal) {
    if (!dishes || dishes.length === 0) return '';
    
    var html = '';
    for (var i = 0; i < dishes.length; i++) {
        var dish = dishes[i];
        html += '<div class="dish-item">' +
            '<button class="dish-delete" onclick="deleteDish(\'' + date + '\', \'' + meal + '\', \'' + dish.id + '\')">×</button>' +
            '<div class="dish-info">' +
                '<div class="dish-name">' + (i+1) + '. ' + dish.name + '</div>' +
                '<div class="dish-nutrients">Кал: ' + dish.calories + ' | Белки: ' + dish.proteins + ' | Жиры: ' + dish.fats + ' | Углеводы: ' + dish.carbs + '</div>' +
            '</div>' +
            '<input type="number" class="dish-weight" data-dish-id="' + dish.id + '" data-meal="' + meal + '" value="' + (dish.weight || 100) + '" min="1" max="10000" onchange="updateWeight(\'' + date + '\', \'' + meal + '\', \'' + dish.id + '\', this.value)">' +
        '</div>';
    }
    return html;
}

function attachDishListeners(date) {}

function deleteDish(date, meal, dishId) {
    fetch('api.php?action=deleteDish', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            date: date,
            meal: meal,
            dishId: dishId
        })
    })
    .then(function() {
        renderDayDetail(date);
    });
}

function updateWeight(date, meal, dishId, weight) {
    fetch('api.php?action=updateDishWeight', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            date: date,
            meal: meal,
            dishId: dishId,
            weight: parseInt(weight) || 0
        })
    })
    .then(function() {
        renderDayDetail(date);
    });
}

function calculateDayTotals(dayData) {
    var totals = {calories: 0, proteins: 0, fats: 0, carbs: 0};
    if (!dayData || !dayData.meals) return totals;

    var mealTypes = ['breakfast', 'lunch', 'dinner'];
    for (var i = 0; i < mealTypes.length; i++) {
        var meals = dayData.meals[mealTypes[i]] || [];
        for (var j = 0; j < meals.length; j++) {
            var dish = meals[j];
            totals.calories += dish.calories || 0;
            totals.proteins += dish.proteins || 0;
            totals.fats += dish.fats || 0;
            totals.carbs += dish.carbs || 0;
        }
    }

    return {
        calories: Math.round(totals.calories),
        proteins: Math.round(totals.proteins * 10) / 10,
        fats: Math.round(totals.fats * 10) / 10,
        carbs: Math.round(totals.carbs * 10) / 10
    };
}

// Страница 3: Модальное окно добавления блюда
function openAddDishModal(meal) {
    currentMeal = meal;
    console.log('Opening modal for meal:', meal);
    
    var modal = document.createElement('div');
    modal.className = 'modal-overlay';
    // ✅ Изменено: ни одна вкладка не активна по умолчанию
    modal.innerHTML = 
        '<div class="modal-content">' +
            '<div class="modal-header">Добавление блюда</div>' +
            '<div class="tabs">' +
                '<div class="tab" data-source="cafe" onclick="switchTab(\'cafe\')">Кафе</div>' +
                '<div class="tab" data-source="recipes" onclick="switchTab(\'recipes\')">Рецепты</div>' +
                '<div class="tab" data-source="buy" onclick="switchTab(\'buy\')">Купить</div>' +
            '</div>' +
            '<input type="text" class="search-input" id="searchInput" placeholder="Выберите вкладку и введите поиск" oninput="performSearch()">' +
            '<div class="search-results" id="searchResults"></div>' +
        '</div>';

    document.body.appendChild(modal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });

    // ✅ Не делаем поиск по умолчанию
}

function switchTab(source) {
    currentTab = source;
    var tabs = document.querySelectorAll('.tab');
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].classList.remove('active');
        if (tabs[i].getAttribute('data-source') === source) {
            tabs[i].classList.add('active');
        }
    }
    performSearch();
}

function performSearch() {
    var query = document.getElementById('searchInput').value;
    var resultsDiv = document.getElementById('searchResults');
    
    // ✅ Если вкладка не выбрана, не делаем поиск
    if (!currentTab) {
        resultsDiv.innerHTML = '<div class="loading">Выберите вкладку</div>';
        return;
    }
    
    console.log('Searching:', currentTab, query);

    resultsDiv.innerHTML = '<div class="loading">Поиск...</div>';

    var action = '';
    switch(currentTab) {
        case 'cafe':
            action = 'searchCafe';
            break;
        case 'recipes':
            action = 'searchRecipes';
            break;
        case 'buy':
            action = 'searchBuy';
            break;
        default:
            action = 'searchBuy';
    }

    var url = 'api.php?action=' + action + '&q=' + encodeURIComponent(query);
    console.log('URL:', url);

    fetch(url)
        .then(function(response) {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(function(data) {
            console.log('Search results:', data);
            
            if (!data || data.length === 0) {
                resultsDiv.innerHTML = '<div class="loading">Ничего не найдено</div>';
                return;
            }
            
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var item = data[i];
                var itemData = encodeURIComponent(JSON.stringify(item));
                html += '<div class="result-item" onclick="addDishFromSearch(\'' + itemData + '\')">' +
                    '<div class="result-name">' + item.name + '</div>' +
                    '<div class="result-nutrients">Кал: ' + item.calories + ' | Белки: ' + item.proteins + ' | Жиры: ' + item.fats + ' | Углеводы: ' + item.carbs + (item.total_weight !== 100 ? ' | Вес: ' + item.total_weight + 'г' : '') + '</div>' +
                '</div>';
            }
            
            resultsDiv.innerHTML = html;
        })
        .catch(function(error) {
            console.error('Search error:', error);
            resultsDiv.innerHTML = '<div class="error">Ошибка поиска: ' + error.message + '</div>';
        });
}

function addDishFromSearch(encodedData) {
    var item = JSON.parse(decodeURIComponent(encodedData));
    addDish(item);
}

function addDish(dishData) {
    console.log('Adding dish:', dishData);
    var dish = {
        name: dishData.name,
        source: currentTab,
        calories_per_100: dishData.calories,
        proteins_per_100: dishData.proteins,
        fats_per_100: dishData.fats,
        carbs_per_100: dishData.carbs,
        total_weight: dishData.total_weight || 100,
        weight: 100,
        calories: dishData.calories,
        proteins: dishData.proteins,
        fats: dishData.fats,
        carbs: dishData.carbs
    };

    fetch('api.php?action=addDish', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            date: currentDate,
            meal: currentMeal,
            dish: dish
        })
    })
    .then(function() {
        var modal = document.querySelector('.modal-overlay');
        if (modal) modal.remove();
        renderDayDetail(currentDate);
    })
    .catch(function(error) {
        console.error('Add dish error:', error);
        alert('Ошибка добавления: ' + error.message);
    });
}

function copyDay() {
    var newDate = prompt('Введите дату для копирования (ГГГГ-ММ-ДД):', currentDate);
    if (!newDate) return;
    
    fetch('api.php?action=copyDay', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            fromDate: currentDate,
            toDate: newDate
        })
    })
    .then(function() {
        alert('День скопирован!');
        window.location.hash = '/day/' + newDate;
    });
}

// ✅ Изменено: календарь вместо prompt
function changeDate() {
    var modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = 
        '<div class="modal-content">' +
            '<div class="modal-header">Изменить дату</div>' +
            '<input type="date" id="datePicker" value="' + currentDate + '" style="width:100%;padding:12px;font-size:16px;margin-bottom:15px;">' +
            '<button class="btn btn-primary" onclick="confirmDateChange()" style="width:100%;">Применить</button>' +
            '<button class="btn btn-secondary" onclick="this.closest(\'.modal-overlay\').remove()" style="width:100%;margin-top:10px;">Отмена</button>' +
        '</div>';
    document.body.appendChild(modal);
}

function confirmDateChange() {
    var newDate = document.getElementById('datePicker').value;
    if (!newDate || newDate === currentDate) {
        var modal = document.querySelector('.modal-overlay');
        if (modal) modal.remove();
        return;
    }
    
    fetch('api.php?action=updateDate', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            oldDate: currentDate,
            newDate: newDate
        })
    })
    .then(function() {
        window.location.hash = '/day/' + newDate;
    });
}