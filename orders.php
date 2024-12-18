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
            max-width: 500px;
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
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
        }
        .create-order-form textarea {
            resize: vertical;
            min-height: 100px;
        }
        .create-order-form button {
            background-color: #26a8e5;
            color: #fff;
            cursor: pointer;
            border: none;
            transition: background-color 0.3s;
        }
        .create-order-form button:hover {
            background-color: #228fc2;
        }
        .log-container {
            position: fixed;
            left: -300px;
            top: 0;
            width: 300px;
            height: 100%;
            background-color: #313131;
            overflow-y: auto;
            transition: left 0.3s;
            z-index: 1000;
        }
        .log-container.active {
            left: 0px;
            padding-left: 40px;
            max-width: 400px;
        }
        .log-toggle {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 100px;
            background-color: #26a8e5;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            border-top-right-radius: 5px;
            border-bottom-right-radius: 5px;
            transition: left 0.3s;
            z-index: 1001;
        }
        .log-container.active ~ .log-toggle {
            left: 300px;
        }
        .log-entry {
            padding: 10px;
            border-bottom: 2px solid #515151;
        }
        .log-entry p {
            margin: 5px 0;
        }
        .log-entry a {
            color: #26a8e5;
            text-decoration: none;
        }
        .log-entry a:hover {
            text-decoration: underline;
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

        /* Мобильные стили */
        @media (max-width: 768px) {
            .orders-container {
                flex-direction: column;
            }
            .orders-column {
                width: 100%;
                margin-bottom: 20px;
                font-size: 0.9em; /* Уменьшаем размер шрифта */
            }
            .order-block {
                padding: 10px;
            }
            .order-actions {
                position: static;
                flex-direction: column;
                align-items: flex-end;
                margin-top: 10px;
            }
            .order-actions button {
                font-size: 1em; /* Уменьшаем размер шрифта для кнопок */
                margin-left: 0;
                margin-bottom: 10px;
                padding: 8px;
            }
            .log-container {
                width: 100%;
                left: -100%;
                /* Убираем фиксированное положение для мобильных устройств */
                height: auto;
                top: 0;
            }
            .log-container.active {
                left: 0;
            }
            .log-toggle {
                height: 50px;
                /* Уменьшаем высоту для мобильных устройств */
            }
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
            <a href="pc_list">К компьютерам</a>
            <a href="new_order">Создать заявку</a>
        </div>
    </nav>
    <div class="orders-container">
        <div class="orders-column">
            <h2>Активные заявки</h2>
            <?php if (!empty($active_orders)): ?>
                <?php foreach ($active_orders as $order): ?>
                    <?php
                    // Получение цены продукта из таблицы компьютеров
                    $product_name = $order['product'];
                    $query_price = "SELECT price FROM computers WHERE name = ?";
                    $stmt_price = $conn->prepare($query_price);
                    $stmt_price->bind_param("s", $product_name);
                    $stmt_price->execute();
                    $result_price = $stmt_price->get_result();
                    $product_price = $result_price->fetch_assoc()['price'];
                    $stmt_price->close();
                    ?>
                    <div class="order-block">
                        <h3><?php echo htmlspecialchars($order['product']); ?> - <?php echo number_format($product_price, 0,); ?> ₽</h3>
                        <p style="margin-top: 20px;"><a style="background-color: #2452a1; padding: 5px; border-radius: 4px;">Клиент: <?php echo htmlspecialchars($order['name']); ?></a></p>
                        <p style="margin-top: 20px;"><a style="background-color: #a17d24; padding: 5px; border-radius: 4px;">Стадия заказа: <?php echo htmlspecialchars($order['order_status']); ?></a></p>
                        <p style="margin-top: 20px;"><a style="background-color: #a12424; padding: 5px; border-radius: 4px;">Статус заявки: <?php echo htmlspecialchars($order['request_status']); ?></a></p>
                        <p style="margin-top: 20px;"><a style="background-color: #a24676; padding: 5px; border-radius: 4px;">Дополнительная информация: <?php echo htmlspecialchars($order['additional_info']);?></a></p>
                        <p style="margin-top: 20px;"><a style="background-color: #a24676; padding: 5px; border-radius: 4px;">Создано: <?php echo date('d.m.Y H:i:s', strtotime($order['created_at'])); ?></a></p>
                        <p style="margin-top: 20px;"><a style="background-color: #a24676; padding: 5px; border-radius: 4px;">Обновлено: <?php echo date('d.m.Y H:i:s', strtotime($order['updated_at'])); ?></a></p>
                        <div class="order-actions">
                            <button class="edit-btn" onclick="window.location.href='edit_order?id=<?php echo $order['id']; ?>'"></button>
                            <?php if ($order['request_status'] === 'открыта'): ?>
                                <button class="complete-btn" onclick="window.location.href='complete_order?id=<?php echo $order['id']; ?>'"></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Нет активных заявок.</p>
            <?php endif; ?>
        </div>
        <div class="orders-column">
            <h2>Завершенные заявки</h2>
            <?php if (!empty($completed_orders)): ?>
                <?php foreach ($completed_orders as $order): ?>
                    <?php
                    // Получение цены продукта из таблицы компьютеров
                    $product_name = $order['product'];
                    $query_price = "SELECT price FROM computers WHERE name = ?";
                    $stmt_price = $conn->prepare($query_price);
                    $stmt_price->bind_param("s", $product_name);
                    $stmt_price->execute();
                    $result_price = $stmt_price->get_result();
                    $product_price = $result_price->fetch_assoc()['price'];
                    $stmt_price->close();
                    ?>
                    <div class="order-block">
                        <h3><?php echo htmlspecialchars($order['product']); ?> - <?php echo number_format($product_price, 0,); ?> ₽</h3>
                        <p>Клиент: <?php echo htmlspecialchars($order['name']); ?></p>
                        <p>Статус заявки: <?php echo htmlspecialchars($order['order_status']); ?></p>
                        <p>Статус запроса: <?php echo htmlspecialchars($order['request_status']); ?></p>
                        <p>Дополнительная информация: <?php echo htmlspecialchars($order['additional_info']); ?></p>
                        <p>Обновлено: <?php echo date('d.m.Y H:i:s', strtotime($order['updated_at'])); ?></p>
                        <div class="order-actions">
                            <button class="edit-btn" onclick="window.location.href='edit_order?id=<?php echo $order['id']; ?>'"></button>
                            <!-- Не показываем кнопку завершения заявки для завершенных заявок -->
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Нет завершенных заявок.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="log-container" id="logContainer">
        <h2>Журнал изменений</h2>
        <?php if (!empty($change_logs)): ?>
            <?php foreach ($change_logs as $log): ?>
                <div class="log-entry">
                    <p><strong>Время:</strong> <?php echo date("d.m.Y H:i:s", strtotime($log['created_at'])); ?></p>
                    <p><strong>Действие:</strong> <?php
                        $action_message = '';
                        switch ($log['action']) {
                            case 'create':
                                $action_message = "{$log['user_login']} создал заявку для клиента {$log['client_name']}";
                                break;
                            case 'update':
                                $action_message = "{$log['user_login']} изменил статус заявки клиента {$log['client_name']}";
                                break;
                            case 'close':
                                $action_message = "{$log['user_login']} закрыл заявку клиента {$log['client_name']}";
                                break;
                            case 'add_info':
                                $action_message = "{$log['user_login']} добавил дополнительную информацию для заявки клиента {$log['client_name']}";
                                break;
                        }
                        echo htmlspecialchars($action_message);
                    ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Нет записей в журнале изменений.</p>
        <?php endif; ?>
        <div class="log-toggle" onclick="toggleLog()">≡</div>
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
