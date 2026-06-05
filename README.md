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

Start the local PHP server:

```sh
scripts/serve-local.sh
```

Then open:

```text
http://127.0.0.1:8000
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

## DreamHost Notes

- Upload the project files.
- Ensure the web root points to `public`.
- Ensure `storage` is writable by PHP.
- Set `GET_QUALITY_STUFF_ADMIN_EMAIL` and `GET_QUALITY_STUFF_ADMIN_PASSWORD` in the hosting environment before first run.
