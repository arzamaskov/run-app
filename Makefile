.PHONY: help build up down restart logs shell composer pnpm artisan migrate fresh seed test \
        lint stan psql backup-db restore-db test-db-create test-db-drop ci permissions \
        clean install fresh-install volumes ps info tinker release release-minor release-patch release-major
.DEFAULT_GOAL := help

# =============================================================================
# Переменные и настройки
# =============================================================================

# Цвета для вывода
GREEN  := \033[0;32m
YELLOW := \033[0;33m
NC     := \033[0m

# Пользователь для выполнения команд в контейнере
# По умолчанию используется пользователь laravel (uid:1000, gid:1000)
# Можно переопределить: make composer-install USER_ID=$(id -u)
USER_ID ?= 1000
GROUP_ID ?= 1000
EXEC_USER := -u $(USER_ID):$(GROUP_ID)

# Docker Compose команды
# Используем docker compose (v2) вместо docker-compose (v1)
DC := docker compose
DC_EXEC := $(DC) exec $(EXEC_USER)
DC_EXEC_ROOT := $(DC) exec -u root

# PostgreSQL
DB_USERNAME := laravel
DB_PASSWORD := secret
DB_DATABASE := runtracker
DB_DATABASE_TEST := $(DB_DATABASE)_test
PROJECT := $(shell basename "$(CURDIR)" | tr '[:upper:]' '[:lower:]')

# =============================================================================
# Справка
# =============================================================================

help: ## Показать эту справку
	@echo "$(GREEN)Доступные команды:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-20s$(NC) %s\n", $$1, $$2}'

# =============================================================================
# Docker Compose - управление контейнерами
# =============================================================================

build: ## Собрать Docker образы
	$(DC) build --no-cache

up: ## Запустить все контейнеры
	$(DC) up -d

down: ## Остановить все контейнеры
	$(DC) down

restart: ## Перезапустить все контейнеры
	$(DC) restart

shell: ## Подключиться к контейнеру PHP (от пользователя laravel)
	$(DC_EXEC) php-fpm sh

shell-root: ## Подключиться к контейнеру PHP от root
	$(DC_EXEC_ROOT) php-fpm sh

# =============================================================================
# Логи
# =============================================================================

logs: ## Показать логи всех контейнеров
	$(DC) logs -f

logs-nginx: ## Показать логи Nginx
	$(DC) logs -f nginx

logs-php: ## Показать логи PHP
	$(DC) logs -f php-fpm

logs-postgres: ## Показать логи PostgreSQL
	$(DC) logs -f postgres

postgres-logs: ## Показать логи PostgreSQL
	$(DC) logs -f postgres

# =============================================================================
# Подключения к сервисам
# =============================================================================

psql: ## Подключиться к PostgreSQL консоли
	$(DC) exec postgres psql -U $(DB_USERNAME) -d $(DB_DATABASE)

psql-root: ## Подключиться к PostgreSQL от суперпользователя
	$(DC) exec postgres psql -U postgres

redis-cli: ## Подключиться к Redis консоли
	$(DC) exec redis redis-cli

# =============================================================================
# Composer - управление PHP зависимостями
# =============================================================================

composer: ## Выполнить Composer команду (make composer CMD="install")
	$(DC_EXEC) php-fpm composer $(CMD)

composer-install: ## Установить PHP зависимости
	$(DC_EXEC) php-fpm composer install

composer-update: ## Обновить PHP зависимости
	$(DC_EXEC) php-fpm composer update

composer-require: ## Установить пакет (make composer-require PKG="vendor/package")
	$(DC_EXEC) php-fpm composer require $(PKG)

composer-dump: ## Обновить autoload
	$(DC_EXEC) php-fpm composer dump-autoload

# =============================================================================
# PNPM - управление фронтенд зависимостями
# =============================================================================

pnpm: ## Выполнить PNPM команду (make pnpm CMD="install")
	$(DC) exec node sh -lc 'corepack enable && pnpm $(CMD)'

pnpm-install: ## Установить Node зависимости
	$(DC) exec node sh -lc 'corepack enable && pnpm install'

pnpm-dev: ## Запустить Vite в режиме разработки
	$(DC) exec node sh -lc 'corepack enable && pnpm run dev'

pnpm-build: ## Собрать production версию фронтенда
	$(DC) exec node sh -lc 'corepack enable && pnpm run build'

pnpm-watch: ## Запустить watch mode
	$(DC) exec node sh -lc 'corepack enable && pnpm run watch'

# =============================================================================
# Artisan - Laravel команды
# =============================================================================

artisan: ## Выполнить Artisan команду (make artisan CMD="migrate")
	$(DC_EXEC) php-fpm php artisan $(CMD)

# =============================================================================
# Миграции и сидеры
# =============================================================================

migrate: ## Запустить миграции
	$(DC_EXEC) php-fpm php artisan migrate

migrate-rollback: ## Откатить последнюю миграцию
	$(DC_EXEC) php-fpm php artisan migrate:rollback

migrate-fresh: ## Пересоздать базу данных и запустить миграции
	$(DC_EXEC) php-fpm php artisan migrate:fresh

migrate-fresh-seed: ## Пересоздать базу данных, запустить миграции и сидеры
	$(DC_EXEC) php-fpm php artisan migrate:fresh --seed

seed: ## Запустить сидеры
	$(DC_EXEC) php-fpm php artisan db:seed

# =============================================================================
# Тестирование
# =============================================================================

test: ## Запустить тесты
	$(DC_EXEC) php-fpm php artisan test

test-coverage: ## Запустить тесты с покрытием
	$(DC_EXEC) php-fpm php artisan test --coverage

test-filter: ## Запустить конкретный тест (make test-filter FILTER="TestName")
	$(DC_EXEC) php php artisan test --filter=$(FILTER)

test-db-create: ## Создать тестовую базу данных
	@echo "$(GREEN)Создание тестовой базы данных...$(NC)"
	$(DC) exec postgres psql -U $(DB_USERNAME) -d postgres -c "CREATE DATABASE $(DB_DATABASE_TEST);" || echo "База уже существует"
	@echo "$(GREEN)✅ Тестовая БД создана$(NC)"

test-db-drop: ## Удалить тестовую базу данных
	@echo "$(YELLOW)⚠️  Удаление тестовой базы данных...$(NC)"
	$(DC) exec postgres psql -U $(DB_USERNAME) -d postgres -c "DROP DATABASE IF EXISTS $(DB_DATABASE_TEST);"
	@echo "$(GREEN)✅ Тестовая БД удалена$(NC)"

test-db-reset: ## Пересоздать тестовую БД с миграциями
	@echo "$(GREEN)Пересоздание тестовой базы данных...$(NC)"
	@make test-db-drop
	@make test-db-create
	$(DC_EXEC) php-fpm php artisan migrate --database=pgsql --env=testing
	@echo "$(GREEN)✅ Тестовая БД готова$(NC)"

# =============================================================================
# Качество кода
# =============================================================================

lint: ## Проверить код на соответствие стандартам (Laravel Pint)
	$(DC_EXEC) php-fpm ./vendor/bin/pint --test

lint-fix: ## Автоматически исправить стиль кода (Laravel Pint)
	$(DC_EXEC) php-fpm ./vendor/bin/pint

pint: lint ## Алиас для lint

pint-fix: lint-fix ## Алиас для lint-fix

phpcs: lint ## Алиас для lint (совместимость)

stan: ## Запустить статический анализ кода (PHPStan)
	$(DC_EXEC) php-fpm ./vendor/bin/phpstan analyse --memory-limit=2G

phpstan: stan ## Алиас для stan

deptrac: ## Запустить проверку на соответствие архитектурным правилам (Deptrac)
	$(DC_EXEC) php-fpm ./vendor/bin/deptrac analyse

ci: lint deptrac stan test ## Комбо для локальной проверки перед git push

# =============================================================================
# Кеш и оптимизация
# =============================================================================

cache-clear: ## Очистить все кеши
	$(DC_EXEC) php-fpm php artisan cache:clear
	$(DC_EXEC) php-fpm php artisan config:clear
	$(DC_EXEC) php-fpm php artisan route:clear
	$(DC_EXEC) php-fpm php artisan view:clear

optimize: ## Оптимизировать приложение
	$(DC_EXEC) php-fpm php artisan config:cache
	$(DC_EXEC) php-fpm php artisan route:cache
	$(DC_EXEC) php-fpm php artisan view:cache

optimize-clear: ## Очистить оптимизацию
	$(DC_EXEC) php-fpm php artisan optimize:clear

# =============================================================================
# Laravel утилиты
# =============================================================================

key-generate: ## Сгенерировать ключ приложения
	$(DC_EXEC) php-fpm php artisan key:generate

storage-link: ## Создать симлинк для storage
	$(DC_EXEC) php-fpm php artisan storage:link

queue-work: ## Запустить обработку очередей
	$(DC_EXEC) php-fpm php artisan queue:work

queue-listen: ## Запустить прослушивание очередей
	$(DC_EXEC) php-fpm php artisan queue:listen

queue-restart: ## Перезапустить queue workers
	$(DC_EXEC) php-fpm php artisan queue:restart

tinker: ## Запустить Tinker REPL
	$(DC_EXEC) php-fpm php artisan tinker

# =============================================================================
# Права доступа
# =============================================================================

permissions: ## Установить права доступа (выполняется от root)
	$(DC_EXEC_ROOT) php-fpm chown -R $(USER_ID):$(GROUP_ID) /var/www/html
	$(DC_EXEC_ROOT) php-fpm chmod -R 755 /var/www/html/storage
	$(DC_EXEC_ROOT) php-fpm chmod -R 755 /var/www/html/bootstrap/cache

permissions-fix: ## Исправить права доступа для storage и cache
	$(DC_EXEC_ROOT) php-fpm chown -R $(USER_ID):$(GROUP_ID) /var/www/html/storage /var/www/html/bootstrap/cache
	$(DC_EXEC_ROOT) php-fpm chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# =============================================================================
# Установка проекта
# =============================================================================

install: build up composer-install pnpm-install key-generate migrate storage-link permissions ## Полная установка проекта

fresh-install: build up composer-install pnpm-install key-generate migrate-fresh-seed storage-link permissions ## Полная установка с пересозданием БД

# =============================================================================
# Очистка
# =============================================================================

clean: down ## ⚠️  Удалить все контейнеры и volumes (УДАЛЯЕТ БД!)
	@echo "$(YELLOW)⚠️  ВНИМАНИЕ: Эта команда удалит ВСЕ ДАННЫЕ включая БД!$(NC)"
	@echo "$(YELLOW)Нажмите Ctrl+C для отмены или Enter для продолжения...$(NC)"
	@read confirm
	$(DC) down -v
	docker system prune -f

clean-all: ## ⚠️  Удалить всё включая образы (УДАЛЯЕТ БД!)
	@echo "$(YELLOW)⚠️  ВНИМАНИЕ: Эта команда удалит ВСЕ ДАННЫЕ включая БД и образы!$(NC)"
	@echo "$(YELLOW)Нажмите Ctrl+C для отмены или Enter для продолжения...$(NC)"
	@read confirm
	$(DC) down -v --rmi all
	docker system prune -af

# =============================================================================
# База данных - управление
# =============================================================================

backup-db: ## Создать бэкап базы данных
	@mkdir -p ./backups
	@echo "$(GREEN)Создание бэкапа БД...$(NC)"
	$(DC) exec -T postgres pg_dump -U $(DB_USERNAME) $(DB_DATABASE) | gzip > ./backups/backup_$(shell date +%Y%m%d_%H%M%S).sql.gz
	@echo "$(GREEN)✅ Бэкап создан в ./backups/$(NC)"

restore-db: ## Восстановить БД из бэкапа (make restore-db FILE=backup.sql.gz)
	@if [ -z "$(FILE)" ]; then \
		echo "$(YELLOW)⚠️  Укажите файл: make restore-db FILE=backup.sql.gz$(NC)"; \
		exit 1; \
	fi
	@echo "$(YELLOW)⚠️  ВНИМАНИЕ: Текущие данные БД будут заменены!$(NC)"
	@echo "$(YELLOW)Нажмите Ctrl+C для отмены или Enter для продолжения...$(NC)"
	@read confirm
	@echo "$(GREEN)Восстановление БД из $(FILE)...$(NC)"
	@gunzip < $(FILE) | $(DC) exec -T postgres psql -U $(DB_USERNAME) -d $(DB_DATABASE)
	@echo "$(GREEN)✅ БД восстановлена$(NC)"

db-list: ## Показать список всех баз данных
	@echo "$(GREEN)Список баз данных:$(NC)"
	$(DC) exec postgres psql -U $(DB_USERNAME) -d postgres -c "\l"

db-reset: ## ⚠️ Полностью пересоздать основную БД
	@echo "$(YELLOW)⚠️  ВНИМАНИЕ: Все данные БД будут удалены!$(NC)"
	@echo "$(YELLOW)Нажмите Ctrl+C для отмены или Enter для продолжения...$(NC)"
	@read confirm
	$(DC_EXEC) php-fpm php artisan migrate:fresh --seed
	@echo "$(GREEN)✅ БД пересоздана$(NC)"

db-schema: ## Показать структуру таблиц основной БД
	@echo "$(GREEN)Структура таблиц:$(NC)"
	$(DC) exec postgres psql -U $(DB_USERNAME) -d $(DB_DATABASE) -c "\dt"

db-schema-test: ## Показать структуру таблиц тестовой БД
	@echo "$(GREEN)Структура таблиц тестовой БД:$(NC)"
	$(DC) exec postgres psql -U $(DB_USERNAME) -d $(DB_DATABASE_TEST) -c "\dt"

# =============================================================================
# Информация и мониторинг
# =============================================================================

volumes: ## Показать информацию о volumes
	@echo "$(GREEN)Docker volumes проекта:$(NC)"
	@docker volume ls | grep '$(PROJECT)_' || echo "Volumes не найдены"
	@echo ""
	@echo "$(GREEN)Размер volumes:$(NC)"
	@docker system df -v | grep '$(PROJECT)_' || echo "Volumes не найдены"

check: ## Проверить, что все контейнеры запущены
	@echo "$(GREEN)Проверка контейнеров...$(NC)"
	@$(DC) ps
	@echo ""
	@echo "$(GREEN)Проверка подключения к БД...$(NC)"
	@$(DC) exec postgres pg_isready -U $(DB_USERNAME) || echo "$(YELLOW)⚠️ PostgreSQL не готов!$(NC)"

ps: ## Показать статус контейнеров
	$(DC) ps

ps-all: ## Показать все контейнеры Docker
	docker ps -a

stats: ## Показать статистику использования ресурсов
	docker stats

info: ## Показать информацию о проекте
	@echo "$(GREEN)Информация о проекте:$(NC)"
	@echo "  USER_ID:  $(USER_ID)"
	@echo "  GROUP_ID: $(GROUP_ID)"
	@echo ""
	@echo "$(GREEN)Версии:$(NC)"
	@printf "  PHP:     " && $(DC_EXEC) php-fpm php -v | head -n 1
	@printf "  Composer: " && $(DC_EXEC) php-fpm composer --version 2>/dev/null | head -n 1
	@printf "  Node.js: " && $(DC) exec node node -v
	@printf "  pnpm:    " && $(DC) exec node sh -lc 'corepack enable && pnpm -v'
	@echo ""
	@echo "$(GREEN)Laravel:$(NC)"
	@printf "  " && $(DC_EXEC) php-fpm php artisan --version

# =============================================================================
# Релизы
# =============================================================================

release: ## Подготовить релиз (make release TYPE=minor VERSION=1.2.3)
	@if [ -z "$(TYPE)" ]; then \
		$(DC_EXEC) php-fpm php artisan release:prepare $(if $(VERSION),$(VERSION),) --type=$(or $(TYPE),minor); \
	else \
		$(DC_EXEC) php-fpm php artisan release:prepare $(if $(VERSION),$(VERSION),) --type=$(TYPE); \
	fi

release-minor: ## Подготовить minor релиз (make release-minor VERSION=1.2.3)
	$(DC_EXEC) php-fpm php artisan release:prepare $(if $(VERSION),$(VERSION),) --type=minor

release-patch: ## Подготовить patch релиз (make release-patch VERSION=1.2.3)
	$(DC_EXEC) php-fpm php artisan release:prepare $(if $(VERSION),$(VERSION),) --type=patch

release-major: ## Подготовить major релиз (make release-major VERSION=1.2.3)
	$(DC_EXEC) php-fpm php artisan release:prepare $(if $(VERSION),$(VERSION),) --type=major

release-no-commit: ## Подготовить релиз без коммита (make release-no-commit TYPE=minor)
	$(DC_EXEC) php-fpm php artisan release:prepare $(if $(VERSION),$(VERSION),) --type=$(or $(TYPE),minor) --no-commit

release-no-tag: ## Подготовить релиз без тега (make release-no-tag TYPE=minor)
	$(DC_EXEC) php-fpm php artisan release:prepare $(if $(VERSION),$(VERSION),) --type=$(or $(TYPE),minor) --no-tag
