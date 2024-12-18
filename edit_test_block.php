<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['login'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../user/auth");
    exit();
}
require '../user/db_connect.php';

// Проверка роли пользователя
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

// Получение информации о тестовом блоке
$test_block_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($test_block_id <= 0) {
    die("Неверный идентификатор тестового блока.");
}

$query = "SELECT * FROM test_blocks WHERE id = ?";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Ошибка подготовки запроса: " . $conn->error);
}
$stmt->bind_param('i', $test_block_id);
$stmt->execute();
$result = $stmt->get_result();
$test_block = $result->fetch_assoc();
if (!$test_block) {
    die("Тестовый блок не найден.");
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $temperature_cpu = $_POST['temperature_cpu'];
    $temperature_gpu = $_POST['temperature_gpu'];
    $additional_info = $_POST['additional_info'];

    // Обновление данных в таблице test_blocks
    $update_query = "UPDATE test_blocks SET temperature_cpu = ?, temperature_gpu = ?, additional_info = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt === false) {
        die("Ошибка подготовки запроса: " . $conn->error);
    }
    $stmt->bind_param('ddsi', $temperature_cpu, $temperature_gpu, $additional_info, $test_block_id);

    if ($stmt->execute()) {
        // Запись в журнал изменений
        $log_query = "INSERT INTO test_block_logs (test_block_id, user_login, action) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        if ($log_stmt === false) {
            die("Ошибка подготовки запроса журнала: " . $conn->error);
        }
        $user_login = $_SESSION['login'];
        $action = "обновил значения для";
        $log_stmt->bind_param('iss', $test_block_id, $user_login, $action);
        $log_stmt->execute();

        header("Location: test_base");
        exit();
    } else {
        die("Ошибка выполнения запроса: " . $stmt->error);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать тестовый блок</title>
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
            display: flex;
            justify-content: flex-start;
            width: 100%;
            padding: 20px;
            padding-top: 80px;
            background-color: #1e1e1e;
        }
        nav a {
            color: #e0e0e0;
            text-decoration: none;
            margin: 0 15px;
            font-size: 1.2em;
        }
        .form-container {
            margin-top: 80px;
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            width: 60%;
        }
        .form-group {
            font-family: 'Montserrat', sans-serif;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .form-group label {
            font-family: 'Montserrat', sans-serif;
            display: block;
            margin-bottom: 5px;
            text-align: center;
        }
        .form-group input, .form-group textarea {
            font-family: 'Montserrat', sans-serif;
            width: 100%;
            max-width: 400px;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #333;
            background-color: #2b2b2b;
            color: #e0e0e0;
        }
        .form-group input[type="number"] {
            -moz-appearance: textfield; /* Firefox */
            -webkit-appearance: none; /* Safari and Chrome */
            appearance: none; /* Standard */
        }
        .form-group input[type="number"]::-webkit-inner-spin-button,
        .form-group input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .form-group input[type="submit"] {
            background-color: #26a8e5;
            color: #fff;
            border: none;
            cursor: pointer;
            max-width: 200px;
        }
        .form-group input[type="submit"]:hover {
            background-color: #228fc2;
        }
        .form-group h2 {
            margin: 0 0 20px;
            text-align: center;
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
        /* Медиа-запросы для мобильных устройств */
        @media (max-width: 600px) {
            .form-container {
                width: 90%;
                padding: 10px;
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
    <div class="form-container">
        <div class="form-group">
            <h2>Редактировать тестовый блок</h2>
        </div>
        <form method="post" action="">
            <div class="form-group">
                <label for="temperature_cpu">Температура ЦП:</label>
                <input type="number" id="temperature_cpu" name="temperature_cpu" value="<?php echo htmlspecialchars($test_block['temperature_cpu']); ?>" step="0.1">
            </div>
            <div class="form-group">
                <label for="temperature_gpu">Температура ГП:</label>
                <input type="number" id="temperature_gpu" name="temperature_gpu" value="<?php echo htmlspecialchars($test_block['temperature_gpu']); ?>" step="0.1">
            </div>
            <div class="form-group">
                <label for="additional_info">Дополнительная информация:</label>
                <textarea id="additional_info" name="additional_info" rows="4"><?php echo htmlspecialchars($test_block['additional_info']); ?></textarea>
            </div>
            <div class="form-group">
                <input type="submit" value="Сохранить">
            </div>
        </form>
    </div>
</body>
<script>
    document.getElementById('backLink').addEventListener('click', function(event) {
    event.preventDefault(); 
    if (document.referrer && document.referrer !== window.location.href) {
        window.history.back(); 
    } else {
        window.location.href = '/pclist'; 
    }
});
</script>
</html>
