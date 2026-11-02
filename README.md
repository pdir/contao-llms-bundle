# Contao LLMs Bundle

Stellt für Contao-Websites automatisch eine [`llms.txt`](https://llmstxt.org/) und eine `llms-full.txt` bereit. Sprachmodelle wie ChatGPT, Claude oder Gemini erhalten damit eine strukturierte Übersicht der Website bzw. deren vollständige Inhalte als Markdown.

## Funktionen

- **`/llms.txt`** – Titel, Beschreibung und eine Liste aller öffentlichen Seiten und Artikel-Teaser im llms.txt-Format
- **`/llms-full.txt`** – der Inhalt jeder Seite als bereinigtes Markdown, erzeugt aus der gerenderten Seite, inklusive Tabellen
- Einstellungen pro Root-Seite und pro Seite direkt im Seitenbaum, ohne Programmierung
- Gliederung der `llms.txt` in frei benannte Abschnitte, inklusive des Abschnitts `Optional` aus der Spezifikation
- Abschnitt „Rechtliches“ mit frei wählbaren Seiten wie Impressum, Datenschutz und Barrierefreiheit
- Optionaler Link auf die `sitemap.xml`
- Nachrichten und Termine aus dem Contao News- und Calendar-Bundle als eigene Einträge (beide Bundles optional, abschaltbar); nur kommende Termine
- Ausgabe auf Deutsch oder Englisch, passend zur Sprache der Root-Seite
- Mehrsprachige Websites: alle Root-Seiten einer Domain erscheinen in einer gemeinsamen Datei
- Generierte Inhalte werden gespeichert und nur neu erzeugt, wenn sich die Seite geändert hat; manuelles Überschreiben möglich
- Bilder aus dem Seiteninhalt werden entfernt; gezielt ausgewählte Bilder pro Seite
- Relative Links werden zu absoluten URLs, Insert-Tags in Teasern werden ersetzt
- HTTP-Caching mit automatischer Invalidierung bei Änderungen im Backend
- `Last-Modified`-Header mit dem Zeitpunkt der letzten inhaltlichen Änderung
- Inhalte per Konsole vorab erzeugen, z. B. in einem Cronjob
- Gespeicherte Daten per Konsole oder Systemwartung leeren
- Hook `getLlmsPages`, über den Erweiterungen Einträge ergänzen oder entfernen können

## Voraussetzungen

| Contao | PHP |
|---|---|
| 5.3 | ab 8.1 |
| 6.0 | ab 8.4 |

## Installation

Über den Contao Manager nach `pdir/contao-llms-bundle` suchen oder per Composer installieren:

```bash
composer require pdir/contao-llms-bundle
```

Anschließend die Datenbank aktualisieren:

```bash
vendor/bin/contao-console contao:migrate
```

### Update

Nach einem Update ebenfalls die Datenbank aktualisieren, da neue Versionen weitere Felder mitbringen können. Bereits gespeicherte Inhalte werden erst nach und nach mit der neuen Version erzeugt; um alle sofort neu zu erzeugen, die [LLMs-Daten leeren](#llms-daten-leeren).

## Einrichtung

### Root-Seite (Website-Startpunkt)

In der Legende **LLMs-Einstellungen**:

| Feld | Bedeutung |
|---|---|
| llms.txt aktivieren | Schaltet `/llms.txt` für diese Domain ein |
| llms-full.txt aktivieren | Schaltet `/llms-full.txt` ein; die `llms.txt` verlinkt dann darauf |
| LLMs-Titel | Überschrift der Datei (Standard: Seitentitel bzw. Titel der Root-Seite) |
| LLMs-Beschreibung | Beschreibung unter der Überschrift (Standard: Beschreibung der Root-Seite, sonst die der ersten Seite). Der erste Absatz wird als Zitat (`>`) ausgegeben, alle weiteren Absätze nach einer Leerzeile als normales Markdown, z. B. eine Liste mit wichtigen Fakten; Einrückungen bleiben erhalten |
| Individuelle llms.txt- / llms-full.txt-Anweisungen | Freitext, der unter der Beschreibung der jeweiligen Datei ausgegeben wird |
| LLMs-E-Mail-Adresse, LLMs-Telefon | Kontaktangaben im Abschnitt „Kontakt“; die E-Mail-Adresse erscheint auch als Support-Adresse in der `llms-full.txt`. Leere Felder werden nicht ausgegeben |
| Nutzungshinweise ausgeben | Gibt Hinweise zur Nutzung und Quellenangabe aus (standardmäßig aktiv) |
| Sitemap verlinken | Verlinkt die `sitemap.xml` im Abschnitt `Optional` der `llms.txt` (standardmäßig aus) |
| llms.txt auf allen Seiten verlinken | Verweist auf jeder Seite per `rel="describedby"` auf die `llms.txt`, damit Crawler sie finden (standardmäßig aktiv, siehe unten) |
| Nachrichten ausschließen, Termine ausschließen | Nimmt Nachrichten bzw. Termine nicht in die Dateien auf; nur sichtbar, wenn das jeweilige Bundle installiert ist |
| Rechtliche Seiten | Seiten wie Impressum, Datenschutz und Barrierefreiheit für den Abschnitt „Rechtliches“ (siehe unten) |

Die Dateien werden über die **Sprachen-Fallback-Root-Seite** der Domain ausgeliefert. Dort muss die jeweilige Option aktiviert sein. Weitere Root-Seiten derselben Domain werden aufgenommen, wenn die Option auch bei ihnen aktiviert ist.

Haben die Root-Seiten ein **Sprachkürzel** (URL-Präfix, z. B. `de`), gibt es zusätzlich sprachspezifische Dateien wie `/de/llms.txt` und `/de/llms-full.txt`. Sie enthalten nur die Seiten dieser Root-Seite und verwenden deren LLMs-Einstellungen; dafür muss die Option bei dieser Root-Seite aktiviert sein. Die `/de/llms.txt` verlinkt entsprechend auf die `/de/llms-full.txt`.

#### Verweis per rel="describedby"

Ist die `llms.txt` aktiviert, verweist jede Seite der Root-Seite auf sie, und zwar auf zwei Wegen:

```html
<link rel="describedby" href="https://example.org/de/llms.txt" type="text/plain">
```

```
Link: <https://example.org/de/llms.txt>; rel="describedby"; type="text/plain"
```

Der `<link>` steht im HTML-Kopf, der Header ist auch über einen `HEAD`-Request ohne Auswerten des HTML sichtbar. Bei Root-Seiten mit Sprachkürzel zeigt der Verweis auf die sprachspezifische Datei, sonst auf `/llms.txt`. Die `llms-full.txt` wird nicht verlinkt, sie ist in der `llms.txt` aufgeführt. Ausschalten lässt sich der Verweis über die Option „llms.txt auf allen Seiten verlinken“.

### Reguläre Seiten

| Feld | Bedeutung |
|---|---|
| LLMs-Titel | Linktext in der `llms.txt` (Standard: Seitentitel bzw. Titel) |
| LLMs-Beschreibung | Beschreibung in der `llms.txt` (Standard: Beschreibung der Seite); Zeilenumbrüche bleiben erhalten |
| LLMs-Abschnitt | Abschnitt der `llms.txt`, in dem die Seite erscheint; wird an Unterseiten vererbt, bereits verwendete Abschnitte werden vorgeschlagen |
| In den LLMs-Dateien ausblenden | Nimmt die Seite aus beiden Dateien heraus |
| LLMs-Bilder | Bilder, die in der `llms-full.txt` unter der Seitenbeschreibung ausgegeben werden (siehe unten) |
| LLMs-Seitenbeschreibung manuell überschreiben | Ersetzt den automatisch erzeugten Inhalt in der `llms-full.txt` durch einen eigenen Text |
| LLMs – Letzte Änderung | Wird automatisch gesetzt, wenn Inhalte der Seite geändert werden |

**Bilder:** Bilder aus dem Seiteninhalt werden in der `llms-full.txt` immer entfernt, da sie dort meist nur Dekoration sind. Soll ein Bild erscheinen, etwa ein Screenshot eines Produkts, wird es im Feld **LLMs-Bilder** ausgewählt. Die Bilder stehen in der gewählten Reihenfolge unter der Seitenbeschreibung. Als Bildbeschreibung dient der Alternativtext aus der Dateiverwaltung in der Sprache der Seite, sonst die Bildunterschrift oder der Titel:

```markdown
# Creuva Contao Theme

URL: https://example.org/creuva-contao-theme
Zuletzt geändert: 2026-10-29T10:00:00+01:00

> Flexibles Designsystem für Contao 5.7 und 6.

![Creuva auf Laptop und Smartphone](https://example.org/files/creuva.jpg)

## Funktionen
…
```

Aufgenommen werden wie in der Sitemap nur veröffentlichte, routbare HTML-Seiten. Seiten, deren Robots-Tag mit `noindex` beginnt (z. B. Danke-Seiten), werden ausgelassen. Geschützte Seiten erscheinen nur, wenn der aufrufende Benutzer berechtigt ist, und Root-Seiten im Wartungsmodus werden übersprungen. Artikel mit aktiviertem Teaser erscheinen als eigene Einträge.

### Nachrichten und Termine

Sind das [Contao News-Bundle](https://packagist.org/packages/contao/news-bundle) oder das [Contao Calendar-Bundle](https://packagist.org/packages/contao/calendar-bundle) installiert, erscheinen Nachrichten bzw. Termine als eigene Einträge. Beide Bundles sind optional; mit dem Core-Bundle allein funktioniert alles andere unverändert. Über die Optionen **Nachrichten ausschließen** und **Termine ausschließen** an der Root-Seite lassen sie sich pro Sprache abschalten.

Es gelten dieselben Regeln wie in der Contao-Sitemap:

- Das Nachrichtenarchiv bzw. der Kalender muss auf eine Leseseite innerhalb der Website verweisen. Die Leseseite muss veröffentlicht sein und darf nicht auf `noindex,nofollow` stehen.
- Geschützte Archive, Kalender und Leseseiten erscheinen nur für berechtigte Mitglieder.
- Einträge, die auf eine Seite, einen Artikel oder eine externe URL weiterleiten, und Einträge mit `noindex` werden ausgelassen.

Abweichend von der Sitemap werden **nur kommende und laufende Termine** aufgenommen. Bei wiederkehrenden Terminen zählt der nächste Termin der Serie; sein Datum wird angezeigt. Ist die Serie beendet, fällt der Termin weg. Die Termine sind kalenderübergreifend chronologisch aufsteigend sortiert, der nächste zuerst. Da die `llms.txt` bis zu einem Tag im HTTP-Cache liegt, verschwinden vergangene Termine spätestens nach einem Tag.

Nachrichten und Termine erscheinen im **LLMs-Abschnitt ihrer Leseseite**. Die Leseseite selbst kann über „In den LLMs-Dateien ausblenden“ ausgeblendet werden, da sie ohne Nachricht bzw. Termin keinen eigenen Inhalt hat. Als Beschreibung dient die Meta-Beschreibung, sonst der Teaser. Bei Terminen stehen Datum und Ort davor:

```markdown
- [Contao-Workshop](https://example.org/termin/contao-workshop): 2026-11-12 – 2026-11-13, Dresden. Zwei Tage Theme-Entwicklung.
```

In der `llms-full.txt` stehen bei Nachrichten unter der URL das Datum und der Autor, das Datum im Format der Sprache der Root-Seite. Ist kein Autor eingetragen, entfällt er:

```markdown
# Contao Themes installieren

URL: https://example.org/ratgeber/contao-themes-installieren
Datum: 25.10.2026 · Autor: Max Mustermann
Zuletzt geändert: 2026-10-29T10:00:00+01:00
```

Der Inhalt wird wie bei Seiten aus der gerenderten Nachricht bzw. dem gerenderten Termin erzeugt. Er wird in der Tabelle `tl_llms_content` gespeichert und verworfen, sobald der Eintrag oder eines seiner Inhaltselemente geändert wird.

### Abschnitte

Über das Feld **LLMs-Abschnitt** wird die `llms.txt` gegliedert. Der Wert gilt auch für alle Unterseiten, solange diese keinen eigenen Abschnitt angeben. Seiten ohne Abschnitt erscheinen unter „Wichtige Seiten“ bzw. „Key Pages“. Beim Eintippen schlägt das Feld die bereits verwendeten Abschnitte und „Optional“ vor. Das verhindert doppelte Abschnitte durch abweichende Schreibweisen wie „Theme“ und „Themes“.

Der Abschnitt **`Optional`** hat laut [Spezifikation](https://llmstxt.org/) eine besondere Bedeutung: LLMs dürfen ihn überspringen, wenn sie nur einen kurzen Überblick brauchen. Er steht immer am Ende und eignet sich für Nebenseiten. Dort stehen auch die Links auf die `llms-full.txt` und, wenn aktiviert, auf die `sitemap.xml`.

Bei mehreren Sprachen erhält jeder Abschnitt die Sprache als Zusatz, z. B. „Themes (de)“.

### Rechtliches

Im Feld **Rechtliche Seiten** der Root-Seite lassen sich Seiten wie Impressum, Datenschutzerklärung und Erklärung zur Barrierefreiheit auswählen und sortieren. Sie werden in beiden Dateien im Abschnitt **„Rechtliches“** bzw. „Legal“ verlinkt, nach den Inhalten und vor Kontakt und Nutzungshinweisen. Der Inhalt der Seiten wird nicht in die `llms-full.txt` übernommen.

- Ohne ausgewählte Seiten entfällt der Abschnitt.
- Die Seiten erscheinen auch dann, wenn sie auf `noindex` stehen oder ausgeblendet sind, aber nicht mehr in ihrem normalen Abschnitt.
- Unveröffentlichte und geschützte Seiten (ohne Berechtigung) werden ausgelassen.
- Bei mehreren Sprachen wählt jede Root-Seite ihre eigenen Seiten; die Linktexte erhalten die Sprache als Zusatz.

### Sprache

Überschriften und feste Texte (z. B. „Kontakt“, „Nutzungshinweise“) werden in der Sprache der Fallback-Root-Seite ausgegeben. Mitgeliefert werden Deutsch und Englisch, für andere Sprachen wird Englisch verwendet. Eigene Übersetzungen lassen sich wie in Contao üblich über `contao/languages/<sprache>/llms.php` ergänzen.

## Beispiel

### llms.txt

```markdown
# Testfirma

> Testfirma entwickelt Websites mit Contao.

Wichtige Fakten:

- Sitz: Dresden
- Leistungen:
    - Webdesign
    - Hosting

Bitte freundlich sein.

## Wichtige Seiten

- [Startseite](https://example.org/): Die Startseite
- [Kontakt](https://example.org/kontakt)

## Über uns

- [Über uns – Testfirma](https://example.org/ueber-uns)
- [Geschichte](https://example.org/ueber-uns/articles/geschichte): Seit 1999 am Markt.
- [Team](https://example.org/team): Unser Team

## Rechtliches

- [Impressum](https://example.org/impressum)
- [Datenschutz](https://example.org/datenschutz)
- [Barrierefreiheit](https://example.org/barrierefreiheit)

## Kontakt

- E-Mail: info@example.org
- Telefon: +49 1

…

## Optional

- [llms-full.txt](https://example.org/llms-full.txt): Vollständige Inhalte aller Seiten als Markdown
- [Sitemap](https://example.org/sitemap.xml): Alle indexierbaren URLs als XML
```

Die erste Zeile unter der Überschrift ist der erste Absatz der LLMs-Beschreibung, „Wichtige Fakten“ samt Liste der Rest der Beschreibung, „Bitte freundlich sein.“ die individuelle Anweisung.

### llms-full.txt

```markdown
# Testfirma

> Testfirma entwickelt Websites mit Contao.

…

Stand: Oktober 2026 · Sprache: Deutsch · Support: info@example.org

---

# Startseite

URL: https://example.org/
Zuletzt geändert: 2026-10-29T10:00:00+01:00

> Die Startseite

## Willkommen

Wir entwickeln Websites mit Contao.

**Was kostet eine Website?**

| Paket | Preis |
|---|---|
| Basis | 990 € |

---

# Kontakt
…
```

## Wie die llms-full.txt entsteht

Unter Beschreibung und Anweisungen steht eine Zeile mit Stand, Sprache und Support-Adresse, gefolgt von einer Trennlinie:

```markdown
Stand: Oktober 2026 · Sprache: Deutsch · Support: support@example.org
```

Der Stand ist der Monat der letzten Änderung an einer der aufgenommenen Seiten, Nachrichten oder Termine. Bei mehreren Sprachen werden alle genannt. Die Support-Adresse ist die LLMs-E-Mail-Adresse der Root-Seite und entfällt, wenn keine eingetragen ist.

Danach folgt jede Seite, Nachricht und jeder Termin mit Überschrift (`#`), URL, Datum der letzten Änderung, Beschreibung und Inhalt, jeweils durch eine Trennlinie getrennt. Am Ende stehen wie in der `llms.txt` Rechtliches, Kontakt, Nutzungshinweise und Quellenangabe, hier ebenfalls als `#`-Überschriften.

Die Datei kennt nur zwei Überschrift-Ebenen: `#` für die Seiten und `##` für die Abschnitte ihres Inhalts. Bei der Ausgabe werden die Überschriften des Inhalts deshalb vereinheitlicht:

- `h1` und `h2` werden zu `##`, tiefere Überschriften (z. B. die Fragen eines FAQ) zu fettem Text.
- Wiederholt die erste Überschrift den Seitentitel, entfällt sie.
- Ein einzelner Punkt am Ende einer Überschrift entfällt.

Das gilt auch für gespeicherte und manuell überschriebene Inhalte.

Für jede Seite ruft das Bundle die gerenderte Seite per HTTP ab und wandelt den Hauptbereich (`#main`) in Markdown um:

- Navigation, Header, Footer, Formulare und Skripte werden entfernt, ebenso Bilder (inklusive Bildunterschrift) und Links ohne Text. Bilder lassen sich stattdessen pro Seite auswählen (siehe [Reguläre Seiten](#reguläre-seiten)).
- Teile einer Überschrift, die nur per CSS getrennt sind (z. B. Dachzeile und Überschrift in zwei `<span>`), werden mit einem Doppelpunkt verbunden: aus „So funktioniert es“ und „In vier Schritten“ wird „So funktioniert es: In vier Schritten“. Zeilenumbrüche in Überschriften werden zu Leerzeichen.
- Links erhalten absolute URLs.
- Tabellen werden als Markdown-Tabellen ausgegeben, inklusive Beschriftung und Fußzeile. Tabellen ohne Kopfzeile erhalten eine leere, Tabellen ohne Inhalt werden weggelassen. Zeilenumbrüche in Zellen werden zu Leerzeichen.
- Überflüssige Leerzeichen und Leerzeilen werden entfernt; Listen-Einrückungen und Code-Blöcke bleiben erhalten.

Das Ergebnis wird in der Seite gespeichert und erst neu erzeugt, wenn sich die Seite oder einer ihrer Inhalte geändert hat, spätestens aber nach 24 Stunden. Artikel-Teaser werden direkt umgewandelt, ohne die Seite abzurufen.

Hinweise:

- Der Webserver muss sich selbst unter der eigenen Domain erreichen können.
- Weiterleitungen werden nicht verfolgt. Leitet eine Seite weiter oder antwortet mit einem Client-Fehler (z. B. 404), gilt sie als leer.
- Seiten ohne eigenen Inhalt (z. B. Weiterleitungen oder leere Leseseiten) fehlen in der `llms-full.txt` und, sobald sie einmal erzeugt wurden, auch in der `llms.txt`.
- Bei Serverfehlern (z. B. 503 im Wartungsmodus) oder Timeouts (10 Sekunden) wird der zuletzt gespeicherte Text verwendet und eine Warnung geloggt.
- Der erste Aufruf nach vielen Änderungen kann entsprechend lange dauern. Das lässt sich durch [Vorab-Erzeugen](#llms-daten-vorab-erzeugen) vermeiden.
- Nach einem Update des Bundles werden gespeicherte Inhalte erst nach und nach neu erzeugt. Inhalte aus Vorabversionen haben um zwei Ebenen herabgestufte Überschriften, die bis zur Neuerzeugung als fetter Text erscheinen. Überzählige Leerzeilen werden zwar sofort bei der Ausgabe entfernt, andere Verbesserungen der Umwandlung greifen aber erst mit der Neuerzeugung.
- Manuell überschriebene Seitenbeschreibungen werden unverändert ausgegeben, nur überzählige Leerzeilen werden entfernt.
- Um alle gespeicherten Inhalte sofort neu zu erzeugen, siehe [LLMs-Daten leeren](#llms-daten-leeren).

## LLMs-Daten vorab erzeugen

Ohne Vorab-Erzeugung werden abgelaufene Inhalte beim ersten Aufruf der `llms-full.txt` neu erzeugt, bei großen Websites kann dieser Aufruf entsprechend dauern. Der Konsolenbefehl übernimmt das stattdessen für alle Domains und Sprachkürzel (z. B. auch `/de/llms.txt`):

```bash
vendor/bin/contao-console pdir:llms:generate
```

Er erzeugt geänderte Inhalte und solche, die älter als 20 Stunden sind. Ein täglicher Cronjob genügt also, damit Besucher nicht mehr auf das Ablaufen nach 24 Stunden warten müssen; läuft er häufiger (z. B. stündlich), werden auch geänderte Inhalte zeitnah erzeugt:

```
0 * * * * /pfad/zu/contao/vendor/bin/contao-console pdir:llms:generate --quiet
```

Hinweise:

- Der Befehl ruft die Seiten wie gewohnt per HTTP ab; der Server, auf dem er läuft, muss die Domains also erreichen können.
- Für Root-Seiten ohne eingetragene Domain muss die Umgebungsvariable `DEFAULT_URI` gesetzt sein.
- Konnte eine Datei oder ein Eintrag nicht erzeugt werden, endet der Befehl mit einem Fehlercode. Betroffene Einträge behalten ihren gespeicherten Inhalt.

## LLMs-Daten leeren

Alle gespeicherten Inhalte (Seiten, Nachrichten und Termine) lassen sich auf einmal verwerfen, z. B. nach einem Update des Bundles. Sie werden beim nächsten Aufruf der `llms-full.txt` neu erzeugt. Manuell überschriebene Seitenbeschreibungen bleiben erhalten, und der HTTP-Cache beider Dateien wird geleert.

- **Backend:** System → Systemwartung → Daten bereinigen → „LLMs-Daten leeren“
- **Konsole:**

  ```bash
  vendor/bin/contao-console pdir:llms:purge
  ```

Der erste Aufruf danach dauert länger, weil alle Seiten neu abgerufen werden. Mit [`pdir:llms:generate`](#llms-daten-vorab-erzeugen) lässt sich das direkt im Anschluss erledigen.

## Caching

Beide Dateien werden wie die Contao-Sitemap im HTTP-Cache gespeichert: die `llms.txt` für einen Tag, die `llms-full.txt` für eine Stunde. Der Cache wird automatisch geleert, sobald im Backend eine Seite, ein Artikel, ein Inhaltselement, eine Nachricht, ein Termin, ein Nachrichtenarchiv oder ein Kalender gespeichert, verschoben, kopiert oder gelöscht wird. Auch das [Leeren der LLMs-Daten](#llms-daten-leeren) leert den Cache. Die Antworten tragen dafür das Cache-Tag `pdir.llms`; Erweiterungen können es bei Bedarf selbst invalidieren.

Für eingeloggte Mitglieder werden die Dateien nicht gecacht, da geschützte Seiten nur ihnen angezeigt werden.

Beide Dateien senden einen `Last-Modified`-Header. Er nennt den Zeitpunkt, an dem sich der Inhalt der Datei zuletzt geändert hat. Dafür wird für jede Datei ein Hash des Inhalts gespeichert, und der Zeitpunkt ändert sich nur, wenn der Inhalt abweicht. So berücksichtigt er auch gelöschte Seiten, abgelaufene Termine und Änderungen an der gerenderten Seite. Anfragen mit `If-Modified-Since` werden bei unverändertem Inhalt mit `304 Not Modified` beantwortet. Das [Leeren der LLMs-Daten](#llms-daten-leeren) behält diese Zeitpunkte bei.

## Für Entwickler: Hook `getLlmsPages`

Der Hook wird pro Root-Seite aufgerufen und muss die (ggf. geänderte) Liste der Einträge zurückgeben:

```php
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

#[AsHook('getLlmsPages')]
class LlmsPagesListener
{
    public function __invoke(array $items, int $rootPageId, bool $isFrontend, string $language): array
    {
        $items[] = [
            'type' => 'custom',
            'title' => 'Aktuelle Angebote',
            'description' => 'Unsere Angebote der Woche',
            'url' => 'https://example.org/angebote',
            'tstamp' => time(),
            'lastModified' => 0,
            'section' => 'Angebote', // optional, sonst „Wichtige Seiten“
            'date' => time(), // optional, Veröffentlichungsdatum in der llms-full.txt
            'author' => 'Max Mustermann', // optional, Autor in der llms-full.txt
        ];

        return $items;
    }
}
```

Die mitgelieferten Einträge haben den Typ `page`, `article`, `news` oder `event`. Einträge eines anderen Typs (z. B. `custom`) erscheinen in der `llms-full.txt` mit Titel, URL und Beschreibung, aber ohne Inhalt.

## Entwicklung

Die Tests laufen mit PHPUnit und brauchen weder Datenbank noch Contao-Installation:

```bash
composer install
composer test
```

Für die Tests von Nachrichten und Terminen werden das News- und das Calendar-Bundle als Entwicklungsabhängigkeiten installiert; für den Betrieb bleiben sie optional.

## Lizenz

LGPL-3.0-or-later, entwickelt von [pdir GmbH](https://pdir.de).
