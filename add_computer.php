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
// Данный блок отвечает за отправку данных, введенных в строчку (название, цена, наличие, описание)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $description = $_POST['description'];

    // Обработчик изображений
    $images = [];
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($_FILES["image$i"]['name'])) {
            $target_dir = "../uploads/pc/"; // Директория фоток
            $target_file = $target_dir . basename($_FILES["image$i"]["name"]);
            move_uploaded_file($_FILES["image$i"]["tmp_name"], $target_file);
            $images[] = $target_file;
        }
    }

    $images_json = json_encode($images);

    $stmt = $conn->prepare("INSERT INTO computers (name, price, stock, description, images) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $name, $price, $stock, $description, $images_json);
    $stmt->execute();

    // Запись действия в журнал (логи)
    $user_login = $_SESSION['login'];
    $action = "добавил новый компьютер за $price рублей";
    $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, new_value) VALUES (?, ?, ?, ?)");
    $stmt_log->bind_param("ssss", $user_login, $action, $name, $price);
    $stmt_log->execute();

    header("Location: pc_list");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить компьютер</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #121212;
            color: #e0e0e0;
            margin: 0;
        }
        .container {
            padding-left: 20px;
            padding-right: 40px;
            padding-top: 60px;
            max-width: 800px;
            margin: auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: none;
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        .form-group textarea {
            resize: vertical;
            height: 150px;
        }
        .images-preview {
            display: flex;
            flex-wrap: wrap;
        }
        .image-upload {
            width: 150px;
            height: 150px;
            margin: 10px;
            background-color: #333;
            border: 2px dashed #555;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
        }
        .image-upload input[type="file"] {
            display: none;
        }
        .image-upload img {
            max-width: 150px;
            max-height: 150px;
            display: block;
        }
        .submit-btn {
            background-color: #26a8e5;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
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
            echo '<a href="/user/auth" class="link menu-link"><img src="/img/reg.png" class="menu-image"></a>';
        }
        ?>
        <a href="/user/bug_report" class="link menu-link"><img src="/img/report.png" class="menu-image"></a>
    </div>
    <div class="container">
        <h1>Добавить компьютер</h1>
        <form action="add_computer" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Название</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="price">Цена</label>
                <input type="number" id="price" name="price" required>
            </div>
            <div class="form-group">
                <label for="stock">В наличии</label>
                <input type="number" id="stock" name="stock" required>
            </div>
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" required></textarea>
            </div>
            <div class="form-group">
    <label>Изображения</label>
    <div class="images-preview">
        <div class="image-upload">
            <label>
                <input type="file" name="images[]" onchange="handleFileUpload(this)">
                <img src="upload_icon.png" alt="Загрузить изображение">
            </label>
                </div>
            </div>
        </div>
            <button type="submit" class="submit-btn">Добавить компьютер</button>
        </form>
    </div>
    <script>
        let imageUploadCount = 1;
        /* Скрипт на загрузку изображений*/
        function handleFileUpload(input) {
            const reader = new FileReader();
            reader.onload = function(e) {
                input.parentElement.querySelector('img').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);

            if (imageUploadCount < 5) {
                const imagesPreview = document.querySelector('.images-preview');
        
                const newImageUpload = document.createElement('div');
                newImageUpload.className = 'image-upload';
                newImageUpload.innerHTML = `
                    <label>
                        <input type="file" name="images[]" onchange="handleFileUpload(this)">
                        <img src="upload_icon.png" alt="Загрузить изображение">
                    </label>
                `;
                imagesPreview.appendChild(newImageUpload);
                imageUploadCount++;
            }
        }
        /* Скрипт на загрузку изображений в нужном формате*/
        document.querySelectorAll('.image-upload input[type="file"]').forEach((input, index) => {
            input.addEventListener('change', function() {
                const reader = new FileReader();
                reader.onload = function(e) {
                    input.parentElement.querySelector('img').src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            });
        });
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
