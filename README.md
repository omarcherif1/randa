<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Pilotage de la satisfaction client — Groupe Randa (V2)

Application décisionnelle : comprendre pourquoi le taux de satisfaction client baisse,
suivre commandes / livraisons / ruptures / prévisions, avec des **chiffres fiables et
vérifiables** (priorité absolue sur l'esthétique).

- Stack : **Laravel 12 + React + Inertia.js + Tailwind + MySQL**.
- Architecture et périmètre : [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- Constats sur les données réelles + points à clarifier : [`docs/DATA_NOTES.md`](docs/DATA_NOTES.md)

## Installation sur un nouveau PC (Windows)

### 1. Logiciels à installer

| Outil | Version | Rôle |
|---|---|---|
| [XAMPP](https://www.apachefriends.org/) | PHP **8.2+**, MariaDB/MySQL | PHP + base de données |
| [Composer](https://getcomposer.org/) | 2.x | dépendances PHP |
| [Node.js](https://nodejs.org/) | 20+ (testé en 24) | front React / Vite |
| [Python](https://www.python.org/) | 3.11+ (testé en 3.14) | service de prévision ML (facultatif) |
| [Git](https://git-scm.com/) | — | récupérer le code |

Dans `C:\xampp\php\php.ini`, vérifier que ces extensions sont actives (pas de `;` devant) :
`extension=zip`, `extension=gd`, `extension=mbstring`, `extension=pdo_mysql`, `extension=fileinfo`.

### 2. Récupérer le code et les dépendances

```powershell
cd C:\xampp\htdocs
git clone <url-du-depot> randa2
cd randa2

composer install
npm install
```

### 3. Configuration (`.env`)

```powershell
copy .env.example .env
php artisan key:generate
```

Puis, dans `.env`, remplacer le bloc base de données par :

```ini
APP_NAME="Randa"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=randa2
DB_USERNAME=root
DB_PASSWORD=

ML_FORECAST_URL=http://127.0.0.1:8001
ML_FORECAST_TIMEOUT=30
```

### 4. Base de données

Démarrer MySQL (panneau XAMPP → **Start** sur MySQL, ou en ligne de commande) :

```powershell
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
```

Dans un autre terminal, créer la base puis le schéma et les données de référence :

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS randa2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed     # schéma + 32 familles + 21 catégories + seuils + compte admin
```

### 5. Importer les données

Les fichiers source (dossier `eya/`) ne sont pas dans Git : les copier à la main dans le projet, puis :

```powershell
php artisan randa:import "eya\F1790079066766_OMSTAGE.csv"          # fichier unifié (commandes)
php artisan randa:import "stock.csv" --type=stock                 # facultatif : fichier stock au format CSV « ; »
php artisan randa:forecast:run                                      # calcul des prévisions
```

L'import est aussi possible depuis l'application : **Administration → Import**.
Les zips `stock2025.zip` / `stock2026.zip` contiennent des `.xlsx` : les enregistrer en CSV (séparateur « ; ») avant l'import stock.

> Alternative : restaurer une sauvegarde faite sur l'ancien PC (copier son dossier
> `storage\app\backups\<date>` au même endroit) : `php artisan randa:restore --force`.

### 6. Service de prévision ML (facultatif, page Prévisions)

Une seule fois :

```powershell
cd ml-service
python -m venv venv
venv\Scripts\pip install -r requirements.txt
cd ..
```

### 7. Lancer le projet (à chaque fois)

Quatre terminaux, tous dans `C:\xampp\htdocs\randa2` :

```powershell
# 1 — base de données (sauf si déjà démarrée depuis le panneau XAMPP)
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone

# 2 — serveur Laravel
php artisan serve

# 3 — front (rechargement à chaud)
npm run dev

# 4 — service ML (facultatif)
cd ml-service
venv\Scripts\python -m uvicorn app.main:app --port 8001
```

Raccourci : `composer run dev` lance d'un coup Laravel + file d'attente + logs + Vite
(la base et le service ML restent à lancer à part).

Ouvrir **http://localhost:8000**. Compte de démarrage : `admin@randa.local` /
`Admin-Provisoire-2026` — changement de mot de passe forcé à la 1re connexion.

Sans `npm run dev`, compiler une fois le front : `npm run build`.

### 8. Vérifier l'installation

```powershell
php artisan test        # toute la suite doit être verte
```

### Problèmes fréquents

| Symptôme | Solution |
|---|---|
| `SQLSTATE[HY000] [2002] … refused` | MySQL n'est pas démarré (étape 4). |
| `Vite manifest not found` | lancer `npm run dev` ou `npm run build`. |
| Page blanche / anciennes valeurs après une modif de `.env` | `php artisan optimize:clear` |
| Prévisions ML « service indisponible » | lancer le service ML (terminal 4). |
| Accents remplacés par « � » dans les libellés | ajouter le mot dans `config/lexique_accents.php`, puis réimporter. |

## Vérifier les règles métier sur un fichier source (analyse à blanc, sans écriture)

```bash
php artisan randa:analyze-source "F1787140089784_OMSTAGE.csv"           # rapport de contrôle (sans écriture)
php artisan randa:analyze-source "F1787140089784_OMSTAGE.csv" --weights # + table article → poids (règle 2.8)
php artisan randa:import "F1787140089784_OMSTAGE.csv"                    # import persistant (dédoublonnage par remplacement)
php artisan randa:user:create "x@randa.local" "Nom" --societe=RAN --page=dashboard   # créer un compte
php artisan randa:forecast:run                                          # recalcule les prévisions (snapshots)
php artisan test          # 270 tests — métier + import + droits + dashboard + Livraisons/Stock/Export/Prévisions + cache + admin + notifications + polish + non-fuite export + scoping société + sauvegarde/restauration + risque dédoublonnage

php artisan randa:backup                 # sauvegarde manuelle (auto tous les jours à 02:00)
php artisan randa:restore [dossier]      # restauration (dernier dossier par défaut ; --force)
```

Compte de démarrage (local) : `admin@randa.local` / `Admin-Provisoire-2026` — **changement forcé à la 1re connexion**.

---

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
