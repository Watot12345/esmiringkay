# Emiringkay: Product Requirements Document (PRD)

Version 0.1 | Status: Draft | Owner: Founder

## 1. Problem
Young adults are lonely and stressed but dislike profile-heavy social apps. Existing random-chat apps are either unsafe, empty, or one-shot with no way to continue a good conversation.

## 2. Vision
A calm, playful place where a random hello can become a real friendship, rivalry, or romance, safely.

## 3. Goals and success metrics
| Goal | Metric | Target (6 months post-launch) |
|---|---|---|
| Fast matching | Median time to match | < 8 s |
| Good conversations | Avg. Room length | > 4 min |
| Retention through Keeps | % of Rooms ending in mutual Keep | > 12% |
| Stickiness | D7 retention | > 25% |
| Safety | Reports per 1,000 Rooms | < 8, with 95% reviewed < 1 h |
| Stress relief | Post-call "I feel lighter" score | > 4.0 / 5 |

## 4. Personas
- **Mika, 21, student**: bored at night, wants friends, shy on camera. Needs avatar/mask mode.
- **Jun, 27, night-shift worker**: wants casual company. Needs fast matching and Vibe filters.
- **Ana, 24, introvert**: wants to vent without being judged. Needs Vent mode with a Listener.
- **Marco, 29, competitive**: loves banter and games. Needs Rival mode.

## 5. Core features

### Phase 1: MVP (text and video)
- Sign up with Google or phone OTP, 18+ confirmation.
- Vibe selector (Chill, Vent, Play, Spark, Debate).
- Random matching with interest tags and language.
- 1:1 text chat and WebRTC video, Skip/Next, Report, Block.
- Basic moderation: report queue, temp bans, admin panel.
- Online counter.

### Phase 2: Community and interaction
- **Keep system** with Friend / Rival / Spark / Confidant.
- **Shared activities** in Room: doodle board, trivia duel, 30-second breathing sync, shared playlist, icebreaker cards.
- **Avatar mode**: face-tracked mask so users can be on video without showing their face.
- Friends list, direct messages, presence, call-back requests.
- Reactions and stickers during calls.

### Phase 3: Plaza and growth
- Interest-based group rooms (up to 8 users, LiveKit).
- Daily prompt and weekly themed events ("Midnight Confessions", "Trivia Night").
- Vent Rooms with trained volunteer Listeners.
- Karma and badges (Kind Stranger, Great Listener, Game Champion).
- PWA install, push notifications, then Capacitor mobile wrapper.

### Phase 4: Monetization (optional)
- Emiringkay Plus: priority matching, more filters, extra masks, custom themes.
- Never sell user data. No ads inside Rooms.

## 6. User stories (sample)
- As a user, I pick a Vibe so I match with someone in the same mood.
- As a user, I can skip instantly and never see that person again.
- As a shy user, I can join video with a mask instead of my face.
- As a user, after a great call I can send a Keep and, if mutual, add them as Friend or Rival.
- As a user, I can report abuse in one tap and know someone reviewed it.
- As a moderator, I see flagged Rooms ranked by severity and act in two clicks.

## 7. Safety requirements (mandatory, not optional)
- 18+ only: age confirmation at signup plus verification before video unlocks.
- Camera is off until both sides consent in the Room; blur-until-accept option.
- Automated frame sampling for nudity/violence on-device or via provider; flagged Rooms end instantly.
- Report, block, and "Panic Skip" always visible.
- Trust Score: new or low-trust users are matched in a restricted pool.
- Repeat offenders: device and account bans, appeal flow.
- Spark mode is mutual opt-in with additional verification.
- Clear Terms, Community Guidelines, and law-enforcement request process.

## 8. Out of scope
Recording calls, public profile browsing, user-to-user payments, anonymous file sharing.

## 9. Risks
| Risk | Mitigation |
|---|---|
| Abuse and explicit content | Sentinel pipeline, verified 18+, bans |
| WebRTC connection failures | TURN servers, ICE restarts, fallback to text |
| Low liquidity (nobody online) | Launch by region and hour, seeded events, bot-free but scheduled "Rush Hours" |
| Legal and privacy exposure | Data minimization, DPA compliance, legal review before launch |
| Moderation cost | Automation first, volunteer community moderators, strong tooling |

## 10. Release criteria for MVP
- Median match < 10 s, call connect success > 92%.
- Sentinel and report flow tested end to end.
- Terms, Privacy Policy, and age gate live.
- Load test at 1,000 concurrent users passes.
