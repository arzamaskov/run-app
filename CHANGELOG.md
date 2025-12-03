# Changelog

Все значимые изменения в проекте документируются в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/).

## [0.5.0] - 2025-12-03

### Added

- feat(identity): реализована регистрация пользователей с использованием CQRS (#14) ([45d1e10])
- feat(identity): метод find в UserRepository с UserMapper и UuidCast (#13) ([296d605])

### Changed

- refactor(release): рефакторинг PrepareRelease в DDD-стиле ([48d8713])

## [Unreleased]

## [0.4.0] - 2025-11-20

## Changed

- Обновлен дизайн лендинга

## [3] - 2025-10-29

## Changed

- Изменено название приложения с 42к.рф на RunTracker

## [2] - 2025-10-24

## Added

- Nginx конфигурация для production
- Поддержка PostgreSQL вместо MySQL

## Fixed

- Настроен правильный роутинг для Laravel в nginx

## Changed

- Мигрировали с MySQL на PostgreSQL 16

## [1] - 2025-10-24

### Added

- Лендинг с секциями: hero, features, how it works, pricing, FAQ
- Иконки и манифесты приложения (favicon, apple-touch-icon, web-app-manifest)
- CI/CD pipeline на GitHub Actions (lint, static-analysis, tests)
- CD workflow для автоматического деплоя по тегам
- Настройка MySQL 8.4 для тестов в CI
- CODEOWNERS для автоматического назначения ревьюеров
- Шаблон Pull Request
- Команда `make ci` для локальной проверки перед push
- Настройка Pint (исключение служебных папок)
- Кастомное правило PHPStan для проверки DDD архитектуры
- CHANGELOG.md для ведения истории изменений

### Changed

- Обновлен PHP до версии 8.4
- Бейдж версии в README теперь берется из git тегов

