#!/bin/bash

# Exécuter la commande pour créer le client personnel
php artisan passport:client --personal

# Extraire les informations du client personnel
CLIENT_ID=$(php artisan passport:client --personal | grep 'Client ID' | awk '{print $NF}')
CLIENT_SECRET=$(php artisan passport:client --personal | grep 'Client secret' | awk '{print $NF}')

# Ajouter les informations au fichier .env
echo "PASSPORT_PERSONAL_ACCESS_CLIENT_ID=$CLIENT_ID" >> .env
echo "PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET=$CLIENT_SECRET" >> .env
