# Emiringkay: System Requirements

## 1. Functional requirements

### Accounts and identity
- FR-01: Sign up/in via Google OAuth or phone OTP.
- FR-02: Mandatory 18+ declaration; verification step required to unlock video.
- FR-03: Pseudonymous display name and avatar; no real name shown to strangers.
- FR-04: Account deletion with full data erasure within 30 days.

### Matching
- FR-10: User selects Vibe, language, optional interest tags, and mode (text or video).
- FR-11: Matching pairs two compatible users from a Redis queue within a target of 8 s.
- FR-12: Users never rematch with someone they skipped or blocked within 7 days.
- FR-13: Trust-tiered pools (new, standard, trusted).

### Room (live session)
- FR-20: Realtime text chat with typing indicator and reactions.
- FR-21: WebRTC video/audio with mute, camera toggle, mask mode, and blur.
- FR-22: Skip/Next, Report, Block, and Panic Skip at all times.
- FR-23: Shared activities: doodle board, trivia duel, breathing sync, music, icebreaker cards.
- FR-24: Keep request, mutual acceptance creates a Connection with a type.

### Social and community
- FR-30: Friends/Connections list with presence and direct messages.
- FR-31: Plaza group rooms with host controls and up to 8 participants.
- FR-32: Daily prompts, events calendar, badges, and karma.
- FR-33: Notifications: in-app, email, web push.

### Moderation and admin
- FR-40: One-tap report with category and optional note.
- FR-41: Sentinel automated checks flag and end Rooms.
- FR-42: Moderator queue sorted by severity with evidence snapshots (frames only for flagged events).
- FR-43: Sanctions: warn, mute, temp ban, permanent ban, device ban; appeals.
- FR-44: Admin dashboard with metrics, user lookup, audit log.

## 2. Non-functional requirements

| Area | Requirement |
|---|---|
| Latency | Signaling round-trip < 150 ms p95; video end-to-end < 400 ms p95 |
| Match time | < 8 s median under normal load |
| Availability | 99.5% monthly at launch, 99.9% target later |
| Scalability | 1,000 concurrent users on MVP infra; horizontal scale path to 50,000 |
| Call success | > 92% connect rate (with TURN fallback) |
| Security | OWASP Top 10 covered, rate limits on all public endpoints, CSRF/XSS protections |
| Privacy | No recording or storage of media; minimal retention (see below) |
| Accessibility | WCAG 2.1 AA: captions option, keyboard navigation, reduced motion, contrast |
| Browser support | Latest 2 versions of Chrome, Edge, Safari 16+, Firefox; Android Chrome, iOS Safari |
| Localization | English and Filipino at launch; i18n-ready |
| Observability | Structured logs, metrics, tracing, error alerts within 5 min |

## 3. Data retention
- Chat text: delete 24 h after Room ends unless reported (then 90 days).
- Reports and evidence frames: 90 days, extended for legal holds.
- Account data: until deletion request, then purge within 30 days.
- Logs: 30 days, IPs hashed after 7 days.

## 4. Compliance and legal
- Philippines Data Privacy Act (RA 10173): consent, purpose limitation, DPO contact, breach notification.
- GDPR-ready: data export, erasure, lawful basis, DPA with processors.
- Age: strictly 18+; terms and enforcement documented.
- Content: takedown and law-enforcement request procedures; cooperation with child-safety reporting obligations.
- App store policies for user-generated content (reporting, blocking, moderation) if wrapped for stores.

## 5. Development environment
- PHP 8.3+, Composer 2, Node 20+, PostgreSQL 15+, Redis 7+, Docker.
- Laravel Sail for local, with services: app, Reverb, Redis, Postgres, Mailpit, coturn.
- Pest, Larastan (level 6+), Laravel Pint, ESLint, Prettier, Vitest.

## 6. Capacity assumptions (MVP)
- 1,000 concurrent users, about 400 active Rooms.
- Signaling traffic only goes through the server (P2P media), roughly 5-20 messages per Room setup.
- TURN relay expected for about 15-25% of sessions; budget bandwidth accordingly.
