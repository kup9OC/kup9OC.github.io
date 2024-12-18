<?php
// Начало сессии и проверка авторизован ли пользователь, если нет = пересылаем на страницу с авторизацией
session_start();
// Подключение к базе данных
require '../user/db_connect.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../user/auth");
    exit();
}
// Проверка наличия роли админа или rempc
$isRemPC = ($_SESSION['user_role'] === 'rempc');
$isAdmin = ($_SESSION['user_role'] === 'admin');
// Если роль не совпала = возвращаем пользователя в личный кабинет
if (!$isRemPC && !$isAdmin) {
    header("Location: /user/lk");
    exit();
}
// Обработчик заявки
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    
    // Обновляем статус заявки на "завершена"
    $update_query = "UPDATE clients SET request_status = 'завершена', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $order_id);
    
    if ($stmt->execute()) {
        // Запись в журнале изменений
        $log_query = "INSERT INTO order_logs (user_login, client_name, computer_name, action) 
                      SELECT ?, name, product, 'close' FROM clients WHERE id = ?";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param('si', $_SESSION['login'], $order_id);
        $log_stmt->execute();
        
        // Перенаправление обратно на страницу с заявками
        header("Location: orders");
        exit();
    } else {
        // Вывод ошибки на текущей странице
        echo "Ошибка завершения заявки: " . $conn->error;
    }
} else {
    echo "Неверный запрос.";
}
?>
