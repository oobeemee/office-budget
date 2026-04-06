<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Учет офисного бюджета</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="text-center auth-body">
    <main class="form-signin">
        <form id="login-form">
            <h1 class="h3 mb-3 fw-normal">Пожалуйста, войдите</h1>
            <div id="error-message" class="alert alert-danger" style="display: none;"></div>
            <div class="form-floating">
                <input type="text" class="form-control" id="login" placeholder="Логин" required>
                <label for="login">Логин</label>
            </div>
            <div class="form-floating mt-2">
                <input type="password" class="form-control" id="password" placeholder="Пароль" required>
                <label for="password">Пароль</label>
            </div>
            <button class="w-100 btn btn-lg btn-primary mt-3" type="submit">Войти</button>
        </form>
    </main>
    <script src="assets/js/login.js"></script>
</body>
</html>