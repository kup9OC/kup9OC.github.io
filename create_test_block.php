<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['login'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../user/auth");
    exit();
}
// Подключение к базе данных
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

// Получение списка компьютеров
$query = "SELECT id, name, price, images FROM computers WHERE is_archived = 0 ORDER BY name";
$result = $conn->query($query);
if (!$result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$computers = $result->fetch_all(MYSQLI_ASSOC);

// Получение списка архивных компьютеров
$archive_query = "SELECT id, name, price, images FROM computers WHERE is_archived = 1 ORDER BY name";
$archive_result = $conn->query($archive_query);
if (!$archive_result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$archived_computers = $archive_result->fetch_all(MYSQLI_ASSOC);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $computer_id = $_POST['computer_id'];
    $temperature_cpu = $_POST['temperature_cpu'];
    $temperature_gpu = $_POST['temperature_gpu'];
    $additional_info = $_POST['additional_info'];

    // Вставка данных в таблицу test_blocks
    $insert_query = "INSERT INTO test_blocks (computer_id, temperature_cpu, temperature_gpu, additional_info, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($insert_query);
    if ($stmt === false) {
        die("Ошибка подготовки запроса: " . $conn->error);
    }
    $stmt->bind_param('idss', $computer_id, $temperature_cpu, $temperature_gpu, $additional_info);

    if ($stmt->execute()) {
        // Получение названия компьютера
        $computer_name_query = "SELECT name FROM computers WHERE id = ?";
        $name_stmt = $conn->prepare($computer_name_query);
        if ($name_stmt === false) {
            die("Ошибка подготовки запроса: " . $conn->error);
        }
        $name_stmt->bind_param('i', $computer_id);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result();
        $computer = $name_result->fetch_assoc();
        $computer_name = $computer['name'];

        // Запись в журнал изменений
        $log_query = "INSERT INTO test_block_logs (test_block_id, user_login, action) 
                      VALUES (?, ?, ?)";
        $test_block_id = $stmt->insert_id;
        $user_login = $_SESSION['login'];
        $action = "cоздал запись для компьютера";
        $log_stmt = $conn->prepare($log_query);
        if ($log_stmt === false) {
            die("Ошибка подготовки запроса журнала: " . $conn->error);
        }
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
    <title>Создать тестовый блок</title>
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
        .form-group input, .form-group select, .form-group textarea {
            font-family: 'Montserrat', sans-serif;
            width: 100%;
            max-width: 400px;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #333;
            background-color: #2b2b2b;
            color: #e0e0e0;
            margin-left: 10px;
            margin-right: 10px;
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
        .computer-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center; /* Центрирование блоков по центру */
        }
        .computer-item {
            width: 150px;
            background-color: #2b2b2b;
            padding: 10px;
            border-radius: 10px;
            text-align: center;
            color: #e0e0e0;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .computer-item.selected {
            background-color: #333;
        }
        .computer-item img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
        }
        .computer-item .price {
            display: block;
            margin-top: 5px;
        }
        .archive-button {
            font-family: 'Montserrat', sans-serif;
            background-color: #26a8e5;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .archive-button:hover {
            background-color: #228fc2;
        }
        .archive-list {
            display: none;
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
        @media (max-width: 600px) {
            .computer-list {
                justify-content: center; /* Центрирование на мобильных устройствах */
                flex-direction: column; /* Расположение блоков в колонку на мобильных устройствах */
                align-items: center;
            }
            .computer-item {
                width: 80%; /* Ширина блоков на мобильных устройствах */
                max-width: 300px; /* Максимальная ширина блоков */
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
            <h2>Создать тестовый блок</h2>
        </div>
        <form method="post" action="">
            <div class="form-group">
                <label for="computer_id">Выберите компьютер:</label>
                <div class="computer-list" id="computerList">
                    <?php foreach ($computers as $computer): ?>
                        <?php
                        $images = json_decode($computer['images'], true);
                        $first_image = !empty($images) ? htmlspecialchars($images[0]) : 'no-image.png';
                        ?>
                        <div class="computer-item" data-id="<?php echo htmlspecialchars($computer['id']); ?>" onclick="selectComputer(this)">
                            <img src="<?php echo htmlspecialchars($first_image); ?>" alt="<?php echo htmlspecialchars($computer['name']); ?>">
                            <div><?php echo htmlspecialchars($computer['name']); ?></div>
                            <div class="price"><?php echo htmlspecialchars($computer['price']); ?> руб.</div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="archive-button" onclick="toggleArchiveList()">Показать компьютеры из архива</button>
                <div class="computer-list archive-list" id="archiveList">
                    <?php foreach ($archived_computers as $computer): ?>
                        <?php
                        $images = json_decode($computer['images'], true);
                        $first_image = !empty($images) ? htmlspecialchars($images[0]) : 'no-image.png';
                        ?>
                        <div class="computer-item" data-id="<?php echo htmlspecialchars($computer['id']); ?>" onclick="selectComputer(this)">
                            <img src="<?php echo htmlspecialchars($first_image); ?>" alt="<?php echo htmlspecialchars($computer['name']); ?>">
                            <div><?php echo htmlspecialchars($computer['name']); ?></div>
                            <div class="price"><?php echo htmlspecialchars($computer['price']); ?> руб.</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <input type="hidden" name="computer_id" id="hiddenComputerId" value="">
            <div class="form-group">
                <label for="temperature_cpu">Температура ЦП:</label>
                <input type="number" id="temperature_cpu" name="temperature_cpu">
            </div>
            <div class="form-group">
                <label for="temperature_gpu">Температура ГП:</label>
                <input type="number" id="temperature_gpu" name="temperature_gpu">
            </div>
            <div class="form-group">
                <label for="additional_info">Дополнительная информация:</label>
                <textarea id="additional_info" name="additional_info" rows="4"></textarea>
            </div>
            <div class="form-group">
                <input type="submit" value="Сохранить">
            </div>
        </form>
    </div>
    <script>
        function selectComputer(element) {
            const computerList = document.querySelectorAll('.computer-item');
            computerList.forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('hiddenComputerId').value = element.getAttribute('data-id');
        }

        function toggleArchiveList() {
            const archiveList = document.getElementById('archiveList');
            if (archiveList.style.display === 'none' || archiveList.style.display === '') {
                archiveList.style.display = 'flex';
            } else {
                archiveList.style.display = 'none';
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
