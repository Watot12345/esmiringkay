# Emiringkay: Workflow

## 1. Product roadmap (suggested timeline for a small team)

| Phase | Weeks | Deliverable |
|---|---|---|
| 0. Foundation | 1-2 | Repo, Sail, CI, auth, design tokens, legal drafts |
| 1. Text matching MVP | 3-5 | Queue, matching, text Rooms, skip/report/block, admin basics |
| 2. Video | 6-8 | WebRTC signaling, TURN, consent blur, mask mode (basic) |
| 3. Sentinel and safety | 9-10 | Frame moderation, report queue, sanctions, trust score |
| 4. Keeps and Circle | 11-13 | Keep system, DMs, presence, notifications |
| 5. Activities | 14-16 | Doodle, trivia duel, breathing sync, icebreakers |
| 6. Plaza | 17-20 | Group rooms (LiveKit), events, daily prompt, badges |
| 7. Hardening and launch | 21-24 | Load test, pen test, soft launch, metrics |

## 2. User journey workflow
```
Open app -> Sign in -> Age gate -> Profile -> Pick Vibe -> Start
  -> Matching (queue) -> MatchFound -> Consent blur -> Room
  -> (Activities / chat / reactions)
  -> End (skip/leave) -> Mood check -> Keep? -> Next or Circle
```

## 3. Development workflow
1. **Plan**: write or update the issue with acceptance criteria and abuse cases.
2. **Branch**: `feature/<domain>-<short-name>` from `main`.
3. **Build**: migration, model, policy, request validation, controller/action, event, test.
4. **Test locally**: `sail test`, Pint, Larastan, ESLint.
5. **PR**: small, with screenshots or a short video for UI, linked issue, checklist.
6. **Review**: at least one reviewer; security reviewer for auth, moderation, or media changes.
7. **CI**: lint, static analysis, tests, build.
8. **Merge** to `main` -> auto-deploy to staging -> smoke tests.
9. **Release**: tag, promote to production, monitor dashboards for 30 min.

### Definition of Done
- Tests written and passing, policy and validation in place.
- No new N+1 queries; indexes added for new lookups.
- Accessibility checked, copy reviewed.
- Docs updated (these plan files if decisions changed).
- Abuse case considered and mitigated.

## 4. Git conventions
- Conventional commits: `feat:`, `fix:`, `chore:`, `docs:`, `test:`, `refactor:`.
- Protected `main`, squash merges, semantic version tags.

## 5. Moderation workflow
1. Trigger: user report or Sentinel flag.
2. Auto action: severe flags end the Room immediately and restrict the account pending review.
3. Queue: Filament shows items ranked by severity and recency.
4. Review: moderator views evidence, history, and Trust Score.
5. Decide: dismiss, warn, mute, temp ban, permanent ban, device ban.
6. Notify: user gets a clear message and an appeal link.
7. Appeal: second moderator reviews within 72 h.
8. Learn: weekly review of false positives and thresholds.

## 6. Incident response
1. Detect via alerts.
2. Triage severity (SEV1 safety or outage, SEV2 degraded, SEV3 minor).
3. Communicate in the incident channel and a status page for SEV1/2.
4. Mitigate first, then fix.
5. Post-mortem within 5 days with action items.

## 7. Release and feature flags
- Use feature flags (Laravel Pennant) for Spark mode, Plaza, and new activities.
- Roll out to 5% -> 25% -> 100% while watching safety and retention metrics.

## 8. Analytics loop
- Weekly metrics review: match time, call success, Room length, Keep rate, report rate, mood score.
- Monthly: retention cohorts, funnel drop-offs, moderator workload.
- Decisions recorded in the architecture ADR table or the PRD.

## 9. Community workflow
- Volunteer Listener and Moderator programs with training and badges.
- Weekly themed events announced in Plaza and via notifications.
- Feedback channel in-app, triaged every week.
