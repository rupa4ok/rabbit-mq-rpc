init: docker-network docker-down-clear docker-build docker-up composer-install

docker-network:
	docker network create --driver=bridge --subnet=192.168.221.0/24 rpc-network || true

docker-down-clear:
	docker-compose down -v --remove-orphans

docker-pull:
	docker-compose pull

docker-build:
	docker-compose build

docker-up:
	docker-compose up -d

composer-install:
	docker-compose run --rm rpc-php-cli composer install

consume:
	docker-compose run --rm rpc-php-cli bin/console consume

publish:
	docker-compose run --rm rpc-php-cli bin/console publish