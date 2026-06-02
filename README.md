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

Set the admin login before first run:

```sh
export GET_QUALITY_STUFF_ADMIN_EMAIL="admin@example.com"
export GET_QUALITY_STUFF_ADMIN_PASSWORD="choose-a-real-password"
php -S localhost:8000 -t public
```

If pretty URLs such as `/brands/example` do not resolve in your PHP version, use the router file:

```sh
php -S localhost:8000 -t public public/router.php
```

Then open:

```text
http://localhost:8000
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

The first configured admin account is seeded automatically when the app starts. Change the default password before using anything beyond local testing.

Defaults:

```text
email: admin@example.com
password: change-me-now
```

## DreamHost Notes

- Upload the project files.
- Ensure the web root points to `public`.
- Ensure `storage` is writable by PHP.
- Set `GET_QUALITY_STUFF_ADMIN_EMAIL` and `GET_QUALITY_STUFF_ADMIN_PASSWORD` in the hosting environment before first run, or edit `app/config.php` for a simple shared-hosting setup.
