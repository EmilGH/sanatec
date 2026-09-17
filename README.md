# SanaTec Diving

### An unreasonable amount of ambition. A refreshingly small website. Some very large holes full of water.

Ever wanted to have your own dive shop? Well, someone had the balls to actually do it—and this is his tech stack.

Welcome to **SanaTec Diving**, the digital beachhead for a venture built around dive training, cenote adventures, and the entirely understandable desire to spend less time on the surface. What began as a modest single-page website is becoming a considerably more capable home for divers, instructors, explorers, and the people making sure everyone knows where to be and what they signed up for.

It has since acquired a database, a second language and a place for the owner to change a price himself. It has still resisted the temptation to require seventeen services, a quarterly infrastructure summit, and a dedicated platform engineering department to display a price list. There is no build step, no package manager and no JavaScript on the public page. Changing a price is a form and a Save button, not a deployment.

Civilization may yet recover.

## What exists today

The site is a small PHP application in front of a MySQL database, in English and
Spanish, with an administration area where the shop owner changes prices without
anyone opening a text editor.

- **Dive training:** ten course listings, with prices and durations, served from the database.
- **Cenote adventures:** eleven route listings, with dive-package prices and certification requirements.
- **Two languages:** English at `/` and Spanish at `/es/`, with `hreflang` alternates, a language
  switcher, and a fallback to English wherever a Spanish translation has not been written yet.
- **An owner's admin at `/admin/`:** add, edit, reorder, hide and delete courses and cenote routes;
  edit the address, opening hours, what is and is not included, and every piece of text on the page,
  in both languages. Saving publishes immediately. There is no build step and nothing to upload.
- **One contact number:** stored once, used by every WhatsApp, SMS and telephone link on both pages.
  Previously it appeared in eight places in one file, which is eight chances to update seven of them.
- **Findable:** Open Graph and Twitter cards so the link shows a picture when it is shared on
  WhatsApp, `LocalBusiness` structured data with the catalogue priced in MXN, a canonical URL,
  a generated `sitemap.xml`, and `robots.txt`.
- **Still quick:** no JavaScript on the public page, no third-party fonts, no external libraries,
  no package manager, no framework. The stylesheet is inline because one request beats two on a
  phone with one bar of signal at a cenote.

Sign-up, booking management and online payments remain future work. The buttons open the visitor's
messaging service; the website still does not send messages or confirm reservations.

### A note on the cenote price columns

The printed guide's three columns are published here as the **total price for a trip of that many
dives** — a two-dive day at $3,900 rather than $3,900 for the second dive on its own. This is the
only reading the guide supports: Dreamgate has a two-dive price and no one-dive price, which is
meaningless under the other interpretation. Multi-cenote routes are joined with `+` for the same
reason. Both are worth confirming with the shop; if they are wrong, the fix is two column headings
and eleven names, not the data.

## New features: the gloriously ambitious roadmap

The following features are **planned, not implemented**. They describe the direction of travel, not a contractual promise, a release schedule, or evidence that someone has already built an international payments department in a cenote.

### Diver sign up

A proper beginning to the relationship, beyond an enthusiastic message that says “diving tomorrow?”

The proposed sign-up experience would let divers create a profile, provide contact details, record certification levels, and share relevant diving experience. The aim is to give the team the context needed to discuss suitable training and adventures without reconstructing a diver’s history across an archaeological deposit of chat messages.

Any collection of personal information will need deliberate privacy, consent, and access controls. A profile will support planning; it will not replace qualification checks or instructor judgment.

### CENOTE Exploration Passport

Because memories are wonderful, but a growing collection of places explored deserves something more dignified than a folder called `dive_photos_FINAL_2`.

The **CENOTE Exploration Passport** is envisioned as a personal record of cenote adventures: places visited, dive dates, notes, and a satisfying account of the underwater landscapes a diver has experienced. Potential additions include digital stamps and a wish list of places still to explore.

Think expedition journal with fewer waterlogged pages. It would document experiences, not confer qualifications or grant access to dives beyond someone’s training and experience.

### Multiple Languages (Including Mayan) — English and Spanish are done

The water may be a universal attraction; the information about getting into it should not require everyone to read English.

English and Spanish are now live. A Mayan-language experience is still planned. The specific Mayan language and regional variety will be selected with appropriate local input rather than treating “Mayan” as one interchangeable language.

Course descriptions, adventure information, and contact journeys should receive thoughtful translation and review. Safety-related wording deserves qualified human attention, not blind confidence in a translation button.

The aspiration is hospitality expressed through language: clear, useful information that makes more people feel welcome before the first conversation even begins.

### Payment in Most Currencies via WISE

An international diving audience should ideally be able to arrange payment without first undertaking an advanced certification in exchange-rate confusion.

The ambition is to support payment in most currencies via **Wise**, with clear amounts, payment instructions, and confirmation of payment status. The exact experience—payment links, bank-transfer instructions, or an integration—will depend on the options available to the business.

“Most currencies” is the roadmap goal, not a claim of current or universal support. Actual currencies, countries, account eligibility, fees, conversion rates, and integration options must be verified with Wise before implementation. The current website does not process payments.

### Backend to manage training and adventures — the catalogue half is done

Behind every relaxed-looking dive operation is someone coordinating an impressive quantity of details while answering three messages about whether tomorrow is available.

The administration area exists and covers the catalogue:

- ✅ Training courses, prices, durations and notes.
- ✅ Adventure routes, certification requirements and dive-package pricing.
- ✅ Translated content and the text of the public site, in both languages.
- ✅ A record of who changed which price, and when.
- ⬜ Schedules, availability, capacity and instructor assignments.
- ⬜ Diver inquiries, registrations and booking status.
- ⬜ Payment references and confirmation status.

The unticked items all need a diver to exist as a record in the system, which is
the sign-up work above, so they wait for it.

The objective is straightforward: make routine coordination easier, keep published information current, and give the people running the operation more time to run the operation. The backend should earn its complexity by doing useful work.

## The present-day technical marvel

```text
sanatec/
├── index.php               # The public page, in whichever language was asked for
├── sitemap.php             # Generated sitemap, served as /sitemap.xml
├── .htaccess               # Routing, and blocking everything below that must not be served
├── robots.txt
├── admin/                  # The owner's administration area
│   ├── index.php           #   Overview, and a list of what is still blank
│   ├── courses.php         #   Dive training
│   ├── routes.php          #   Cenote adventures
│   ├── settings.php        #   Address, hours, what is included, page text
│   ├── login.php, logout.php, password.php
│   └── _init.php, _layout.php
├── src/                    # Not served. Guards against being run directly anyway.
│   ├── bootstrap.php       #   Configuration, database handle, helpers
│   ├── Settings.php        #   Site content, and the shape of the admin form
│   ├── Catalog.php         #   Courses and routes
│   ├── I18n.php            #   Interface strings the owner does not edit
│   ├── Auth.php            #   Sign-in, throttling, password rules
│   ├── Csrf.php, Audit.php
├── templates/              # Not served.
│   ├── public.php          #   The page
│   ├── head.php            #   Metadata, link previews, structured data
│   └── styles.php          #   The stylesheet, inlined into the page
├── db/                     # Not served.
│   ├── schema.sql
│   └── seed.sql            #   The catalogue as printed in the supplied guides
├── bin/
│   ├── install.php         #   Create the tables, load the catalogue, make an admin user
│   └── router.php          #   Stands in for .htaccess under PHP's built-in server
├── assets/
│   ├── training.jpeg       #   Original training guide; also cropped for the hero
│   ├── adventures.jpeg     #   Original adventure guide
│   └── og-image.jpg        #   The picture people see when the link is shared
├── UPLOAD.txt              # Setup and hosting instructions
├── README.md               # This increasingly less restrained document
└── LICENSE                 # Apache License 2.0
```

The configuration file is deliberately **not** in this tree. It lives outside the
document root, because the site is served straight from a git checkout and
anything inside it is one webserver misconfiguration away from being downloaded.

The site uses system fonts, ordinary links, semantic tables, and local image files. There is no package manager to appease and no compilation ceremony to perform before changing a price.

That simplicity is intentional. Future features may require a server, storage, authentication, and integrations; today’s price list does not.

## Run it locally

You need PHP 8.1+ with `pdo_mysql`, and a MySQL database.

Put a `config.local.php` in the repository root (it is git-ignored):

```php
<?php
return [
    'db' => ['host' => '127.0.0.1', 'port' => 3306,
             'name' => 'sanatec', 'user' => '...', 'pass' => '...'],
    'base_url' => 'http://localhost:8765',
];
```

Then create the tables, load the catalogue and make yourself an admin user:

```sh
php bin/install.php owner
```

It prints a generated password once. Now serve it:

```sh
php -S 127.0.0.1:8765 -t . bin/router.php
```

The router stands in for the `.htaccess` rules, so [localhost:8765](http://localhost:8765),
[/es/](http://localhost:8765/es/) and [/admin/](http://localhost:8765/admin/) all behave the
way they do under Apache.

## Put it online

The site is deployed as a git checkout published in place, so shipping is `git pull` on the
server. Three things have to be true:

- **`AllowOverride All`**, so the `.htaccess` file is honoured. Without it the `src/`,
  `templates/` and `db/` directories — and the `.git` directory, from which the whole
  repository can usually be reconstructed — become downloadable.
- **`DirectoryIndex index.php`** ahead of `index.html`.
- **The config file lives outside the document root.** If it is inside, one webserver
  hiccup away is your database password.

There is no build step and no `composer install`. There is nothing to compile.

## Keep the information shipshape

Sign in at `/admin/` and change it there. Prices, durations, certifications, the address,
the opening hours, what is and is not included, and every line of text on both language
pages are all editable, and saving publishes immediately.

A few details still deserve attention:

- Prices are listed in **Mexican pesos (MXN)** and are subject to change.
- The three cenote columns are **total prices for that many dives**. Confirm this is how
  the shop actually sells them.
- Divemaster has no price, so the page shows **"Ask for pricing"**. Leaving any course
  price blank does the same.
- Yaa Kun keeps the source guide's special-price marker, which is a checkbox on the route.
- The **location and opening hours start empty on purpose.** Until they are filled in, the
  page shows no location block and search engines are given no address — better than
  publishing a guess and sending divers to the wrong place. The admin overview lists this,
  and everything else still blank, on the front page.
- **"What is included" is written but switched off.** It was drafted from ordinary Riviera
  Maya practice, not from anything the shop confirmed. Check every line, then publish it.
- If prices change, update the original guide images as well, so visitors are not offered
  two competing versions of reality.

## Development philosophy

Build what helps divers and the dive team. Keep the public experience quick, clear, and usable on a phone. Add infrastructure when the work requires it. Treat translations, payment flows, and personal data as responsibilities that deserve thoughtful implementation.

Above all, remember that the website is here to support the diving. Nobody came all this way to admire our dependency graph.

## License

Repository code is licensed under the [Apache License 2.0](LICENSE). Supplied branding and imagery remain subject to their respective owners’ rights; inclusion in this repository should not be read as a separate grant of trademark or image-reuse permission.

---

**SanaTec Diving — big plans above the water. Better adventures below it.**
