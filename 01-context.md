# Emiringkay: Project Context

> Read this first. It is the single source of truth for what Emiringkay is, who it is for, and the rules every contributor (human or AI) follows.

## 1. One-line summary
Emiringkay is a realtime social community app where adults meet random people through text and video, relieve stress together, and turn good encounters into lasting connections: friends, rivals, sparks, or confidants.

## 2. Why it exists
- People want spontaneous human contact without the pressure of profiles and swiping.
- Omegle-style apps proved the demand but failed on safety, so the gap is a **fun, calm, safe** version.
- Stress relief is the core promise: every interaction should leave the user lighter.

## 3. What makes it different from Omegle
| Omegle | Emiringkay |
|---|---|
| Pure random, then gone forever | Random first, then optional **Keep** to continue the connection |
| Anonymous, no accountability | Anonymous to strangers, **accountable to the platform** (verified 18+, trust score) |
| Empty video screen | **Shared activities** during calls (doodle board, trivia duel, breathing sync, music) |
| One mode | **Vibe-based matching** (Chill, Vent, Play, Spark, Debate) |
| No community | **Plaza**: interest rooms, daily prompts, events |
| Moderation was an afterthought | Moderation is a **core system** from day one |

## 4. Target users
- Primary: ages 18-30, students and young professionals, mobile-first, Philippines first, then SEA and global.
- Secondary: remote workers and night-shift workers seeking casual company.
- Not for: anyone under 18 (hard block), users seeking explicit content (prohibited).

## 5. Relationship types ("Keeps")
After a call, both users may choose to Keep each other, mutual only:
- **Friend**: casual ongoing chat.
- **Rival**: playful competition (trivia, game duels, banter leaderboards). Never harassment; either side can end it instantly.
- **Spark**: romantic interest, 18+ only, both must opt in.
- **Confidant**: a safe person to vent to.

## 6. Product principles
1. **Safety before growth.** No feature ships without its abuse case reviewed.
2. **Calm by default.** Soft visuals, no dark patterns, no endless-scroll pressure.
3. **Consent for every escalation.** Camera, Keep, Spark, and sharing all need explicit opt-in.
4. **Skip is always one tap.** Leaving a conversation never costs the user anything.
5. **Realtime or nothing.** Latency is a feature.

## 7. Glossary
- **Room**: a live 1:1 session between two users.
- **Plaza**: public interest-based group spaces.
- **Keep**: mutual decision to stay connected after a Room ends.
- **Vibe**: the mood a user selects before matching.
- **Trust Score**: internal 0-100 score affecting match priority and limits.
- **Signal**: WebRTC signaling message (offer, answer, ICE).
- **Sentinel**: the automated moderation pipeline.

## 8. Tech stack (decided)
Laravel 12+, Reverb (WebSockets), Redis, PostgreSQL, Inertia + Vue 3 + TypeScript, Tailwind CSS, WebRTC (P2P for 1:1, LiveKit SFU for group), coturn (TURN), Filament (admin), Horizon (queues), Pest (tests).

## 9. Non-goals (for now)
- Native iOS/Android apps (ship a PWA first; Capacitor wrapper later).
- Recording or storing video calls.
- Marketplace, payments between users, or dating-style profile browsing.

## 10. Open questions
- Final age/identity verification provider (phone OTP vs ID check vs selfie liveness).
- Monetization timing (free first vs premium from launch).
- Legal review for the Philippines Data Privacy Act (RA 10173) and international users.
- Name meaning, tagline, and brand voice sign-off.

## 11. Rules for AI assistants working on this repo
- Follow Laravel conventions and the structure in `04-architecture.md`.
- Never log or store raw video/audio. Never weaken a safety check to make a feature work.
- Every new endpoint needs a policy, a request validator, and a Pest test.
- Prefer small, reviewable PRs. Update the relevant plan doc when a decision changes.
