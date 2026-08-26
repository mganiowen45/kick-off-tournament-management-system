# Tournament Rules

KICKOFF supports exactly three tournament types:

- 1V1 Tournament (`1v1`): exactly two players, Best-of-3 series, champion is the first player to two game wins.
- Full Knockout (`full_knockout`): stable single-elimination bracket generated once at start.
- Group Stage + Knockout (`group_knockout`): groups of four, one match per opponent, top two qualify to knockout.

Group scoring:

- Win = 3 points.
- Draw = 1 point.
- Loss = 0 points.

Tie-breakers:

1. Points.
2. Goal difference.
3. Goals scored.
4. Head-to-head.
5. Admin review if still unresolved.

Knockout and 1V1 games must produce a winner or be replayed. Group matches may draw.

Tournament creators participate automatically but are not administrators. Admins start/cancel/resolve/administer competition. Creators can share invites and request cancellation.

When an open tournament becomes full, KICKOFF schedules an automatic start no later than 12 hours after the fill time. Participants are notified immediately, check-in opens before the scheduled start, and the lifecycle cron starts the tournament when due after required payments are resolved.
