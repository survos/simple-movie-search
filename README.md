
Setup the project as follows.

add a DATABASE_URL to .env.local if you want to use something besides the default database.  It must be postgres, because of the jsonb columns.

Install meilisearch, run it on 7700

```bash
symfony proxy:domain:attach movie
composer install
yarn install && yarn dev
bin/console doctrine:database:create
bin/console doctrine:schema:update --force --complete

# download the raw data from imdb
bin/imdb.sh 
bin/imdb.sh title.basics.tsv.gz

# import a subset of the data.
bin/console app:import-imdb --limit 500

```

composer config repositories.dbal '{"type": "path", "url": "~/g/tacman/symfony_dynamic_db"}'
composer req tacman/symfony_dynamic_db:dev-tac

symfony server:start -d

and then open https://movie.wip
