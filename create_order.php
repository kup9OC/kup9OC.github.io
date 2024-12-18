<?php
// Начало сессии и проверка авторизован ли пользователь, если нет = пересылаем на страницу с авторизацией
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: ../user/auth");
    exit();
}
// Подключение к базе данных
require '../user/db_connect.php';

// Проверка прав пользователя
$isRemPC = ($_SESSION['user_role'] === 'rempc');
$isAdmin = ($_SESSION['user_role'] === 'admin');
// Если роль не совпала = возвращаем пользователя в личный кабинет
if (!$isRemPC && !$isAdmin) {
    header("Location: /user/lk");
    exit();
}

// Получение данных из формы
$clientName = $_POST['client_name']; // ФИО клиента
$productId = $_POST['product']; // ID продукта (он же компьютер)
$orderStatus = $_POST['order_status']; // статус заказа
$requestStatus = $_POST['request_status']; 
$additionalInfo = $_POST['additional_info']; // доп. поле

// Вставка новой заявки
$query = "INSERT INTO clients (name, product, order_status, request_status, additional_info, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
$stmt = $conn->prepare($query);
// Выводж страницы с ошибкой
if (!$stmt) {
    die("Ошибка подготовки запроса: " . $conn->error);
}

$stmt->bind_param("sssss", $clientName, $productId, $orderStatus, $requestStatus, $additionalInfo);

if ($stmt->execute()) {
    $clientId = $stmt->insert_id; // Получаем ID последней вставленной записи

    // Получаем данные для записи в журнал
    // Имя клиента
    $client_query = "SELECT name FROM clients WHERE id = ?";
    $client_stmt = $conn->prepare($client_query);
    $client_stmt->bind_param("i", $clientId);
    $client_stmt->execute();
    $client_result = $client_stmt->get_result();
    $client_data = $client_result->fetch_assoc();
    $client_name = $client_data['name'];

    // Название продукта
    $product_query = "SELECT name FROM computers WHERE id = ?";
    $product_stmt = $conn->prepare($product_query);
    $product_stmt->bind_param("i", $productId);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    $product_data = $product_result->fetch_assoc();
    $product_name = $product_data['name'];

    // Имя пользователя
    $user_login = $_SESSION['login']; // Используем логин из сессии

    // Запись в журнал изменений
    $log_query = "INSERT INTO order_logs (user_login, action, client_name, computer_name, created_at) VALUES (?, 'create', ?, ?, NOW())";
    $stmt_log = $conn->prepare($log_query);
    
    if (!$stmt_log) {
        die("Ошибка подготовки запроса журнала: " . $conn->error);
    }
    
    $stmt_log->bind_param("sss", $user_login, $client_name, $product_name);
    
    if (!$stmt_log->execute()) {
        die("Ошибка записи в журнал: " . $stmt_log->error);
    }

    // Перенаправление на страницу заказов с сообщением об успехе
    $_SESSION['message'] = "Заявка успешно создана.";
} else {
    $_SESSION['message'] = "Ошибка создания заявки: " . $stmt->error;
}

$stmt->close();
$stmt_log->close();
$conn->close();
// Перенаправление на страницу с заказами
header("Location: orders");
exit();
?>
