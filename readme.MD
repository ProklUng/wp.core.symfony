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

/** Загрузить окружение. Параметр конструктора - путь к .env файлам */
$environment = new LoadEnvironment($_SERVER['DOCUMENT_ROOT']);
$environment->load();

$environment->process();
```

В `functions.php` темы:

```php
use Prokl\ServiceProvider\ServiceProvider;

$serviceProvider = new ServiceProvider(
    'app/symfony/services.yaml',
    $_ENV['APP_ENV'],
    (bool)$_ENV['APP_DEBUG'],
    '/config/bundles.php'
);
```

Для обеспечения "преемственности" (похожести) с оригиналом можно задать путь к файлу конфигурации
 (скажем, `bundles.php`) бандлов четвертым (необязательным) параметром конструктора.

#### Значимые переменные окружения

- `APP_ENV` - код окружения (dev, prod, test и т.д.)
- `APP_DEBUG` - режим отладки

## Конфигурирование

1) Опция `compile.container` в подтягиваемом конфиге - компилировать ли контейнер в файл. Если не задана, то "нет, не компилировать".
Имеет смысл для окружения, не равного "dev". Т.е. опция управляет дампированием контейнера на проде.

Место, где хранятся дампы контейнеров: `<значение переменной контейнера kernel.cache_dir>/symfony-app/containers` 

#### Пути к кэшу и логам

Определяются классом `AppKernel`. По умолчанию:

- путь к кэшу (`kernel.cache_dir`) - `/wp-content/cache`
- путь к логам (`kernel.logs_dir`) - `'/../../logs'` (два уровня выше DOCUMENT_ROOT - особенности используемой
сборки Битрикс)

Чтобы это изменить нужно отнаследоваться от класса `AppKernel` и переопределить несколько переменных:

```php
use Prokl\ServiceProvider\Services\AppKernel;

class MyKernel extends AppKernel
{
   protected $cacheDir = '/bitrix/cache/mycache';
    
   protected $logDir = '/logs-saver';
}
```
(второй вариант - отнаследоваться от `AppKernel` и переопределить методы `getCacheDir` и `getLogDir`).

Изменить через наследование класс ядра:

```php
class MyServiceProvider extends ServiceProvider
{
    protected $kernelServiceClass = MyKernel::class;
    
    protected $cacheDir = '/wp-content/my-cache';

}
```

Второй вариант - отнаследоваться от `ServiceProvider` и заменить метод `getPathCacheDirectory` своей логикой.

## Поддержка бандлов

Файл конфигурации - `/config/standalone_bundles.php`. Этот путь можно изменить через конструктор.

Папка, где лежат конфигурации - `/config`. Конфигурации бандлов - `/config/packages`.

#### Проблема с приватными сервисами

Согласно концепции Symfony все сервисы (в идеале) должны быть приватными и инжектиться. Но в кастомном случае 
часто нужно получать их через хелпер-сервис-локатор. Для превращения нужных сервисов в публичные предлагается
такое решение. В общем разделе параметров контейнера появилась опция `publicable_services`:

```yaml
parameters:
  publicable_services:
    - 'snc_redis.default'
```

После компиляции контейнера приватный сервис `snc_redis.default` станет публичным. 

## Сепаратные микро-контейнеры

Отдельные контейнеры - со своим конфигом, полностью изолированные (для модулей, плагинов и т.п.).

```php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Prokl\ServiceProvider\Micro\AbstractStandaloneServiceProvider;
use Prokl\ServiceProvider\Micro\ExampleAppKernel;

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
    
     /**
     * @var string $kernelServiceClass Класс, реализующий сервис kernel.
     * Нужен для того, чтобы экземпляры контейнеров в kernel сервисе не перемешивались.
     */
    protected $kernelServiceClass = ExampleAppKernel::class;

}
```

Пример класса `ExampleAppKernel`:

```php
/**
 * Class ExampleAppKernel
 * @package Prokl\ServiceProvider\Micro
 */
use Prokl\ServiceProvider\Micro\AbstractKernel;

class ExampleAppKernel extends AbstractKernel
{
    protected static $kernelContainer;
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

## Сервисы по умолчанию

Автоматом регистрируются несколько сервисов:

- `service_container` (и alias) - сервис-контейнер целиком
- `app.request` - конвертор глобалов в Request
- `custom.post.type.registrator` - регистратор кастомных типов постов в Wordpress
- синонимы сервиса `kernel`.
- `delegated_container_manipulator` - манипулятор делегированными контейнерами.
 

## Хэлперы

1) `container()` - отдает экземпляр контейнера (выступает в роли сервис-локатора):

```php
$kernel = container()->get('kernel');
```

2) `delegatedContainer()` - отдает экземпляр манипулятора (реализующего интерфейс `Symfony\Component\DependencyInjection\ContainerInterface`) 
делегированными контейнерами.

```php
$kernel = delegatedContainer()->get('my_module_id.service');
```

Делегированный контейнер - автономный контейнер, сформированные в модуле, плагине и тому подобных местах.