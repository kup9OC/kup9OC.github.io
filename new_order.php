<?php
session_start();
if (!isset($_SESSION['login'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../user/auth");
    exit();
}

require '../user/db_connect.php';

// Проверка прав пользователя
$isRemPC = ($_SESSION['user_role'] === 'rempc');
$isAdmin = ($_SESSION['user_role'] === 'admin');

if (!$isRemPC && !$isAdmin) {
    header("Location: /user/lk");
    exit();
}

// Получаем IP-адрес пользователя
$ipAddress = $_SERVER['REMOTE_ADDR'];

// Получаем текущий URL страницы
$pageUrl = $_SERVER['REQUEST_URI'];

// Проверяем, авторизован ли пользователь
$login = isset($_SESSION['login']) ? $_SESSION['login'] : 'NonAuth_User';

// Записываем посещение страницы в базу данных, включая логин
$stmt = $conn->prepare("INSERT INTO page_visits (ip_address, page_url, login) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $ipAddress, $pageUrl, $login);
$stmt->execute();
$stmt->close();

// Получение активных заявок с возможностью фильтрации по статусу
$orderStatusFilter = isset($_GET['order_status']) ? $_GET['order_status'] : '';

$query_active = "SELECT * FROM clients WHERE request_status = 'открыта'";
if ($orderStatusFilter) {
    $query_active .= " AND order_status = ?";
}
$query_active .= " ORDER BY created_at DESC";

$stmt_active = $conn->prepare($query_active);
if ($orderStatusFilter) {
    $stmt_active->bind_param("s", $orderStatusFilter);
}
$stmt_active->execute();
$result_active = $stmt_active->get_result();
$active_orders = $result_active->fetch_all(MYSQLI_ASSOC);
$stmt_active->close();

// Получение завершенных заявок
$query_completed = "SELECT * FROM clients WHERE request_status = 'завершена' ORDER BY updated_at DESC";
$result_completed = $conn->query($query_completed);
$completed_orders = $result_completed->fetch_all(MYSQLI_ASSOC);

// Получение всех компьютеров
$query_computers = "SELECT * FROM computers WHERE is_archived = 0 ORDER BY price DESC";
$result_computers = $conn->query($query_computers);
$computers = $result_computers->fetch_all(MYSQLI_ASSOC);

// Получение архивированных компьютеров
$query_archived_computers = "SELECT * FROM computers WHERE is_archived = 1 ORDER BY price DESC";
$result_archived_computers = $conn->query($query_archived_computers);
$archived_computers = $result_archived_computers->fetch_all(MYSQLI_ASSOC);

// Получение записей из журнала изменений
$log_query = "SELECT * FROM order_logs ORDER BY created_at DESC";
$log_result = $conn->query($log_query);
$change_logs = $log_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #121212;
            color: #e0e0e0;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        nav {
            margin-left: 35px;
            margin-top: 0px;
            display: flex;
            justify-content: space-between;
            width: 100%;
            padding: 20px;
            padding-top: 80px;
            background-color: #1e1e1e;
        }
        nav a {
            margin: 0 15px;
            color: #e0e0e0;
            text-decoration: none;
        }
        .buttons {
            display: flex;
        }
        .buttons a {
            margin: 0 5px;
            padding: 10px 20px;
            background-color: #26a8e5;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
        }
        .buttons a:hover {
            background-color: #228fc2;
        }
        .orders-container {
            display: flex;
            justify-content: space-between;
            width: 80%;
            margin: 20px 0;
            flex-wrap: wrap;
            font-family: 'Montserrat', sans-serif;
        }
        .orders-column {
            width: 48%;
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
            position: relative;
        }
        .orders-column h2 {
            margin: 0 0 20px 0;
            font-size: 1.5em;
            color: #e0e0e0;
        }
        .order-block {
            background-color: #2e2e2e;
            padding: 15px;
            margin: 10px 0;
            border-radius: 10px;
            position: relative;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .order-block h3 {
            margin: 0 0 10px 0;
            font-size: 1.2em;
        }
        .order-block p {
            margin: 5px 0;
        }
        .order-actions {
            display: flex;
            justify-content: flex-end;
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .order-actions button {
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 10px;
            padding: 5px;
            color: #e0e0e0;
            font-size: 1.2em;
            transition: color 0.3s;
        }
        .order-actions button:hover {
            color: #26a8e5;
        }
        .order-actions .edit-btn::before {
            content: url('/img/edit-icon.png');
        }
        .order-actions .complete-btn::before {
            content: url('/img/complete-icon.png');
        }
        .create-order-form {
            width: 80%;
            max-width: 800px;
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 0px 0px 10px 10px;
        }
        .create-order-form input, .create-order-form select, .create-order-form textarea, .create-order-form button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: none;
            border-radius: 5px;
        }
        .create-order-form input, .create-order-form select, .create-order-form textarea {
            background-color: #2e2e2e;
            color: #e0e0e0;
            font-family: 'Montserrat', sans-serif;
        }
        .create-order-form textarea {
            resize: vertical;
            min-height: 100px;
            font-family: 'Montserrat', sans-serif;
        }
        .create-order-form button {
            background-color: #26a8e5;
            color: #fff;
            cursor: pointer;
            border: none;
            transition: background-color 0.3s;
            font-family: 'Montserrat', sans-serif;
        }
        .create-order-form button:hover {
            background-color: #228fc2;
        }
        .mainmenu-button {
            margin-bottom: 0px;
            text-align: left;
            margin: 10px;
            display: block;
            position: absolute;
            top: -5px;
            left: 10px;
            z-index: 999;
        }
        .menu-image {
            width: 50px;
            height: 50px;
            transition: transform 0.3s ease;
        }
        .menu-link {
            display: inline-block;
        }
        .menu-link:hover .menu-image {
            transform: scale(1.2);
        }
    </style>
</head>
<body>
    <div class="mainmenu-button">
        <a href="/" class="link menu-link"><img src="/img/menu.png" class="menu-image"></a>
        <a id="backLink" class="link menu-link"><img src="/img/back.png" class="menu-image"></a>
        <?php 
        if(isset($_SESSION['login'])) {
            echo '<a href="/user/lk" class="link menu-link"><img src="/img/avatar.png" class="menu-image"></a>';
        } else {
            echo '<a href="/user/auth" class="link menu-link"><img src="/img/auth.png" class="menu-image"></a>';
        }
        ?>
        <a href="/user/bug_report" class="link menu-link"><img src="/img/report.png" class="menu-image"></a>
    </div>
    <nav>
        <div class="buttons">
            <a href="orders">Назад</a>
        </div>
    </nav>
    <div class="create-order-form">
    <h2>Создать заявку</h2>
    <form action="create_order" method="post">
        <label for="client_name">Имя клиента:</label>
        <input type="text" id="client_name" name="client_name" required>
        <label for="product">Товар:</label>
        <select id="product" name="product" required>
            <?php foreach ($computers as $computer): ?>
                <option value="<?php echo htmlspecialchars($computer['name']); ?>">
                    <?php echo htmlspecialchars($computer['name']); ?> - <?php echo number_format($computer['price'], 0,); ?> ₽
                </option>
            <?php endforeach; ?>
            <?php foreach ($archived_computers as $archived_computer): ?>
                <option value="<?php echo htmlspecialchars($archived_computer['name']); ?>">
                    <?php echo htmlspecialchars($archived_computer['name']); ?> - <?php echo number_format($archived_computer['price'], 0,); ?> ₽ (архив)
                </option>
            <?php endforeach; ?>
        </select>
        <label for="order_status">Статус заявки:</label>
        <select id="order_status" name="order_status" required>
            <option value="оплачен">Оплачен</option>
            <option value="выдан чек">Выдан чек</option>
            <option value="создана накладная">Создана накладная</option>
            <option value="отправлен">Отправлен</option>
            <option value="получен">Получен</option>
        </select>
        <label for="request_status">Статус запроса:</label>
        <select id="request_status" name="request_status" required>
            <option value="открыта">Открыта</option>
            <option value="завершена">Завершена</option>
        </select>
        <label for="additional_info">Дополнительная информация:</label>
        <textarea id="additional_info" name="additional_info"></textarea>
        <button type="submit">Создать заявку</button>
    </form>
    </div>
    <script>
        function toggleLog() {
            const logContainer = document.getElementById('logContainer');
            logContainer.classList.toggle('active');
        }
        function searchLog() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const logEntries = document.getElementsByClassName('log-entry');

            for (let i = 0; i < logEntries.length; i++) {
                const text = logEntries[i].textContent || logEntries[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    logEntries[i].style.display = '';
                } else {
                    logEntries[i].style.display = 'none';
                }
            }
        }
        document.getElementById('backLink').addEventListener('click', function(event) {
        event.preventDefault(); 
        if (document.referrer && document.referrer !== window.location.href) {
            window.history.back(); 
        } else {
            window.location.href = '/pclist'; 
        }
        });
    </script>
</body>
</html>