
.PHONY: up
up:
	docker-compose up -d
	composer install -o
	sleep 2 # improve with container healthcheck
	bin/console messenger:setup-transports
	bin/console doctrine:database:create --if-not-exists
	bin/console doctrine:migrations:migrate -n

.PHONY: db
db:
	docker-compose exec mariadb mysql -uroot -proot main

.PHONY: db-logs
db-logs:
	docker-compose exec mariadb mysql -uroot -proot main -e 'SET GLOBAL log_output = "FILE"'
	docker-compose exec mariadb mysql -uroot -proot main -e 'SET GLOBAL general_log_file = "/var/lib/mysql/queries.log"'
	docker-compose exec mariadb mysql -uroot -proot main -e 'SET GLOBAL general_log = "ON"'
	docker-compose exec mariadb tail -f /var/lib/mysql/queries.log
