# Bundled geographic data

Inert JSON snapshots, committed so the app needs no API key and makes no external
call at request time. Read by `App\Geo\PhLocations`; nothing else should parse
these files directly.

| File | Contents |
|---|---|
| `psgc-regions.json` | 18 regions |
| `psgc-provinces.json` | 82 provinces, each with its parent region code |
| `psgc-cities.json` | 1642 cities and municipalities, each with region, province and class |
| `geo-by-city.json` | City code to postal code, latitude and longitude, for 1488 of the 1642 |

## Sources and attribution

**Philippine Standard Geographic Code (PSGC)**, Revision 1, snapshot of
2025-07-31, from the Philippine Statistics Authority.
<https://psa.gov.ph/classification/psgc> - CC BY 4.0.

**GeoNames** postal codes and coordinates for the Philippines, `PH.zip`.
<https://download.geonames.org/export/zip/> - CC BY 4.0.

Both are used under CC BY 4.0 and are credited in the app footer.

## The join, and why it is imperfect

PSGC and GeoNames share no key, so cities were matched on name. Name alone is
not sufficient: 123 normalised names are ambiguous - `Quezon` alone is seven
distinct municipalities - so a match is only accepted when the name agrees
**and** the province (or, for province-less cities, the region) agrees. Anything
else is dropped rather than guessed. That leaves 1488 of 1642 cities with
coordinates.

Three genuine divergences between the datasets were recorded as explicit aliases
rather than papered over by loosening the matcher:

- GeoNames calls the capital region **Metro Manila**; PSGC calls it
  **National Capital Region (NCR)**.
- PSGC 2025 carries the **Negros Island Region**, re-established in 2024 out of
  Western and Central Visayas. GeoNames predates that and still files those
  cities under the two older regions.
- GeoNames keys Metro Manila by postal district (`manila cpo ermita`, `diliman`),
  never by city, so no city row exists to match. The thirteen Metro Manila cities
  whose own central post office row is present are keyed to it by hand, so both
  their coordinates and their postal code come from that city's own post office -
  Manila 1000, Quezon City 1100, Makati 1200 and so on.

### Known gaps

- **154 of the 1642 cities have no coordinates**, so an address in one of them
  produces no map pin. They are spread across every region - BARMM alone accounts
  for 40 - and the cause is the same in each: GeoNames has no place row whose name
  and parent both agree with PSGC, so the join drops it rather than guessing.
- **Taguig and Pateros** are the two Metro Manila cities still in that set. The
  other fifteen are pinned from their own central post office, which is the alias
  recorded above. Note that this was not always so: the join alone left all
  seventeen of them unpinned, which is how an address in the capital came to have
  no pin at all.

## On postal codes

**Postal codes here are approximate and must not be presented as authoritative.**
PSGC carries no postal code at all. Philippine postal codes are delivery-area
identifiers, not administrative ones: a city commonly has several, and one code
can span several localities, so there is no 1:1 city-to-postal mapping to be had.
GeoNames provided the plausible values; PHLPost's locator is the only authority
and it has no API.

Latitude and longitude come from the same GeoNames row as the postal code, so
they are the coordinates of the matched place rather than of a specific street
address. They are adequate for a map pin and are not survey data.

## Regenerating

The transform that produced `psgc-*` and `geo-by-city` lives outside the project
root and is not part of the build. Re-running it is only necessary when the
upstream snapshots are refreshed, and it must keep the "name plus parent agree"
rule intact - relaxing it silently reintroduces the wrong-postal-code problem. It
must also reapply the thirteen Metro Manila city anchors, which were added to
`geo-by-city.json` by hand; a regeneration that does not know the rule drops them
and the capital loses its pins again.
