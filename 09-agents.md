# Emiringkay: Agents

This document covers two kinds of agents: **in-app AI agents** (features users meet) and **development agents** (AI assistants that help build the project).

---

## Part A: In-app AI agents

All in-app agents must: be clearly labeled as AI, never impersonate a human, never store raw media, log decisions for audit, and defer to human moderators on severe actions.

### 1. Sentinel (safety agent)
- **Role**: moderate text and sampled video frames in real time.
- **Inputs**: chat messages, low-res frames, report context.
- **Actions**: warn, blur, end Room, raise a flag to the moderator queue.
- **Guardrails**: thresholds tuned per category; humans review all bans; appeal path always available.

### 2. Icebreaker
- **Role**: suggests conversation starters and activities when a Room goes quiet for 20 s.
- **Style**: playful, short, optional (user can dismiss or disable).
- **Context**: Vibe, shared interests, language.

### 3. Vibe Matcher
- **Role**: improves pairing using interest overlap, Vibe, language, and past good outcomes (mood scores, Keeps).
- **Privacy**: uses aggregated signals, not chat content.

### 4. Calm Coach
- **Role**: guided breathing and mood check-ins, available solo or in Rooms.
- **Boundaries**: not a therapist. If a user expresses crisis or self-harm intent, show supportive language and local helpline resources, and never keep the user in an AI-only loop.

### 5. Community Host
- **Role**: posts the daily prompt, summarizes the event calendar, welcomes new Plaza members.
- **Guardrails**: no fake users or fake engagement.

### 6. Moderator Assistant
- **Role**: summarizes reports and evidence for human moderators and suggests an action with reasoning.
- **Guardrails**: advisory only; the human decides.

---

## Part B: Development agents (for Claude Code or similar)

### Shared rules
- Read `01-context.md` and `04-architecture.md` before coding.
- Follow Laravel conventions and the domain folder layout.
- Every change: migration, policy, validation, test.
- Never commit secrets; never log or persist media.
- Ask before changing architecture decisions (ADRs).
- Keep PRs small and describe the abuse case considered.

### Agent roster
| Agent | Responsibility | Typical tasks |
|---|---|---|
| **Architect** | System design, ADRs, schema review | Propose domain boundaries, review migrations, scaling plans |
| **Backend (Laravel)** | Domain logic, APIs, queues, events | Matching service, Reverb events, policies, jobs |
| **Realtime/WebRTC** | Signaling, TURN, connection reliability | useWebRTC composable, ICE restarts, fallback logic |
| **Frontend (Vue/Inertia)** | Screens, components, accessibility | Room UI, Plaza, Circle, theme tokens |
| **QA/Test** | Pest, browser tests, load tests | Channel auth tests, fake-media browser tests, k6 scripts |
| **Security Reviewer** | Threat modeling, auth, abuse cases | Review PRs touching auth, moderation, media, uploads |
| **DevOps** | CI/CD, Docker, infra, observability | Sail setup, GitHub Actions, coturn config, alerts |
| **Moderation Tools** | Filament queue and sanction flows | Report resources, evidence viewer, appeal workflow |
| **Docs/Product** | Keep plan docs in sync | Update PRD, changelog, copy review |

### Agent task template
```
Goal:
Context files: (list)
Acceptance criteria:
Abuse case considered:
Tests required:
Out of scope:
```

### Handoff protocol
1. Architect defines the contract (events, tables, endpoints).
2. Backend and Frontend implement against the contract in parallel.
3. QA writes tests from acceptance criteria.
4. Security Reviewer signs off on safety-relevant changes.
5. DevOps verifies deploy and monitoring hooks.
