#!/bin/bash
# Local, in the project folder of a site where scripts/generate.php just ran (DDEV).
# Rewrites recipes/dhsb_content/content from that site's terms and content.
set -e
rm -rf recipes/dhsb_content/content
ddev exec "cd web && ../vendor/bin/dr content:export taxonomy_term --bundle=skill --bundle=location_type --bundle=volunteer_group --dir=../recipes/dhsb_content/content"
ddev exec "cd web && ../vendor/bin/dr content:export node --bundle=location --bundle=opportunity --bundle=story --dir=../recipes/dhsb_content/content"
