# Operations

Everything here is a thing you will want at a moment when you would rather not
be working it out from first principles.

Nothing in this file names a bucket, an account number or a credential. Where
one is needed it says where to find it.

## Tests

```sh
php tests/run.php          # everything
php tests/run.php page     # only files matching "page"
```

The suite builds its own database — the configured name with `_test` appended —
from `db/migrations` and `db/seed.sql`, and drops it at the start of every run.
It never reads or writes the live one, and it refuses to run at all if the
configured database already ends in `_test`.

The database user needs rights on it once:

```sql
GRANT ALL PRIVILEGES ON `sanatec_test`.* TO 'sanatec'@'localhost';
```

On the live database the application user needs `SELECT, INSERT, UPDATE, DELETE,
CREATE, INDEX, ALTER, REFERENCES` **and `DROP`** — the last because migrations
remove tables and columns. Migration 0004 stopped on its final statement for
want of `DROP` and had to be finished by hand; the grant is in place now.

On the server the tests run as the web user, so they read the real config
without a copy of the credentials being made:

```sh
rsync -az --exclude '.git/' ./ er-pair-01:/tmp/sanatec-tests/
ssh er-pair-01 'cd /tmp/sanatec-tests && sudo -u www-data php tests/run.php'
```

There is no PHPUnit. The project has no package manager, and adding one to run
a few dozen assertions would cost more than it returns. `tests/bootstrap.php` is
the whole framework.

## Migrations

```sh
php bin/migrate.php --status          # what is applied, what is pending
php bin/migrate.php                   # apply everything pending
php bin/migrate.php --new "add inquiries table"
```

Migrations are plain `.sql` files in `db/migrations`, named `NNNN_description.sql`
and applied in filename order. Applied versions are recorded in
`schema_migrations` with a SHA-256 of the file.

Three things follow from that, and they are the whole design:

- **Editing a migration that has already run is refused.** The checksum will not
  match and `--status` exits non-zero. Write a new migration instead.
- **There is no down direction.** MySQL does not roll DDL back. A migration that
  fails half way leaves the database part-changed and says so. Keep each file to
  one logical change, so "half way" stays a small place to be. Fix forward.
- **An existing database adopts migrations safely.** A migration whose header
  carries `-- baseline-if-table-exists: courses` is recorded as applied, not run,
  when that table is already present. That is how the live database joined this
  system without trying to re-create the tables it already had.

## Backups

A nightly job on the server dumps every database and uploads it to S3. It is not
part of this repository — it is `/usr/local/bin/db-backup.sh`, run from
`/etc/cron.d/db-backup` at 03:30, and configured by `/etc/db-backup.conf`.
It discovers databases dynamically, so `sanatec` is included without anyone
having to remember to add it. MySQL binary logging is on, so point-in-time
recovery between nightly dumps is possible.

**The server can write backups but not read them.** Its instance role has
`ListBucket` and `PutObject`, and deliberately not `GetObject` or `DeleteObject`.
A compromised web server therefore cannot read the backup history and cannot
destroy it. This is correct, and it has one consequence worth knowing before you
need it: **restoring is done from a workstation, not from the server.** Use an
AWS profile that has `GetObject` on the backup bucket; `aws configure list-profiles`
will show what is available, and the bucket name is in `/etc/db-backup.conf`.

### Restoring

Verified end to end on 2026-09-23: all six tables, matching row counts, accented
Spanish intact.

```sh
# 1. From a workstation, with a profile that can read the bucket.
BUCKET=$(ssh er-pair-01 'sudo grep BUCKET /etc/db-backup.conf | cut -d= -f2')
aws --profile <profile> s3 ls "s3://$BUCKET/er-pair-01/sanatec/"
aws --profile <profile> s3 cp "s3://$BUCKET/er-pair-01/sanatec/<file>" ./restore.sql.gz

# 2. Check it before trusting it.
gunzip -t restore.sql.gz
gunzip -c restore.sql.gz | grep -c 'CREATE TABLE'      # expect 7

# 3. Restore into a scratch database first. Never straight over the live one.
scp restore.sql.gz er-pair-01:/tmp/
ssh er-pair-01
sudo mysql -e "CREATE DATABASE sanatec_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c /tmp/restore.sql.gz | sudo mysql sanatec_restore

# 4. Compare before you commit to it.
sudo mysql -e "SELECT COUNT(*) FROM sanatec_restore.courses"
sudo mysql -e "SELECT val_en FROM sanatec_restore.settings WHERE skey='addr_locality'"

# 5. Only then swap. Take a dump of the live one first — it is about 4KB.
```

Rehearse this occasionally. A backup nobody has restored is a hypothesis.

## Signing in when mail is down

There are no passwords. If no message can be delivered — provider outage,
misconfiguration, first install — issue a one-time link from the server:

```sh
sudo -u www-data php bin/login-link.php emil@rensing.com --minutes 30
```

It works once and expires. Treat it like a password while it lives.

Queued messages that are not sign-in codes are delivered by cron:

```
* * * * * www-data php /var/www/sanatecdiving.com/bin/send-messages.php
```

## Deploying

The document root **is** the git checkout, so deploying is a pull:

```sh
git push origin main
ssh er-pair-01 'cd /var/www/sanatecdiving.com && git pull --ff-only origin main'
ssh er-pair-01 'cd /var/www/sanatecdiving.com && php bin/migrate.php'
```

Run the migration step whenever the release contains one; it is a no-op when it
does not. There is no build step.

Two things that will bite once each:

- The docroot is owned by the web user while `.git` is owned by the deploying
  user, so git reports "dubious ownership". Fixed permanently with
  `git config --global --add safe.directory /var/www/sanatecdiving.com`.
- `.htaccess` is tracked. If an untracked one exists on the server, the pull
  refuses until it is moved aside.
