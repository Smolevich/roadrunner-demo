# RoadRunner + Temporal Demo

Демо-проект для тестирования RoadRunner с PHP workers. Демонстрирует автомасштабирование worker pool при работе с Temporal workflows.

## Требования

- Docker
- Docker Compose
- Temporal CLI (опционально, для мониторинга workflows)

## Быстрый старт

### 1. Запуск проекта

```bash
# Запустить все сервисы
docker compose up -d

# Проверить статус контейнеров
docker compose ps
```

Доступные сервисы:
- **Temporal UI**: http://localhost:8084
- **RoadRunner**: http://localhost:8085
- **Temporal Server**: localhost:7234
- **PostgreSQL**: localhost:5433

### 2. Установка зависимостей

Зависимости устанавливаются локально с помощью Docker:

```bash
docker run --rm -v "$(pwd):/app" -w /app roadrunner-demo-roadrunner \
  composer install --no-dev --optimize-autoloader
```

### 3. Проверка работы RoadRunner

```bash
# Посмотреть логи RoadRunner
docker compose logs -f roadrunner

# Проверить количество активных workers
docker compose exec roadrunner rr -c rr.yaml workers
```

## Запуск нагрузочного тестирования

### Запуск load test

```bash
# Запустить тест с 1 миллионом workflows (батчами по 1000)
docker run --rm \
  -v "$(pwd):/app" \
  -w /app \
  --network roadrunner-demo_default \
  -e TEMPORAL_ADDRESS=temporal:7233 \
  roadrunner-demo-roadrunner \
  php load-test.php 1000000 1000

# Или с меньшим количеством для быстрого теста
docker run --rm \
  -v "$(pwd):/app" \
  -w /app \
  --network roadrunner-demo_default \
  -e TEMPORAL_ADDRESS=temporal:7233 \
  roadrunner-demo-roadrunner \
  php load-test.php 10000 100
```

Параметры:
- Первый аргумент: общее количество workflows (по умолчанию 1000)
- Второй аргумент: размер батча для отображения прогресса (по умолчанию 100)

### Мониторинг автомасштабирования

Во время выполнения load test наблюдайте за автомасштабированием workers:

```bash
# В реальном времени (обновление каждые 3 секунды)
watch -n 3 'docker compose exec roadrunner rr -c rr.yaml workers'

# Или однократная проверка
docker compose exec roadrunner rr -c rr.yaml workers
```

Вы увидите как RoadRunner автоматически создает новые workers под нагрузкой:
- Стартовое количество: 2 workers (`num_workers: 2`)
- Максимальное: 25 workers (`max_workers: 25`)
- Скорость создания: 10 workers за цикл (`spawn_rate: 10`)

## Проверка статуса workflows в Temporal

### Использование Temporal CLI

```bash
# Установить Temporal CLI (если еще не установлен)
# macOS
brew install temporal

# Linux/Windows - см. https://docs.temporal.io/cli

# Подсчитать workflows в статусе Running
docker compose exec temporal-admin-tools temporal workflow count \
  --query 'ExecutionStatus="Running"'

# Подсчитать завершенные workflows
docker compose exec temporal-admin-tools temporal workflow count \
  --query 'ExecutionStatus="Completed"'

# Список активных workflows (первые 10)
docker compose exec temporal-admin-tools temporal workflow list \
  --query 'ExecutionStatus="Running"' \
  --limit 10
```

### Проверка через Temporal UI

Откройте http://localhost:8084 в браузере для визуального мониторинга:
- Количество запущенных workflows
- Статус выполнения
- История выполнения
- Детали каждого workflow

## Тестирование scale-down workers

После завершения load test, RoadRunner автоматически уменьшит количество workers:

### 1. Дождитесь завершения всех workflows

```bash
# Проверить количество Running workflows
docker compose exec temporal-admin-tools temporal workflow count \
  --query 'ExecutionStatus="Running"'

# Когда результат будет 0, все workflows завершены
```

### 2. Проверка уменьшения workers

```bash
# Проверить текущее количество workers
docker compose exec roadrunner rr -c rr.yaml workers

# Подождать 60 секунд (idle_timeout: 60s)
sleep 60

# Проверить снова - количество уменьшится
docker compose exec roadrunner rr -c rr.yaml workers
```

Workers автоматически уменьшаются до исходного количества (2) благодаря настройке `idle_timeout: 60s` в секции `dynamic_allocator`.

## Конфигурация автомасштабирования

Настройки в `rr.yaml`:

```yaml
temporal:
  activities:
    num_workers: 2                # Начальное количество workers
    max_concurrent: 100           # Максимум параллельных задач
    allocate_timeout: 60s         # Таймаут выделения worker
    reset_timeout: 60s            # Таймаут перезапуска worker
    destroy_timeout: 60s          # Таймаут остановки worker
    dynamic_allocator:
      max_workers: 25             # Максимальное количество workers
      spawn_rate: 10              # Сколько workers создавать за раз
      idle_timeout: 60s           # Через сколько останавливать idle workers
    supervisor:
      watch_tick: 1s              # Интервал проверки состояния workers
      ttl: 0s                     # Время жизни worker (0 = без ограничений)
      idle_ttl: 0s                # Idle время worker (0 = disabled)
      max_worker_memory: 256      # Лимит памяти на worker (MB)
      exec_ttl: 60s               # Максимальное время выполнения задачи
```

Переменные окружения (в `docker-compose.yml`):
- `TEMPORAL_ROADRUNNER_MAX_WORKERS_COUNT`: максимальное количество workers (по умолчанию 25)

[Полная документация по конфигурации RoadRunner](https://github.com/roadrunner-server/roadrunner/blob/master/.rr.yaml)

## Структура проекта

```
.
├── docker-compose.yml          # Конфигурация Docker Compose
├── Dockerfile                  # Образ RoadRunner с PHP
├── rr.yaml                     # Конфигурация RoadRunner
├── composer.json               # PHP зависимости
├── worker.php                  # Temporal worker
├── load-test.php               # Скрипт нагрузочного тестирования
└── src/
    ├── DemoWorkflow.php        # Пример Temporal workflow
    ├── DemoActivity.php        # Пример Temporal activity
    └── DemoActivityInterface.php
```

## Остановка проекта

```bash
# Остановить все сервисы
docker compose down

# Остановить и удалить volumes (очистить базу данных)
docker compose down -v
```

## Troubleshooting

### Workers не масштабируются

Проверьте настройки в `rr.yaml`:
- `max_concurrent` должен быть достаточно большим (рекомендуется 100+)
- `idle_ttl` в supervisor должен быть 0 или достаточно большим
- `max_worker_memory` должен быть больше реального потребления памяти

### Workers не уменьшаются после завершения workflows

Проверьте:
- Все ли workflows завершены (используйте `temporal workflow count`)
- Прошло ли достаточно времени (см. `idle_timeout` в `dynamic_allocator`)
- Есть ли в логах сообщения об ошибках: `docker compose logs roadrunner`

### Workflows не выполняются

```bash
# Проверьте логи RoadRunner
docker compose logs roadrunner

# Проверьте подключение к Temporal
docker compose logs temporal
```

### Ошибка "connection refused"

Убедитесь что все контейнеры запущены:
```bash
docker compose ps
```
