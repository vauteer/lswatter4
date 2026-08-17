---
paths:
  - routes/web.php
---

# Routes

## Route registration order matters for wildcard vs static segments
`tournaments/{tournament}` (public tournaments.show) must be registered AFTER `tournaments/create` (auth-only tournaments.create) in routes/web.php. Otherwise a GET to /tournaments/create matches the wildcard route first (treating "create" as the {tournament} id) and 404s via failed route-model-binding, since {tournament} has no numeric constraint. Same trap applies to any other static-segment route sharing a prefix with a wildcard route of the same segment count. tournaments.index/show/lists are intentionally public (guests can browse); create/store/edit/update/destroy/draw/register stay behind the auth group.
