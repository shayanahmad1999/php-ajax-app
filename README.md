# PHP AJAX + .env (Notes App)

A tiny CRUD app demonstrating:
- PHP + PDO database access
- `.env` configuration via `vlucas/phpdotenv`
- Fetch-based AJAX (no frameworks)

**Default DB:** MySQL (works out of the box on Azure App Service).  
**Optional:** PostgreSQL (see notes below).

---

## 1) Run locally

### Prereqs
- PHP 8.x
- Composer
- MySQL (or PostgreSQL if you switch drivers)

### Steps
```bash
git clone <this-repo>
cd php-ajax-env-app
cp .env.example .env           # edit DB settings
composer install
```

Create the DB schema:

**MySQL**
```sql
-- in MySQL client:
SOURCE database.mysql.sql;
```

**PostgreSQL (optional)**
```sql
-- run in psql:
\i database.postgres.sql
```

Start a local dev server:
```bash
php -S localhost:8000
```
Open http://localhost:8000/

---

## 2) Deploy to Azure App Service

### Option A — GitHub Actions (recommended)

1. Create an **Azure App Service (Linux)** with the **PHP 8.x** runtime.
2. In your GitHub repo, add a secret named **AZURE_WEBAPP_PUBLISH_PROFILE** (download it from *App Service → Deployment Center → Get publish profile*).
3. In your GitHub repo, add another secret **AZURE_WEBAPP_NAME** set to your app name.
4. Commit the workflow at `.github/workflows/azure-webapps.yml` (included).
5. Push to `main`. The workflow builds (`composer install`) and deploys.

### Configure environment variables
Set the following in *App Service → Configuration → Application settings*:

- `DB_DRIVER` = `mysql` (or `pgsql` if using Postgres)
- `DB_HOST`   = your server host (e.g., `yourmysql.mysql.database.azure.com`)
- `DB_PORT`   = `3306` for MySQL, `5432` for Postgres
- `DB_NAME`   = `php_ajax_env`
- `DB_USER`   = your DB username
- `DB_PASS`   = your DB password
- `DB_SSLMODE` = `require` (PostgreSQL only)

> App settings are exposed to PHP via `getenv()` on App Service. Do **not** deploy your local `.env`. Use App Settings in production.

### Create the database on Azure

**MySQL Flexible Server**
- Create *Azure Database for MySQL – Flexible Server*
- Allow public access (for a quick test) and add your client IP
- Create the database and table with `database.mysql.sql`

**PostgreSQL Flexible Server (optional)**
- Create *Azure Database for PostgreSQL – Flexible Server*
- Ensure SSL is enforced (default) and set `DB_SSLMODE=require`
- Create the database and table with `database.postgres.sql`

### Logs
Use *App Service → Log stream* to watch PHP errors, or enable via Azure CLI:
```bash
az webapp log config --resource-group <rg> --name <app> --docker-container-logging filesystem --level Verbose
```

---

## 3) PostgreSQL notes on App Service

App Service supports PHP on Linux. If the `pdo_pgsql` extension isn't enabled in your runtime, you can either:
- Use **MySQL** (pdo_mysql is available by default), or
- **Enable/bring the extension** via a custom `.ini` & extension `.so`, or
- Deploy a **custom container** with the extension baked in.

See links in the guide for details.

---

## 4) API

- `GET  api.php?action=list` — list notes
- `POST api.php?action=create` — body: `{ "title": "...", "body": "..." }`
- `POST api.php?action=update` — body: `{ "id": 1, "title": "...", "body": "..." }`
- `POST api.php?action=delete` — body: `{ "id": 1 }`

All responses are JSON.

---

## 5) Security & production hints

- This demo is intentionally minimal; add auth + CSRF in production.
- Use App Settings instead of `.env` in production environments.
- Restrict DB networking to your App Service VNet or outbound IPs.
- Turn off debug/error display in production.