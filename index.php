<?php
session_start();
require 'user/db_connect.php';

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
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="/img/home.png" />
    <title>Главная страница</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Montserrat', sans-serif;
            background-color: #121212;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            padding-top: 50px;
            text-align: center;
        }
        .btn-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 20px;
        }
        .btn {
            background-color: #1e1e1e;
            color: #ffffff;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            width: 200px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        .btn:hover {
            background-color: #333333;
            color: #ffffff;
            transform: scale(1.05);
        }
        h1 {
            margin-bottom: 50px;
            font-size: 36px;
            color: #bb86fc;
        }
        .mainmenu-button {
            text-align: left;
            margin: 10px;
            display: block;
            position: absolute;
            top: 10px;
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
        /* Соц. сети */
        .social-links {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .social-links img {
            width: 40px;
            height: 40px;
            transition: transform 0.3s ease;
        }
        .social-links a:hover img {
            transform: scale(1.2);
        }
    </style>
</head>
<body>

<!-- Меню авторизации -->
<div class="mainmenu-button">
    <?php 
    if (isset($_SESSION['login'])) {
        echo '<a href="/user/lk" class="link menu-link"><img src="/img/avatar.png" class="menu-image"></a>';
    } else {
        echo '<a href="/user/auth" class="link menu-link"><img src="/img/reg.png" class="menu-image"></a>';
    }
    ?>
    <a href="/user/bug_report" class="link menu-link"><img src="/img/report.png" class="menu-image"></a>
</div>

<!-- Основной контент -->
<div class="container">
    <h1>Разделы</h1>
    <div class="btn-container">
        <a href="sites/list" class="btn">Сайты</a>
        <a href="programs/list" class="btn">Программы</a>
        <a href="anime/main" class="btn">Аниме</a>
        <a href="video_content/main" class="btn">Фильмы и сериалы</a>
    </div>
</div>

<!-- Ссылки на соц. сети -->
<div class="social-links">
    <a href="https://vk.com/boriskit" target="_blank"><img src="/img/vk.png" alt="VK"></a>
    <a href="https://t.me/boriskit" target="_blank"><img src="/img/tg.png" alt="Telegram"></a>
    <a href="https://www.youtube.com/@boriskitty" target="_blank"><img src="/img/yt.png" alt="YouTube"></a>
</div>

</body>
</html>
