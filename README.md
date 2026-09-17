# SanaTec Diving

### An unreasonable amount of ambition. A refreshingly small website. Some very large holes full of water.

Ever wanted to have your own dive shop? Well, someone had the balls to actually do it—and this is his tech stack.

Welcome to **SanaTec Diving**, the digital beachhead for a venture built around dive training, cenote adventures, and the entirely understandable desire to spend less time on the surface. What begins here as a modest single-page website is intended to become a considerably more capable home for divers, instructors, explorers, and the people making sure everyone knows where to be and what they signed up for.

For now, we have resisted the temptation to require seventeen services, a quarterly infrastructure summit, and a dedicated platform engineering department to display a price list. The current site is plain HTML and CSS. It loads the supplied imagery, presents the courses and adventures, and gives visitors a direct line to a human.

Civilization may yet recover.

## What exists today

The current release is a lightweight, mobile-friendly placeholder website with an ocean-blue visual identity and a very specific job: help someone find a dive or course and get in touch.

- **Dive training:** ten course listings, with prices and durations taken from the supplied training guide.
- **Cenote adventures:** eleven route listings, with the supplied dive-price columns and certification requirements.
- **Direct contact:** WhatsApp and SMS links to **+52 984 106 3306**, including persistent contact buttons on mobile.
- **Original guides:** links to the supplied training and adventure images for reference.
- **Responsive presentation:** readable tables, with horizontal scrolling for the wider adventure table on small screens.
- **Minimal machinery:** no JavaScript, PHP, database, build step, third-party fonts, or external libraries required.

This is an informational site. Sign-up, booking management, online payments, and administrative tools are future work. The buttons open the visitor’s messaging service; the website itself does not send messages or confirm reservations.

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

### Multiple Languages (Including Mayan)

The water may be a universal attraction; the information about getting into it should not require everyone to read English.

The plan is to support multiple languages, including English, Spanish, and a Mayan-language experience. The specific Mayan language and regional variety will be selected with appropriate local input rather than treating “Mayan” as one interchangeable language.

Course descriptions, adventure information, and contact journeys should receive thoughtful translation and review. Safety-related wording deserves qualified human attention, not blind confidence in a translation button.

The aspiration is hospitality expressed through language: clear, useful information that makes more people feel welcome before the first conversation even begins.

### Payment in Most Currencies via WISE

An international diving audience should ideally be able to arrange payment without first undertaking an advanced certification in exchange-rate confusion.

The ambition is to support payment in most currencies via **Wise**, with clear amounts, payment instructions, and confirmation of payment status. The exact experience—payment links, bank-transfer instructions, or an integration—will depend on the options available to the business.

“Most currencies” is the roadmap goal, not a claim of current or universal support. Actual currencies, countries, account eligibility, fees, conversion rates, and integration options must be verified with Wise before implementation. The current website does not process payments.

### Backend to manage training and adventures

Behind every relaxed-looking dive operation is someone coordinating an impressive quantity of details while answering three messages about whether tomorrow is available.

A future administration area would give the team a central place to manage:

- Training courses, descriptions, prices, durations, and prerequisites.
- Adventure routes, certification requirements, and pricing options.
- Schedules, availability, capacity, and instructor assignments.
- Diver inquiries, registrations, and booking status.
- Payment references and confirmation status, where supported.
- Translated content and updates to the public site.

The objective is straightforward: make routine coordination easier, keep published information current, and give the people running the operation more time to run the operation. The backend should earn its complexity by doing useful work.

## The present-day technical marvel

```text
sanatec/
├── index.html              # The entire public page, including its CSS
├── assets/
│   ├── training.jpeg       # Original training guide; also used in the hero
│   └── adventures.jpeg     # Original adventure guide
├── UPLOAD.txt              # Short hosting and editing instructions
├── README.md               # This admirably restrained document
└── LICENSE                 # Apache License 2.0
```

The site uses system fonts, ordinary links, semantic tables, and local image files. There is no package manager to appease and no compilation ceremony to perform before changing a price.

That simplicity is intentional. Future features may require a server, storage, authentication, and integrations; today’s price list does not.

## Run it locally

Open `index.html` in a browser, or serve the repository with a local web server. For example, with Python installed:

```sh
python3 -m http.server 8765
```

Then open [localhost:8765](http://localhost:8765).

## Put it online

Upload **`index.html` and the `assets` folder together** to the website’s public directory, often named `public_html` or `www`. Preserve the folder structure so the images and original-guide links resolve correctly.

The current version works on ordinary static hosting. PHP support is unnecessary. Pushing to this repository alone does not configure or publish a hosted website.

## Keep the information shipshape

Edit the text, pricing, and links directly in `index.html`. When changing contact details, update every WhatsApp, SMS, and telephone link, including the mobile contact bar.

A few details deserve attention before publishing price changes:

- Prices are listed in **Mexican pesos (MXN)** and are subject to change.
- The adventure table preserves the original guide’s **1st dive / 2nd dive / 3rd dive** columns. Confirm whether those values represent cumulative packages before describing them that way.
- Divemaster pricing appears as **“Ask for pricing”**, reflecting the source guide’s “PM” entry.
- Yaa Kun retains the source guide’s special-price marker.
- If prices change, update the original guide images as well as the HTML so visitors are not offered two competing versions of reality.

## Development philosophy

Build what helps divers and the dive team. Keep the public experience quick, clear, and usable on a phone. Add infrastructure when the work requires it. Treat translations, payment flows, and personal data as responsibilities that deserve thoughtful implementation.

Above all, remember that the website is here to support the diving. Nobody came all this way to admire our dependency graph.

## License

Repository code is licensed under the [Apache License 2.0](LICENSE). Supplied branding and imagery remain subject to their respective owners’ rights; inclusion in this repository should not be read as a separate grant of trademark or image-reuse permission.

---

**SanaTec Diving — big plans above the water. Better adventures below it.**
