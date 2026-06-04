<?php
// ==========================================
// 1. НАКАРАЙ PHP ДА ПОКАЗВА ГРЕШКИТЕ (АКО ИМА)
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Стартиране на сесията
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

// ==========================================
// 2. НАСТРОЙКА НА БАЗАТА ДАННИ ЗА DOCKER
// ==========================================
$host = 'db'; // ВАЖНО: В Docker използваме името на контейнера 'db', а не 'localhost'
$db   = 'web_project';
$user = 'root'; 
$pass = 'secret123'; // Паролата, зададена в compose.yml

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    die("Грешка при връзка с базата данни: " . $e->getMessage());
}

$msg = ""; 
$is_admin = (isset($_SESSION['username']) && $_SESSION['username'] === 'admin');

// ==========================================
// 3. ЛОГИКА (ОБРАБОТКА НА ДЕЙСТВИЯ)
// ==========================================
$page = $_GET['page'] ?? 'home';
$action = $_GET['action'] ?? '';

if ($action == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($action == 'delete_product' && isset($_GET['id'])) {
    if ($is_admin) {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$_GET['id']]);
    }
    header("Location: index.php?page=manage");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Регистрация
    if (isset($_POST['register'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $profile_pic = 'default.png';
        
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
            $filename = time() . '_' . $_FILES['profile_pic']['name'];
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], 'uploads/' . $filename)) {
                $profile_pic = $filename;
            }
        }
        
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password, profile_pic) VALUES (?, ?, ?, ?)');
        try {
            $stmt->execute([$_POST['username'], $_POST['email'], $password, $profile_pic]);
            $msg = "<p class='success'>Регистрацията е успешна! Можете да влезете.</p>";
            $page = 'login';
        } catch (PDOException $e) { 
            $msg = "<p class='error'>Потребителското име или имейл вече съществуват!</p>"; 
        }
    }
    
    // Вход
    if (isset($_POST['login'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($_POST['password'], $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php?page=profile");
            exit;
        } else { 
            $msg = "<p class='error'>Грешни данни за вход!</p>"; 
        }
    }
    
    // Добавяне на продукт
    if (isset($_POST['add_product'])) {
        if ($is_admin) {
            $stmt = $pdo->prepare('INSERT INTO products (name, description, price) VALUES (?, ?, ?)');
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['price']]);
        }
        header("Location: index.php?page=manage");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <title>Онлайн Магазин</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 0; color: #333; }
        nav { background: #1a252f; padding: 18px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        nav a { color: #ebf5fb; margin: 0 20px; text-decoration: none; font-weight: bold; font-size: 16px; }
        nav a:hover { color: #3498db; }
        .container { max-width: 1100px; margin: 30px auto; background: white; padding: 35px; box-shadow: 0 5px 25px rgba(0,0,0,0.05); border-radius: 12px; }
        
        .hero { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; padding: 40px; text-align: center; border-radius: 12px; margin-bottom: 40px; }
        .hero h1 { margin: 0 0 10px 0; font-size: 36px; }
        .hero p { margin: 0; font-size: 16px; opacity: 0.9; }
        
        .stats-container { display: flex; justify-content: space-around; margin: 30px 0; gap: 20px; }
        .stat-card { background: #fdfefe; border: 1px solid #e5e8e8; padding: 20px; border-radius: 8px; text-align: center; flex: 1; }
        .stat-card h4 { margin: 0; color: #7f8c8d; font-size: 14px; }
        .stat-card p { margin: 10px 0 0 0; font-size: 24px; font-weight: bold; color: #2c3e50; }

        input, textarea, button { width: 100%; padding: 12px; margin: 10px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; }
        button { background: #27ae60; color: white; border: none; cursor: pointer; font-size: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #e5e8e8; padding: 14px; text-align: left; }
        th { background: #f8f9f9; }
        
        .shop-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 30px; margin-top: 20px; }
        .product-card { background: #fff; border: 1px solid #eaeded; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .product-card h3 { margin: 15px 0 10px 0; font-size: 20px; color: #2c3e50; }
        .product-card p { color: #7f8c8d; font-size: 14px; margin-bottom: 20px; }
        
        .price-box { background: #fef5e7; padding: 10px; border-radius: 6px; margin-bottom: 20px; border: 1px dashed #f5b041; }
        .price-bgn { font-weight: bold; color: #e67e22; font-size: 22px; display: block; }
        .price-eur { color: #27ae60; font-size: 15px; font-weight: 600; display: block; margin-top: 5px; }
        
        .buy-btn { background: #3498db; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block; }
        .error { color: #c0392b; font-weight: bold; background: #fadbd8; padding: 12px; border-radius: 6px; }
        .success { color: #27ae60; font-weight: bold; background: #d5f5e3; padding: 12px; border-radius: 6px; }
        .profile-img { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 4px solid #3498db; }
    </style>
</head>
<body>

<nav>
    <a href="index.php?page=home">🏠 Начало</a>
    <a href="index.php?page=shop">🛒 Магазин</a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($is_admin): ?>
            <a href="index.php?page=manage">⚙️ Управление (Админ)</a>
        <?php endif; ?>
        <a href="index.php?page=profile">👤 Моят Профил</a>
        <a href="index.php?action=logout" style="color: #e74c3c;">Изход (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
    <?php else: ?>
        <a href="index.php?page=login">Вход</a>
        <a href="index.php?page=register">Регистрация</a>
    <?php endif; ?>
</nav>

<div class="container">
    <?= $msg ?>

    <!-- НАЧАЛНА СТРАНИЦА -->
    <?php if ($page == 'home'): 
        $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $latest_products = $pdo->query("SELECT * FROM products ORDER BY id DESC LIMIT 3")->fetchAll();
    ?>
        <div class="hero">
            <h1>Добре дошли в нашия магазин!</h1>
            <p>Вашето любимо място за продукти на най-добрите цени в Лева и Евро.</p>
        </div>

        <h3>📊 Нашата статистика накратко</h3>
        <div class="stats-container">
            <div class="stat-card">
                <h4>Регистрирани Клиенти</h4>
                <p><?= $total_users ?> 👤</p>
            </div>
            <div class="stat-card">
                <h4>Продукта в Каталога</h4>
                <p><?= $total_products ?> 📦</p>
            </div>
            <div class="stat-card">
                <h4>Валутен курс днес</h4>
                <p>1 EUR = 1.96 BGN 💶</p>
            </div>
        </div>

        <hr style="border:0; border-top: 1px solid #eaeded; margin: 40px 0;">

        <h3>🔥 Последни попълнения в магазина</h3>
        <div class="shop-grid">
            <?php if (empty($latest_products)): ?>
                <p style="color: #7f8c8d;">Все още няма добавени продукти в системата.</p>
            <?php else: ?>
                <?php foreach ($latest_products as $p): 
                    $price_in_eur = round($p['price'] / 1.95583, 2);
                ?>
                <div class="product-card">
                    <h3><?= htmlspecialchars($p['name']) ?></h3>
                    <p><?= htmlspecialchars($p['description']) ?></p>
                    <div class="price-box">
                        <span class="price-bgn"><?= htmlspecialchars($p['price']) ?> лв.</span>
                        <span class="price-eur">€ <?= $price_in_eur ?> EUR</span>
                    </div>
                    <a href="index.php?page=shop" class="buy-btn">Виж в магазина</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <!-- РЕГИСТРАЦИЯ -->
    <?php elseif ($page == 'register'): ?>
        <h2>Регистрация на акаунт</h2>
        <form action="index.php?page=register" method="POST" enctype="multipart/form-data">
            <input type="text" name="username" placeholder="Потребителско име" required>
            <input type="email" name="email" placeholder="Имейл адрес" required>
            <input type="password" name="password" placeholder="Парола" required>
            <label style="display:block; margin-top:15px; font-weight:600;">Качи профилна снимка:</label>
            <input type="file" name="profile_pic">
            <button type="submit" name="register">Регистрирай ме</button>
        </form>

    <!-- ВХОД -->
    <?php elseif ($page == 'login'): ?>
        <h2>Вход в профила</h2>
        <p style="color: #7f8c8d; font-size:14px; background:#eaf2f8; padding:10px; border-radius:6px;">💡 Влезте с потребител <b>admin</b>, за да управлявате стоките.</p>
        <form action="index.php?page=login" method="POST">
            <input type="text" name="username" placeholder="Потребителско име" required>
            <input type="password" name="password" placeholder="Парола" required>
            <button type="submit" name="login">Влез</button>
        </form>

    <!-- ПРОФИЛНА СТРАНИЦА -->
    <?php elseif ($page == 'profile' && isset($_SESSION['user_id'])): 
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    ?>
        <h2>Вашият Профил</h2>
        <div style="display:flex; align-items:center; gap:30px; margin-top:20px;">
            <img src="uploads/<?= htmlspecialchars($user['profile_pic']) ?>" class="profile-img" alt="Снимка">
            <div>
                <p style="font-size:18px; margin:5px 0;"><strong>Потребител:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p style="font-size:18px; margin:5px 0;"><strong>Имейл:</strong> <?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>

    <!-- МАГАЗИН -->
    <?php elseif ($page == 'shop'): 
        $products = $pdo->query('SELECT * FROM products')->fetchAll();
    ?>
        <h2>🛒 Официален продуктов каталог</h2>
        <div class="shop-grid">
            <?php foreach ($products as $p): 
                $price_in_eur = round($p['price'] / 1.95583, 2);
            ?>
            <div class="product-card">
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <p><?= htmlspecialchars($p['description']) ?></p>
                <div class="price-box">
                    <span class="price-bgn"><?= htmlspecialchars($p['price']) ?> лв.</span>
                    <span class="price-eur">€ <?= $price_in_eur ?> EUR</span>
                </div>
                <a href="#" class="buy-btn" onclick="alert('Успешна поръчка!')">Поръчай</a>
            </div>
            <?php endforeach; ?>
        </div>

    <!-- УПРАВЛЕНИЕ (АДМИН) -->
    <?php elseif ($page == 'manage' && $is_admin): 
        $products = $pdo->query('SELECT * FROM products')->fetchAll();
    ?>
        <h2>🛠️ Панел за управление</h2>
        <fieldset style="border: 1px solid #ddd; padding: 25px; background: #fcfcfc; border-radius:8px;">
            <legend style="font-weight:bold; padding:0 10px;">Добавяне на нова стока</legend>
            <form action="index.php?page=manage" method="POST">
                <input type="text" name="name" placeholder="Име на продукта" required>
                <textarea name="description" placeholder="Описание" rows="3"></textarea>
                <input type="number" step="0.01" name="price" placeholder="Цена в Лева (лв.)" required>
                <button type="submit" name="add_product">Пусни в продажба</button>
            </form>
        </fieldset>

        <h3>Продукти активни в момента</h3>
        <table>
            <tr><th>Име</th><th>Описание</th><th>Цена (BGN)</th><th>Цена (EUR)</th><th>Действие</th></tr>
            <?php foreach ($products as $p): 
                $price_in_eur = round($p['price'] / 1.95583, 2);
            ?>
            <tr>
                <td><b><?= htmlspecialchars($p['name']) ?></b></td>
                <td><?= htmlspecialchars($p['description']) ?></td>
                <td><?= htmlspecialchars($p['price']) ?> лв.</td>
                <td style="color:#27ae60; font-weight:600;">€ <?= $price_in_eur ?></td>
                <td><a href="index.php?action=delete_product&id=<?= $p['id'] ?>" onclick="return confirm('Изтриване на продукта?')" style="color:#e74c3c; text-decoration:none; font-weight:bold;">❌ Изтрий</a></td>
            </tr>
            <?php endforeach; ?>
        </table>

    <?php else: ?>
        <h2>Нямате достъп</h2>
        <p>Страницата изисква специални администраторски права или сте написали грешен адрес.</p>
    <?php endif; ?>
</div>

</body>
</html>