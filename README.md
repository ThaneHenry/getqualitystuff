# Get Quality Stuff

A plain PHP + SQLite directory for good physical brands and items.

## Requirements

- PHP 8.2+ with PDO SQLite enabled
- SQLite 3

On macOS, one common setup is:

```sh
brew install php
```

## Run Locally

At the start of a work session, sync the primary production database from
DreamHost and start the local PHP server:

```sh
scripts/start-work.sh
```

Then open:

```text
http://127.0.0.1:8000
```

The current local database is backed up to `backups/local` before it is replaced.
If the production download or database integrity check fails, the existing local
database is left unchanged and the server does not start. Database copies are
kept private to the local user, and local backups older than 30 days are removed.

To start without syncing production, or to sync without starting the server:

```sh
scripts/start-work.sh --offline
scripts/start-work.sh --sync-only
```

To start the server directly without the work-session checks:

```sh
scripts/serve-local.sh
```

At the end of a work session, run:

```sh
scripts/end-work.sh
```

This checks changed PHP files and whitespace, stops this project's local server,
verifies and snapshots the local database, reports uncommitted changes, and
prints the recommended commit and deployment reminders. It does not commit,
deploy, or upload the local database automatically. Servers started through
`scripts/start-work.sh` or `scripts/serve-local.sh` are supervised and can be
stopped reliably by the end-work script.

To deploy reviewed and committed code to production, run:

```sh
scripts/deploy-production.sh
```

This refuses to deploy uncommitted changes, checks all tracked PHP files, backs
up the primary production database, previews the DreamHost sync, requires a
typed `DEPLOY` confirmation, deploys code while preserving the production
database and uploads, and verifies that the production site returns a successful
HTTP response.

To run only the local pre-deployment checks:

```sh
scripts/deploy-production.sh --check-only
```

The local server seeds a dev-only admin login if it does not already exist:

```text
email: local-admin@getqualitystuff.test
password: local-admin-password
```

To use another host or port:

```sh
HOST=localhost PORT=8080 scripts/serve-local.sh
```

The database is created automatically at `storage/getqualitystuff.sqlite`.

## Import CSV Data

Place your real CSV at `data/initial.csv`. A template is available at `data/initial.example.csv`.

From the command line:

```sh
php scripts/import_csv.php data/initial.csv
```

Or log in at `/admin/login` and use the CSV import page.

Expected columns:

```text
type,name,brand_name,category,description,url,image_url,featured,sustainability_score,ethics_score,durability_score,repairability_score,transparency_score,packaging_score,value_score
```

Use `type=brand` for brand rows and `type=item` for item rows. `brand_name` is required for item rows.

The importer also accepts the current brand export format directly:

```text
rn,url,category,company location,manufacturing location,warranty,notes
```

Those rows are imported as brands. `rn` becomes the brand name, `notes` is stored on the brand profile, and the location/warranty fields are stored on the brand profile.

## Brand Metadata

If a brand has a website URL but no custom description, Get Quality Stuff tries to use the website's Open Graph description (`og:description`) when the brand is saved or imported. It also uses the website's Open Graph image (`og:image`) when no image URL has been provided.

To backfill metadata for already-imported brands:

```sh
php scripts/backfill_og_descriptions.php 25
php scripts/backfill_og_images.php 25
```

The optional number limits how many brands are checked in one run. Existing custom descriptions and image URLs are left unchanged.

## Admin

The first configured admin account is seeded automatically when the app starts, but only when both `GET_QUALITY_STUFF_ADMIN_EMAIL` and `GET_QUALITY_STUFF_ADMIN_PASSWORD` are set. The local start script sets dev-only credentials for testing.

## Account Email

Email verification and password recovery use a configurable mail transport:

```sh
GET_QUALITY_STUFF_APP_URL=https://getqualitystuff.com
GET_QUALITY_STUFF_MAIL_TRANSPORT=mail
GET_QUALITY_STUFF_MAIL_FROM=hello@getqualitystuff.com
```

`GET_QUALITY_STUFF_MAIL_TRANSPORT=mail` sends through PHP's configured mail service. The default `log` transport writes email previews to `storage/mail.log`, which is useful during local development.

`GET_QUALITY_STUFF_DATABASE_PATH` can optionally point the app at a different SQLite database for testing or deployment.

## DreamHost Notes

- Upload the project files.
- Ensure the web root points to `public`.
- Ensure `storage` is writable by PHP.
- Set `GET_QUALITY_STUFF_ADMIN_EMAIL` and `GET_QUALITY_STUFF_ADMIN_PASSWORD` in the hosting environment before first run.
