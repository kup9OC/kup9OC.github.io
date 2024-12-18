<?php
session_start();
require '../user/db_connect.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../user/auth");
    exit();
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $order_id = intval($_POST['id']);
    $order_status = $_POST['order_status'];
    $request_status = $_POST['request_status'];
    $additional_info = $_POST['additional_info']; // Новое поле

    // Обновляем заявку
    $update_query = "UPDATE clients SET order_status = ?, request_status = ?, additional_info = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('sssi', $order_status, $request_status, $additional_info, $order_id);

    if ($stmt->execute()) {
        // Запись в журнале изменений
        $log_query = "INSERT INTO order_logs (user_login, client_name, computer_name, action, additional_info, created_at) 
                      SELECT ?, name, product, 'update', ?, NOW() FROM clients WHERE id = ?";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param('ssi', $_SESSION['login'], $additional_info, $order_id);
        $log_stmt->execute();

        // Перенаправление обратно на страницу с заявками
        header("Location: orders");
        exit();
    } else {
        echo "Ошибка обновления заявки: " . $conn->error;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);

    // Получаем данные заявки
    $query = "SELECT * FROM clients WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        echo "Заявка не найдена.";
        exit();
    }
} else {
    echo "Неверный запрос.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать заявку</title>
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
        .form-container {
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            width: 80%;
            max-width: 500px;
            margin-top: 20px;
        }
        .form-container input, .form-container select, .form-container button, .form-container textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: none;
            border-radius: 5px;
        }
        .form-container input, .form-container select, .form-container textarea {
            background-color: #2e2e2e;
            color: #e0e0e0;
        }
        .form-container button {
            background-color: #26a8e5;
            color: #fff;
            cursor: pointer;
            border: none;
            transition: background-color 0.3s;
        }
        .form-container button:hover {
            background-color: #228fc2;
        }
        .back-link {
            color: #26a8e5;
            text-decoration: none;
            margin: 20px 0;
            display: block;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Редактировать заявку</h2>
        <form action="edit_order" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($order['id']); ?>">
            <label for="order_status">Статус заявки:</label>
            <select id="order_status" name="order_status" required>
                <option value="оплачен" <?php echo $order['order_status'] === 'оплачен' ? 'selected' : ''; ?>>Оплачен</option>
                <option value="выдан чек" <?php echo $order['order_status'] === 'выдан чек' ? 'selected' : ''; ?>>Выдан чек</option>
                <option value="создана накладная" <?php echo $order['order_status'] === 'создана накладная' ? 'selected' : ''; ?>>Создана накладная</option>
                <option value="отправлен" <?php echo $order['order_status'] === 'отправлен' ? 'selected' : ''; ?>>Отправлен</option>
                <option value="получен" <?php echo $order['order_status'] === 'получен' ? 'selected' : ''; ?>>Получен</option>
            </select>
            <label for="request_status">Статус запроса:</label>
            <select id="request_status" name="request_status" required>
                <option value="открыта" <?php echo $order['request_status'] === 'открыта' ? 'selected' : ''; ?>>Открыта</option>
                <option value="завершена" <?php echo $order['request_status'] === 'завершена' ? 'selected' : ''; ?>>Завершена</option>
            </select>
            <label for="additional_info">Дополнительная информация:</label>
            <textarea id="additional_info" name="additional_info" rows="4"><?php echo htmlspecialchars($order['additional_info']); ?></textarea>
            <button type="submit">Сохранить изменения</button>
        </form>
        <a href="orders" class="back-link">Вернуться к заявкам</a>
    </div>
</body>
</html>
