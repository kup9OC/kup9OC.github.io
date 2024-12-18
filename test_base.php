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

// Получение всех тестов для компьютеров, сортировка по дате создания
$query = "SELECT tb.*, c.name AS computer_name, c.price, c.images 
          FROM test_blocks tb 
          JOIN computers c ON tb.computer_id = c.id 
          ORDER BY tb.created_at DESC";
$result = $conn->query($query);
if (!$result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$test_blocks = $result->fetch_all(MYSQLI_ASSOC);

// Получение записей из журнала изменений для тестов
$log_query = "
    SELECT l.*, tb.computer_id, c.name AS computer_name
    FROM test_block_logs l
    JOIN test_blocks tb ON l.test_block_id = tb.id
    JOIN computers c ON tb.computer_id = c.id
    ORDER BY l.timestamp DESC
";
$log_result = $conn->query($log_query);
if (!$log_result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$change_logs = $log_result->fetch_all(MYSQLI_ASSOC);

// Функция для получения класса цвета температуры
function getTemperatureClass($temperature) {
    if ($temperature >= 30 && $temperature <= 60) {
        return 'temperature-low';
    } elseif ($temperature >= 61 && $temperature <= 80) {
        return 'temperature-medium';
    } elseif ($temperature >= 81 && $temperature <= 120) {
        return 'temperature-high';
    } else {
        return ''; // Если температура вне диапазона, не применяем класс
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>База тестов</title>
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
            margin-top: 0;
            display: flex;
            justify-content: center;
            width: 100%;
            padding: 20px;
            background-color: #1e1e1e;
            box-sizing: border-box;
        }

        nav .buttons {
            padding-top: 10px;
            padding-bottom: 10px;
            padding-left: 5px;
            padding-right: 5px;
            padding-top: 80px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }

        nav a {
            padding: 10px;
            background-color: #26a8e5;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            text-align: center;
            white-space: nowrap;
            box-sizing: border-box;
        }

        nav a:hover {
            background-color: #228fc2;
        }

        .test-blocks-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            padding: 20px;
            width: 90%;
            box-sizing: border-box;
        }

        .test-block {
            width: 250px;
            background-color: #1e1e1e;
            margin: 10px;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .test-block img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .test-block h3 {
            margin: 10px 0;
        }

        .test-block p {
            margin: 5px 0;
        }

        .test-block .details {
            display: none;
            margin-top: 10px;
            text-align: left;
        }

        .test-block .details p {
            margin: 5px 0;
        }

        .test-block.expanded .details {
            display: block;
        }

        .test-block button {
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

        .test-block button:hover {
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
                padding: 10px;
            }

            .buttons {
                flex-direction: column;
                align-items: center;
                gap: 5px;
            }

            nav a {
                width: 100%;
            }

            .test-blocks-container {
                width: 100%;
            }

            .test-block {
                width: calc(100% - 20px);
            }
        }

        /* Стили для температурных классов */
        .temperature-low {
            color: #4CAF50; /* Зеленый для температуры от 30 до 60 */
        }

        .temperature-medium {
            color: #FFEB3B; /* Желтый для температуры от 61 до 80 */
        }

        .temperature-high {
            color: #F44336; /* Красный для температуры от 81 до 120 */
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
            <a href="create_test_block">Создать запись</a>
        </div>
    </nav>
    <div class="log-container" id="logContainer">
        <div class="search-bar">
            <input type="text" id="searchInput" onkeyup="searchLog()" placeholder="Поиск по действиям...">
        </div>
        <?php foreach ($change_logs as $log): ?>
            <div class="log-entry">
                <p>
                    <?php echo htmlspecialchars($log['user_login']); ?> 
                    <?php echo htmlspecialchars($log['action']); ?> 
                    <a href="view_computer?id=<?php echo htmlspecialchars($log['computer_id']); ?>">
                        <?php echo htmlspecialchars($log['computer_name']); ?>
                    </a>
                </p>
                <p><?php echo date("d.m.Y H:i", strtotime($log['timestamp'])); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="log-toggle" onclick="toggleLog()">≡</div>
    <div class="test-blocks-container">
        <?php foreach ($test_blocks as $block): ?>
            <div class="test-block" onclick="this.classList.toggle('expanded')">
                <?php
                $images = json_decode($block['images'], true);
                if (!empty($images)) {
                    echo '<img src="'.$images[0].'" alt="'.$block['computer_name'].'">';
                }
                ?>
                <h3><?php echo htmlspecialchars($block['computer_name']); ?></h3>
                <p>Цена: <?php echo htmlspecialchars($block['price']); ?> руб.</p>
                <div class="details">
                    <p class="<?php echo getTemperatureClass($block['temperature_cpu']); ?>">
                        Температура ЦП: <?php echo htmlspecialchars($block['temperature_cpu']); ?>°C
                    </p>
                    <p class="<?php echo getTemperatureClass($block['temperature_gpu']); ?>">
                        Температура ГП: <?php echo htmlspecialchars($block['temperature_gpu']); ?>°C
                    </p>
                    <?php if (!empty($block['additional_info'])): ?>
                        <p>Дополнительная информация: <?php echo htmlspecialchars($block['additional_info']); ?></p>
                    <?php endif; ?>
                    <p>Дата создания: <?php echo date("d.m.Y", strtotime($block['created_at'])); ?></p>
                    <p>Дата редактирования: <?php echo date("d.m.Y", strtotime($block['updated_at'])); ?></p>
                    <button onclick="window.location.href='edit_test_block?id=<?php echo htmlspecialchars($block['id']); ?>'">Изменить</button>
                </div>
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

        window.addEventListener('load', function() {
            const buttons = document.querySelectorAll('nav .buttons a');
            let maxWidth = 0;

            buttons.forEach(button => {
                const width = button.offsetWidth;
                if (width > maxWidth) {
                    maxWidth = width;
                }
            });

            buttons.forEach(button => {
                button.style.width = `${maxWidth}px`;
            });
        });
    </script>
</body>
</html>
