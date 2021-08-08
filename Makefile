build: run-migrate run-fetch-trailers

run-migrate:
	@exec php bin/console orm:schema-tool:update --force

run-fetch-trailers:
	@exec php bin/console fetch:trailers