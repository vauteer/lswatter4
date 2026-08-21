---
paths:
  - app/Http/Controllers/TournamentController.php
---

# Controllers

## tournaments.show only exists once the tournament is drawn
show() aborts 404 unless $tournament->drawn(). There is nothing to display before the draw (no fixtures, no standings), and discardDraw() takes the page away again.
Consequences: draw/redraw is offered on the Register page (and on Show for a redraw), never on an undrawn Show page; the tournaments index only links to show when the row's `drawn` flag (TournamentResource) is true. Don't add a link to tournaments.show without checking `drawn` first.
