"""Generates spec/conformance.json from the reference implementation (the Python package).

Every implementation of cambodia-geo (Python, JS, PHP, ...) must return exactly these results.
Run it again whenever the CSV data is updated (the expected values come from the real data):

    pip install cambodia-geo          # the reference implementation
    python spec/generate_conformance.py
"""
import json
import pathlib

import cambodia_geo as kh

OUT = pathlib.Path(__file__).with_name("conformance.json")
FUNCS = {
    "provinces": kh.provinces,
    "districts": kh.districts,
    "communes": kh.communes,
    "villages": kh.villages,
    "get_province": kh.get_province,
    "get_district": kh.get_district,
    "get_commune": kh.get_commune,
    "get_village": kh.get_village,
    "search": kh.search,
}
LEVEL = {kh.Province: "province", kh.District: "district", kh.Commune: "commune", kh.Village: "village"}


def dictify(value, with_level=False):
    if value is None:
        return None
    if isinstance(value, list):
        return [dictify(v, with_level) for v in value]
    d = value.to_dict()
    return {"level": LEVEL[type(value)], **d} if with_level else d


# --- discover real examples of the data quirks (so the file stays valid after a data update) ---
by_name = {}
shared_village = None
for p in kh.provinces():
    for d in kh.districts(p):
        for c in kh.communes(p, d):
            by_name.setdefault(c.name_en.replace(" ", "").lower(), []).append(c)
            seen = {}
            for v in kh.villages(p, d, c):
                if v.code in seen and shared_village is None:
                    shared_village = v
                seen.setdefault(v.code, v)
ambiguous_commune = next((cs for cs in by_name.values() if len(cs) > 1 and cs[0].name_en), None)

CASES = []


def case(fn, *args, **kwargs):
    CASES.append((fn, list(args), kwargs))


# provinces / lists
case("provinces")
for q in ["17", "សៀមរាប", "Siemreap", 17, "  SIEM reap ", "017", "១៧", "01", "12"]:
    case("get_province", q)
for q in ["does-not-exist", "", "   ", None, "-", 9999]:
    case("get_province", q)
for q in ["Siemreap", "17", 17, "សៀមរាប", "unknown"]:
    case("districts", q)
case("communes", "17", "Angkor Thum")
case("communes", "សៀមរាប", "1702")
case("communes", "17", "unknown")
case("communes", "unknown", "1702")
case("villages", "17", "1702", "ជប់តាត្រាវ")
case("villages", "Siemreap", "Angkor Thum", "170201")
case("villages", "17", "1702", "unknown")
case("villages", "12", "1209", "120913")  # commune missing from the MEF commune list -> []

# single lookups
case("get_district", "1702")
case("get_district", "Angkor Thum", province="Siemreap")
case("get_district", "Angkor Thum")
case("get_district", "1702", province="does-not-exist")
case("get_district", "1702", province="12")
case("get_commune", "170201")
case("get_commune", "ជប់តាត្រាវ", district="Angkor Thum", province="17")
case("get_commune", "ជប់តាត្រាវ", province="17")
case("get_commune", "ជប់តាត្រាវ", district="1702")
case("get_commune", "170201", district="1703")
if ambiguous_commune:
    first = ambiguous_commune[0]
    case("get_commune", first.name_en)  # same name in several communes -> null without a parent
    case("get_commune", first.name_en, district=first.district_code, province=first.province_code)
    case("get_commune", first.name_kh, province=first.province_code)
case("get_village", "17020101")
case("get_village", "ត្រពាំងទូក", commune="170201", district="1702", province="17")
case("get_village", "Trapeang Tuk", commune="170201")
if shared_village:
    v = shared_village
    case("get_village", v.code)  # shared code -> null without a parent
    case("get_village", v.code, commune=v.commune_code, district=v.district_code, province=v.province_code)
case("get_village", "0506140-", commune="050614")

# search
case("search", "Siemreap")
case("search", "Siemreap", limit=1)
case("search", "Siemreap", limit=0)
case("search", "")
case("search", "angkor", limit=5)
case("search", "angkor", level="district", limit=None)
case("search", "angkor", level="commune", limit=3)
case("search", "សៀមរាប", level="province")
case("search", "17", level="province")
case("search", "a", level="province", limit=None)
case("search", "x", level="country")
case("search", "x", limit=-1)

LARGE = {("search", "a")}  # compared by length only

out = []
for fn, args, kwargs in CASES:
    entry = {"fn": fn, "args": args, "kwargs": kwargs}
    try:
        result = FUNCS[fn](*args, **kwargs)
    except ValueError:
        entry["raises"] = "invalid_argument"
    else:
        entry["expect"] = dictify(result, with_level=(fn == "search"))
        if fn == "search" and args[0] == "a":
            entry["expect_len"] = len(entry.pop("expect"))
    out.append(entry)

OUT.write_text(
    json.dumps({"data_year": kh.DATA_YEAR, "reference": "cambodia-geo (Python) " + kh.__version__, "cases": out},
               ensure_ascii=False, indent=1) + "\n",
    encoding="utf-8",
)
print(f"{len(out)} cases written to {OUT.name} (data year {kh.DATA_YEAR})")
