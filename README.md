# dhsb_content

Millbrook Community Food Network content for the DrupalHelps Site Building course, Exercise 2.7.

It imports content only: 22 terms across Skill, Location type, and Volunteer group, 7 locations, 30 shifts with 66 dates, and 3 stories. It creates no content types or fields. It expects the Module 2 model with the course's exact machine names. Students apply it after deleting the 3 terms they made by hand in 2.2b, because the import matches items by UUID, not by name.

Tested on Drupal 11.5.0-beta1, November 23, 2026:
- Clean run on an end-of-2.6 site: 7 / 30 / 3, 22 terms, no duplicates, Food prep under Kitchen, Sort donations at Eastside with 16 dates.
- Without deleting the hand-made terms: Kitchen, Food prep, and Pantry are duplicated.
- With a wrong machine name: stops with "Field field_capacity is unknown." and keeps what it imported. After the field is fixed, a second run finishes with no duplicates.

## Refreshing the dates

Shift dates are fixed in the YAML. They start January 5, 2027 and run about 7 weeks. Regenerate them before each cohort so the listing has upcoming shifts.

1. On a site with the Module 2 model and no content (no terms, no nodes), in the project folder:
   ```bash
   ddev exec "DHSB_START=2027-09-07 vendor/bin/drush php:script ../recipes/dhsb_content/scripts/generate.php"
   ```
   `DHSB_START` must be a Tuesday. UUIDs come from item names, so they don't change between runs.
2. Export:
   ```bash
   recipes/dhsb_content/scripts/export.sh
   ```

Shift times are entered as Eastern time and stored in UTC.

## assets/

Files students upload themselves (the recipe doesn't import them):
- 7 location photos, including `eastside-pantry.jpg` (3.3b). These are labeled placeholders made by `scripts/make-placeholder-photos.php`. Replace them with real, licensed photos before release.
- `annual-report-2026.pdf` (3.2). A one-page placeholder.
- `old-urls.txt` (5.3).
- `README.txt`. Lists which exercise uses each file. For the 3.2 donor video, students pick any YouTube video.
