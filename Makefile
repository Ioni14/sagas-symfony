
.PHONY: up
up:
	docker-compose up -d
	composer install -o
	bin/console doctrine:database:create
	bin/console doctrine:migrations:migrate -n
