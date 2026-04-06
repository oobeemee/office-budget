<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Вход</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f5f5; }
.login-box { width: 320px; padding: 20px; background:#fff; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.1); }
#error-message { display:none; word-break:break-word; }
</style>
</head>
<body>

<div class="login-box">
    <h4 class="mb-3">Вход</h4>
    <div id="error-message" class="alert alert-danger"></div>
    <form id="login-form">
        <input id="login" class="form-control mb-2" placeholder="Логин" required>
        <input id="password" type="password" class="form-control mb-2" placeholder="Пароль" required>
        <button class="btn btn-primary w-100">Войти</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('login-form');
    var error = document.getElementById('error-message');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        error.style.display = 'none';
        error.textContent = '';

        var login = document.getElementById('login').value;
        var password = document.getElementById('password').value;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/auth.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onreadystatechange = function() {
            if(xhr.readyState === 4) {
                if(xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if(data.success) {
                            window.location.href = '/dashboard.php';
                        } else {
                            error.textContent = data.message;
                            error.style.display = 'block';
                        }
                    } catch(e) {
                        error.textContent = 'Ошибка обработки ответа сервера';
                        error.style.display = 'block';
                    }
                } else {
                    error.textContent = 'Ошибка сервера: ' + xhr.status;
                    error.style.display = 'block';
                }
            }
        };

        xhr.send(JSON.stringify({ login: login, password: password }));
    });
});
</script>

</body>
</html>





