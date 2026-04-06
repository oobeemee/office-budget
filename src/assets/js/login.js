document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('login-form');
    const error = document.getElementById('error-message');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.style.display = 'none';

        const login = document.getElementById('login').value;
        const password = document.getElementById('password').value;

        try {
            const res = await fetch('/api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ login, password })
            });

            const data = await res.json();

            if (data.success) {
                window.location.href = '/dashboard.php';
            } else {
                error.textContent = data.message;
                error.style.display = 'block';
            }
        } catch {
            error.textContent = 'Ошибка соединения с сервером';
            error.style.display = 'block';
        }
    });
});


