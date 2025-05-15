#!/bin/sh
set -e

# Le nom du service de base de données défini dans docker-compose.yml
DB_HOST="db"
# Le port PostgreSQL par défaut
DB_PORT="5432"
# L'utilisateur PostgreSQL, récupéré depuis les variables d'environnement (provenant du .env)
# Symfony utilise DATABASE_URL, mais pg_isready préfère PGUSER ou -U.
# Nous allons supposer que POSTGRES_USER est défini dans votre .env
DB_USER="${POSTGRES_USER:-symfony}" # Utilise POSTGRES_USER ou "symfony" par défaut

# Nouvelles variables pour RabbitMQ
RABBITMQ_HOST="rabbitmq"
RABBITMQ_PORT="5672"

echo "Attente de RabbitMQ sur ${RABBITMQ_HOST}:${RABBITMQ_PORT}..."

# Boucle jusqu'à ce que netcat puisse se connecter au port RabbitMQ
until nc -z "${RABBITMQ_HOST}" "${RABBITMQ_PORT}"; do
  >&2 echo "RabbitMQ n'est pas encore disponible - nouvelle tentative dans 1 seconde..."
  sleep 1
done

>&2 echo "RabbitMQ est disponible."

echo "Attente de PostgreSQL sur ${DB_HOST}:${DB_PORT} avec l'utilisateur ${DB_USER}..."

# Boucle jusqu'à ce que pg_isready retourne un code de succès (0)
# L'option -q (quiet) supprime les messages normaux, ne montrant que les erreurs.
until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -q; do
  >&2 echo "PostgreSQL n'est pas encore disponible - nouvelle tentative dans 1 seconde..."
  sleep 1
done

>&2 echo "PostgreSQL est disponible."
>&2 echo "Exécution des migrations Doctrine..."

# Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

>&2 echo "Migrations exécutées."

# Exécute la commande passée en argument à ce script (le CMD du Dockerfile)
exec "$@" 