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

$id = $_GET['id'];
$query = "SELECT * FROM computers WHERE id = $id";
$result = $conn->query($query);
$computer = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $computer['name']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #121212;
            color: #e0e0e0;
            margin: 0;
        }
        .computer-container {
            max-width: 800px;
            margin: 80px auto;
            padding: 20px;
            background-color: #1e1e1e;
            border-radius: 10px;
        }
        .computer {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .computer img {
            width: 100%;
            max-width: 400px;
            border-radius: 10px;
            margin: 10px 0;
        }
        .computer h1 {
            margin: 10px 0;
        }
        .computer p {
            margin: 5px 0;
        }
        .computer button {
            margin-top: 20px;
            padding: 10px;
            border: none;
            border-radius: 5px;
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
    <div class="computer-container">
        <div class="computer">
            <h1><?php echo $computer['name']; ?></h1>
            <p>Цена: <?php echo $computer['price']; ?> руб.</p>
            <p>В наличии: <?php echo $computer['stock']; ?></p>
            <p><?php echo nl2br($computer['description']); ?></p>
            <?php
            $images = json_decode($computer['images'], true);
            if (!empty($images)) {
                foreach ($images as $image) {
                    echo '<img src="'.$image.'" alt="'.$computer['name'].'">';
                }
            }
            ?>
            <button onclick="window.location.href='edit_computer?id=<?php echo $computer['id']; ?>'">Редактировать</button>
        </div>
    </div>
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
</body>
</html>
