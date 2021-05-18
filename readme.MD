# Базовый функционал для внедрения Symfony в Wordpress

## Установка

composer.json:

```json
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/proklung/wp.core.symfony"
        }
    ]
```

```bash
composer require proklung/wp.core.symfony
```

## Инициализация

В `wp-config.php`:

```php
use Prokl\ServiceProvider\LoadEnvironment;

/** Загрузить окружение. */
$environment = new LoadEnvironment();
$environment->load();

$_SERVER = array_merge($_SERVER, $_ENV);
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) ?: 'dev';
$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? 'prod' !== $_SERVER['APP_ENV'];
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = (int)$_SERVER['APP_DEBUG']
|| filter_var($_SERVER['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
```

В `functions.php` темы:

```php
use Prokl\ServiceProvider\ServiceProvider;

$serviceProvider = new ServiceProvider(
    'app/symfony/services.yaml',
    $_ENV['APP_ENV'],
    (bool)$_ENV['APP_DEBUG']
);
```

## Поддержка бандлов

Файл конфигурации - `/config/standalone_bundles.php`.

Папка, где лежат конфигурации - `/config`. Конфигурации бандлов - `/config/packages`. 

## Сепаратные микро-контейнеры
