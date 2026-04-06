document.addEventListener('DOMContentLoaded', () => {

    // =================================================================
    // 1. КОНФИГУРАЦИЯ И ЭЛЕМЕНТЫ DOM
    // =================================================================
    const role = window.userRole;
    const isAdminOrOperator = role === 'operator' || role === 'admin';
    const transactionsTableBody = document.querySelector('#transactions-table tbody');
    const transactionsTableHead = document.querySelector('#transactions-table thead');
    const paginationContainer = document.getElementById('pagination-container');
    const totalIncomeEl = document.getElementById('total_income');
    const totalExpenseEl = document.getElementById('total_expense');
    const balanceEl = document.getElementById('balance');
    const filtersForm = document.getElementById('filters-form');
    const userFilterSelect = document.getElementById('user_filter');
    let currentPage = 1, sortBy = 'date', sortOrder = 'DESC', limit = 8;
    let categoryChart, monthlyChart;

    const chartColors = [
        '#4285F4', '#DB4437', '#F4B400', '#0F9D58', '#AB47BC', '#00ACC1', '#FF7043', '#9E9D24', '#5C6BC0', '#AD1457',
        '#FFB300', '#7CB342', '#EF6C00', '#6D4C41', '#78909C', '#C2185B', '#E53935', '#FB8C00', '#FDD835', '#43A047'
    ];
    function getColor(index) {
        return chartColors[index % chartColors.length];
    }

    if (role === 'admin') {
        const addUserBtn = document.getElementById('add-user-btn');
        const userEditForm = document.getElementById('user-edit-form');
        const usersTableBody = document.getElementById('users-table-body');
        if(addUserBtn) addUserBtn.addEventListener('click', () => {
            closeModal('usersManagementModal');
            openUserEditModal();
        });
        if(userEditForm) userEditForm.addEventListener('submit', handleUserFormSubmit);
        if(usersTableBody) usersTableBody.addEventListener('click', (e) => {
            const target = e.target;
            if (target.classList.contains('edit-user-btn')) fetchUserDataAndOpenUserModal(target.dataset.id);
            if (target.classList.contains('delete-user-btn')) handleUserDelete(target.dataset.id);
        });
    }

    // =================================================================
    // 2. ФУНКЦИИ УПРАВЛЕНИЯ МОДАЛЬНЫМИ ОКНАМИ
    // =================================================================
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.classList.add('modal-open');
        }
    }
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            if (!document.querySelector('.custom-modal-overlay.active')) {
                document.body.classList.remove('modal-open');
            }
        }
    }

    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('custom-modal-close')) {
            const modalOverlay = e.target.closest('.custom-modal-overlay');
            if (modalOverlay) closeModal(modalOverlay.id);
        }
        if (e.target.classList.contains('custom-modal-overlay')) {
            closeModal(e.target.id);
        }
    });
    // =================================================================
    // 3. ГЛОБАЛЬНЫЕ ФУНКЦИИ (window)
    // =================================================================
    window.openUsersManager = async () => { if (role !== 'admin') return; await loadUsersForManager(); openModal('usersManagementModal'); };
    window.openNewRequestModal = () => {
        const newRequestForm = document.getElementById('new-request-form');
        if(newRequestForm) newRequestForm.reset();
        openModal('newRequestModal');
    };
    window.editCategory = () => {
        if (!isAdminOrOperator) return;
        const categoryForm = document.getElementById('category-form');
        if(!categoryForm) return console.error('Category form not found');
        categoryForm.reset();
        document.getElementById('category_id_input').value = '0';
        document.getElementById('categoryModalLabel').textContent = 'Добавить новую категорию';
        openModal('categoryModal');
    };
    window.openCategoryManager = async () => {
        if (!isAdminOrOperator) return;
        const categoryListContainer = document.getElementById('category-list-container');
        if(!categoryListContainer) return;
        try {
            const res = await fetch('/api/categories.php');
            const responseData = await res.json();
            if (!responseData.success || !Array.isArray(responseData.data)) return alert('Ошибка: не удалось загрузить список категорий.');

            categoryListContainer.innerHTML = '';
            if (responseData.data.length === 0) {
                categoryListContainer.innerHTML = '<p class="text-center">Категории еще не созданы.</p>';
            } else {
                responseData.data.forEach(cat => {
                    const item = document.createElement('div');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center';
                    const typeDisplay = cat.type === 'income' ? 'Пополнение' : 'Расход';
                    item.innerHTML = `<span><strong>${escapeHtml(cat.name)}</strong><small class="text-muted ms-2">(Тип: ${typeDisplay}, ${cat.is_active == 1 ? 'Активна' : 'Неактивна'})</small></span><button class="btn btn-sm btn-outline-primary edit-cat-btn" data-category='${JSON.stringify(cat)}'>Редактировать</button>`;
                    categoryListContainer.appendChild(item);
                });
            }
            openModal('managementModal');
        } catch (e) { console.error('Ошибка:', e); alert('Сетевая ошибка.'); }
    };
    
    window.editTransaction = async (id = 0, requestId = null, amount = null, description = null, requesterId = null) => {
        if (!isAdminOrOperator) return;
        const transactionForm = document.getElementById('transaction-form');
        if (!transactionForm) return console.error('Transaction form not found!');

        transactionForm.reset();
        document.getElementById('transaction_id').value = 0;
        document.getElementById('request_id_input').value = requestId || '';
        document.getElementById('transactionModalLabel').textContent = 'Добавить транзакцию';
        document.getElementById('attachment-info').innerHTML = '';
        document.getElementById('existing_attachment_path').value = '';
        document.getElementById('transaction_requester_id').value = requesterId || '';

        // Устанавливаем текущую дату и время по умолчанию для НОВЫХ транзакций
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('transaction_date').value = now.toISOString().slice(0, 16);

        if (id > 0) { // Если редактируем существующую
            document.getElementById('transactionModalLabel').textContent = 'Редактировать транзакцию';
            const res = await fetch('/api/transactions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) });
            const responseData = await res.json();
            if (responseData.success && responseData.data.transactions && responseData.data.transactions.length > 0) {
                const tx = responseData.data.transactions[0];
                document.getElementById('transaction_id').value = tx.id;
                document.getElementById('transaction_date').value = tx.date.substring(0, 16).replace(' ', 'T');
                document.getElementById('transaction_type').value = tx.type;
                document.getElementById('transaction_amount').value = tx.amount;
                document.getElementById('transaction_category').value = tx.category_id;
                document.getElementById('transaction_comment').value = tx.comment || '';
                document.getElementById('transaction_payment_method').value = tx.payment_method || '';
                document.getElementById('transaction_counterparty').value = tx.counterparty || '';
                if(tx.attachment_path) {
                    document.getElementById('attachment-info').innerHTML = `Текущий файл: <a href="/api/download.php?file=${tx.attachment_path}" target="_blank">${tx.attachment_path}</a>`;
                    document.getElementById('existing_attachment_path').value = tx.attachment_path;
                }
            }
        } else if (requestId) { // Если создаем из заявки
            document.getElementById('transactionModalLabel').textContent = 'Одобрить заявку';
            document.getElementById('transaction_amount').value = amount;
            document.getElementById('transaction_comment').value = description;
            closeModal('approveRequestsModal');
        }
        openModal('transactionModal');
    };

    window.directAddTransaction = () => { if (!isAdminOrOperator) return; window.editTransaction(); };
    window.deleteTransaction = async (id) => { if (!isAdminOrOperator || !confirm('Удалить транзакцию?')) return; await fetch('/api/transactions.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) }); loadTransactions(); };

    // =================================================================
    // 4. ОБРАБОТЧИКИ СОБЫТИЙ
    // =================================================================
    // === ИЗМЕНЕНИЕ 1: При смене фильтров вызываем с флагом true ===
    filtersForm.addEventListener('submit', (e) => { e.preventDefault(); currentPage = 1; loadTransactions(true); });

    const openExportModalBtn = document.getElementById('open-export-modal-btn');
    const exportModal = document.getElementById('exportModal');
    if (openExportModalBtn) {
        openExportModalBtn.addEventListener('click', () => openModal('exportModal'));
    }
    if (exportModal) {
        exportModal.addEventListener('click', (e) => {
            const exportBtn = e.target.closest('.export-btn');
            if (exportBtn) {
                const format = exportBtn.dataset.format;
                exportFile(format);
                closeModal('exportModal');
            }
        });
    }

    // === ИЗМЕНЕНИЕ 2: При сортировке вызываем с флагом false ===
    if (transactionsTableHead) transactionsTableHead.addEventListener('click', (e) => {
        const th = e.target.closest('th.sortable');
        if (!th) return;
        const newSortBy = th.dataset.sort;
        if (sortBy === newSortBy) {
            sortOrder = sortOrder === 'ASC' ? 'DESC' : 'ASC';
        } else {
            sortBy = newSortBy;
            sortOrder = 'ASC';
        }
        currentPage = 1;
        loadTransactions(false); // Не обновляем графики при сортировке
    });

    // === ИЗМЕНЕНИЕ 3: При пагинации вызываем с флагом false ===
    if (paginationContainer) paginationContainer.addEventListener('click', (e) => {
        e.preventDefault();
        const pageLink = e.target.closest('.page-link');
        if (!pageLink) return;
        const newPage = parseInt(pageLink.dataset.page, 10);
        if (newPage && newPage !== currentPage) { currentPage = newPage; loadTransactions(false); } // Не обновляем графики при пагинации
    });

    const newRequestForm = document.getElementById('new-request-form');
    if (newRequestForm) newRequestForm.addEventListener('submit', handleNewRequestSubmit);

    if (isAdminOrOperator) {
        const requestsWidget = document.getElementById('requests-widget');
        const transactionForm = document.getElementById('transaction-form');
        const categoryForm = document.getElementById('category-form');
        const pendingRequestsList = document.getElementById('pending-requests-list');
        if (requestsWidget) requestsWidget.addEventListener('click', loadAndShowPendingRequests);
        if (transactionForm) transactionForm.addEventListener('submit', handleTransactionFormSubmit);
        if (categoryForm) categoryForm.addEventListener('submit', handleCategoryFormSubmit);

        document.addEventListener('click', (e) => { if (e.target.classList.contains('edit-cat-btn')) { editCategoryFromManager(JSON.parse(e.target.dataset.category)); } });
        if (pendingRequestsList) pendingRequestsList.addEventListener('click', (e) => {
            const approveBtn = e.target.closest('.approve-request-btn');
            if (approveBtn) {
                const { id, amount, description, requesterId } = approveBtn.dataset;
                window.editTransaction(0, id, amount, description, requesterId);
            }
            const rejectBtn = e.target.closest('.reject-request-btn');
            if (rejectBtn) handleRejectRequest(rejectBtn.dataset.id);
        });
    }
    // =================================================================
    // 5. ОСНОВНАЯ ЛОГИКА
    // =================================================================
    async function exportFile(format = 'csv') {
        const params = {
            from_date: document.getElementById('from_date').value,
            to_date: document.getElementById('to_date').value,
            type: document.getElementById('type').value,
            category_id: document.getElementById('category_id').value,
            user_id: userFilterSelect.value
        };
        try {
            const res = await fetch(`/api/export.php?format=${format}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(params)
            });
            if (!res.ok) {
                throw new Error(`Ошибка сервера: ${res.statusText}`);
            }
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `transactions_${new Date().toISOString().slice(0, 10)}.${format}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch (e) {
            console.error('Ошибка экспорта:', e);
            alert('Не удалось экспортировать файл. ' + e.message);
        }
    }
    async function handleNewRequestSubmit(e) {
        e.preventDefault();
        const payload = { action: 'create', amount: document.getElementById('request_amount').value, description: document.getElementById('request_description').value };
        try {
            const res = await fetch('/api/requests.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const responseData = await res.json();
            if (res.ok && responseData.success) {
                closeModal('newRequestModal');
                alert(responseData.data.message);
                if (isAdminOrOperator) await loadPendingRequestsCount();
            } else { alert('Ошибка: ' + (responseData.data.message || 'Не удалось отправить заявку.')); }
        } catch (e) { console.error(e); alert('Сетевая ошибка.'); }
    }
    async function loadAndShowPendingRequests() {
        if (!isAdminOrOperator) return;
        try {
            const res = await fetch('/api/requests.php');
            const responseData = await res.json();
            if (responseData.success) {
                renderPendingRequests(responseData.data);
                openModal('approveRequestsModal');
            } else { alert('Ошибка: ' + responseData.data.message); }
        } catch (e) { console.error(e); }
    }
    async function loadPendingRequestsCount() {
        if (!isAdminOrOperator) return;
        try {
            const res = await fetch('/api/requests.php');
            const responseData = await res.json();
            if (responseData.success) renderPendingRequests(responseData.data);
            else console.error('Ошибка загрузки заявок: ' + responseData.data.message);
        } catch (e) { console.error('Сетевая ошибка при загрузке заявок', e); }
    }
    function renderPendingRequests(requests) {
        if (!isAdminOrOperator) return;
        const pendingRequestsCount = document.getElementById('pending-requests-count');
        const requestsWidget = document.getElementById('requests-widget');
        const pendingRequestsList = document.getElementById('pending-requests-list');
        if(!pendingRequestsCount || !requestsWidget || !pendingRequestsList) return;

        pendingRequestsCount.textContent = requests.length;
        requestsWidget.classList.toggle('pulse-animation', requests.length > 0);
        pendingRequestsList.innerHTML = '';
        if (requests.length === 0) {
            pendingRequestsList.innerHTML = '<p class="text-center">Нет заявок, ожидающих утверждения.</p>';
            return;
        }
        requests.forEach(req => {
            const item = document.createElement('div');
            item.className = 'list-group-item';
            item.innerHTML = `<div class="d-flex w-100 justify-content-between"><h5 class="mb-1">${escapeHtml(req.requester_login)} просит ${req.amount} руб.</h5><small>${new Date(req.created_at).toLocaleString()}</small></div><p class="mb-1">${escapeHtml(req.description)}</p><div><button class="btn btn-sm btn-success approve-request-btn" data-id="${req.id}" data-amount="${req.amount}" data-description="${escapeHtml(req.description)}" data-requester-id="${req.requester_id}">Одобрить</button><button class="btn btn-sm btn-danger reject-request-btn" data-id="${req.id}">Отклонить</button></div>`;
            pendingRequestsList.appendChild(item);
        });
    }
    async function handleRejectRequest(id) {
        const reason = prompt("Укажите причину отклонения (необязательно):");
        if (reason === null) return;
        const payload = { action: 'reject', request_id: id, reason: reason };
        try {
            const res = await fetch('/api/requests.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const responseData = await res.json();
            if (res.ok && responseData.success) {
                alert(responseData.data.message);
                await loadAndShowPendingRequests();
            } else { alert('Ошибка: ' + (responseData.data.message || 'Не удалось отклонить заявку.')); }
        } catch (e) { console.error(e); alert('Сетевая ошибка'); }
    }
    async function handleCategoryFormSubmit(e) {
        e.preventDefault();
        const payload = { id: document.getElementById('category_id_input').value, name: document.getElementById('category_name').value.trim(), type: document.getElementById('category_type').value, description: document.getElementById('category_description').value.trim(), is_active: document.getElementById('category_active').value };
        try {
            const res = await fetch('/api/categories.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const responseData = await res.json();
            if (res.ok && responseData.success) {
                closeModal('categoryModal');
                alert(responseData.data.message || 'Категория успешно сохранена!');
                await loadCategories();
                await loadTransactions(); // Вызываем без флага, обновит всё (по умолчанию true)
            } else { alert('Ошибка: ' + (responseData.data.message || 'Произошла неизвестная ошибка')); }
        } catch (err) { console.error(err); alert('Сетевая ошибка.'); }
    }
    async function handleTransactionFormSubmit(e) {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Сохранение...';
        const attachmentFile = document.getElementById('transaction_attachment').files[0];
        let attachmentPath = document.getElementById('existing_attachment_path').value || null;
        try {
            if (attachmentFile) {
                const formData = new FormData();
                formData.append('attachment', attachmentFile);
                const uploadRes = await fetch('/api/upload.php', { method: 'POST', body: formData });
                const uploadData = await uploadRes.json();
                if (!uploadRes.ok || !uploadData.success) throw new Error(uploadData.data.message || 'Ошибка загрузки файла.');
                attachmentPath = uploadData.data.path;
            }

            const payload = {
                id: document.getElementById('transaction_id').value,
                date: document.getElementById('transaction_date').value,
                type: document.getElementById('transaction_type').value,
                amount: document.getElementById('transaction_amount').value,
                category_id: document.getElementById('transaction_category').value,
                comment: document.getElementById('transaction_comment').value,
                payment_method: document.getElementById('transaction_payment_method').value,
                counterparty: document.getElementById('transaction_counterparty').value,
                attachment_path: attachmentPath,
                requester_id: document.getElementById('transaction_requester_id').value
            };
            const res = await fetch('/api/transactions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const responseData = await res.json();
            if (res.ok && responseData.success) {
                closeModal('transactionModal');
                alert(responseData.data.message);
                const requestId = document.getElementById('request_id_input').value;
                if (requestId) {
                    const newTransactionId = responseData.data.id;
                    if (!newTransactionId) return console.error("API транзакций не вернул ID!");
                    await fetch('/api/requests.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'approve', request_id: requestId, transaction_id: newTransactionId }) });
                    await loadAndShowPendingRequests();
                }
                await loadTransactions(); // Вызываем без флага, обновит всё (по умолчанию true)
            } else { alert('Ошибка сохранения: ' + (responseData.data.message || 'Неизвестная ошибка сервера.')); }
        } catch (err) { alert('Ошибка: ' + err.message); } finally { submitButton.disabled = false; submitButton.textContent = 'Сохранить'; }
    }
    function editCategoryFromManager(cat) { closeModal('managementModal'); const categoryForm = document.getElementById('category-form'); if(!categoryForm) return; categoryForm.reset(); document.getElementById('category_id_input').value = cat.id; document.getElementById('category_name').value = cat.name; document.getElementById('category_type').value = cat.type; document.getElementById('category_description').value = cat.description || ''; document.getElementById('category_active').value = cat.is_active; document.getElementById('categoryModalLabel').textContent = 'Редактировать категорию'; openModal('categoryModal'); }
    async function loadUsersForManager() { if (role !== 'admin') return; try { const res = await fetch('/api/users.php'); const responseData = await res.json(); if (responseData.success) renderUsersInManager(responseData.data); else alert('Ошибка: ' + responseData.data.message); } catch (e) { console.error(e); alert('Сетевая ошибка при загрузке пользователей.'); } }
    function renderUsersInManager(users) { const usersTableBody = document.getElementById('users-table-body'); if (!usersTableBody) return; usersTableBody.innerHTML = ''; users.forEach(user => { const tr = document.createElement('tr'); tr.innerHTML = `<td>${user.id}</td><td>${escapeHtml(user.login)}</td><td>${escapeHtml(user.role)}</td><td><button class="btn btn-sm btn-primary edit-user-btn" data-id="${user.id}">Редактировать</button> <button class="btn btn-sm btn-danger delete-user-btn" data-id="${user.id}">Удалить</button></td>`; usersTableBody.appendChild(tr); }); }
    function openUserEditModal(user = null) {
        const userEditForm = document.getElementById('user-edit-form');
        if (!userEditForm) return; userEditForm.reset(); document.getElementById('edit_user_id_input').value = user ? user.id : '0';
        const passwordInput = document.getElementById('edit_user_password');
        const passwordHelpText = document.getElementById('edit_password_help_text');
        const modalLabel = document.getElementById('userEditModalLabel');
        if (user) { modalLabel.textContent = 'Редактировать пользователя'; document.getElementById('edit_user_login').value = user.login; document.getElementById('edit_user_role').value = user.role; passwordInput.required = false; passwordHelpText.textContent = 'Оставьте пустым, если не хотите менять пароль.'; }
        else { modalLabel.textContent = 'Новый пользователь'; passwordInput.required = true; passwordHelpText.textContent = 'Пароль обязателен для нового пользователя.'; }
        openModal('userEditModal');
    }
    function fetchUserDataAndOpenUserModal(id) { if (role !== 'admin') return; closeModal('usersManagementModal'); const row = document.querySelector(`.edit-user-btn[data-id='${id}']`).closest('tr'); const login = row.cells[1].textContent; const userRole = row.cells[2].textContent; openUserEditModal({ id, login, role: userRole }); }
    async function handleUserFormSubmit(e) {
        e.preventDefault();
        const payload = { id: document.getElementById('edit_user_id_input').value, login: document.getElementById('edit_user_login').value, password: document.getElementById('edit_user_password').value, role: document.getElementById('edit_user_role').value };
        try {
            const res = await fetch('/api/users.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const responseData = await res.json();
            if (responseData.success) { closeModal('userEditModal'); alert(responseData.data.message); await loadUsersForManager(); openModal('usersManagementModal'); await loadUsersForFilter(); }
            else { alert('Ошибка: ' + responseData.data.message); }
        } catch (e) { console.error(e); alert('Сетевая ошибка при сохранении пользователя.'); }
    }
    async function handleUserDelete(id) { if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) return; try { const res = await fetch('/api/users.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) }); const responseData = await res.json(); if (responseData.success) { alert(responseData.data.message); await loadUsersForManager(); await loadUsersForFilter(); } else { alert('Ошибка: ' + responseData.data.message); } } catch (e) { console.error(e); alert('Сетевая ошибка при удалении пользователя.'); } }

    // === ИЗМЕНЕНИЕ 4: Функция принимает флаг updateGlobalStats ===
    async function loadTransactions(updateGlobalStats = true) {
        const params = { from_date: document.getElementById('from_date').value, to_date: document.getElementById('to_date').value, type: document.getElementById('type').value, category_id: document.getElementById('category_id').value, user_id: userFilterSelect.value, page: currentPage, limit: limit, sortBy: sortBy, sortOrder: sortOrder };
        try {
            const res = await fetch('/api/transactions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(params) });
            const responseData = await res.json();
            if (responseData.success && responseData.data.transactions) {
                // Эти функции вызываются всегда, т.к. обновляют таблицу и пагинацию
                renderTransactions(responseData.data.transactions);
                renderPagination(responseData.data.total);
                updateSortIndicators();
                
                // Эти функции вызываются только если нужно обновить графики и итоги
                if (updateGlobalStats) {
                    updateAggregates(responseData.data.all_transactions);
                    renderCharts(responseData.data.all_transactions);
                }

            } else {
                console.error('Ошибка в данных транзакций', responseData);
                if(transactionsTableBody) transactionsTableBody.innerHTML = `<tr><td colspan="11" class="text-center">Не удалось загрузить данные. Проверьте консоль.</td></tr>`;
            }
        } catch (e) {
            console.error('Ошибка загрузки транзакций', e);
            if(transactionsTableBody) transactionsTableBody.innerHTML = `<tr><td colspan="11" class="text-center">Сетевая ошибка.</td></tr>`;
        }
    }

    async function loadCategories() { try { const res = await fetch('/api/categories.php'); const responseData = await res.json(); if (!responseData.success || !Array.isArray(responseData.data)) return; const categories = responseData.data; const categorySelect = document.getElementById('transaction_category'); const filterSelect = document.getElementById('category_id'); if (categorySelect) categorySelect.innerHTML = ''; if (filterSelect) filterSelect.innerHTML = '<option value="">Все</option>'; categories.forEach(cat => { if (cat.is_active == 1) { const optionHtml = `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`; if (categorySelect) categorySelect.insertAdjacentHTML('beforeend', optionHtml); if (filterSelect) filterSelect.insertAdjacentHTML('beforeend', optionHtml); } }); } catch (e) { console.error('Ошибка загрузки категорий', e); } }
    async function loadUsersForFilter() { if (!userFilterSelect) return; try { const res = await fetch('/api/users.php'); const responseData = await res.json(); if (responseData.success && Array.isArray(responseData.data)) { userFilterSelect.innerHTML = '<option value="">Все</option>'; responseData.data.forEach(user => { userFilterSelect.insertAdjacentHTML('beforeend', `<option value="${user.id}">${escapeHtml(user.login)}</option>`); }); } } catch (e) { console.error("Не удалось загрузить пользователей для фильтра", e); } }
    function renderTransactions(transactions) { if (!transactionsTableBody) return; transactionsTableBody.innerHTML = ''; transactions.forEach(tx => { const tr = document.createElement('tr'); const attachmentCell = tx.attachment_path ? `<td><a href="/api/download.php?file=${tx.attachment_path}" target="_blank" title="Посмотреть чек">📎</a></td>` : '<td>-</td>'; const typeDisplay = tx.type === 'income' ? 'Пополнение' : 'Расход'; tr.innerHTML = `<td>${tx.id}</td><td>${tx.date}</td>${attachmentCell}<td>${typeDisplay}</td><td>${tx.amount}</td><td>${escapeHtml(tx.category_name || '-')}</td><td>${escapeHtml(tx.comment || '')}</td><td>${escapeHtml(tx.payment_method || '-')}</td><td>${escapeHtml(tx.counterparty || '-')}</td><td>${escapeHtml(tx.user_login || '')}</td> ${isAdminOrOperator ? `<td><button class="btn btn-sm btn-primary" onclick="editTransaction(${tx.id})" title="Редактировать">✎</button> <button class="btn btn-sm btn-danger" onclick="deleteTransaction(${tx.id})" title="Удалить">🗑</button></td>` : ''}`; transactionsTableBody.appendChild(tr); }); }
    function renderPagination(total) { if(!paginationContainer) return; paginationContainer.innerHTML = ''; const totalPages = Math.ceil(total / limit); if (totalPages <= 1) return; let html = '<ul class="pagination justify-content-center">'; html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}">«</a></li>`; for (let i = 1; i <= totalPages; i++) { html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`; } html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}">»</a></li>`; html += '</ul>'; paginationContainer.innerHTML = html; }
    function updateSortIndicators() { if(!transactionsTableHead) return; transactionsTableHead.querySelectorAll('th.sortable span').forEach(span => span.textContent = ''); const activeTh = transactionsTableHead.querySelector(`th[data-sort="${sortBy}"]`); if (activeTh) { activeTh.querySelector('span').textContent = sortOrder === 'ASC' ? '▲' : '▼'; } }
    function updateAggregates(transactions) { if(!totalIncomeEl || !totalExpenseEl || !balanceEl) return; let income = 0, expense = 0; transactions.forEach(tx => { if (tx.type === 'income') income += parseFloat(tx.amount); else expense += parseFloat(tx.amount); }); totalIncomeEl.textContent = income.toFixed(2); totalExpenseEl.textContent = expense.toFixed(2); balanceEl.textContent = (income - expense).toFixed(2); }

    function renderCharts(transactions) {
        const ctx1 = document.getElementById('categoryChart');
        const ctx2 = document.getElementById('monthlyChart');
        if(!ctx1 || !ctx2) return;

        const catData = {};
        const monthlyData = {};

        transactions.forEach(tx => {
            if (tx.type === 'expense' && tx.category_name) {
                if (!catData[tx.category_name]) catData[tx.category_name] = 0;
                catData[tx.category_name] += parseFloat(tx.amount);
            }
            const month = tx.date.slice(0, 7); // 'YYYY-MM'
            if (!monthlyData[month]) monthlyData[month] = { income: 0, expense: 0 };
            monthlyData[month][tx.type] += parseFloat(tx.amount);
        });

        if (categoryChart) categoryChart.destroy();
        const categoryLabels = Object.keys(catData);
        categoryChart = new Chart(ctx1.getContext('2d'), {
            type: 'pie',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: Object.values(catData),
                    backgroundColor: categoryLabels.map((_, index) => getColor(index))
                }]
            }
        });

        const monthLabels = Object.keys(monthlyData).sort();
        if (monthlyChart) monthlyChart.destroy();
        monthlyChart = new Chart(ctx2.getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Пополнение',
                        data: monthLabels.map(m => monthlyData[m].income || 0),
                        backgroundColor: 'rgba(75, 192, 192, 0.6)'
                    },
                    {
                        label: 'Расход',
                        data: monthLabels.map(m => monthlyData[m].expense || 0),
                        backgroundColor: 'rgba(255, 99, 132, 0.6)'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function escapeHtml(str) { if (typeof str !== 'string') return ''; const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }; return str.replace(/[&<>"']/g, m => map[m]); }
    
    // --- ПЕРВЫЙ ЗАПУСК ---
    // === ИЗМЕНЕНИЕ 5: При первой загрузке вызываем с флагом true ===
    loadTransactions(true);
    loadCategories();
    loadUsersForFilter();
    if (isAdminOrOperator) loadPendingRequestsCount();
});





















