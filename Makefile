compose_command = docker-compose run -u $(id -u ${USER}):$(id -g ${USER}) --rm php85

build:
	docker-compose build

shell: build
	$(compose_command) bash

destroy:
	docker-compose down -v

composer: build
	$(compose_command) composer install

lint: build
	$(compose_command) composer lint

refactor: build
	$(compose_command) composer refactor

test: build
	$(compose_command) composer test

test\:lint: build
	$(compose_command) composer test:lint

test\:refactor: build
	$(compose_command) composer test:refactor

test\:type-coverage: build
	$(compose_command) composer test:type-coverage

test\:types: build
	$(compose_command) composer test:types

test\:unit: build
	$(compose_command) composer test:unit

# Run all database and key type combinations (mirrors CI matrix)
test\:docker: test\:docker\:sqlite test\:docker\:mysql test\:docker\:postgres

# Run all key type variations for all databases
test\:docker\:all: test\:docker\:sqlite\:id test\:docker\:sqlite\:numeric test\:docker\:sqlite\:ulid test\:docker\:sqlite\:uuid test\:docker\:mysql\:id test\:docker\:mysql\:numeric test\:docker\:mysql\:ulid test\:docker\:mysql\:uuid test\:docker\:postgres\:id test\:docker\:postgres\:numeric test\:docker\:postgres\:ulid test\:docker\:postgres\:uuid

# SQLite tests (all key types)
test\:docker\:sqlite: test\:docker\:sqlite\:id test\:docker\:sqlite\:numeric test\:docker\:sqlite\:ulid test\:docker\:sqlite\:uuid

test\:docker\:sqlite\:id: build
	docker-compose run --rm -e DB_CONNECTION=sqlite -e CASCADE_PRIMARY_KEY_TYPE=id php85 vendor/bin/pest $(ARGS)

test\:docker\:sqlite\:numeric: build
	docker-compose run --rm -e DB_CONNECTION=sqlite -e CASCADE_PRIMARY_KEY_TYPE=id php85 vendor/bin/pest --configuration=phpunit.numeric.xml $(ARGS)

test\:docker\:sqlite\:ulid: build
	docker-compose run --rm -e DB_CONNECTION=sqlite -e CASCADE_PRIMARY_KEY_TYPE=ulid php85 vendor/bin/pest --configuration=phpunit.ulid.xml $(ARGS)

test\:docker\:sqlite\:uuid: build
	docker-compose run --rm -e DB_CONNECTION=sqlite -e CASCADE_PRIMARY_KEY_TYPE=uuid php85 vendor/bin/pest --configuration=phpunit.uuid.xml $(ARGS)

# MySQL tests (all key types)
test\:docker\:mysql: test\:docker\:mysql\:id test\:docker\:mysql\:numeric test\:docker\:mysql\:ulid test\:docker\:mysql\:uuid

test\:docker\:mysql\:id: build
	docker-compose up -d mysql
	docker-compose run --rm -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 -e DB_DATABASE=cascade_test -e DB_USERNAME=root -e DB_PASSWORD=password -e CASCADE_PRIMARY_KEY_TYPE=id php85 vendor/bin/pest $(ARGS)

test\:docker\:mysql\:numeric: build
	docker-compose up -d mysql
	docker-compose run --rm -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 -e DB_DATABASE=cascade_test -e DB_USERNAME=root -e DB_PASSWORD=password -e CASCADE_PRIMARY_KEY_TYPE=id php85 vendor/bin/pest --configuration=phpunit.numeric.xml $(ARGS)

test\:docker\:mysql\:ulid: build
	docker-compose up -d mysql
	docker-compose run --rm -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 -e DB_DATABASE=cascade_test -e DB_USERNAME=root -e DB_PASSWORD=password -e CASCADE_PRIMARY_KEY_TYPE=ulid php85 vendor/bin/pest --configuration=phpunit.ulid.xml $(ARGS)

test\:docker\:mysql\:uuid: build
	docker-compose up -d mysql
	docker-compose run --rm -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 -e DB_DATABASE=cascade_test -e DB_USERNAME=root -e DB_PASSWORD=password -e CASCADE_PRIMARY_KEY_TYPE=uuid php85 vendor/bin/pest --configuration=phpunit.uuid.xml $(ARGS)

# PostgreSQL tests (all key types)
test\:docker\:postgres: test\:docker\:postgres\:id test\:docker\:postgres\:numeric test\:docker\:postgres\:ulid test\:docker\:postgres\:uuid

test\:docker\:postgres\:id: build
	docker-compose up -d postgres
	docker-compose run --rm -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_PORT=5432 -e DB_DATABASE=cascade_test -e DB_USERNAME=postgres -e DB_PASSWORD=password -e CASCADE_PRIMARY_KEY_TYPE=id php85 vendor/bin/pest $(ARGS)

test\:docker\:postgres\:numeric: build
	docker-compose up -d postgres
	docker-compose run --rm -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_PORT=5432 -e DB_DATABASE=cascade_test -e DB_USERNAME=postgres -e DB_PASSWORD=password -e CASCADE_PRIMARY_KEY_TYPE=id php85 vendor/bin/pest --configuration=phpunit.numeric.xml $(ARGS)

test\:docker\:postgres\:ulid: build
	docker-compose up -d postgres
	docker-compose run --rm -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_PORT=5432 -e DB_DATABASE=cascade_test -e DB_USERNAME=postgres -e DB_PASSWORD=password -e CASCADE_PRIMARY_KEY_TYPE=ulid php85 vendor/bin/pest --configuration=phpunit.ulid.xml $(ARGS)

test\:docker\:postgres\:uuid: build
	docker-compose up -d postgres
	docker-compose run --rm -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_PORT=5432 -e DB_DATABASE=cascade_test -e DB_USERNAME=postgres -e DB_PASSWORD=password -e CASCADE_PRIMARY_KEY_TYPE=uuid php85 vendor/bin/pest --configuration=phpunit.uuid.xml $(ARGS)

# Local tests (without Docker) for different primary key types
test\:local: build
	vendor/bin/pest --parallel

test\:local\:id: build
	vendor/bin/pest --parallel

test\:local\:numeric: build
	vendor/bin/pest --parallel --configuration=phpunit.numeric.xml

test\:local\:ulid: build
	vendor/bin/pest --parallel --configuration=phpunit.ulid.xml

test\:local\:uuid: build
	vendor/bin/pest --parallel --configuration=phpunit.uuid.xml

test\:local\:all: test\:local\:id test\:local\:numeric test\:local\:ulid test\:local\:uuid
