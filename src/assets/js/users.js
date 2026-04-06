document.addEventListener('DOMContentLoaded', () => {
    // --- Элементы DOM ---
    const addUserBtn = document.getElementById('add-user-btn');
    const userModalEl = document.getElementById('userModal');
    const userModal = new bootstrap.Modal(userModalEl);
    const userForm = document.getElementById('user-form');
    const userTableBody = document.getElementById('users-table-body');
    const passwordInput = document.getElementById('user_password');
    const passwordLabel = passwordInput.previousElementSibling;

    // --- Загрузка списка пользователей при старте ---
    loadUsers();

    // --- Обработчики событий ---
    addUserBtn.addEventListener('click', () => {
        openUserModal();
    });

    userForm.addEventListener('submit', handleFormSubmit);

    userTableBody.addEventListener('click', (e) => {
        const target = e.target;
        if (target.classList.contains('edit-btn')) {
            const userId = target.dataset.id;
            // Получаем данные пользователя для редактирования
            fetchUserDataAndOpenModal(userId);
        }
        if (target.classList.contains('delete-btn')) {
            const userId = target.dataset.id;
            handleDelete(userId);
        }
    });

    // --- Функции ---
    async function loadUsers() {
        try {
            const res = await fetch('/api/users.php');
            const responseData = await res.json();
            if (responseData.success) {
                renderUsers(responseData.data);
            } else {
                alert('Ошибка: ' + responseData.data.message);
            }
        } catch (e) {
            console.error(e);
            alert('Сетевая ошибка при загрузке пользователей.');
        }
    }

    function renderUsers(users) {
        userTableBody.innerHTML = '';
        users.forEach(user => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${user.id}</td>
                <td>${escapeHtml(user.login)}</td>
                <td>${escapeHtml(user.role)}</td>
                <td>
                    <button class="btn btn-sm btn-primary edit-btn" data-id="${user.id}">Редактировать</button>
                    <button class="btn btn-sm btn-danger delete-btn" data-id="${user.id}">Удалить</button>
                </td>
            `;
            userTableBody.appendChild(tr);
        });
    }

    function openUserModal(user = null) {
        userForm.reset();
        document.getElementById('user_id_input').value = user ? user.id : '0';
        
        if (user) { // Редактирование
            document.getElementById('userModalLabel').textContent = 'Редактировать пользователя';
            document.getElementById('user_login').value = user.login;
            document.getElementById('user_role').value = user.role;
            passwordInput.required = false;
            passwordLabel.textContent = 'Новый пароль (оставьте пустым, чтобы не менять)';
        } else { // Создание
            document.getElementById('userModalLabel').textContent = 'Новый пользователь';
            passwordInput.required = true;
            passwordLabel.textContent = 'Пароль';
        }
        userModal.show();
    }
    
    async function fetchUserDataAndOpenModal(id) {
        // Так как у нас уже есть все данные в JS, можно было бы их найти в `users`
        // Но для надежности лучше запросить свежие данные с сервера.
        // Сейчас просто откроем с теми, что есть, для скорости.
        // В реальном проекте лучше был бы GET /api/users.php?id=...
        
        // В нашем случае, мы можем просто найти юзера в уже загруженном списке
        // но для простоты, предположим, что роли не меняются ежесекундно
        // и откроем модальное окно с данными из таблицы (не лучший подход, но рабочий)
        const row = document.querySelector(`.edit-btn[data-id='${id}']`).closest('tr');
        const login = row.cells[1].textContent;
        const role = row.cells[2].textContent;
        openUserModal({ id, login, role });
    }

    async function handleFormSubmit(e) {
        e.preventDefault();
        const payload = {
            id: document.getElementById('user_id_input').value,
            login: document.getElementById('user_login').value,
            password: document.getElementById('user_password').value,
            role: document.getElementById('user_role').value
        };

        try {
            const res = await fetch('/api/users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const responseData = await res.json();
            if (responseData.success) {
                userModal.hide();
                alert(responseData.data.message);
                loadUsers();
            } else {
                alert('Ошибка: ' + responseData.data.message);
            }
        } catch (e) {
            console.error(e);
            alert('Сетевая ошибка при сохранении пользователя.');
        }
    }

    async function handleDelete(id) {
        if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) return;
        
        try {
            const res = await fetch('/api/users.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const responseData = await res.json();
            if (responseData.success) {
                alert(responseData.data.message);
                loadUsers();
            } else {
                alert('Ошибка: ' + responseData.data.message);
            }
        } catch (e) {
            console.error(e);
            alert('Сетевая ошибка при удалении пользователя.');
        }
    }
    
    function escapeHtml(str) {
        return str.toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);
    }
});
