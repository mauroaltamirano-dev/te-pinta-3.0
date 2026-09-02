SHELL := /bin/bash

.DEFAULT_GOAL := help

SAIL := ./vendor/bin/sail
COMPOSER_IMAGE ?= composer:2
ARGS ?=

.PHONY: help setup guard up dev stop down restart status logs shell admin migrate format lint-check types test check build e2e audit artisan npm composer ops-prepare ops-status ops-verify ops-start ops-migrate ops-stop route-status route-cutover route-rollback media-route-status media-route-cutover

help:
	@printf '%s\n' \
		'Comandos de desarrollo de Te Pinta' \
		'' \
		'  make setup     Instala dependencias e inicializa el entorno local configurado' \
		'  make up        Inicia los contenedores de Sail en segundo plano' \
		'  make dev       Inicia Sail, migra y ejecuta Vite en primer plano' \
		'  make stop      Detiene los contenedores sin eliminarlos' \
		'  make down      Detiene y elimina contenedores y la red local' \
		'  make restart   Reinicia los contenedores existentes' \
		'  make status    Muestra el estado de los contenedores' \
		'  make logs      Sigue los logs de los contenedores' \
		'  make shell     Abre una terminal en el contenedor de la aplicación' \
		'  make admin     Crea un administrador de forma interactiva' \
		'  make migrate   Ejecuta las migraciones pendientes' \
		'' \
		'  make format    Aplica el formato de Pint y Prettier' \
		'  make lint-check  Verifica lint de PHP y frontend sin cambiar archivos' \
		'  make types     Ejecuta los controles de PHPStan y TypeScript' \
		'  make test      Ejecuta el control completo de PHP' \
		'  make check     Ejecuta controles CI y compila assets de producción' \
		'  make build     Compila los assets frontend de producción' \
		'  make e2e       Ejecuta las pruebas de Playwright' \
		'  make audit     Audita dependencias de Composer y npm' \
		'' \
		'  make ops-status    Muestra el estado del contenedor operativo aislado' \
		'  make ops-verify    Verifica salud operativa y assets compilados' \
		'  make ops-prepare   Compila y valida la imagen operativa' \
		'  make ops-start     Inicia la imagen operativa preparada (confirmado)' \
		'  make ops-migrate   Ejecuta migraciones operativas (confirmado)' \
		'  make ops-stop      Detiene el contenedor operativo (confirmado)' \
		'  make route-status  Muestra la ruta actual de Tailscale Serve' \
		'  make route-cutover Dirige Serve a Te Pinta 3.0 (confirmado)' \
		'  make route-rollback  Dirige Serve a Te Pinta 2.0 (confirmado)' \
		'  make media-route-status  Muestra la ruta Funnel pública de Instagram' \
		'  make media-route-cutover Dirige sólo medios de Instagram a :8082 (confirmado)' \
		'' \
		'  make artisan ARGS="route:list"' \
		'  make npm ARGS="run lint:check"' \
		'  make composer ARGS="audit"' \
		'' \
		'Los objetivos operativos mutables conservan las confirmaciones de los scripts.' \
		'Consultá docs/COMMANDS.md para configuración, controles focalizados y confirmaciones.'

setup:
	@if [[ ! -f .env ]]; then \
		cp .env.example .env; \
		printf 'Se creó .env. Definí un DB_PASSWORD local y ejecutá make setup otra vez.\n' >&2; \
		exit 2; \
	fi
	@password="$$(sed -n 's/^DB_PASSWORD=//p' .env | tail -n 1)"; \
	if [[ -z "$$password" || "$$password" == '""' || "$$password" == "''" ]]; then \
		printf 'Definí un DB_PASSWORD local no vacío en .env antes de configurar.\n' >&2; \
		exit 2; \
	fi
	@command -v docker >/dev/null || { printf 'Docker es obligatorio para la configuración.\n' >&2; exit 1; }
	docker run --rm --user "$$(id -u):$$(id -g)" -e HOME=/tmp -v "$(CURDIR):/app" -w /app $(COMPOSER_IMAGE) composer install
	$(SAIL) up -d
	@if ! grep -Eq '^APP_KEY=.+$$' .env; then $(SAIL) artisan key:generate; fi
	$(SAIL) artisan migrate
	$(SAIL) npm ci

guard:
	@if [[ ! -x "$(SAIL)" ]]; then \
		printf 'Falta %s. Instalá primero las dependencias de Composer.\n' "$(SAIL)" >&2; \
		printf 'Consultá docs/COMMANDS.md#configuración-inicial para iniciar sólo con Docker.\n' >&2; \
		exit 1; \
	fi

up: guard
	$(SAIL) up -d

dev: guard
	$(SAIL) up -d
	$(SAIL) artisan migrate
	$(SAIL) npm run dev

stop: guard
	$(SAIL) stop

down: guard
	$(SAIL) down

restart: guard
	$(SAIL) restart

status: guard
	$(SAIL) ps

logs: guard
	$(SAIL) logs -f $(ARGS)

shell: guard
	$(SAIL) shell

admin: guard
	$(SAIL) artisan admin:create

migrate: guard
	$(SAIL) artisan migrate

format: guard
	$(SAIL) composer lint
	$(SAIL) npm run format

lint-check: guard
	$(SAIL) composer lint:check
	$(SAIL) npm run lint:check

types: guard
	$(SAIL) composer types:check
	$(SAIL) npm run types:check

test: guard
	$(SAIL) composer test

check: guard
	$(SAIL) composer ci:check
	$(SAIL) npm run build

build: guard
	$(SAIL) npm run build

e2e: guard
	$(SAIL) npm run test:e2e

audit: guard
	$(SAIL) composer audit
	$(SAIL) npm audit

artisan: guard
	$(SAIL) artisan $(ARGS)

npm: guard
	$(SAIL) npm $(ARGS)

composer: guard
	$(SAIL) composer $(ARGS)

ops-prepare:
	deploy/tailscale/prepare-runtime.sh

ops-status:
	deploy/tailscale/runtime.sh status

ops-verify:
	deploy/tailscale/runtime.sh verify

ops-start:
	deploy/tailscale/runtime.sh start

ops-migrate:
	deploy/tailscale/runtime.sh migrate

ops-stop:
	deploy/tailscale/runtime.sh stop

route-status:
	deploy/tailscale/route.sh status

route-cutover:
	deploy/tailscale/route.sh cutover

route-rollback:
	deploy/tailscale/route.sh rollback

media-route-status:
	deploy/tailscale/instagram-media-route.sh status

media-route-cutover:
	deploy/tailscale/instagram-media-route.sh cutover
