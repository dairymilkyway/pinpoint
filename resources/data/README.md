# Bundled geographic data

Inert JSON snapshots, committed so the app needs no API key and makes no external
call at request time. Read by `App\Geo\PhLocations`; nothing else should parse
these files directly.

| File | Contents |
|---|---|
| `psgc-regions.json` | 18 regions |
| `psgc-provinces.json` | 82 provinces, each with its parent region code |
| `psgc-cities.json` | 1642 cities and municipalities, each with region, province and class |
| `geo-by-city.json` | City code to postal code, latitude and longitude: 1490 of the 1642 cities have a row, all 1490 of them with coordinates |

## Sources and attribution

**Philippine Standard Geographic Code (PSGC)**, Revision 1, snapshot of
2025-07-31, from the Philippine Statistics Authority.
<https://psa.gov.ph/classification/psgc> - CC BY 4.0.

**GeoNames** postal codes and coordinates for the Philippines, drawn from two of
its downloads: the postal file `PH.zip` at
<https://download.geonames.org/export/zip/>, which supplies nearly every row here,
and the gazetteer dump `PH.zip` at <https://download.geonames.org/export/dump/>,
which supplies Taguig and Pateros - the two cities the postal file cannot reach,
being keyed by district rather than by city.

Both are used under CC BY 4.0 and are credited in the app footer.

## The join, and why it is imperfect

PSGC and GeoNames share no key, so cities were matched on name. Name alone is
not sufficient: 123 normalised names are ambiguous - `Quezon` alone is seven
distinct municipalities - so a match is only accepted when the name agrees
**and** the province (or, for province-less cities, the region) agrees. Anything
else is dropped rather than guessed. That leaves 1490 of 1642 cities with
coordinates.

Three genuine divergences between the datasets were recorded as explicit aliases
rather than papered over by loosening the matcher:

- GeoNames calls the capital region **Metro Manila**; PSGC calls it
  **National Capital Region (NCR)**. An address is written in the first name and
  classified in the second, so `PhLocations::REGION_LABELS` overrides that one
  region's label to **Metro Manila** - in code rather than in
  `psgc-regions.json`, so the snapshot stays a faithful copy of the PSA release.
  Both forms still resolve when a reader qualifies a city by region.
- PSGC 2025 carries the **Negros Island Region**, re-established in 2024 out of
  Western and Central Visayas. GeoNames predates that and still files those
  cities under the two older regions.
- GeoNames keys Metro Manila by postal district (`manila cpo ermita`, `diliman`),
  never by city, so no city row exists to match. The fifteen Metro Manila cities
  whose own central post office row is present are keyed to it by hand, so both
  their coordinates and their postal code come from that city's own post office -
  Manila 1000, Quezon City 1100, Makati 1200 and so on. Taguig and Pateros are the
  two the postal file cannot reach even that way: it names neither, so their
  positions come from the gazetteer instead, and their postal codes from PHLPost.
  They are the sixteenth and seventeenth cities of the capital to be placed, and
  the only two placed without it.

### Known gaps

- **152 of the 1642 cities have no coordinates**. They are spread across every
  region - BARMM alone accounts for 40 - and the cause is the same in each:
  GeoNames has no place row whose name and parent both agree with PSGC, so the
  join drops it rather than guessing.
  No coordinate is stored for these: the address row keeps a null latitude, and
  the coverage figure counts it as unpinned. The map still draws it, at the centre of
  the province or region it sits in, flagged as approximate - so the gap is
  visible on the map rather than being a silent hole in it, and no invented
  coordinate ever reaches the database.
- **The capital is fully pinned**, which it has not always been. All seventeen NCR
  cities now have a row carrying a position: fifteen from their own central post
  office, the alias recorded above, and Taguig and Pateros from the gazetteer.
  That matters more than the count suggests, because the join alone left every one
  of the seventeen unpinned - a capital with no pin at all was the default state
  rather than an edge case.

  **Taguig's 1630 is one of nine codes PHLPost lists for the city** (1630-1638,
  divided by delivery area), so it is the main code rather than the only one, and
  the caveat below applies to it as much as to any other. Pateros' is 1620, with
  1621 for Santa Ana.

- **A row may carry a postal code and no coordinates**, and two did before the
  gazetteer placed them. That shape is still legal and still handled: this dataset
  takes coordinates from the GeoNames postal file, which keys the capital by
  district and so names neither city, so a row for either could only ever have
  held the postal half. `approximateFor()` and `indexCentres()` ask whether a row
  *holds* coordinates rather than whether it exists, which is what draws such a
  row at the centre it borrows instead of taking it off the map - and what keeps
  the missing latitude from being averaged in as 0.0 and dragging the whole
  region's centre off the archipelago. No bundled row is written that way today.

## On postal codes

**Postal codes here are approximate and must not be presented as authoritative.**
PSGC carries no postal code at all. Philippine postal codes are delivery-area
identifiers, not administrative ones: a city commonly has several, and one code
can span several localities, so there is no 1:1 city-to-postal mapping to be had.
GeoNames provided the plausible values, with two exceptions: Taguig's and
Pateros' came from PHLPost's own locator instead, because the GeoNames file the
join consumes has no row for either - it keys the capital by district, not by
city. That locator is the only authority and it has no API.

Latitude and longitude come from the same GeoNames row as the postal code - or,
for Taguig and Pateros, from the gazetteer's place of that name, since the postal
file has no row to take them from - so they are the coordinates of the matched
place rather than of a specific street address. They are adequate for a map pin
and are not survey data.

## Regenerating

The transform that produced `psgc-*` and `geo-by-city` lives outside the project
root and is not part of the build. Re-running it is only necessary when the
upstream snapshots are refreshed, and it must keep the "name plus parent agree"
rule intact - relaxing it silently reintroduces the wrong-postal-code problem. It
must also reapply the Metro Manila city anchors, and the Taguig and Pateros rows,
which were added to `geo-by-city.json` by hand; a regeneration that does not know
the rule drops them and the capital loses its pins again. The join cannot produce
either of those last two at all, from either GeoNames download: the gazetteer
places the cities but carries no postal code, and the postal file has no row for
them to begin with.
