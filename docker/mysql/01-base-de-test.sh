#!/bin/sh
# Exécuté par l'image MySQL à la création du volume (premier lancement uniquement) :
# crée la base des tests automatiques et donne les droits à l'utilisateur de l'application.
# Symfony ajoute le suffixe « _test » au nom de la base en environnement de test.
set -e

mysql --protocol=socket -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}_test\` CHARACTER SET utf8mb4;
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_test\`.* TO '${MYSQL_USER}'@'%';
SQL
