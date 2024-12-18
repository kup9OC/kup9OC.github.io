<?php
// Начало сессии и проверка авторизован ли пользователь, если нет = пересылаем на страницу с авторизацией
session_start();
if (!isset($_SESSION['login'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../user/auth");
    exit();
}
// Подключение к базе данных
require '../user/db_connect.php';

// Проверка наличия роли админа или rempc
$isRemPC = ($_SESSION['user_role'] === 'rempc');
$isAdmin = ($_SESSION['user_role'] === 'admin');

// Если роль не совпала = возвращаем пользователя в личный кабинет
if (!$isRemPC && !$isAdmin) {
    header("Location: /user/lk");
    exit();
}
// Снизу блок, который нужен для отслеживания посещения страниц пользователями сайта
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
// Данный блок отвечает за отправку данных, которые пометили как архивированные компьютеры (по ID в базе данных)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    if ($_GET['action'] == 'archive') {
        // Архивирование компьютера
        $stmt = $conn->prepare("UPDATE computers SET is_archived = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Запись действия в журнал
        $user_login = $_SESSION['login'];
        $action = "добавил в архив товар";
        $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name) VALUES (?, ?, (SELECT name FROM computers WHERE id = ?))");
        $stmt_log->bind_param("ssi", $user_login, $action, $id);
        $stmt_log->execute();
    } elseif ($_GET['action'] == 'restore') {
        // Восстановление компьютера из архива
        $stmt = $conn->prepare("UPDATE computers SET is_archived = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Запись действия в журнал
        $user_login = $_SESSION['login'];
        $action = "достал из архива товар";
        $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name) VALUES (?, ?, (SELECT name FROM computers WHERE id = ?))");
        $stmt_log->bind_param("ssi", $user_login, $action, $id);
        $stmt_log->execute();
    }
    header("Location: archive");
    exit();
}

$query = "SELECT * FROM computers WHERE is_archived = 1";
$result = $conn->query($query);
$computers = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Архивированные компьютеры</title>
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
            padding-top: 10px;
            padding-bottom: 10px;
            padding-left: 5px;
            padding-right: 5px;
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

        .buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
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

            .buttons {
                flex-direction: column; /* Расположите кнопки вертикально */
                align-items: center; /* Выравнивание кнопок по центру */
            }

            .buttons a {
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
            <a href="orders">База клиентов</a>
            <a href="test_base">База тестов</a>
            <a href="pc_list">Назад</a>
        </div>
    </nav>
    <div class="computers-container">
        <?php foreach ($computers as $computer): ?>
            <div class="computer">
                <?php
                $images = json_decode($computer['images'], true);
                if (!empty($images)) {
                    echo '<img src="'.$images[0].'" alt="'.$computer['name'].'">';
                }
                ?>
                <h3><?php echo htmlspecialchars($computer['name']); ?></h3>
                <p>Цена: <?php echo htmlspecialchars($computer['price']); ?> руб.</p>
                <p>В наличии: <?php echo htmlspecialchars($computer['stock']); ?></p>
                <button onclick="window.location.href='edit_computer.php?id=<?php echo htmlspecialchars($computer['id']); ?>'">Редактировать</button>
                <button onclick="window.location.href='view_computer.php?id=<?php echo htmlspecialchars($computer['id']); ?>'">Смотреть</button>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        /* Скрипт на возвращение на предыдущую страницу, используя кнопку "Назад"*/
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
