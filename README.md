# Импорт товаров для Bitrix24

Приложение для массового импорта и экспорта товарных позиций в смарт-процессах Bitrix24.

## Описание

Это Laravel-приложение интегрируется с Bitrix24 и позволяет:
- Импортировать товары из Excel файлов (XLSX, XLS)
- Экспортировать существующие товарные позиции в Excel
- Управлять товарными позициями в смарт-процессах
- Автоматически создавать свойства товаров (Артикул, Бренд)

## Требования

- PHP 8.2+
- Composer
- Laravel 11.x
- SQLite/MySQL/PostgreSQL
- Redis (для очередей)

## Установка

1. Клонируйте репозиторий:
```bash
git clone <repository-url>
cd import-products-bitrix24
```

2. Установите зависимости:
```bash
composer install
npm install
```

3. Скопируйте файл окружения:
```bash
cp .env.example .env
```

4. Сгенерируйте ключ приложения:
```bash
php artisan key:generate
```

5. Настройте базу данных в `.env`:
```env
DB_CONNECTION=sqlite
# или для MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=your_database
# DB_USERNAME=your_username
# DB_PASSWORD=your_password
```

6. Выполните миграции:
```bash
php artisan migrate
```

7. Создайте символическую ссылку для storage:
```bash
php artisan storage:link
```

8. Настройте очереди в `.env`:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Конфигурация Bitrix24

1. Создайте локальное приложение в Bitrix24
2. Получите `client_id` и `client_secret`
3. Обновите файл `config/services.php`:
```php
'bitrix24' => [
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
],
```

4. Настройте webhook URLs в Bitrix24:
   - Установка: `https://your-domain.com/install`
   - Обработчик событий: `https://your-domain.com/event/handler`

## Запуск

1. Запустите веб-сервер:
```bash
php artisan serve
```

2. Запустите воркер очередей:
```bash
php artisan horizon
# или
php artisan queue:work
```

3. Для production настройте cron задачи:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Особенности

### Импорт товаров

- Поддерживаемые форматы: XLSX, XLS
- Структура файла:
  ```
  № | Артикул | Бренд | Наименование | Цена | Кол-во
  1 | ART001  | Nike  | Товар 1      | 1000 | 5
  ```

- При импорте все существующие товарные позиции удаляются
- Дублирующиеся товары объединяются по количеству
- Максимальная длина наименования: 255 символов

### Экспорт товаров

- Экспорт всех товарных позиций текущего смарт-процесса
- Включает информацию о товаре и его свойствах
- Результат в формате XLSX

### Управление размещениями

- Автоматическая установка в доступные смарт-процессы
- Управление активными размещениями через интерфейс
- Только администраторы могут изменять настройки

## Архитектура

### Основные компоненты

- **BaseController** - основная логика интеграции с Bitrix24
- **SyncController** - обработка импорта/экспорта
- **Bitrix24Service** - сервис для работы с API Bitrix24
- **ProcessImportJob** - задача для асинхронного импорта
- **BatchImportToBitrix24** - обработка Excel файлов

### Модели

- **Integration** - данные интеграции с порталом
- **IntegrationField** - настройки полей товара
- **Import** - логирование операций импорта

### Команды

- `bitrix:update-access-token` - обновление токенов доступа
- `app:maintenance {status}` - управление режимом обслуживания

## Логирование

Приложение ведет детальные логи:
- `storage/logs/critical.log` - критические ошибки
- `storage/logs/installApplication.log` - установка приложения
- `storage/logs/eventHandler.log` - события от Bitrix24
- База данных `log_imports` - история импортов

## Безопасность

- CSRF защита отключена для webhook endpoints
- Валидация всех входящих данных
- Проверка прав администратора для настроек
- Использование HTTPS для production

## API Endpoints

- `GET /` - главная страница приложения
- `POST /import` - импорт товаров
- `GET /export` - экспорт товаров
- `POST /install` - установка приложения
- `POST /event/handler` - обработчик событий Bitrix24
- `POST /feedback` - форма обратной связи
- `PATCH /placement/status` - управление размещениями

## Разработка

### Структура проекта

```
app/
├── Console/Commands/     # Artisan команды
├── Http/Controllers/     # Контроллеры
├── Http/Services/        # Сервисы
├── Models/              # Eloquent модели
├── Jobs/                # Задачи очередей
├── Imports/             # Обработчики импорта
└── Exports/             # Обработчики экспорта

resources/views/         # Blade шаблоны
config/                  # Конфигурация
database/migrations/     # Миграции БД
```

### Тестирование

```bash
php artisan test
```
