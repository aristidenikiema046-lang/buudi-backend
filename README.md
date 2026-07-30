## Déploiement Docker (VPS mutualisé ISPConfig)

Ce backend tourne en Docker sur un VPS mutualisé (`vps111567`, Debian 12, ISPConfig) qui héberge aussi d'autres projets clients. Les fichiers Docker sont conçus pour ne rien casser chez les voisins : pas de conteneur PostgreSQL propre (on utilise le Postgres natif partagé du serveur), pas de port 80/443 pris par nos conteneurs, `network_mode: host` pour joindre les services natifs du serveur (Postgres, Redis...) sans reconfigurer/redémarrer quoi que ce soit de partagé.

### Prérequis côté serveur

- Docker Engine + plugin Compose installés (dépôt officiel Docker, pas `docker.io` de Debian) :
  ```bash
  apt-get update
  apt-get install -y ca-certificates curl gnupg
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
  apt-get update
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  usermod -aG docker web52   # remplacer web52 par l'utilisateur jailé ISPConfig du site
  ```
- Une base PostgreSQL dédiée créée dans le Postgres natif du serveur (`127.0.0.1:5432`) :
  ```bash
  sudo -u postgres psql -c "CREATE DATABASE buudi;"
  sudo -u postgres psql -c "CREATE USER buudi_user WITH ENCRYPTED PASSWORD '...';"
  sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE buudi TO buudi_user;"
  sudo -u postgres psql -d buudi -c "GRANT ALL ON SCHEMA public TO buudi_user;"
  ```
- Un site ISPConfig créé (`api.buudi.net`), qui provisionne le répertoire `/var/www/clients/clientX/webY/` (accessible aussi via le lien `/var/www/api.buudi.net/`) et l'utilisateur système jailé associé.
- Un enregistrement DNS `A` pour le sous-domaine choisi, pointant vers l'IP du serveur.

### Récupérer le code sur le serveur

Toujours en tant qu'utilisateur jailé du site (jamais en root, pour ne pas casser la propriété des fichiers gérée par ISPConfig) :

```bash
cd /tmp
sudo -u web52 git clone https://github.com/aristidenikiema046-lang/buudi-backend.git /tmp/buudi-clone
sudo -u web52 rm -rf /var/www/api.buudi.net/web/error /var/www/api.buudi.net/web/stats \
  /var/www/api.buudi.net/web/favicon.ico /var/www/api.buudi.net/web/robots.txt \
  /var/www/api.buudi.net/web/standard_index.html
sudo -u web52 bash -c "shopt -s dotglob && mv /tmp/buudi-clone/* /var/www/api.buudi.net/web/"
sudo -u web52 rm -rf /tmp/buudi-clone
```

Pour les mises à jour suivantes, rester cohérent avec l'utilisateur propriétaire (sinon Git refuse avec une erreur "dubious ownership") :

```bash
cd /var/www/api.buudi.net/web/
sudo -u web52 git pull origin main
```

### Configurer l'environnement

```bash
cd /var/www/api.buudi.net/web/
sudo -u web52 cp .env.docker.example .env
sudo -u web52 nano .env
```

À compléter dans `.env` :

- `DB_PASSWORD` : mot de passe de `buudi_user`
- `APP_KEY` : générer en local avec `php -r "echo 'base64:'.base64_encode(random_bytes(32));"` et coller le résultat
- `JWT_SECRET` : générer avec `openssl rand -base64 64 | tr -d '\n'`
- `MAIL_*` : identifiants SMTP
- `FIREBASE_CREDENTIALS` : le fichier JSON doit être déposé sur le serveur puis référencé par ce chemin (interne au conteneur) :
  ```bash
  scp firebase-credentials.json root@vps111567:/var/www/api.buudi.net/web/storage/app/firebase-credentials.json
  sudo chown web52:client5 /var/www/api.buudi.net/web/storage/app/firebase-credentials.json
  ```

`APP_KEY` doit être renseigné **avant** de démarrer les conteneurs — `docker/entrypoint.sh` refuse de démarrer si `APP_KEY` est vide plutôt que de tenter de le générer dans le conteneur (un fichier `.env` n'existe pas dans l'image, seules les variables sont injectées via `env_file` dans `docker-compose.yml`).

### Build et démarrage

```bash
cd /var/www/api.buudi.net/web/
docker compose build
docker compose up -d
docker compose ps
docker compose logs -f app
```

Vérifier en local sur le serveur que ça répond (le service `webserver` écoute sur `127.0.0.1:8090`, jamais exposé publiquement directement) :

```bash
curl -I http://127.0.0.1:8090/
```

### Réseau : pourquoi `network_mode: host`

Le Postgres natif du serveur n'écoute que sur `127.0.0.1:5432` (pas sur l'IP du pont Docker `172.17.0.1`). Plutôt que de reconfigurer/redémarrer ce Postgres partagé — ce qui couperait temporairement les autres projets qui l'utilisent — les 4 services (`app`, `webserver`, `queue`, `scheduler`) tournent en `network_mode: host`. Ils utilisent donc directement la pile réseau de l'hôte : `DB_HOST=127.0.0.1` fonctionne nativement, Nginx écoute directement sur `127.0.0.1:8090`, et PHP-FPM sur `127.0.0.1:9000` (jamais exposés sur `0.0.0.0`).

### Brancher ISPConfig

Dans le site ISPConfig `api.buudi.net` :
- Onglet **SSL** : activer SSL + Let's Encrypt (le DNS doit déjà pointer vers le serveur).
- Ajouter un `ProxyPass`/`ProxyPassReverse` **dans ce vhost uniquement** vers `http://127.0.0.1:8090/` — jamais dans une configuration Apache globale.

### Pièges rencontrés pendant ce déploiement (pour la prochaine fois)

- `npm ci` échoue si `package-lock.json` n'est pas commité — toujours le générer (`npm install` en local) et le versionner.
- `docker/entrypoint.sh` ne doit pas faire `cp .env.example .env` : `.dockerignore` exclut les fichiers `.env*` de l'image (pour ne jamais embarquer de secrets), et de toute façon les variables sont déjà injectées par `env_file` — pas besoin d'un fichier `.env` physique dans le conteneur.
- Toujours effectuer les opérations Git/fichiers dans le dossier du site en tant que l'utilisateur jailé (`sudo -u web52 ...`), jamais en root, pour ne pas casser la propriété gérée par ISPConfig.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
