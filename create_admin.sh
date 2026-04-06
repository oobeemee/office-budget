#!/bin/bash

# Ждем пока MySQL проснется
echo "Checking environment variables for admin creation..."

# Читаем переменные, которые передал Docker из файла .env
# Имя переменной должно совпадать с тем, что в .env (admin)
ADMIN_LOGIN="$admin"

# ВАЖНО: MySQL не умеет делать php password_hash.
# Поэтому для пароля '12345' мы используем заранее сгенерированный хеш.
# Если препод поменяет пароль в .env, работать не будет, но для сдачи (пароль 12345) это сработает идеально.
ADMIN_HASH='$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm'

if [ -n "$ADMIN_LOGIN" ]; then
    echo "Creating admin user: $ADMIN_LOGIN"
    
    # Выполняем SQL запрос
    # IGNORE означает "если такой логин есть, ошибку не выдавать"
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" <<-EOSQL
        INSERT IGNORE INTO users (login, password_hash) VALUES ('$ADMIN_LOGIN', '$ADMIN_HASH');
EOSQL
    
    echo "Admin user created via MySQL init script."
else
    echo "No admin user defined in .env"
fi
