<?php

/**
 * @file
 * Builds Millbrook's content with fixed UUIDs, ready to export.
 *
 * Run it on a site that has the Module 2 content model and NO content:
 *   ddev exec "DHSB_START=2027-01-05 vendor/bin/drush php:script ../recipes/dhsb_content/scripts/generate.php"
 * Then run scripts/export.sh to write the YAML into content/.
 *
 * DHSB_START is the first Tuesday of the season. Every shift date is computed
 * from it, so regenerate before each cohort and the listing stays in the
 * future. UUIDs are derived from names, so they never change between runs.
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

$start = getenv('DHSB_START') ?: '2027-01-05';
$tz = new DateTimeZone('America/New_York');

/**
 * A stable UUID from a key, so re-running never changes an item's identity.
 */
$uuid = function (string $key): string {
  $h = md5('dhsb_content:' . $key);
  $h[12] = '5';
  $h[16] = dechex((hexdec($h[16]) & 0x3) | 0x8);
  return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
};

$terms = [];
$term = function (string $vid, string $name, ?string $parent = NULL, int $weight = 0) use (&$terms, $uuid): int {
  $t = Term::create([
    'vid' => $vid,
    'name' => $name,
    'uuid' => $uuid("term:$vid:$name"),
    'weight' => $weight,
    'parent' => $parent ? [$terms["$vid:$parent"]] : [0],
  ]);
  $t->save();
  return $terms["$vid:$name"] = (int) $t->id();
};

// Skill. Kitchen > Food prep is the hierarchy students build by hand in 2.2b.
$w = 0;
foreach ([
  'Kitchen' => ['Food prep', 'Dishwashing'],
  'Driving' => ["Commercial driver's license"],
  'Warehouse' => ['Forklift certified', 'Heavy lifting'],
  'Languages' => ['Spanish', 'Vietnamese'],
  'Office' => ['Data entry', 'Phone calls'],
] as $parent => $children) {
  $term('skill', $parent, NULL, $w++);
  foreach ($children as $i => $child) {
    $term('skill', $child, $parent, $i);
  }
}
foreach (['Pantry', 'Kitchen', 'Warehouse', 'Mobile'] as $i => $n) {
  $term('location_type', $n, NULL, $i);
}
foreach (['Individuals', 'Families', 'Youth 14+', 'Corporate teams'] as $i => $n) {
  $term('volunteer_group', $n, NULL, $i);
}

// Locations. Phone numbers use the 555-01xx range reserved for fiction.
$locations = [];
$hours = fn(array $lines) => ['value' => '<p>' . implode('<br>', $lines) . '</p>', 'format' => 'basic_html'];
foreach ([
  ['Eastside Pantry', 'Pantry', '412 Oak Street', '(555) 010-0101', ['Tuesday 9 to noon', 'Thursday 9 to noon', 'Saturday 10 to 2']],
  ['Northgate Pantry', 'Pantry', '88 Mill Road', '(555) 010-0102', ['Monday 4 to 7', 'Wednesday 4 to 7']],
  ['Riverside Pantry', 'Pantry', '230 River Avenue', '(555) 010-0103', ['Wednesday 10 to 1', 'Friday 10 to 1']],
  ['Southfield Pantry', 'Pantry', '15 Field Lane', '(555) 010-0104', ['Tuesday 3 to 6', 'Saturday 9 to noon']],
  ['Community Kitchen', 'Kitchen', '501 Main Street', '(555) 010-0105', ['Monday to Friday, lunch served 11:30 to 1']],
  ['Millbrook Warehouse', 'Warehouse', '9 Depot Way', '(555) 010-0106', ['Monday to Friday 8 to 4']],
  ['Mobile Pantry Van', 'Mobile', NULL, '(555) 010-0107', ['Thursday 3 to 6, at a different church each week']],
] as [$name, $type, $street, $phone, $h]) {
  $v = [
    'type' => 'location',
    'title' => $name,
    'uuid' => $uuid("node:location:$name"),
    'status' => 1,
    'field_location_type' => $terms["location_type:$type"],
    'field_phone' => $phone,
    'field_hours' => $hours($h),
  ];
  if ($street) {
    $v['field_address'] = [
      'country_code' => 'US',
      'address_line1' => $street,
      'locality' => 'Millbrook',
      'administrative_area' => 'OH',
      'postal_code' => '45011',
    ];
  }
  $n = Node::create($v);
  $n->save();
  $locations[$name] = (int) $n->id();
}

// Shift dates: [week offset from DHSB_START, weekday offset from Tuesday,
// start time, end time]. Stored in UTC, entered as Millbrook (Eastern) time.
$date = function (int $week, int $day, string $from, string $to) use ($start, $tz): array {
  $d = (new DateTimeImmutable($start, $tz))->modify('+' . ($week * 7 + $day) . ' days');
  $utc = new DateTimeZone('UTC');
  $mk = fn($t) => (new DateTimeImmutable($d->format('Y-m-d') . " $t", $tz))->setTimezone($utc)->format('Y-m-d\TH:i:s');
  return ['value' => $mk($from), 'end_value' => $mk($to)];
};

// The repeating shift from Marcus's note: Tuesday and Thursday mornings.
$eastside = [];
for ($wk = 0; $wk < 8; $wk++) {
  $eastside[] = $date($wk, 0, '09:00', '12:00');
  $eastside[] = $date($wk, 2, '09:00', '12:00');
}

// [title, location, summary, dates, capacity, skills, groups].
$shifts = [
  ['Sort donations at Eastside', 'Eastside Pantry', 'Sort canned and dry goods for the week ahead. No experience needed.', $eastside, 8, [], ['Individuals', 'Youth 14+', 'Corporate teams']],
  ['Stock shelves at Eastside', 'Eastside Pantry', 'Restock shelves before Saturday distribution.', [$date(0, 4, '09:00', '12:00'), $date(2, 4, '09:00', '12:00'), $date(4, 4, '09:00', '12:00')], 4, [], ['Individuals', 'Families']],
  ['Saturday distribution at Eastside', 'Eastside Pantry', 'Help families shop the pantry on its busiest day.', [$date(0, 4, '10:00', '14:00'), $date(1, 4, '10:00', '14:00'), $date(2, 4, '10:00', '14:00')], 10, [], ['Individuals', 'Families', 'Corporate teams']],
  ['Greet clients in Spanish at Eastside', 'Eastside Pantry', 'Welcome Spanish-speaking clients and help them check in.', [$date(1, 0, '09:00', '12:00'), $date(3, 0, '09:00', '12:00')], 1, ['Spanish'], ['Individuals']],
  ['Evening distribution at Northgate', 'Northgate Pantry', 'Hand out groceries to working families after school.', [$date(0, 6, '16:00', '19:00'), $date(1, 6, '16:00', '19:00')], 6, [], ['Individuals', 'Families']],
  ['Sort produce at Northgate', 'Northgate Pantry', 'Check and sort fresh produce from the Monday delivery.', [$date(1, 1, '15:00', '17:00')], 4, [], ['Individuals', 'Youth 14+']],
  ['Translate for clients at Northgate', 'Northgate Pantry', 'Help Vietnamese-speaking clients with intake forms.', [$date(2, 6, '16:00', '19:00')], 1, ['Vietnamese'], ['Individuals']],
  ['Pack produce boxes at Riverside', 'Riverside Pantry', 'Pack fresh produce into family boxes.', [$date(0, 1, '10:00', '13:00'), $date(2, 1, '10:00', '13:00')], 6, [], ['Families', 'Youth 14+']],
  ['Friday distribution at Riverside', 'Riverside Pantry', 'Help clients choose groceries at the Friday pantry.', [$date(0, 3, '10:00', '13:00'), $date(1, 3, '10:00', '13:00')], 6, [], ['Individuals']],
  ['Enter client visits at Riverside', 'Riverside Pantry', 'Record client visits in the intake spreadsheet.', [$date(1, 3, '10:00', '13:00')], 1, ['Data entry'], ['Individuals']],
  ['Afternoon distribution at Southfield', 'Southfield Pantry', 'Hand out groceries on Tuesday afternoons.', [$date(0, 0, '15:00', '18:00'), $date(2, 0, '15:00', '18:00')], 5, [], ['Individuals', 'Families']],
  ['Saturday setup at Southfield', 'Southfield Pantry', 'Set up tables and stock before the doors open.', [$date(1, 4, '08:00', '09:30')], 3, ['Heavy lifting'], ['Individuals', 'Corporate teams']],
  ['Kids snack packs at Southfield', 'Southfield Pantry', 'Pack weekend snack bags for school kids.', [$date(3, 4, '09:00', '11:00')], 8, [], ['Families', 'Youth 14+']],
  ['Prep lunch at Community Kitchen', 'Community Kitchen', 'Chop, measure, and prep for the daily lunch service.', [$date(0, -1, '09:00', '11:30'), $date(0, 1, '09:00', '11:30'), $date(1, -1, '09:00', '11:30')], 4, ['Food prep'], ['Individuals']],
  ['Serve lunch at Community Kitchen', 'Community Kitchen', 'Serve a hot lunch and clear tables.', [$date(0, 0, '11:00', '13:30'), $date(0, 2, '11:00', '13:30'), $date(1, 0, '11:00', '13:30')], 6, [], ['Individuals', 'Corporate teams']],
  ['Wash dishes at Community Kitchen', 'Community Kitchen', 'Keep the dish line moving after lunch.', [$date(0, 3, '13:00', '15:00'), $date(1, 3, '13:00', '15:00')], 2, ['Dishwashing'], ['Individuals', 'Youth 14+']],
  ['Bake bread at Community Kitchen', 'Community Kitchen', 'Bake bread for the week\'s lunches.', [$date(2, -1, '07:00', '10:00')], 3, ['Food prep'], ['Individuals']],
  ['Cooking class helper at Community Kitchen', 'Community Kitchen', 'Assist the chef at the monthly family cooking class.', [$date(3, 2, '18:00', '20:00')], 2, ['Kitchen'], ['Individuals']],
  ['Unload the delivery truck', 'Millbrook Warehouse', 'Unload pallets from the regional food bank truck.', [$date(0, -1, '08:00', '10:00'), $date(1, -1, '08:00', '10:00'), $date(2, -1, '08:00', '10:00')], 4, ['Forklift certified'], ['Individuals']],
  ['Inventory count at the warehouse', 'Millbrook Warehouse', 'Count stock so the pantries can order what they need.', [$date(1, 1, '09:00', '12:00')], 4, [], ['Individuals', 'Corporate teams']],
  ['Pack holiday boxes', 'Millbrook Warehouse', 'Pack boxes for the spring holiday drive.', [$date(6, 2, '09:00', '13:00'), $date(6, 4, '09:00', '13:00')], 20, [], ['Families', 'Corporate teams', 'Youth 14+']],
  ['Sort the food drive', 'Millbrook Warehouse', 'Sort everything the school food drive brought in.', [$date(4, 1, '13:00', '16:00')], 12, [], ['Corporate teams', 'Youth 14+']],
  ['Repack bulk rice', 'Millbrook Warehouse', 'Repack bulk rice into family-size bags.', [$date(2, 3, '09:00', '12:00')], 6, [], ['Individuals', 'Families']],
  ['Drive the mobile pantry', 'Mobile Pantry Van', 'Drive the van to this week\'s church stop.', [$date(0, 2, '14:00', '18:00'), $date(1, 2, '14:00', '18:00'), $date(2, 2, '14:00', '18:00')], 1, ["Commercial driver's license"], ['Individuals']],
  ['Hand out food from the van', 'Mobile Pantry Van', 'Hand out groceries at the van\'s weekly stop.', [$date(0, 2, '15:00', '18:00'), $date(1, 2, '15:00', '18:00')], 4, [], ['Individuals', 'Families']],
  ['Load the van', 'Mobile Pantry Van', 'Load the van at the warehouse before it leaves.', [$date(0, 2, '13:00', '14:00')], 2, ['Heavy lifting'], ['Individuals', 'Youth 14+']],
  ['Answer the volunteer phone line', 'Millbrook Warehouse', 'Return calls from people who want to volunteer.', [$date(0, 1, '13:00', '16:00'), $date(2, 1, '13:00', '16:00')], 1, ['Phone calls'], ['Individuals']],
  ['Update the shift calendar', 'Millbrook Warehouse', 'Help Marcus move the old PDF calendar into the new site.', [$date(1, 2, '10:00', '12:00')], 1, ['Data entry'], ['Individuals']],
  ['Teen volunteer orientation', 'Northgate Pantry', 'A short orientation for volunteers aged 14 to 17.', [$date(2, 4, '10:00', '11:30')], 15, [], ['Youth 14+']],
  ['Corporate team day at the warehouse', 'Millbrook Warehouse', 'A half day of sorting and packing for a company team.', [$date(5, 3, '09:00', '13:00')], 10, [], ['Corporate teams']],
];

foreach ($shifts as [$title, $loc, $summary, $dates, $cap, $skills, $groups]) {
  Node::create([
    'type' => 'opportunity',
    'title' => $title,
    'uuid' => $uuid("node:opportunity:$title"),
    'status' => 1,
    'field_summary' => $summary,
    'field_details' => ['value' => '<p>Wear closed-toe shoes. Park in the side lot and sign in at the front desk.</p>', 'format' => 'basic_html'],
    'field_shift' => $dates,
    'field_capacity' => $cap,
    'field_location' => $locations[$loc],
    'field_contact' => 'volunteer@millbrook.example',
    'field_skills' => array_map(fn($s) => $terms["skill:$s"], $skills),
    'field_good_for' => array_map(fn($g) => $terms["volunteer_group:$g"], $groups),
  ])->save();
}

foreach ([
  ['Twenty years at the warehouse', 'Millbrook Warehouse', 'Gloria Mendez started sorting cans at the warehouse in 2006. She hasn\'t missed a Tuesday since.'],
  ['A Thanksgiving table for the Ortiz family', 'Eastside Pantry', 'When the plant closed, the Ortiz family came to Eastside for the first time. This is what happened next.'],
  ['Why I drive the van', 'Mobile Pantry Van', 'Every Thursday the van parks at a different church. Its driver explains why he keeps showing up.'],
] as [$title, $loc, $body]) {
  Node::create([
    'type' => 'story',
    'title' => $title,
    'uuid' => $uuid("node:story:$title"),
    'status' => 1,
    'field_body' => ['value' => "<p>$body</p>", 'format' => 'basic_html'],
    'field_location' => $locations[$loc],
  ])->save();
}

printf("Built %d terms, %d locations, %d shifts (%d dates), 3 stories. Season starts %s.\n",
  count($terms), count($locations), count($shifts), array_sum(array_map(fn($s) => count($s[3]), $shifts)), $start);
