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

## Автозапуск сервисов

Чтобы сервис запустился автоматически после инициализации контейнера, он должен быть помечен тэгом `service.bootstrap`.

```yaml
  app.options:
    class: Local\Services\AppOptions
    arguments: ['%kernel.environment%', '@parameter_bag']
    tags: ['service.bootstrap']
```

## Автоматическая подвязка на хуки Wordpress

Тэг: `custom.events.init`.

1) `type` - add_action, add_filter & etc По умолчанию: `add_action`.
2) `event` - название хука.
3) `method` - метод-обработчик в сервисе
4) `priority` - приоритет

```yaml
Local\Events\CometCacheClearMemcachedEvent:
    tags:
      - { name: 'custom.events.init', event: 'post_class', method: 'handler', type: 'add_filter',  priority: 100 }
```