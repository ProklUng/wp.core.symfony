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
## Конфигурирование

1) Опция `compile.container` в подтягиваемом конфиге - компилировать ли контейнер в файл. Если не задана, то "нет, не компилировать".
Имеет смысл для окружения, не равного "dev". Т.е. опция управляет дампированием контейнера на проде.

Место, где хранятся дампы контейнеров: `/wp-content/cache/symfony-app/containers` 

## Поддержка бандлов

Файл конфигурации - `/config/standalone_bundles.php`.

Папка, где лежат конфигурации - `/config`. Конфигурации бандлов - `/config/packages`. 

## Сепаратные микро-контейнеры

Отдельные контейнеры - со своим конфигом, полностью изолированные (для модулей, плагинов и т.п.).

```php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Prokl\ServiceProvider\Micro\AbstractStandaloneServiceProvider;

class ExampleMicroServiceProvider extends AbstractStandaloneServiceProvider
{
    /**
     * @var ContainerBuilder $containerBuilder Контейнер.
     */
    protected static $containerBuilder;

    /**
     * @var string $pathBundlesConfig Путь к конфигурации бандлов.
     */
    protected $pathBundlesConfig = '/src/Micro/example.config/standalone_bundles.php';

    /**
     * @var string $configDir Папка, где лежат конфиги.
     */
    protected $configDir = '/src/Micro/example.config/example.config/example.yaml';
}
```

Где надо - инициализация:

```php
$micro = new ExampleMicroServiceProvider(
    'src/SymfonyDI/Micro/example.config/example.yaml',
    $_ENV['APP_ENV'],
    (bool)$_ENV['APP_DEBUG']
);
```

Хэлпер `container` заточен под работу с микро-сервис-провайдерами:

```php
var_dump(container($micro)->getParameter('example'));
```

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

## Автоматическая регистрация типов постов

Тэг: `post.type`.

Реализует интерфейс `PostTypeDataInterface` с двумя методами:

- `getNameTypePost` - название типа поста
- `getRegistrationData` - массив с традиционными для объявления типа поста данными. Типа такого:

```php
return [
            'labels' => [
                'name' => __('Instagram'),
                'singular_name' => __('Instagram'),
            ],

            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => 'instagram',
            'capability_type' => 'post',
            'has_archive' => 'instagram',
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'],
        ];
```

```yaml
Local\PostTypes\InstagramPostType:
    tags:
      - { name: 'post.type' }
```

## Автоматическое подхватывание расширений Twig

Посредством Timber. Тэг `twig.extension`.

```yaml
  service.twig.parameter:
    class: Local\Bundles\ParameterBundle\Twig\ParameterExtension
    public: true
    arguments:
      - '@service.parameter'
    tags:
      - { name: twig.extension }
```
## Хэлперы

1) `container()` - отдает экземпляр контейнера (выступает в роли сервис-локатора):

```php
$kernel = container()->get('kernel');
```