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

if (isset($_GET['id'])) {
    $computer_id = $_GET['id'];
    $query = "SELECT * FROM computers WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $computer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $computer = $result->fetch_assoc();
    $images = json_decode($computer['images'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_image'])) {
        $index = (int)$_POST['delete_image'];
        $images = json_decode($computer['images'], true);

        if (isset($images[$index])) {
            unlink($images[$index]); // Удаление файла с сервера
            unset($images[$index]); // Удаление записи из массива
            $images = array_values($images); // Пересобрать массив, чтобы индексы были последовательными
            $imagesJson = json_encode($images);

            $stmt = $conn->prepare("UPDATE computers SET images = ? WHERE id = ?");
            $stmt->bind_param("si", $imagesJson, $computer_id);
            $stmt->execute();

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit();
    }

    // Обработка загрузки новых изображений
    if (isset($_FILES)) {
        $images = json_decode($computer['images'], true);

        for ($i = 0; $i < 5; $i++) {
            if (isset($_FILES["image$i"]) && $_FILES["image$i"]['error'] === UPLOAD_ERR_OK) {
                $imagePath = '../uploads/pc/' . basename($_FILES["image$i"]["name"]);
                move_uploaded_file($_FILES["image$i"]["tmp_name"], $imagePath);
                $images[$i] = $imagePath;
            }
        }

        $imagesJson = json_encode($images);
        $stmt = $conn->prepare("UPDATE computers SET images = ? WHERE id = ?");
        $stmt->bind_param("si", $imagesJson, $computer_id);
        $stmt->execute();
    }

    // Обновление других полей
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $description = $_POST['description'];
    $is_archived = isset($_POST['is_archived']) ? 1 : 0;

    $old_name = $computer['name'];
    $old_price = $computer['price'];
    $old_stock = $computer['stock'];
    $old_archived = $computer['is_archived'];

    $stmt = $conn->prepare("UPDATE computers SET name = ?, price = ?, stock = ?, description = ?, is_archived = ? WHERE id = ?");
    $stmt->bind_param("sdissi", $name, $price, $stock, $description, $is_archived, $computer_id);
    $stmt->execute();
    // Запись в логи
    if ($old_name !== $name) {
        $action = "изменил название компьютера";
        $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, computer_id) VALUES (?, ?, ?, ?)");
        $stmt_log->bind_param("sssi", $_SESSION['login'], $action, $name, $computer_id);
        $stmt_log->execute();
    }
    if ($old_price != $price) {
        $action = "изменил цену компьютера";
        $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, computer_id) VALUES (?, ?, ?, ?)");
        $stmt_log->bind_param("sssi", $_SESSION['login'], $action, $name, $computer_id);
        $stmt_log->execute();
    }
    if ($old_stock != $stock) {
        $action = "изменил количество компьютеров в наличии";
        $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, computer_id) VALUES (?, ?, ?, ?)");
        $stmt_log->bind_param("sssi", $_SESSION['login'], $action, $name, $computer_id);
        $stmt_log->execute();
    }
    if ($old_archived != $is_archived) {
        $action = $is_archived ? "переместил компьютер в архив" : "вернул компьютер из архива";
        $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, computer_id) VALUES (?, ?, ?, ?)");
        $stmt_log->bind_param("sssi", $_SESSION['login'], $action, $name, $computer_id);
        $stmt_log->execute();
    }

    header("Location: pc_list");
    exit();

        // Добавление записи в журнал изменений
        if ($old_name !== $name) {
            $action = "изменил название компьютера";
            $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, computer_id) VALUES (?, ?, ?, ?)");
            $stmt_log->bind_param("sssi", $_SESSION['login'], $action, $name, $computer_id);
            $stmt_log->execute();
        }
        if ($old_price != $price) {
            $action = "изменил цену компьютера";
            $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, computer_id) VALUES (?, ?, ?, ?)");
            $stmt_log->bind_param("sssi", $_SESSION['login'], $action, $name, $computer_id);
            $stmt_log->execute();
        }
        if ($old_stock != $stock) {
            $action = "изменил количество компьютеров в наличии";
            $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, computer_id) VALUES (?, ?, ?, ?)");
            $stmt_log->bind_param("sssi", $_SESSION['login'], $action, $name, $computer_id);
            $stmt_log->execute();
        }
        if ($old_archived != $is_archived) {
            $action = $is_archived ? "переместил компьютер в архив" : "вернул компьютер из архива";
            $stmt_log = $conn->prepare("INSERT INTO change_log (user_login, action, computer_name, computer_id) VALUES (?, ?, ?, ?)");
            $stmt_log->bind_param("sssi", $_SESSION['login'], $action, $name, $computer_id);
            $stmt_log->execute();
        }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать компьютер</title>
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
            height: 500px;
        }
        .images-preview {
            display: flex;
            flex-wrap: wrap;
        }
        .image-upload {
            width: 200px;
            height: 200px;
            margin: 10px;
            background-color: #333;
            border: 2px dashed #555;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .image-upload input[type="file"] {
            display: none;
        }
        .image-upload img {
            max-width: 200px;
            max-height: 200px;
            display: block;
        }
        .submit-btn {
            background-color: #26a8e5;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 60px;
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

        .delete-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #ff0000;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 16px;
            line-height: 16px;
            text-align: center;
            cursor: pointer;
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
        <h1>Редактировать компьютер</h1>
        <form id="edit-computer-form" action="edit_computer.php?id=<?php echo $computer_id; ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Название</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($computer['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="price">Цена</label>
                <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($computer['price']); ?>" required>
            </div>
            <div class="form-group">
                <label for="stock">В наличии</label>
                <input type="number" id="stock" name="stock" value="<?php echo htmlspecialchars($computer['stock']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($computer['description']); ?></textarea>
            </div>
            <div class="form-group">
                <label>Изображения</label>
                <div class="images-preview">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="image-upload">
                        <label>
                            <input type="file" name="image<?php echo $i; ?>" data-index="<?php echo $i; ?>">
                            <img src="<?php echo isset($images[$i]) ? $images[$i] : 'upload_icon.png'; ?>" alt="Загрузить изображение">
                        </label>
                        <?php if (isset($images[$i])): ?>
                        <button type="button" class="delete-image" data-index="<?php echo $i; ?>">&times;</button>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <p>
                <label for="is_archived">Архив</label>
                <input type="checkbox" name="is_archived" id="is_archived" <?php echo $computer['is_archived'] ? 'checked' : ''; ?>>
            </p>
            <button type="submit" class="submit-btn">Сохранить изменения</button>
        </form>
    </div>

    <script>
        document.querySelectorAll('.image-upload input[type="file"]').forEach((input) => {
            input.addEventListener('change', function() {
                const reader = new FileReader();
                reader.onload = function(e) {
                    input.parentElement.querySelector('img').src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            });
        });

        document.querySelectorAll('.delete-image').forEach(button => {
            button.addEventListener('click', function() {
                const index = this.dataset.index;
                const formData = new FormData();
                formData.append('delete_image', index);
                
                fetch('edit_computer.php?id=<?php echo $computer_id; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.parentElement.remove();
                    } else {
                        alert('Ошибка при удалении изображения');
                    }
                });
            });
        });

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
