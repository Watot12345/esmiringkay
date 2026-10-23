# Emiringkay: Skills

Two sections: the **skills the team needs**, and **reusable skill files** (instruction packs) you can create so AI assistants build consistently.

---

## Part A: Skills matrix for the team

| Area | Skill | Level needed | Notes |
|---|---|---|---|
| Backend | Laravel 12 (Eloquent, policies, queues, events) | Advanced | Core of the project |
| Backend | Laravel Reverb and Echo | Intermediate+ | Presence, private channels, whispers |
| Backend | Redis (sorted sets, locks, Lua) | Intermediate | Matchmaking engine |
| Backend | PostgreSQL design and indexing | Intermediate | Query performance on rooms/reports |
| Realtime | WebRTC (ICE, STUN/TURN, SDP) | Intermediate+ | Hardest technical area |
| Realtime | coturn configuration | Intermediate | Time-limited credentials, TLS |
| Frontend | Vue 3 + TypeScript + Inertia | Advanced | Composables for Room state |
| Frontend | Tailwind and design tokens | Intermediate | Theme from `07-theme.md` |
| Frontend | Canvas / realtime collaboration | Intermediate | Doodle board |
| Frontend | Accessibility (WCAG) | Intermediate | Required |
| Safety | Content moderation and trust and safety design | Intermediate | Policies, escalation, appeals |
| Safety | Privacy law basics (RA 10173, GDPR) | Awareness+ | Legal counsel for final review |
| Admin | Filament | Intermediate | Moderator tooling |
| DevOps | Docker, Sail, GitHub Actions | Intermediate | CI/CD |
| DevOps | Linux, Nginx, monitoring | Intermediate | Forge or AWS |
| Quality | Pest, Playwright/Dusk, k6 | Intermediate | Realtime and load tests |
| Product | UX writing, community management | Intermediate | Tone and trust |

### Suggested learning order
1. Laravel basics -> events and broadcasting -> Reverb.
2. WebRTC fundamentals (MDN guide) -> build a 2-browser demo.
3. Redis data structures -> matching prototype.
4. Filament admin -> moderation queue.
5. Trust and safety principles -> write the policy docs.
6. Load testing and observability.

---

## Part B: Reusable skill files (SKILL.md packs)

Create these so an AI assistant follows your conventions every time. Each is a folder with a `SKILL.md`.

### 1. `emiringkay-laravel-feature`
When to use: adding a new domain feature.
Steps: create migration -> model + factory -> policy -> form request -> action class -> controller/Inertia page -> event + listener -> Pest tests -> update docs.
Rules: keep logic in Actions/Services under `app/Domain/*`; always include authorization and tests.

### 2. `emiringkay-reverb-channel`
When to use: adding a realtime event or channel.
Steps: define the event (`ShouldBroadcastNow` for latency-sensitive), register the channel in `routes/channels.php` with an authorization callback, add a Vue composable listener, write channel auth tests.
Rules: never broadcast sensitive data; payloads are minimal.

### 3. `emiringkay-webrtc-signaling`
When to use: touching call setup.
Contents: sequence diagram, ICE server config, TURN credential generation, reconnection and ICE-restart patterns, failure handling and fallback to text, debugging checklist (`chrome://webrtc-internals`).

### 4. `emiringkay-moderation-flow`
When to use: any feature with user-generated content.
Checklist: abuse case, report entry point, Sentinel hook, severity mapping, sanction path, audit log, appeal.

### 5. `emiringkay-filament-resource`
When to use: building admin screens.
Rules: policies on every resource, audit-log actions, never expose raw private messages without a reason code.

### 6. `emiringkay-pest-testing`
Patterns: feature tests for policies, broadcast tests with `Event::fake()`, channel authorization tests, factories for users at different trust tiers, fake-media browser tests.

### 7. `emiringkay-ui-theme`
Contains tokens from `07-theme.md`, component patterns, accessibility checklist, reduced-motion rules.

### Skill file template
```md
---
name: emiringkay-<topic>
description: <one sentence on when to use this skill>
---

# Purpose
# When to use
# Steps
# Rules and guardrails
# Examples
# Checklist
```
