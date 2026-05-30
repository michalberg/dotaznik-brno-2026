# Jaké Brno chcete? — dotazník Zelených Brno 2026

Online dotazník pro sběr podnětů od obyvatel Brna. Z odpovědí vznikne volební program Zelených pro komunální volby v říjnu 2026.

Produkční nasazení: **https://jakebrno.cz**

## Soubory

| Soubor | Účel |
|---|---|
| `index.html` | Single-page dotazník (HTML + CSS + vanilla JS) |
| `save.php` | Endpoint pro průběžné ukládání odpovědí (POST `/save.php`) |
| `admin.php` | Administrace — výpis odpovědí, detail, agregace, sources |
| `stats.php` | Veřejné JSON statistiky (počty) |
| `og_image.png` | Open Graph náhled pro sociální sítě |
| `zeleni-brno-dotaznik-v5.md` | Aktuální podoba dotazníku v Markdownu (refer. dokument) |
| `prompt-social.md` | Texty pro sdílení |

## Datový model

SQLite databáze `dotaznik.db` (mimo webový kořen, `../dotaznik.db`).

```
responses (
  id          INTEGER PRIMARY KEY,
  uuid        TEXT UNIQUE,        -- generován v prohlížeči, drží se v localStorage
  data        TEXT (JSON),        -- veškeré odpovědi + UTM tagy
  email       TEXT,               -- volitelný kontakt na zaslání výsledků
  status      TEXT,               -- 'partial' / 'complete'
  last_page   TEXT,               -- ID poslední navštívené stránky (page-a, page-c…)
  created_at  TEXT,
  updated_at  TEXT
)
```

`save.php` provádí UPSERT podle `uuid` — odpovědi se ukládají průběžně po každé stránce, takže neztratíme rozpracované dotazníky.

## Frontend

- Vanilla JS, žádný build step. Pouze `index.html`.
- Stránkový průvodce s navigací **← Zpět / Pokračovat →**.
- UUID respondenta v `localStorage` (klíč `dotaznikUuid`).
- UTM parametry se sbírají z URL (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`) a ukládají do `data` JSONu.
- Sdílecí tlačítka na konci přidávají vlastní `utm_source` (facebook / messenger / whatsapp / copy).
- Drobnosti, na které jsme narazili: scroll-reveal navbaru přes IntersectionObserver, mobilní fixy (zoom na inputech, double-click na Pokračovat).

## Admin (`admin.php`)

Heslem chráněná stránka pro tým Zelených.

**Záložky v topbaru:**

- **Respondenti** — seznam s filtrem stavu (Všechny / Kompletní / Rozpracované) a fulltextovým hledáním v odpovědích a e-mailu. Detail otevřený přes řádek zobrazí odpovědi seskupené po blocích, s plným zněním otázek a navigací **← / →** mezi respondenty (klávesnice taky).
- **Po otázkách** — pohled „shora": vlevo seznam všech otázek s počty odpovědí, vpravo plné znění otázky + souhrn voleb (počty + %, seřazeno desc) a všechny jednotlivé odpovědi pod sebou.
- **Sources** — UTM rozpad ve třech tabulkách (utm_source / utm_medium / utm_campaign): počet odpovědí, podíl, kompletní/rozpracované, počet s e-mailem.

CSV export přes `?csv=1`. Slovník otázek (`$QUESTIONS` v `admin.php`) je hardcoded — jednorázový extrakt z `index.html` (otázky se už nemění).

## Lokální vývoj

```bash
php -S 127.0.0.1:8000 -t .
```

Pak otevřít http://127.0.0.1:8000/. Pro test admin/save je třeba mít zapisovatelný `../dotaznik.db` v rodičovské složce (vznikne automaticky při prvním uložení).

## Nasazení

- Statický `index.html` + PHP endpointy (`save.php`, `admin.php`, `stats.php`) běží přímo na sdíleném hostingu.
- Databázový soubor `dotaznik.db` patří **mimo webový kořen** (`../dotaznik.db`) — viz cesty v `save.php` a `admin.php`.
- `save.php` má CORS hlavičku omezenou na `https://jakebrno.cz`.

### Admin heslo

Heslo se načítá jako `password_hash()` z konfiguračního souboru **mimo webový kořen** (`../admin-pwd-hash.php`, vedle `dotaznik.db`). Tento soubor není v gitu.

**Nastavení (jednorázově při deploy):**

1. Vygenerovat hash lokálně:
   ```powershell
   php -r "echo password_hash('zde-silne-heslo', PASSWORD_DEFAULT), PHP_EOL;"
   ```
2. Na hostingu vytvořit `../admin-pwd-hash.php` s obsahem:
   ```php
   <?php return '$2y$10$...zde-vlozeny-hash...';
   ```
3. Hotovo — `admin.php` ho načte automaticky.

**Zpětná kompatibilita:** pokud konfigurační soubor neexistuje, použije se fallback heslo `natalie` zadrátované v `admin.php`. To je pouze pro vývoj / staré instalace — na produkci vždy nasadit konfigurační soubor, jinak je heslo veřejně známé z repa.

**Pozn. k HTTPS:** session cookie má flag `Secure` jen pokud běží přes HTTPS, takže lokální `php -S` na HTTP funguje bez úprav.
