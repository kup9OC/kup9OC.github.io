<?php
session_start();
if (!isset($_SESSION['login'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../user/auth");
    exit();
}
require '../user/db_connect.php';

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

// Получение всех компьютеров, не находящихся в архиве и сортировка по убыванию цены
$query = "SELECT * FROM computers WHERE is_archived = 0 ORDER BY price DESC LIMIT 30";
$result = $conn->query($query);
if (!$result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$computers = $result->fetch_all(MYSQLI_ASSOC);

// Получение записей из журнала изменений
$log_query = "SELECT * FROM change_log ORDER BY timestamp DESC";
$log_result = $conn->query($log_query);
if (!$log_result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$change_logs = $log_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Доступные компьютеры</title>
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
            margin: 0;
            padding: 20px;
            background-color: #1e1e1e;
            width: 100%;
            display: flex;
            flex-wrap: wrap; /* Позволяет элементам переноситься на новую строку */
            justify-content: center; /* Центрирование кнопок */
        }
        nav .buttons {
            padding-top: 80px;
            display: flex;
            flex-wrap: wrap; /* Позволяет кнопкам переноситься на новую строку */
            justify-content: center; /* Центрирование кнопок */
            gap: 5px; /* Пробел между кнопками */
        }
        nav a {
            margin: 5px;
            padding-top: 10px;
            padding-bottom: 10px;
            padding-left: 5px;
            padding-right: 5px;
            background-color: #26a8e5;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            text-align: center; /* Центрирование текста */
            white-space: nowrap; /* Не позволяет тексту переноситься */
        }
        nav a:hover {
            background-color: #228fc2;
        }
        .computers-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            padding: 20px;
            width: 80%;
        }
        .computer {
            width: 250px;
            background-color: #1e1e1e;
            margin: 10px;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .computer img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 10px;
        }
        .computer h3 {
            margin: 10px 0;
        }
        .computer p {
            margin: 5px 0;
        }
        .computer button {
            font-family: 'Montserrat', sans-serif;
            padding: 10px;
            margin-top: 10px;
            border: none;
            border-radius: 5px;
            width: 100%;
            background-color: #26a8e5;
            color: #fff;
            cursor: pointer;
            font-size: 1em;
        }
        .computer button:hover {
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
            padding: 10px;
            box-sizing: border-box;
        }
        .log-container.active {
            left: 0;
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
        .search-bar {
            padding: 20px;
        }
        .search-bar input {
            width: calc(100% - 20px);
            padding: 10px;
            font-size: 1em;
            border-radius: 5px;
            border: none;
            font-family: 'Montserrat', sans-serif;
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
        /* Медиа-запросы для маленьких экранов */
        @media (max-width: 600px) {
            nav {
                padding: 10px; /* Уменьшите внутренние отступы на маленьких экранах */
            }

            nav .buttons {
                flex-direction: column; /* Расположите кнопки вертикально */
                align-items: center; /* Выравнивание кнопок по центру */
            }

            nav a {
                margin: 5px 0; /* Уменьшите отступы между кнопками */
                width: 100%; /* Кнопки занимают всю доступную ширину */
                text-align: center; /* Выравнивание текста по центру */
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
            echo '<a href="/user/auth" class="link menu-link"><img src="/img/reg.png" class="menu-image"></a>';
        }
        ?>
        <a href="/user/bug_report" class="link menu-link"><img src="/img/report.png" class="menu-image"></a>
    </div>
    <nav>
        <div class="buttons">
            <a href="add_computer">Добавить компьютер</a>
            <a href="archive">Архив</a>
            <a href="orders">База клиентов</a>
            <a href="test_base">База тестов</a>
        </div>
    </nav>
    <div class="log-container" id="logContainer">
        <div class="search-bar">
            <input type="text" id="searchInput" onkeyup="searchLog()" placeholder="Поиск по действиям...">
        </div>
        <?php foreach ($change_logs as $log): ?>
            <div class="log-entry">
                <p><?php echo htmlspecialchars($log['user_login']); ?> <?php echo htmlspecialchars($log['action']); ?> <a href="view_computer?id=<?php echo htmlspecialchars($log['computer_id']); ?>"><?php echo htmlspecialchars($log['computer_name']); ?></a></p>
                <p><?php echo date("d.m.Y H:i", strtotime($log['timestamp'])); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="log-toggle" onclick="toggleLog()">≡</div>
    <div class="computers-container">
        <?php foreach ($computers as $computer): ?>
            <div class="computer">
                <?php
                $images = json_decode($computer['images'], true);
                if (!empty($images)) {
                    echo '<img src="'.htmlspecialchars($images[0]).'" alt="'.htmlspecialchars($computer['name']).'">';
                }
                ?>
                <h3><?php echo htmlspecialchars($computer['name']); ?></h3>
                <p>Цена: <?php echo htmlspecialchars($computer['price']); ?> руб.</p>
                <p>В наличии: <?php echo htmlspecialchars($computer['stock']); ?></p>
                <button onclick="window.location.href='edit_computer?id=<?php echo htmlspecialchars($computer['id']); ?>'">Редактировать</button>
                <button onclick="window.location.href='view_computer?id=<?php echo htmlspecialchars($computer['id']); ?>'">Смотреть</button>
            </div>
        <?php endforeach; ?>
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
