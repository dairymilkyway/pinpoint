# Bundled geographic data

Inert JSON snapshots, committed so the app needs no API key and makes no external
call at request time. Read by `App\Geo\PhLocations`; nothing else should parse
these files directly.

| File | Contents |
|---|---|
| `psgc-regions.json` | 18 regions |
| `psgc-provinces.json` | 82 provinces, each with its parent region code |
| `psgc-cities.json` | 1642 cities and municipalities, each with region, province and class |
| `geo-by-city.json` | City code to postal code, latitude and longitude, for 1475 of the 1642 |

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
else is dropped rather than guessed. That leaves 1475 of 1642 cities with
coordinates.

Two genuine divergences between the datasets were recorded as explicit aliases
rather than papered over by loosening the matcher:

- GeoNames calls the capital region **Metro Manila**; PSGC calls it
  **National Capital Region (NCR)**.
- PSGC 2025 carries the **Negros Island Region**, re-established in 2024 out of
  Western and Central Visayas. GeoNames predates that and still files those
  cities under the two older regions.

### Known gaps

- **Manila, Makati and Quezon City have no coordinates.** GeoNames has no city
  row for them at all - Metro Manila is keyed by postal district
  (`manila cpo ermita`, `diliman`) rather than by city. They remain selectable in
  the address form; they simply produce no map pin.
- Only **Malabon and Navotas** of the 17 Metro Manila cities have coordinates.

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
rule intact - relaxing it silently reintroduces the wrong-postal-code problem.
