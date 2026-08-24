# LSWatter 4

Webanwendung zur Verwaltung von Turnieren des Kartenspiels *Watten*. Sie läuft
als Single-Page-Application im Browser und ist damit von PC, Tablet und Handy
aus bedienbar.

Nachfolger von Watter3, neu aufgebaut auf Laravel 13, Vue 3 und Inertia.js.
Die Oberfläche ist durchgehend deutsch.

## Funktionen

- **Turniere** anlegen, bearbeiten und löschen – mit Rundenzahl, Spielen pro
  Runde, Gewinnpunkten und Startzeitpunkt
- **Anmeldung** von Einzelspielern und Teams; zwei angemeldete Einzelspieler
  lassen sich nachträglich zu einem Team zusammenfügen
- **Auslosung** des Spielplans, jederzeit verwerfbar und neu auslosbar,
  solange noch keine Ergebnisse eingetragen sind
- **Tischlisten als PDF** zum Mitschreiben der Punkte, je Runde
- **Ergebniserfassung** pro Begegnung, mit automatischer Rangliste des Turniers
- **Ewige Bestenlisten** für Teams und Einzelspieler über alle Turniere hinweg
- **Spielerverwaltung** inklusive Erkennen und Zusammenführen doppelt
  angelegter Spieler
- **Benutzerverwaltung** mit Administratorrechten, Sperren von Konten und
  Anmelden als anderer Benutzer (Impersonation)
- **Backups** der Datenbank – täglich automatisch, zusätzlich manuell, mit
  Download, Wiederherstellung und optionaler Kopie nach AWS S3
- **Öffentlicher Zugang**: Turniere und Ergebnisse sind ohne Anmeldung
  einsehbar, sobald das Turnier begonnen hat; Anlegen und Bearbeiten
  erfordert ein Konto
- **Hell/Dunkel-Design**, Profilbild, Passwortänderung und Passwort-Reset
  per E-Mail

## Voraussetzungen

- PHP 8.4 oder neuer
- Composer
- Node.js mit npm
- MariaDB 10.10+ oder MySQL 5.7+ (die Backups nutzen `mysqldump` und `mysql`)

## Installation

```bash
git clone <repository> lswatter4
cd lswatter4

composer install
cp .env.example .env
php artisan key:generate
```

Anschließend die `.env` anpassen – mindestens `APP_NAME`, `APP_URL`, die
`DB_*`-Werte einer leeren Datenbank und die `MAIL_*`-Werte, damit der
Passwort-Reset E-Mails versenden kann.

```bash
php artisan migrate
php artisan storage:link   # für die Profilbilder
npm install
npm run build
```

Für einen schnellen lokalen Start ersetzt `composer setup` alle Schritte auf
einmal (Installation, `.env`, Key, Migration, Build) – dann allerdings mit der
SQLite-Voreinstellung aus `.env.example`.

### Ersten Administrator anlegen

```bash
php artisan app:user 'Max Mustermann' 'max@mustermann.de' --password=geheim
php artisan tinker --execute 'App\Models\User::where("email", "max@mustermann.de")->update(["admin" => true]);'
```

Ohne `--password` wird ein Zufallspasswort erzeugt und ausgegeben. Alle
weiteren Benutzer legt der Administrator danach in der Oberfläche an; sie
bekommen per E-Mail einen Link zum Setzen ihres Passworts.

### Betrieb

Dokumentenstamm des Webservers ist das Verzeichnis `public`. Für die täglichen
Backups muss der Laravel-Scheduler laufen:

```
* * * * * cd /pfad/zur/anwendung && php artisan schedule:run >> /dev/null 2>&1
```

Ein Deployment bringt `composer deploy` auf den Stand (Abhängigkeiten ohne
Dev-Pakete, Frontend-Build, Config- und Route-Cache).

Telescope zeichnet außerhalb der lokalen Umgebung nur auffällige Vorgänge auf
– Ausnahmen, Antworten mit Status 500 und aufwärts, fehlgeschlagene Jobs und
geplante Aufgaben. Für die Fehlersuche lässt sich mit
`TELESCOPE_RECORD_EVERYTHING=true` vorübergehend alles aufzeichnen; die
Einträge werden dann täglich auf die letzten 48 Stunden gekürzt.

## Entwicklung

```bash
composer dev        # alle Entwicklungsprozesse (Vite, Queue, Logs) zusammen
npm run dev         # nur Vite
```

Lokal wird die Anwendung von [Laravel Herd](https://herd.laravel.com) unter
`https://lswatter4.test` ausgeliefert.

### Tests und Prüfungen

```bash
php artisan test --compact        # Testsuite (Pest, SQLite im Speicher)
composer test                     # Pint, PHPStan und Tests
composer ci:check                 # zusätzlich ESLint, Prettier und vue-tsc
```

Vor dem Commit von PHP-Änderungen `vendor/bin/pint --dirty`, nach Änderungen
in `resources/js` `npm run build`. Dieselben Prüfungen laufen bei jedem Push
über GitHub Actions.

## Backups

Die Datenbank wird täglich um 23:15 Uhr gesichert, aber nur, wenn sich seit
der letzten Sicherung etwas geändert hat. Die Dateien liegen unter
`storage/backups`; behalten werden die Sicherungen der letzten
`BACKUP_RETAIN_DAYS` (180) Tage, mindestens jedoch die neuesten
`BACKUP_RETAIN_COUNT` (10).

```bash
php artisan app:backup            # Sicherung anlegen, falls es Änderungen gab
php artisan app:backup --force    # in jedem Fall sichern
php artisan aws:test              # S3-Zugang prüfen
```

Administratoren finden unter *Backups* dieselben Funktionen in der Oberfläche:
anlegen, herunterladen, wiederherstellen und löschen. Beim Wiederherstellen
wird die komplette Datenbank überschrieben – vorher eine aktuelle Sicherung
anlegen.

Mit `BACKUP_AWS_ENABLED=true` und den `AWS_*`-Werten wird jede erfolgreiche
Sicherung zusätzlich in einen S3-Bucket kopiert.

## Weitere Artisan-Befehle

| Befehl | Zweck |
| --- | --- |
| `app:user {name} {email} --password=` | Benutzer anlegen oder ändern |
| `app:set-password {email} {password}` | Passwort eines Benutzers setzen |
| `app:backup [--force]` | Datenbanksicherung erstellen |
| `aws:test` | S3-Verbindung und Upload testen |
| `app:clear-log` | `storage/logs/laravel.log` leeren |
| `app:consolidate-duplicate-teams` | Doppelt angelegte Teams zusammenführen |

## Benutzte Frameworks und Werkzeuge

- [Laravel](https://laravel.com) mit [Fortify](https://laravel.com/docs/fortify),
  [Wayfinder](https://github.com/laravel/wayfinder) und
  [Telescope](https://laravel.com/docs/telescope)
- [Vue.js](https://vuejs.org) und [Inertia.js](https://inertiajs.com)
- [Tailwind CSS](https://tailwindcss.com), [Reka UI](https://reka-ui.com) und
  [Lucide](https://lucide.dev)
- [Vite](https://vite.dev) und [Pest](https://pestphp.com)
- [FPDF](https://www.fpdf.org) für die Tischlisten

Die vollständige Liste zeigt die Seite *Über* in der Anwendung (`/about`).

## Lizenz

MIT – siehe [opensource.org/licenses/MIT](https://opensource.org/licenses/MIT).
