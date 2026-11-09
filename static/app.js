// Глобальные переменные
let currentRoute = {};
let currentMeal = '';
let currentDate = '';
let currentTab = 'cafe';

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
    
    fetch('?action=getDays')
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            console.log('Days data:', data);
            var today = new Date().toISOString().split('T')[0];
            
            var html = '<h1 class="page-title">Расчет КБЖУ</h1>';
            
            if (data.future) {
                Object.keys(data.future).sort().reverse().forEach(function(date) {
                    html += renderDayCard(date, data.future[date], today, false);
                });
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
                Object.keys(data.past).sort().reverse().forEach(function(date) {
                    html += renderDayCard(date, data.past[date], today, false);
                });
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
            '<div class="stat-item"><div class="stat-label">Вес</div><div class="stat-value">' + formatDayWeight(dayData) + '</div></div>' +
        '</div>' +
    '</div>';
}

function formatDayWeight(dayData) {
    if (!dayData || dayData.weight === null || dayData.weight === undefined || dayData.weight === '') return '—';
    return dayData.weight;
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
        fetch('?action=deleteDay', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({date: date})
        })
        .then(function() {
            renderDaysList();
        });
    }
}

// Страница 2: Детали дня
function renderDayDetail(date, silent) {
    currentDate = date;
    var app = document.getElementById('app');
    var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
    if (!silent) {
        app.innerHTML = '<div class="loading">Загрузка...</div>';
    }
    
    fetch('?action=getDay&date=' + date)
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
            
            var html = '<div class="date-header">' + dateString + '</div>' +
                '<div class="day-header">' +
                    '<button class="btn btn-primary" onclick="window.location.hash=\'/\'">Назад</button>' +
                    '<div class="header-buttons">' +
                        '<button class="btn btn-secondary" onclick="changeDate()">Изменить дату</button>' +
                        '<button class="btn btn-secondary" onclick="copyDay()">Копировать</button>' +
                    '</div>' +
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
                        '<div class="dishes" data-date="' + date + '" data-meal="' + meal.key + '">' +
                            renderDishes(dayData.meals[meal.key] || [], date, meal.key) +
                        '</div>' +
                        '<button class="add-dish-btn" onclick="openAddDishModal(\'' + meal.key + '\')">добавить</button>' +
                    '</div>' +
                '</div>';
            }
            
            html += '<div class="meal-block">' +
                '<div class="meal-title">Вес</div>' +
                '<div class="meal-content">' +
                    '<div class="weight-row">' +
                        '<input type="number" inputmode="decimal" class="day-weight-input" id="dayWeightInput" step="0.1" min="0" max="1000" placeholder="0" value="' + (dayData.weight !== null && dayData.weight !== undefined ? dayData.weight : '') + '" onchange="updateDayWeight(\'' + date + '\', this.value)">' +
                    '</div>' +
                '</div>' +
            '</div>';

            html += '<div class="daily-total">' +
                '<div class="total-title">Итого КБЖУ за день</div>' +
                '<div class="total-stats">' +
                    '<div class="total-item"><div class="total-label">Кал</div><div class="total-value" id="total-cal">' + totals.calories + '</div></div>' +
                    '<div class="total-item"><div class="total-label">Белки</div><div class="total-value" id="total-prot">' + totals.proteins + '</div></div>' +
                    '<div class="total-item"><div class="total-label">Жиры</div><div class="total-value" id="total-fat">' + totals.fats + '</div></div>' +
                    '<div class="total-item"><div class="total-label">Углеводы</div><div class="total-value" id="total-carb">' + totals.carbs + '</div></div>' +
                '</div>' +
            '</div>';
            
            app.innerHTML = html;
            if (silent) {
                window.scrollTo(0, scrollY);
            }
            attachDishListeners(date);
        })
        .catch(function(error) {
            console.error('Error:', error);
            app.innerHTML = '<div class="error">Ошибка: ' + error.message + '</div>';
        });
}

function nutrientsText(dish) {
    return 'Кал: ' + dish.calories + ' | Белки: ' + dish.proteins + ' | Жиры: ' + dish.fats + ' | Углеводы: ' + dish.carbs;
}

function renderDishes(dishes, date, meal) {
    if (!dishes || dishes.length === 0) return '';

    var html = '';
    for (var i = 0; i < dishes.length; i++) {
        var dish = dishes[i];
        html += '<div class="dish-item" data-dish-id="' + dish.id + '">' +
            '<div class="dish-handle" title="Перетащить">⋮⋮</div>' +
            '<div class="dish-name"><span class="dish-num">' + (i+1) + '</span>. ' + dish.name + '</div>' +
            '<div class="dish-nutrients">' + nutrientsText(dish) + '</div>' +
            '<input type="number" class="dish-weight" data-dish-id="' + dish.id + '" data-meal="' + meal + '" value="' + (dish.weight || 100) + '" min="1" max="10000" onchange="updateWeight(\'' + date + '\', \'' + meal + '\', \'' + dish.id + '\', this.value)">' +
            '<button class="dish-delete" onclick="deleteDish(\'' + date + '\', \'' + meal + '\', \'' + dish.id + '\')">×</button>' +
        '</div>';
    }
    return html;
}

function attachDishListeners(date) {
    var handles = document.querySelectorAll('.dish-handle');
    for (var i = 0; i < handles.length; i++) {
        handles[i].addEventListener('pointerdown', onDishDragStart);
    }
}

// ===== Drag & drop блюд (мышь и касание) =====
var dragState = null;

function onDishDragStart(e) {
    if (dragState) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    e.preventDefault();

    var handle = e.currentTarget;
    var item = handle.closest('.dish-item');
    dragState = {
        handle: handle,
        item: item,
        list: item.parentNode,
        pointerId: e.pointerId,
        lastY: e.clientY,
        changed: false,
        raf: null
    };
    try { handle.setPointerCapture(e.pointerId); } catch (err) {}
    item.classList.add('dragging');
    document.body.classList.add('dragging-active');

    handle.addEventListener('pointermove', onDishDragMove);
    handle.addEventListener('pointerup', onDishDragEnd);
    handle.addEventListener('pointercancel', onDishDragEnd);
    dragState.raf = requestAnimationFrame(dragLoop);
}

function onDishDragMove(e) {
    if (!dragState) return;
    dragState.lastY = e.clientY;
}

function dragLoop() {
    if (!dragState) return;
    var y = dragState.lastY;
    // автопрокрутка у краёв экрана
    if (y < 70) {
        window.scrollBy(0, -12);
    } else if (y > window.innerHeight - 70) {
        window.scrollBy(0, 12);
    }
    moveDraggedItem(y);
    dragState.raf = requestAnimationFrame(dragLoop);
}

function moveDraggedItem(y) {
    var item = dragState.item;
    var list = dragState.list;
    var sibling, r;

    sibling = item.previousElementSibling;
    while (sibling) {
        r = sibling.getBoundingClientRect();
        if (y < r.top + r.height / 2) {
            list.insertBefore(item, sibling);
            dragState.changed = true;
            sibling = item.previousElementSibling;
        } else {
            break;
        }
    }

    sibling = item.nextElementSibling;
    while (sibling) {
        r = sibling.getBoundingClientRect();
        if (y > r.top + r.height / 2) {
            list.insertBefore(sibling, item);
            dragState.changed = true;
            sibling = item.nextElementSibling;
        } else {
            break;
        }
    }
    if (dragState.changed) renumberDishes(list);
}

function renumberDishes(list) {
    var nums = list.querySelectorAll('.dish-num');
    for (var i = 0; i < nums.length; i++) {
        nums[i].textContent = i + 1;
    }
}

function onDishDragEnd() {
    if (!dragState) return;
    var st = dragState;
    dragState = null;
    cancelAnimationFrame(st.raf);

    st.handle.removeEventListener('pointermove', onDishDragMove);
    st.handle.removeEventListener('pointerup', onDishDragEnd);
    st.handle.removeEventListener('pointercancel', onDishDragEnd);
    try { st.handle.releasePointerCapture(st.pointerId); } catch (err) {}
    st.item.classList.remove('dragging');
    document.body.classList.remove('dragging-active');

    if (!st.changed) return;

    var ids = [];
    var items = st.list.querySelectorAll('.dish-item');
    for (var i = 0; i < items.length; i++) {
        ids.push(items[i].getAttribute('data-dish-id'));
    }
    fetch('?action=reorderDishes', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            date: st.list.getAttribute('data-date'),
            meal: st.list.getAttribute('data-meal'),
            order: ids
        })
    }).catch(function(error) {
        console.error('Reorder error:', error);
        renderDayDetail(currentDate, true);
    });
}

function deleteDish(date, meal, dishId) {
    fetch('?action=deleteDish', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            date: date,
            meal: meal,
            dishId: dishId
        })
    })
    .then(function() {
        renderDayDetail(date, true);
    });
}

function updateWeight(date, meal, dishId, weight) {
    fetch('?action=updateDishWeight', {
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
        return fetch('?action=getDay&date=' + date);
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(dayData) {
        applyDayData(dayData);
    })
    .catch(function(error) {
        console.error('Update weight error:', error);
    });
}

// Обновляет цифры на странице без перерисовки (скролл и фокус сохраняются)
function applyDayData(dayData) {
    if (!dayData || !dayData.meals) return;

    for (var meal in dayData.meals) {
        if (!dayData.meals.hasOwnProperty(meal)) continue;
        var dishes = dayData.meals[meal] || [];
        for (var i = 0; i < dishes.length; i++) {
            var el = document.querySelector('.dish-item[data-dish-id="' + dishes[i].id + '"] .dish-nutrients');
            if (el) el.textContent = nutrientsText(dishes[i]);
        }
    }

    var totals = calculateDayTotals(dayData);
    var map = {'total-cal': totals.calories, 'total-prot': totals.proteins, 'total-fat': totals.fats, 'total-carb': totals.carbs};
    for (var id in map) {
        if (map.hasOwnProperty(id)) {
            var t = document.getElementById(id);
            if (t) t.textContent = map[id];
        }
    }
}

function updateDayWeight(date, value) {
    var v = String(value).replace(',', '.');
    fetch('?action=setDayWeight', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            date: date,
            weight: v === '' ? null : parseFloat(v)
        })
    }).catch(function(error) {
        console.error('Day weight error:', error);
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

// Модальное окно добавления блюда
function openAddDishModal(meal) {
    currentMeal = meal;
    console.log('Opening modal for meal:', meal);
    
    var modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = 
        '<div class="modal-content">' +
            '<div class="modal-header">Добавление блюда</div>' +
            '<div class="tabs">' +
                '<div class="tab active" data-source="cafe" onclick="switchTab(\'cafe\')">Кафе</div>' +
                '<div class="tab" data-source="recipes" onclick="switchTab(\'recipes\')">Рецепты</div>' +
                '<div class="tab" data-source="buy" onclick="switchTab(\'buy\')">Купить</div>' +
            '</div>' +
            '<input type="text" class="search-input" id="searchInput" placeholder="Поиск по названию из csv" oninput="performSearch()">' +
            '<div class="search-results" id="searchResults"></div>' +
        '</div>';

    document.body.appendChild(modal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });

    performSearch();
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

    var url = '?action=' + action + '&q=' + encodeURIComponent(query);
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

    fetch('?action=addDish', {
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
        renderDayDetail(currentDate, true);
    })
    .catch(function(error) {
        console.error('Add dish error:', error);
        alert('Ошибка добавления: ' + error.message);
    });
}

function copyDay() {
    var newDate = prompt('Введите дату для копирования (ГГГГ-ММ-ДД):', currentDate);
    if (!newDate) return;
    
    fetch('?action=copyDay', {
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

function changeDate() {
    var newDate = prompt('Введите новую дату (ГГГГ-ММ-ДД):', currentDate);
    if (!newDate || newDate === currentDate) return;
    
    fetch('?action=updateDate', {
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