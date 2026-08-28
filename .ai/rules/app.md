---
paths:
  - app/Backup.php
---

# App

## Backup-Dateinamen tragen UTC im Namen — nicht umrechnen
Alles wird in UTC gespeichert: Laravel (`app.timezone`), die Datenbank, der Server. Der Zeitstempel im Backup-Dateinamen ist deshalb ebenfalls UTC und trägt seit 2026-08-28 das Suffix `_utc` (`Backup::TIMEZONE_SUFFIX`), weil er sonst auf einem deutschen Server als Ortszeit gelesen wird und zwei Stunden falsch wirkt.

**Beschriften, nicht umrechnen** — bewusst so entschieden, nachdem die Alternative (eine `app.display_timezone` zum Umrechnen an den Rändern) in lsverein8 gebaut und wieder verworfen wurde. Sie war schwerer als das Problem und schuf eine Fehlerklasse, die es mit einer Uhr gar nicht gibt: `isDirty()` vergleicht `Backup::latestDate()` gegen `max(updated_at)` aus der Datenbank. Sobald das ein Anzeige-String in einer anderen Zone ist, wirkt das Backup jünger als jede Änderung, `isDirty()` meldet dauerhaft `false` und **der nächtliche Dump läuft nie wieder**, ohne Fehlermeldung. Keine zweite Zone einführen.

Nebenbei: UTC-Namen sortieren chronologisch und wiederholen bei der Zeitumstellung keine Stunde — Ortszeit im Dateinamen könnte ein Backup überschreiben.

**Das Suffix ist Pflicht, nicht optional.** Dateien im alten Namensschema fallen damit aus `all()` heraus — sie erscheinen nicht mehr in der Liste und werden von `deleteOld()` auch nicht mehr gelöscht, bleiben also liegen, bis sie von Hand entfernt werden. Am 2026-08-28 bewusst so entschieden; die Altbestände räumt Gerald selbst weg. `dateFromFilename()` schneidet das Suffix mit `Str::chopEnd()` ab.

`routes/console.php` bekommt bewusst **kein** `->timezone()`: `dailyAt('23:15')` ist damit 23:15 UTC = 01:15 deutscher Zeit. Steht als Kommentar dort.

Dieselbe Änderung liegt in lsverein8, lsinvestiq2, lsgallery4, lscraft5 und lswatter4 — `app/Backup.php` ist in allen fünf Apps dieselbe Datei. Wer sie hier ändert, sollte die anderen mitziehen.
