
.PHONY: up
up:
	docker-compose up -d
	composer install -o
	bin/console messenger:setup-transports
	bin/console doctrine:database:create --if-not-exists
	bin/console doctrine:migrations:migrate -n
